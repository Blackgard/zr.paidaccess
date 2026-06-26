<?php

namespace Zr\PaidAccess\Gateway\Providers\Tinkoff;

class TinkoffPaymentUrlResolver
{
    public static function extractFromPayload($payload): string
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);

            return is_array($decoded) ? self::extractFromArray($decoded) : '';
        }

        return is_array($payload) ? self::extractFromArray($payload) : '';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function extractFromArray(array $data): string
    {
        foreach (['PaymentURL', 'PaymentUrl'] as $key) {
            $value = trim((string)($data[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    public static function isAwaitingPayment(string $status): bool
    {
        return in_array(strtoupper($status), ['NEW', 'FORM_SHOWED'], true);
    }

    public static function isStalePaymentStatus(string $status): bool
    {
        return in_array(strtoupper(trim($status)), [
            'DEADLINE_EXPIRED',
            'REJECTED',
            'CANCELED',
            'REVERSED',
            'AUTH_FAIL',
            'REFUNDED',
            'PARTIAL_REFUNDED',
            'CONFIRMED',
        ], true);
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function isHttpErrorResponse(array $response): bool
    {
        return (string)($response['Message'] ?? '') === 'HTTP error';
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function isStaleGatewayResponse(array $response): bool
    {
        if (!empty($response['Success'])) {
            $status = (string)($response['Status'] ?? '');

            return $status !== '' && self::isStalePaymentStatus($status);
        }

        return self::isHttpErrorResponse($response);
    }
}
