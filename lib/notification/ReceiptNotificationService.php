<?php

namespace Zr\PaidAccess\Notification;

use Bitrix\Main\Loader;
use Bitrix\Main\Mail\Event;
use Bitrix\Main\UserTable;
use Zr\PaidAccess\Enum\GatewayEventType;
use Zr\PaidAccess\Gateway\Dto\ReceiptDeliveryInfo;
use Zr\PaidAccess\Gateway\GatewayRepository;
use Zr\PaidAccess\Gateway\ReceiptDeliveryResolver;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Payment\GatewayTransactionRepository;
use Zr\PaidAccess\Payment\PaymentRepository;
use Zr\PaidAccess\Subscription\BillingPolicy;

/**
 * Уведомления после успешной оплаты (F4).
 *
 * Фискальный чек (54-ФЗ) — зона ответственности шлюза (T-Bank + Receipt в Init).
 * Информационное письмо с сайта — опция модуля PAYMENT_EMAIL_NOTIFY + CEvent.
 */
class ReceiptNotificationService
{
    public static function handlePaymentCompleted(int $paymentId, ?string $siteId = null): void
    {
        $payment = PaymentRepository::getById($paymentId);
        if (!$payment) {
            return;
        }

        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $userId = (int)$payment['USER_ID'];
        $customerEmail = self::resolveCustomerEmail($userId);
        $gatewayOptions = self::loadGatewayOptions($payment);
        $providerCode = (string)$payment['GATEWAY_CODE'];

        $deliveryInfo = ReceiptDeliveryResolver::resolve($providerCode, $gatewayOptions, $customerEmail);

        self::logFiscalDeliveryExpectation($paymentId, $payment, $deliveryInfo, $customerEmail);

        if (!PaidAccessCore::isPaymentEmailNotifyEnabled($siteId)) {
            return;
        }

        if ($customerEmail === '') {
            self::logSiteEmailSkipped($paymentId, $payment, 'У пользователя не указан email');
            return;
        }

        self::sendSiteNotification($paymentId, $payment, $userId, $customerEmail, $deliveryInfo, $siteId);
    }

    protected static function sendSiteNotification(
        int $paymentId,
        array $payment,
        int $userId,
        string $customerEmail,
        ReceiptDeliveryInfo $deliveryInfo,
        string $siteId
    ): void {
        $user = UserTable::getByPrimary($userId, [
            'select' => ['ID', 'LOGIN', 'NAME', 'LAST_NAME', 'EMAIL'],
        ])->fetch();

        $userName = trim((string)(($user['NAME'] ?? '') . ' ' . ($user['LAST_NAME'] ?? '')));
        if ($userName === '') {
            $userName = (string)($user['LOGIN'] ?? 'Участник');
        }

        $billingPeriod = (string)($payment['BILLING_PERIOD'] ?? '');
        $periodLabel = BillingPolicy::formatPeriodLabel($billingPeriod, $siteId);
        $amount = number_format((float)$payment['AMOUNT'], 2, '.', ' ');
        $currency = (string)($payment['CURRENCY'] ?? 'RUB');
        $paidAt = '';
        if (!empty($payment['DATE_PAID'])) {
            $paidAt = $payment['DATE_PAID'] instanceof \Bitrix\Main\Type\DateTime
                ? $payment['DATE_PAID']->toString()
                : (string)$payment['DATE_PAID'];
        }

        $fiscalNote = '';
        if ($deliveryInfo->fiscalReceiptEmailByGateway) {
            $fiscalNote = 'Фискальный чек будет отправлен на ваш email платёжным шлюзом.';
        }

        $fields = [
            'EMAIL' => $customerEmail,
            'USER_ID' => $userId,
            'USER_NAME' => $userName,
            'PAYMENT_ID' => $paymentId,
            'ORDER_ID' => (string)($payment['ORDER_ID'] ?? ''),
            'AMOUNT' => $amount,
            'CURRENCY' => $currency,
            'BILLING_PERIOD' => $billingPeriod,
            'BILLING_PERIOD_LABEL' => $periodLabel,
            'DESCRIPTION' => (string)($payment['DESCRIPTION'] ?? ''),
            'DATE_PAID' => $paidAt,
            'FISCAL_RECEIPT_NOTE' => $fiscalNote,
            'GATEWAY_CODE' => (string)($payment['GATEWAY_CODE'] ?? ''),
        ];

        $result = Event::send([
            'EVENT_NAME' => PaidAccessCore::MAIL_EVENT_PAYMENT_PAID,
            'LID' => $siteId,
            'C_FIELDS' => PaidAccessCore::enrichMailFields($fields, $siteId),
        ]);

        $success = $result->isSuccess();
        $errorText = $success ? '' : implode('; ', $result->getErrorMessages());

        GatewayTransactionRepository::log(
            $paymentId,
            (string)$payment['GATEWAY_CODE'],
            GatewayEventType::RECEIPT_EMAIL,
            json_encode(['email' => $customerEmail, 'event' => PaidAccessCore::MAIL_EVENT_PAYMENT_PAID], JSON_UNESCAPED_UNICODE),
            $success ? 'sent' : $errorText,
            $success,
            null,
            null,
            $errorText
        );
    }

    /**
     * @param array<string, mixed> $payment
     */
    protected static function logFiscalDeliveryExpectation(
        int $paymentId,
        array $payment,
        ReceiptDeliveryInfo $deliveryInfo,
        string $customerEmail
    ): void {
        if (!$deliveryInfo->fiscalReceiptEnabled) {
            return;
        }

        GatewayTransactionRepository::log(
            $paymentId,
            (string)$payment['GATEWAY_CODE'],
            GatewayEventType::RECEIPT_FISCAL_GATEWAY,
            json_encode([
                'issuer' => $deliveryInfo->fiscalReceiptIssuer,
                'emailByGateway' => $deliveryInfo->fiscalReceiptEmailByGateway,
                'customerEmail' => $customerEmail,
            ], JSON_UNESCAPED_UNICODE),
            $deliveryInfo->adminHint,
            true,
            null,
            null,
            $customerEmail === '' ? 'Email покупателя не указан — шлюз не сможет отправить чек' : ''
        );
    }

    /**
     * @param array<string, mixed> $payment
     */
    protected static function logSiteEmailSkipped(int $paymentId, array $payment, string $reason): void
    {
        GatewayTransactionRepository::log(
            $paymentId,
            (string)$payment['GATEWAY_CODE'],
            GatewayEventType::RECEIPT_EMAIL,
            null,
            $reason,
            false,
            null,
            null,
            $reason
        );
    }

    protected static function resolveCustomerEmail(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }

        $user = UserTable::getByPrimary($userId, ['select' => ['EMAIL']])->fetch();

        return trim((string)($user['EMAIL'] ?? ''));
    }

    /**
     * @param array<string, mixed> $payment
     * @return array<string, mixed>
     */
    protected static function loadGatewayOptions(array $payment): array
    {
        $gatewayId = (int)($payment['GATEWAY_ID'] ?? 0);
        if ($gatewayId <= 0) {
            return [];
        }

        if (!Loader::includeModule(PaidAccessCore::MODULE_ID)) {
            return [];
        }

        $gatewayRow = GatewayRepository::getById($gatewayId);

        return is_array($gatewayRow) ? GatewayRepository::getOptionsForGateway($gatewayRow) : [];
    }
}
