<?php

namespace Zr\PaidAccess\Gateway;

use Bitrix\Main\UserTable;
use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\Gateway\Dto\InitPaymentRequest;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Payment\PaymentRepository;
use Zr\PaidAccess\PublicUi\PaymentWidgetPresenter;

/**
 * Тестовая оплата шлюза для проверки подключения эквайринга (T-Bank onboarding).
 */
class GatewayTestService
{
    public const ORDER_PREFIX = 'PA-GT-';
    /** Служебный период тестового платежа шлюза (не подписка). */
    public const BILLING_PERIOD = 'GT';

    /**
     * @return array{paymentId: int, orderId: string, qrHtml: string, amount: float}
     */
    public static function createTestPayment(int $gatewayId, int $adminUserId): array
    {
        if ($gatewayId <= 0) {
            throw new \RuntimeException('Не указан шлюз');
        }

        if ($adminUserId <= 0) {
            throw new \RuntimeException('Требуется авторизованный администратор');
        }

        $gateway = GatewayRepository::getById($gatewayId);
        if (!$gateway) {
            throw new \RuntimeException('Шлюз не найден');
        }

        if (($gateway['IS_TEST'] ?? 'N') !== 'Y') {
            throw new \RuntimeException('Тестовый платёж доступен только для шлюза с галочкой «Тестовый шлюз»');
        }

        $gatewayInstance = GatewayFactory::createFromRow($gateway, true);
        $amount = PaidAccessCore::getGatewayTestAmount();
        $description = 'Тестовый платёж подключения эквайринга';

        $modulePaymentId = PaymentRepository::create([
            'USER_ID' => $adminUserId,
            'AMOUNT' => $amount,
            'CURRENCY' => 'RUB',
            'ORDER_ID' => self::ORDER_PREFIX . $gatewayId . '-TMP-' . time(),
            'BILLING_PERIOD' => self::BILLING_PERIOD,
            'GATEWAY_CODE' => (string)$gateway['PROVIDER'],
            'GATEWAY_ID' => $gatewayId,
            'DESCRIPTION' => $description,
            'STATUS' => PaymentStatus::PENDING,
        ]);

        $orderId = self::ORDER_PREFIX . $gatewayId . '-' . $modulePaymentId;
        PaymentRepository::update($modulePaymentId, ['ORDER_ID' => $orderId]);

        $paymentRow = PaymentRepository::getById($modulePaymentId);
        $request = self::buildInitRequest($paymentRow, $adminUserId);
        $result = $gatewayInstance->initPayment($request);

        if (!$result->success) {
            PaymentRepository::update($modulePaymentId, ['STATUS' => PaymentStatus::FAILED]);
            throw new \RuntimeException($result->errorMessage ?: 'Ошибка Init при тестовом платеже');
        }

        PaymentRepository::update($modulePaymentId, [
            'GATEWAY_PAYMENT_ID' => $result->gatewayPaymentId,
            'GATEWAY_PAYMENT_URL' => $result->paymentUrl,
        ]);

        $request->paymentUrl = $result->paymentUrl;
        $gatewaySiteId = trim((string)($gateway['SITE_ID'] ?? ''));
        $request->paymentWidgetMode = PaidAccessCore::getPaymentWidgetMode(
            $gatewaySiteId !== '' ? $gatewaySiteId : null
        );
        $formResult = $gatewayInstance->fetchPaymentForm($result->gatewayPaymentId, $request);
        $qrHtml = PaymentWidgetPresenter::renderFromResult($formResult);

        if (!$formResult->success || $qrHtml === '') {
            throw new \RuntimeException($formResult->errorMessage ?: 'Не удалось получить QR для тестового платежа');
        }

        GatewayRepository::update($gatewayId, [
            'TEST_MODULE_PAYMENT_ID' => $modulePaymentId,
        ]);

        return [
            'paymentId' => $modulePaymentId,
            'orderId' => $orderId,
            'qrHtml' => $qrHtml,
            'amount' => $amount,
        ];
    }

    public static function isGatewayTestPayment(array $payment): bool
    {
        $orderId = (string)($payment['ORDER_ID'] ?? '');

        return strncmp($orderId, self::ORDER_PREFIX, strlen(self::ORDER_PREFIX)) === 0;
    }

    public static function tryCompleteGatewayTest(int $modulePaymentId): bool
    {
        $payment = PaymentRepository::getById($modulePaymentId);
        if (!$payment || !self::isGatewayTestPayment($payment)) {
            return false;
        }

        if (!PaymentStatus::isPaidLike((string)$payment['STATUS'])) {
            return false;
        }

        $gatewayId = (int)($payment['GATEWAY_ID'] ?? 0);
        if ($gatewayId <= 0) {
            return false;
        }

        return GatewayRepository::markTestPassed($gatewayId, $modulePaymentId);
    }

    /**
     * @param array<string, mixed>|null $paymentRow
     */
    protected static function buildInitRequest(?array $paymentRow, int $userId): InitPaymentRequest
    {
        if (!$paymentRow) {
            throw new \RuntimeException('Платёж не найден');
        }

        $email = null;
        $phone = null;
        $user = UserTable::getByPrimary($userId, ['select' => ['EMAIL', 'PERSONAL_PHONE']])->fetch();
        if ($user) {
            $email = $user['EMAIL'] ?: null;
            $phone = $user['PERSONAL_PHONE'] ?: null;
        }

        return new InitPaymentRequest(
            (string)$paymentRow['ORDER_ID'],
            (float)$paymentRow['AMOUNT'],
            (string)$paymentRow['CURRENCY'],
            (string)($paymentRow['DESCRIPTION'] ?? 'Тестовый платёж'),
            $userId,
            $email,
            $phone
        );
    }
}
