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
}
