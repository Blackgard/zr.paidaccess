<?php

namespace Zr\PaidAccess\Payment;

use Bitrix\Main\Type\DateTime;
use Zr\PaidAccess\Enum\GatewayEventType;
use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\Gateway\GatewayTestService;
use Zr\PaidAccess\Notification\ReceiptNotificationService;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Subscription\SubscriptionService;

/**
 * Завершение оплаты: обновление платежа и активация подписки.
 * Вызывается из webhook (и при необходимости из других источников).
 */
class PaymentCompletionService
{
    public static function completeByOrderId(
        string $orderId,
        string $gatewayPaymentId = '',
        string $gatewayStatus = 'CONFIRMED'
    ): bool {
        $payment = PaymentRepository::getByOrderId($orderId);
        if (!$payment) {
            return false;
        }

        return self::completePayment((int)$payment['ID'], $gatewayPaymentId, $gatewayStatus);
    }

    public static function completePayment(
        int $modulePaymentId,
        string $gatewayPaymentId = '',
        string $gatewayStatus = 'CONFIRMED'
    ): bool {
        $payment = PaymentRepository::getById($modulePaymentId);
        if (!$payment) {
            return false;
        }

        if (PaymentStatus::isPaidLike((string)$payment['STATUS'])) {
            if (!empty($payment['DATE_PAID'])) {
                return true;
            }

            return self::backfillPaidPayment($modulePaymentId, $payment, $gatewayPaymentId, $gatewayStatus);
        }

        $userId = (int)$payment['USER_ID'];
        $billingPeriod = (string)$payment['BILLING_PERIOD'];
        if (PaidAccessCore::isBillingEnforceOnePayment()
            && PaymentRepository::hasPaidInPeriod($userId, $billingPeriod, $modulePaymentId)
        ) {
            PaymentRepository::update($modulePaymentId, [
                'STATUS' => PaymentStatus::PAID,
                'DATE_PAID' => new DateTime(),
            ]);

            return true;
        }

        $isNewlyPaid = true;

        $now = new DateTime();
        $update = [
            'STATUS' => PaymentStatus::PAID,
            'DATE_PAID' => $now,
        ];

        if ($gatewayPaymentId !== '') {
            $update['GATEWAY_PAYMENT_ID'] = $gatewayPaymentId;
        }

        PaymentRepository::update($modulePaymentId, $update);

        GatewayTransactionRepository::log(
            $modulePaymentId,
            (string)$payment['GATEWAY_CODE'],
            GatewayEventType::WEBHOOK,
            null,
            json_encode(['gatewayStatus' => $gatewayStatus, 'completed' => true], JSON_UNESCAPED_UNICODE),
            true,
            $gatewayStatus,
            PaymentStatus::PAID,
            null,
            200,
            (int)($payment['GATEWAY_ID'] ?? 0)
        );

        if (GatewayTestService::isGatewayTestPayment($payment)) {
            GatewayTestService::tryCompleteGatewayTest($modulePaymentId);
        } else {
            SubscriptionService::activateFromPayment((int)$payment['USER_ID'], $modulePaymentId, $now);

            if ($isNewlyPaid) {
                ReceiptNotificationService::handlePaymentCompleted($modulePaymentId);
            }
        }

        return true;
    }

    /**
     * Платёж уже в paid-like статусе, но без DATE_PAID (ручная правка, старые данные).
     *
     * @param array<string, mixed> $payment
     */
    protected static function backfillPaidPayment(
        int $modulePaymentId,
        array $payment,
        string $gatewayPaymentId = '',
        string $gatewayStatus = 'CONFIRMED'
    ): bool {
        $now = new DateTime();
        $update = ['DATE_PAID' => $now];

        if ($gatewayPaymentId !== '' && empty($payment['GATEWAY_PAYMENT_ID'])) {
            $update['GATEWAY_PAYMENT_ID'] = $gatewayPaymentId;
        }

        PaymentRepository::update($modulePaymentId, $update);

        if (GatewayTestService::isGatewayTestPayment($payment)) {
            GatewayTestService::tryCompleteGatewayTest($modulePaymentId);

            return true;
        }

        SubscriptionService::activateFromPayment((int)$payment['USER_ID'], $modulePaymentId, $now);

        GatewayTransactionRepository::log(
            $modulePaymentId,
            (string)$payment['GATEWAY_CODE'],
            GatewayEventType::WEBHOOK,
            null,
            json_encode(['gatewayStatus' => $gatewayStatus, 'backfill' => true], JSON_UNESCAPED_UNICODE),
            true,
            $gatewayStatus,
            PaymentStatus::PAID,
            null,
            200,
            (int)($payment['GATEWAY_ID'] ?? 0)
        );

        return true;
    }
}
