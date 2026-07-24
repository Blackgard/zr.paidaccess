<?php

namespace Zr\PaidAccess\Gateway\Providers\Tinkoff;

use Zr\PaidAccess\Gateway\Dto\InitPaymentRequest;
use Zr\PaidAccess\Gateway\Dto\InitPaymentResult;

/**
 * Восстановление PaymentId по OrderId через CheckOrder (дубликат Init).
 */
final class TinkoffDuplicateOrderRecovery
{
    public static function recover(TinkoffApiClient $client, InitPaymentRequest $request): InitPaymentResult
    {
        $orderId = trim($request->orderId);
        if ($orderId === '') {
            return InitPaymentResult::fail('Пустой OrderId');
        }

        $response = $client->checkOrder(['OrderId' => $orderId]);
        $raw = json_encode($response, JSON_UNESCAPED_UNICODE);

        if (empty($response['Success'])) {
            return InitPaymentResult::fail(
                (string)($response['Message'] ?? 'CheckOrder failed') . ' ' . ($response['Details'] ?? ''),
                $raw
            );
        }

        $payment = self::pickRecoverablePayment($response);
        if ($payment === null) {
            return InitPaymentResult::fail(
                'В T-Bank нет активного платежа для order_id ' . $orderId,
                $raw
            );
        }

        $paymentId = trim((string)($payment['PaymentId'] ?? ''));
        if ($paymentId === '') {
            return InitPaymentResult::fail('CheckOrder не вернул PaymentId', $raw);
        }

        $paymentUrl = TinkoffPaymentUrlResolver::extractFromArray($payment);
        if ($paymentUrl === '') {
            $state = $client->getState(['PaymentId' => $paymentId]);
            if (is_array($state)) {
                $paymentUrl = TinkoffPaymentUrlResolver::extractFromArray($state);
            }
        }

        return new InitPaymentResult(true, $paymentId, $paymentUrl, '', '', '', $raw);
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return array<string, mixed>|null
     */
    private static function pickRecoverablePayment(array $response): ?array
    {
        $payments = $response['Payments'] ?? null;
        if (!is_array($payments) || $payments === []) {
            return null;
        }

        foreach ($payments as $payment) {
            if (!is_array($payment)) {
                continue;
            }

            $paymentId = trim((string)($payment['PaymentId'] ?? ''));
            if ($paymentId === '') {
                continue;
            }

            $status = (string)($payment['Status'] ?? '');
            if (TinkoffPaymentUrlResolver::isAwaitingPayment($status)) {
                return $payment;
            }
        }

        return null;
    }
}
