<?php

namespace Zr\PaidAccess\Fund;

use Zr\PaidAccess\Enum\FundMovementSource;
use Zr\PaidAccess\Enum\FundMovementType;
use Zr\PaidAccess\Tables\FundMovementTable;

/**
 * Участники фонда — пользователи с положительным чистым вкладом (взносы минус возвраты).
 */
class FundExpenseParticipantResolver
{
    /**
     * @return array<int, int> userId => userId
     */
    public static function listActiveParticipantIds(int $fundId): array
    {
        if ($fundId <= 0) {
            return [];
        }

        $totalsByUser = [];
        $result = FundMovementTable::getList([
            'filter' => [
                '=FUND_ID' => $fundId,
                '>USER_ID' => 0,
            ],
            'select' => ['USER_ID', 'TYPE', 'AMOUNT'],
        ]);

        while ($row = $result->fetch()) {
            $userId = (int)($row['USER_ID'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $amount = (float)($row['AMOUNT'] ?? 0);
            if (!isset($totalsByUser[$userId])) {
                $totalsByUser[$userId] = ['income' => 0.0, 'expense' => 0.0];
            }

            if ((string)($row['TYPE'] ?? '') === FundMovementType::EXPENSE) {
                $totalsByUser[$userId]['expense'] += $amount;
            } else {
                $totalsByUser[$userId]['income'] += $amount;
            }
        }

        $allocationByUser = FundExpenseAllocationRepository::sumByUserGrouped($fundId);

        $participantIds = [];
        foreach ($totalsByUser as $userId => $totals) {
            $allocated = (float)($allocationByUser[$userId]['amount'] ?? 0);
            $net = FundBalanceService::calculateAvailableBalance(
                (float)$totals['income'],
                (float)$totals['expense'] + $allocated
            );
            if ($net > 0) {
                $participantIds[(int)$userId] = (int)$userId;
            }
        }

        ksort($participantIds);

        return $participantIds;
    }

    /**
     * @return array<int, int> userId => userId
     */
    public static function listContributorIds(int $fundId): array
    {
        if ($fundId <= 0) {
            return [];
        }

        $userIds = [];
        $result = FundMovementTable::getList([
            'filter' => [
                '=FUND_ID' => $fundId,
                '=TYPE' => FundMovementType::INCOME,
                '=SOURCE' => FundMovementSource::PAYMENT,
                '>USER_ID' => 0,
            ],
            'select' => ['USER_ID'],
        ]);

        while ($row = $result->fetch()) {
            $userId = (int)($row['USER_ID'] ?? 0);
            if ($userId > 0) {
                $userIds[$userId] = $userId;
            }
        }

        ksort($userIds);

        return $userIds;
    }
}
