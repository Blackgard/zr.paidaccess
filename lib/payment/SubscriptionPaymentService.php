<?php

namespace Zr\PaidAccess\Payment;

use Bitrix\Main\UserTable;
use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\Gateway\Dto\InitPaymentRequest;
use Zr\PaidAccess\Gateway\GatewayFactory;
use Zr\PaidAccess\Tools\Logger;
use Zr\PaidAccess\Log\ModuleEventLogService;
use Zr\PaidAccess\Notification\SubscriptionNotificationService;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Subscription\BillingPolicy;

class SubscriptionPaymentService
{
    public static function getCurrentBillingPeriod(int $userId = 0, ?string $siteId = null): string
    {
        return BillingPolicy::getCurrentBillingPeriod($userId, $siteId);
    }

    /**
     * Создать или вернуть pending-платёж за текущий период (без Sale).
     */
    public static function preparePayment(int $userId, ?string $siteId = null): int
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $billingPeriod = self::getCurrentBillingPeriod($userId, $siteId);

        BillingPolicy::assertCanInitPayment($userId, $siteId);

        $gatewayRow = GatewayFactory::getDefaultGatewayRow($siteId);
        if (!$gatewayRow) {
            throw new \RuntimeException('Платёжный шлюз не настроен. Создайте шлюз в админке.');
        }

        $existing = PaymentRepository::findPendingForPeriod($userId, $billingPeriod);
        if ($existing) {
            self::ensureGatewayInit((int)$existing['ID'], $existing, $siteId);

            return (int)$existing['ID'];
        }

        $amount = PaidAccessCore::getSubscriptionAmount($siteId);
        $currency = 'RUB';
        $description = PaidAccessCore::getPaymentDescription($siteId);

        $modulePaymentId = PaymentRepository::create([
            'USER_ID' => $userId,
            'AMOUNT' => $amount,
            'CURRENCY' => $currency,
            'ORDER_ID' => 'PA-TMP-' . $userId . '-' . time(),
            'BILLING_PERIOD' => $billingPeriod,
            'GATEWAY_CODE' => (string)$gatewayRow['PROVIDER'],
            'GATEWAY_ID' => (int)$gatewayRow['ID'],
            'DESCRIPTION' => $description,
            'STATUS' => PaymentStatus::PENDING,
        ]);

        $orderAccountNumber = 'PA-' . $modulePaymentId . '-' . $billingPeriod;

        PaymentRepository::update($modulePaymentId, [
            'ORDER_ID' => $orderAccountNumber,
        ]);

        $row = PaymentRepository::getById($modulePaymentId);
        self::ensureGatewayInit($modulePaymentId, $row, $siteId);

