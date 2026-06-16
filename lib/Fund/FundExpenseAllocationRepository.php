<?php

namespace Zr\PaidAccess\Fund;

use Bitrix\Main\Type\DateTime;
use Zr\PaidAccess\Tables\FundExpenseAllocationTable;

class FundExpenseAllocationRepository
{
    /**
     * @param array<int, array{USER_ID: int, AMOUNT: float}> $rows
     */
    public static function createBatch(int $movementId, int $fundId, array $rows): void
    {
        if ($movementId <= 0 || $fundId <= 0 || $rows === []) {
            return;
        }

        foreach ($rows as $row) {
            $userId = (int)($row['USER_ID'] ?? 0);
            $amount = (float)($row['AMOUNT'] ?? 0);
            if ($userId <= 0 || $amount <= 0) {
                continue;
            }

            $result = FundExpenseAllocationTable::add([
                'MOVEMENT_ID' => $movementId,
                'FUND_ID' => $fundId,
                'USER_ID' => $userId,
                'AMOUNT' => $amount,
                'DATE_CREATE' => new DateTime(),
            ]);

            if (!$result->isSuccess()) {
                throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listByMovementId(int $movementId): array
    {
        if ($movementId <= 0) {
            return [];
        }

        $rows = [];
        $result = FundExpenseAllocationTable::getList([
            'filter' => ['=MOVEMENT_ID' => $movementId],
            'select' => [
                '*',
                'USER_NAME' => 'USER.NAME',
                'USER_LAST_NAME' => 'USER.LAST_NAME',
                'USER_LOGIN' => 'USER.LOGIN',
            ],
            'order' => ['ID' => 'ASC'],
        ]);

        while ($row = $result->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param array<int, int> $movementIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public static function listGroupedByMovementIds(array $movementIds): array
    {
        $movementIds = array_values(array_filter(array_map('intval', $movementIds)));
        if ($movementIds === []) {
            return [];
        }

        $grouped = [];
        $result = FundExpenseAllocationTable::getList([
            'filter' => ['@MOVEMENT_ID' => $movementIds],
            'select' => [
                '*',
                'USER_NAME' => 'USER.NAME',
                'USER_LAST_NAME' => 'USER.LAST_NAME',
                'USER_LOGIN' => 'USER.LOGIN',
            ],
            'order' => ['MOVEMENT_ID' => 'ASC', 'ID' => 'ASC'],
        ]);

        while ($row = $result->fetch()) {
            $movementId = (int)($row['MOVEMENT_ID'] ?? 0);
            if ($movementId <= 0) {
                continue;
            }
            $grouped[$movementId][] = $row;
        }

        return $grouped;
    }

    public static function countByMovementId(int $movementId): int
    {
        if ($movementId <= 0) {
            return 0;
        }

        return (int)FundExpenseAllocationTable::getCount(['=MOVEMENT_ID' => $movementId]);
    }

    /**
     * Сумма долей пользователя в списаниях с фонда.
     *
     * @return array{amount: float, count: int}
     */
    public static function sumByUser(int $fundId, int $userId): array
    {
        $amount = 0.0;
        $count = 0;

        if ($fundId <= 0 || $userId <= 0) {
            return ['amount' => $amount, 'count' => $count];
        }

        $result = FundExpenseAllocationTable::getList([
            'filter' => [
                '=FUND_ID' => $fundId,
                '=USER_ID' => $userId,
            ],
            'select' => ['AMOUNT'],
        ]);

        while ($row = $result->fetch()) {
            $amount += (float)($row['AMOUNT'] ?? 0);
            $count++;
        }

        return ['amount' => $amount, 'count' => $count];
    }

    /**
     * @return array<int, array{amount: float, count: int}>
     */
    public static function sumByUserGrouped(int $fundId): array
    {
        if ($fundId <= 0) {
            return [];
        }

        $grouped = [];
        $result = FundExpenseAllocationTable::getList([
            'filter' => ['=FUND_ID' => $fundId],
            'select' => ['USER_ID', 'AMOUNT'],
        ]);

        while ($row = $result->fetch()) {
            $userId = (int)($row['USER_ID'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            if (!isset($grouped[$userId])) {
                $grouped[$userId] = ['amount' => 0.0, 'count' => 0];
            }

            $grouped[$userId]['amount'] += (float)($row['AMOUNT'] ?? 0);
            $grouped[$userId]['count']++;
        }

        return $grouped;
    }
}
