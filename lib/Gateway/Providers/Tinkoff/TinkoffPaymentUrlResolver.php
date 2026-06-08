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
}
