<?php

namespace Zr\PaidAccess\Gateway\Providers\Tinkoff;

use Zr\PaidAccess\Gateway\Dto\InitPaymentResult;

/**
 * Коды ошибок Init T-Bank, влияющие на статус платежа в модуле.
 */
final class TinkoffInitError
{
    /** Заказ с таким OrderId уже зарегистрирован в банке. */
    public const ERROR_DUPLICATE_ORDER_ID = '8';

    /**
     * @param array<string, mixed>|InitPaymentResult|string|null $source
     */
    public static function isDuplicateOrderIdError($source): bool
    {
        if ($source instanceof InitPaymentResult) {
            return self::isDuplicateOrderIdError($source->rawResponse)
                || self::isDuplicateOrderIdError($source->errorMessage);
        }

        $data = self::decode($source);
        if ($data !== null) {
            if ((string)($data['ErrorCode'] ?? '') === self::ERROR_DUPLICATE_ORDER_ID) {
                return true;
            }

            $details = (string)($data['Details'] ?? '');
            if ($details !== '' && stripos($details, 'order_id уже существует') !== false) {
                return true;
            }
        }

        return is_string($source)
            && $source !== ''
            && stripos($source, 'order_id уже существует') !== false;
    }

    /**
     * @param array<string, mixed>|string|null $source
     *
     * @return array<string, mixed>|null
     */
    private static function decode($source): ?array
    {
        if (is_array($source)) {
            return $source;
        }

        if (!is_string($source) || $source === '') {
            return null;
        }

        $decoded = json_decode($source, true);

        return is_array($decoded) ? $decoded : null;
    }
}
