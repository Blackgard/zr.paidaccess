<?php

namespace Zr\PaidAccess\Payment;

use Zr\PaidAccess\Enum\GatewayEventType;
use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\Gateway\Contract\GatewayCancellableInterface;
use Zr\PaidAccess\Gateway\Contract\StaleSessionRecoverableGatewayInterface;
use Zr\PaidAccess\Gateway\GatewayFactory;
use Zr\PaidAccess\Gateway\GatewayRepository;
use Zr\PaidAccess\Log\ModuleEventLogService;
use Zr\PaidAccess\Subscription\SubscriptionService;
use Zr\PaidAccess\Tools\RequestContext;

/**
 * Отмена платежа в модуле и в платёжном шлюзе.
 */
class PaymentCancellationService
{
    private const MANUAL_GATEWAY_CODE = 'manual';

    /**
     * @param array<string, mixed> $payment
     */
    public static function canCancel(array $payment): bool
    {
        $status = (string)($payment['STATUS'] ?? '');

        if ($status === PaymentStatus::CANCELLED) {
            return false;
        }

        if ($status === PaymentStatus::REFUNDED) {
            return false;
        }

        return in_array($status, [
            PaymentStatus::PENDING,
            PaymentStatus::FAILED,
            PaymentStatus::AUTHORIZED,
            PaymentStatus::PAID,
        ], true);
    }

    /**
     * Запрос Cancel в шлюз: для незавершённых — отмена сессии, для оплаченных — возврат, если шлюз это поддерживает.
     *
     * @param array<string, mixed> $payment
     */
    public static function shouldCallGatewayCancel(array $payment): bool
    {
        $gatewayCode = (string)($payment['GATEWAY_CODE'] ?? '');
        $gatewayPaymentId = trim((string)($payment['GATEWAY_PAYMENT_ID'] ?? ''));

        return $gatewayCode !== ''
            && $gatewayCode !== self::MANUAL_GATEWAY_CODE
            && $gatewayPaymentId !== '';
    }

    public static function cancel(int $paymentId): void
    {
        $payment = PaymentRepository::getById($paymentId);
        if (!$payment) {
            throw new \RuntimeException('Платёж не найден');
        }

        if ((string)$payment['STATUS'] === PaymentStatus::CANCELLED) {
            return;
        }

        if (!self::canCancel($payment)) {
            throw new \RuntimeException('Платёж нельзя отменить в текущем статусе');
        }

        $gatewayCode = (string)($payment['GATEWAY_CODE'] ?? '');
        $gatewayPaymentId = trim((string)($payment['GATEWAY_PAYMENT_ID'] ?? ''));
        $gatewayApiCalled = self::shouldCallGatewayCancel($payment);
        $requestMeta = RequestContext::capture('admin_cancel');

        ModuleEventLogService::info(
            'payment_cancel_start',
            'Запрос отмены платежа из админки',
            array_merge($requestMeta, [
                'paymentId' => $paymentId,
                'previousStatus' => (string)$payment['STATUS'],
                'gatewayCode' => $gatewayCode,
                'gatewayPaymentId' => $gatewayPaymentId,
                'gatewayApi' => $gatewayApiCalled,
            ]),
            $paymentId,
            (int)$payment['USER_ID']
        );

        if ($gatewayApiCalled) {
            self::cancelInGateway($payment, $gatewayPaymentId);
        } else {
            GatewayTransactionRepository::log(
                $paymentId,
                $gatewayCode !== '' ? $gatewayCode : self::MANUAL_GATEWAY_CODE,
                GatewayEventType::CANCEL,
                RequestContext::wrapPayload('admin_cancel', [
                    'paymentId' => $paymentId,
                    'gatewayPaymentId' => $gatewayPaymentId,
                    'previousStatus' => (string)$payment['STATUS'],
                    'gatewayApi' => false,
                ]),
                json_encode(['success' => true, 'localOnly' => true], JSON_UNESCAPED_UNICODE),
                true,
                null,
                PaymentStatus::CANCELLED,
                null,
                0,
                (int)($payment['GATEWAY_ID'] ?? 0)
            );
        }

        PaymentRepository::update($paymentId, [
            'STATUS' => PaymentStatus::CANCELLED,
            'DATE_PAID' => null,
        ]);

        ModuleEventLogService::info(
            'payment_cancelled',
            'Платёж отменён',
            array_merge($requestMeta, [
                'gatewayCode' => $gatewayCode,
                'gatewayPaymentId' => $gatewayPaymentId,
                'gatewayApi' => $gatewayApiCalled,
            ]),
            $paymentId,
            (int)$payment['USER_ID']
        );

        SubscriptionService::reconcileUserSubscription((int)$payment['USER_ID']);
    }

    /**
     * @param array<string, mixed> $payment
     */
    private static function cancelInGateway(array $payment, string $gatewayPaymentId): void
    {
        $gatewayId = (int)($payment['GATEWAY_ID'] ?? 0);
        if ($gatewayId <= 0) {
            throw new \RuntimeException('Не указан шлюз платежа для отмены через API');
        }

        $gatewayRow = GatewayRepository::getById($gatewayId);
        if (!$gatewayRow) {
            throw new \RuntimeException('Шлюз #' . $gatewayId . ' не найден');
        }

        $gateway = GatewayFactory::createFromRow($gatewayRow, true);
        if (!$gateway instanceof GatewayCancellableInterface) {
            throw new \RuntimeException('Отмена через API не поддерживается для шлюза: ' . (string)$payment['GATEWAY_CODE']);
        }

        $amountRub = PaymentStatus::grantsAccess((string)$payment['STATUS'])
            ? (float)$payment['AMOUNT']
            : null;

        $response = $gateway->cancelPayment($gatewayPaymentId, (int)$payment['ID'], $amountRub);
        if (empty($response['Success'])) {
            if (
                $gateway instanceof StaleSessionRecoverableGatewayInterface
                && $gateway->isIgnorableCancelFailure($response, (string)$payment['STATUS'])
            ) {
                ModuleEventLogService::warning(
                    'payment_cancel_gateway_ignored',
                    'Отмена в банке недоступна, платёж закрыт локально',
                    [
                        'gatewayPaymentId' => $gatewayPaymentId,
                        'gatewayMessage' => (string)($response['Message'] ?? ''),
                        'gatewayDetails' => (string)($response['Details'] ?? ''),
                    ],
                    (int)$payment['ID'],
                    (int)$payment['USER_ID']
                );

                return;
            }

            $message = trim((string)($response['Message'] ?? 'Cancel failed') . ' ' . (string)($response['Details'] ?? ''));
            throw new \RuntimeException($message !== '' ? $message : 'Шлюз отклонил отмену платежа');
        }
    }
}