        return $modulePaymentId;
    }

    /**
     * QR СБП / ссылка на оплату (HTML).
     */
    public static function renderPaymentWidget(int $modulePaymentId, ?string $siteId = null): void
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);

        $modulePayment = PaymentRepository::getById($modulePaymentId);
        if (!$modulePayment) {
            ModuleEventLogService::error('payment_widget_not_found', 'Платёж не найден', [], $modulePaymentId, null, $siteId);
            PaymentPageErrorRenderer::render($siteId);
            return;
        }

        if (PaymentStatus::isPaidLike((string)$modulePayment['STATUS'])) {
            echo '<p>Оплата уже получена. Обновите страницу.</p>';
            return;
        }

        $gatewayPaymentId = (string)($modulePayment['GATEWAY_PAYMENT_ID'] ?? '');
        if ($gatewayPaymentId === '') {
            echo '<p>Платёж в банке ещё не создан. Обновите страницу.</p>';
            return;
        }

        try {
            $gateway = self::resolveGateway($modulePayment, $siteId);
            $request = self::buildInitRequestWithStoredUrl($modulePayment);
            $widgetMode = PaidAccessCore::getPaymentWidgetMode($siteId);

            Logger::info(
                'renderPaymentWidget',
                [
                    'paymentId' => $modulePaymentId,
                    'userId' => (int)$modulePayment['USER_ID'],
                    'gatewayPaymentId' => $gatewayPaymentId,
                    'widgetMode' => $widgetMode,
                    'orderId' => (string)$modulePayment['ORDER_ID'],
                ],
                (string)$modulePayment['GATEWAY_CODE'],
                $siteId
            );

            $result = $gateway->fetchPaymentForm($gatewayPaymentId, $request);

            if ($result->success && $result->html !== '') {
                if ($result->paymentUrl !== '' && trim((string)($modulePayment['GATEWAY_PAYMENT_URL'] ?? '')) === '') {
                    PaymentRepository::update($modulePaymentId, ['GATEWAY_PAYMENT_URL' => $result->paymentUrl]);
                }

                echo $result->html;
                return;
            }

            $errorMessage = $result->errorMessage ?: 'Не удалось получить форму оплаты';
            Logger::warning(
                'renderPaymentWidget failed',
                [
                    'paymentId' => $modulePaymentId,
                    'error' => $errorMessage,
                    'rawResponse' => Logger::sanitizeParams(
                        is_array(json_decode((string)$result->rawResponse, true))
                            ? json_decode((string)$result->rawResponse, true)
                            : ['raw' => (string)$result->rawResponse]
                    ),
                ],
                (string)$modulePayment['GATEWAY_CODE'],
                $siteId
            );
            ModuleEventLogService::error(
                'payment_widget_form_failed',
                $errorMessage,
                ['gatewayPaymentId' => $gatewayPaymentId],
                $modulePaymentId,
                (int)$modulePayment['USER_ID'],
                $siteId
            );
            PaymentPageErrorRenderer::render($siteId);
        } catch (\Throwable $e) {
            ModuleEventLogService::error(
                'payment_widget_exception',
                $e->getMessage(),
                ['trace' => $e->getFile() . ':' . $e->getLine()],
                $modulePaymentId,
                (int)($modulePayment['USER_ID'] ?? 0),
                $siteId
            );
            PaymentPageErrorRenderer::render($siteId);
        }
    }

    /**
     * @param array<string, mixed> $modulePayment
     */
    private static function ensureGatewayInit(int $modulePaymentId, ?array $modulePayment, ?string $siteId): void
    {
        if (!$modulePayment) {
            $modulePayment = PaymentRepository::getById($modulePaymentId);
        }

        if (!$modulePayment || (string)($modulePayment['GATEWAY_PAYMENT_ID'] ?? '') !== '') {
            return;
        }

        $gateway = self::resolveGateway($modulePayment, $siteId);
        $request = self::buildInitRequest($modulePayment);

        Logger::info(
            'ensureGatewayInit',
            [
                'paymentId' => $modulePaymentId,
                'orderId' => (string)$modulePayment['ORDER_ID'],
                'amount' => (float)$modulePayment['AMOUNT'],
            ],
            (string)$modulePayment['GATEWAY_CODE'],
            $siteId
        );

        $result = $gateway->initPayment($request);

        if (!$result->success) {
            $errorMessage = $result->errorMessage ?: 'Ошибка Init в банке';
            PaymentRepository::update($modulePaymentId, ['STATUS' => PaymentStatus::FAILED]);
            ModuleEventLogService::error(
                'payment_init_failed',
                $errorMessage,
                [
                    'orderId' => (string)$modulePayment['ORDER_ID'],
                    'gatewayCode' => (string)$modulePayment['GATEWAY_CODE'],
                ],
                $modulePaymentId,
                (int)$modulePayment['USER_ID'],
                $siteId
            );
            SubscriptionNotificationService::onPaymentFailed($modulePaymentId, $errorMessage, $siteId);
            throw new \RuntimeException($errorMessage);
        }

        PaymentRepository::update($modulePaymentId, [
            'GATEWAY_PAYMENT_ID' => $result->gatewayPaymentId,
            'GATEWAY_PAYMENT_URL' => $result->paymentUrl,
        ]);
    }

    /**
     * @param array<string, mixed>|null $modulePayment
     */
    private static function resolveGateway($modulePayment, $siteId = null)
    {
        if (is_array($modulePayment) && (int)($modulePayment['GATEWAY_ID'] ?? 0) > 0) {
            return GatewayFactory::createById((int)$modulePayment['GATEWAY_ID']);
        }

        return GatewayFactory::create($siteId);
    }

    /**
     * @param array<string, mixed> $modulePayment
     */
    private static function buildInitRequest(array $modulePayment): InitPaymentRequest
    {
        $userId = (int)$modulePayment['USER_ID'];
        $email = null;
        $phone = null;

        $user = UserTable::getByPrimary($userId, ['select' => ['EMAIL', 'PERSONAL_PHONE']])->fetch();
        if ($user) {
            $email = $user['EMAIL'] ?: null;
            $phone = $user['PERSONAL_PHONE'] ?: null;
        }

        return new InitPaymentRequest(
            (string)$modulePayment['ORDER_ID'],
            (float)$modulePayment['AMOUNT'],
            (string)$modulePayment['CURRENCY'],
            (string)($modulePayment['DESCRIPTION'] ?? 'Подписка'),
            $userId,
            $email,
            $phone
        );
    }

    /**
     * @param array<string, mixed> $modulePayment
     */
    private static function resolveStoredPaymentUrl(array $modulePayment): string
    {
        $url = trim((string)($modulePayment['GATEWAY_PAYMENT_URL'] ?? ''));
        if ($url !== '') {
            return $url;
        }

        $paymentId = (int)($modulePayment['ID'] ?? 0);
        if ($paymentId <= 0) {
            return '';
        }

        $url = GatewayTransactionRepository::extractPaymentUrlFromInit($paymentId);
        if ($url !== '') {
            PaymentRepository::update($paymentId, ['GATEWAY_PAYMENT_URL' => $url]);
        }

        return $url;
    }

    /**
     * @param array<string, mixed> $modulePayment
     */
    private static function buildInitRequestWithStoredUrl(array $modulePayment): InitPaymentRequest
    {
        $request = self::buildInitRequest($modulePayment);
        $request->paymentUrl = self::resolveStoredPaymentUrl($modulePayment);

        return $request;
    }
}
