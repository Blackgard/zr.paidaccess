<?php

namespace Zr\PaidAccess\PublicUi;

use Bitrix\Main\Type\DateTime;
use Zr\PaidAccess\Admin\SubscriberAdminService;
use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\Tables\PaymentTable;

/**
 * Публичные данные кошелька учредительного фонда: баланс и список успешных платежей.
 */
class FundWalletService
{
    /**
     * @return array{
     *     TOTAL_AMOUNT: int,
     *     PAYER_COUNT: int,
     *     ITEMS: array<int, array<string, mixed>>
     * }
     */
    public static function getWalletData(): array
    {
        $total = 0.0;
        $payerIds = [];
        $items = [];

        $result = PaymentTable::getList([
            'filter' => [
                '=STATUS' => PaymentStatus::PAID,
            ],
            'select' => [
                'ID',
                'ORDER_ID',
                'AMOUNT',
                'DATE_PAID',
                'USER_ID',
                'USER_NAME' => 'USER.NAME',
                'USER_LAST_NAME' => 'USER.LAST_NAME',
            ],
            'order' => ['DATE_PAID' => 'DESC', 'ID' => 'DESC'],
        ]);

        while ($row = $result->fetch()) {
            $userId = (int)($row['USER_ID'] ?? 0);
            $amount = (float)($row['AMOUNT'] ?? 0);
            $total += $amount;
            if ($userId > 0) {
                $payerIds[$userId] = true;
            }

            $items[] = [
                'ID' => (int)$row['ID'],
                'ORDER_ID' => (string)($row['ORDER_ID'] ?? ''),
                'DATE_PAID' => self::formatDisplayDate($row['DATE_PAID'] ?? null),
                'PAYER_NAME' => SubscriberAdminService::formatUserName([
                    'NAME' => (string)($row['USER_NAME'] ?? ''),
                    'LAST_NAME' => (string)($row['USER_LAST_NAME'] ?? ''),
                ]),
                'AMOUNT' => $amount,
                'AMOUNT_FORMATTED' => self::formatRubles($amount),
            ];
        }

        return [
            'TOTAL_AMOUNT' => self::roundRubles($total),
            'PAYER_COUNT' => count($payerIds),
            'ITEMS' => $items,
        ];
    }

    public static function roundRubles(float $amount): int
    {
        return (int) round($amount);
    }

    public static function formatRubles(float $amount): string
    {
        return number_format(self::roundRubles($amount), 0, '.', ' ');
    }

    /**
     * @param DateTime|\DateTimeInterface|string|null $value
     */
    protected static function formatDisplayDate($value): string
    {
        if (empty($value)) {
            return '';
        }

        if ($value instanceof DateTime) {
            return $value->format('d.m.Y');
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d.m.Y');
        }

        $ts = strtotime((string)$value);

        return $ts !== false ? date('d.m.Y', $ts) : (string)$value;
    }
}
