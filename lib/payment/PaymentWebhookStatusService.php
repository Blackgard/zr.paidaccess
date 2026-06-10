<?php

namespace Zr\PaidAccess\Payment;

use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\Fund\FundMovementService;
use Zr\PaidAccess\Gateway\Dto\WebhookHandleResult;
use Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffStatusMapper;
use Zr\PaidAccess\Log\ModuleEventLogService;
use Zr\PaidAccess\Notification\SubscriptionNotificationService;
use Zr\PaidAccess\Subscription\SubscriptionService;

/**
 * Смена статуса платежа по webhook (кроме успешного CONFIRMED).
 */
class PaymentWebhookStatusService
{
    /**
     * @param array<string, mixed> $modulePayment
     */
    public static function apply(array $modulePayment, WebhookHandleResult $result): void
    {
        $paymentId = (int)$modulePayment['ID'];
        $userId = (int)$modulePayment['USER_ID'];
        $orderId = (string)$result->orderId;
        $gatewayStatus = (string)$result->gatewayStatus;
        $internalStatus = (string)$result->internalStatus;
        $currentStatus = (string)$modulePayment['STATUS'];

        if (TinkoffStatusMapper::isIntermediateStatus($gatewayStatus)) {
            self::acknowledgeIntermediate($modulePayment, $result);

            return;
        }

        if ($internalStatus === PaymentStatus::REFUNDED && $currentStatus !== PaymentStatus::REFUNDED) {
            PaymentRepository::update($paymentId, [
                'STATUS' => PaymentStatus::REFUNDED,
                'DATE_PAID' => null,
            ]);
            ModuleEventLogService::info(
                'payment_webhook_refunded',
                'Платёж возвращён шлюзом: ' . $gatewayStatus,
                ['orderId' => $orderId, 'gatewayStatus' => $gatewayStatus],
                $paymentId,
                $userId
            );
            SubscriptionService::reconcileUserSubscription($userId);
            FundMovementService::tryRecordPaymentRefund($paymentId);

            return;
        }

        if ($internalStatus === PaymentStatus::CANCELLED && $currentStatus !== PaymentStatus::CANCELLED) {
            PaymentRepository::update($paymentId, [
                'STATUS' => PaymentStatus::CANCELLED,
                'DATE_PAID' => null,
            ]);
            ModuleEventLogService::info(
                'payment_webhook_cancelled',
                'Платёж отменён шлюзом: ' . $gatewayStatus,
                ['orderId' => $orderId, 'gatewayStatus' => $gatewayStatus],
                $paymentId,
                $userId
            );
            SubscriptionService::reconcileUserSubscription($userId);

            return;
        }

        if ($internalStatus === PaymentStatus::FAILED && $currentStatus === PaymentStatus::PENDING) {
            PaymentRepository::update($paymentId, ['STATUS' => PaymentStatus::FAILED]);
            ModuleEventLogService::warning(
                'payment_webhook_failed',
                'Платёж отклонён шлюзом: ' . $gatewayStatus,
                ['orderId' => $orderId, 'gatewayStatus' => $gatewayStatus],
                $paymentId,
                $userId
            );
            SubscriptionNotificationService::onPaymentFailed(
                $paymentId,
                'Статус в банке: ' . $gatewayStatus
            );
        }
    }

    /**
     * @param array<string, mixed> $modulePayment
     */
    private static function acknowledgeIntermediate(array $modulePayment, WebhookHandleResult $result): void
    {
        $paymentId = (int)$modulePayment['ID'];
        $userId = (int)$modulePayment['USER_ID'];
        $gatewayPaymentId = trim((string)$result->gatewayPaymentId);

        if ($gatewayPaymentId !== '' && trim((string)($modulePayment['GATEWAY_PAYMENT_ID'] ?? '')) === '') {
            PaymentRepository::update($paymentId, ['GATEWAY_PAYMENT_ID' => $gatewayPaymentId]);
        }

        ModuleEventLogService::info(
            'payment_webhook_intermediate',
            'Промежуточный статус шлюза: ' . $result->gatewayStatus,
            [
                'orderId' => (string)$result->orderId,
                'gatewayStatus' => (string)$result->gatewayStatus,
            ],
            $paymentId,
            $userId
        );
    }
}
