<?php

namespace Zr\PaidAccess\PublicUi;

use Zr\PaidAccess\Fund\FundBalanceService;
use Zr\PaidAccess\Fund\FundExpenseAllocationRepository;
use Zr\PaidAccess\Fund\FundMovementRepository;
use Zr\PaidAccess\Fund\FundService;
use Zr\PaidAccess\PaidAccessCore;

/**
 * Персональная статистика участника по учредительному фонду (ledger + доли в списаниях).
 *
 * TOTAL_CONTRIBUTED — сумма всех поступлений пользователя в фонд.
 * TOTAL_REFUNDED — возвраты по его платежам.
 * TOTAL_ALLOCATED — списания с его доли при расходах фонда.
 * NET_BALANCE — остаток вклада (поступления − возвраты − списания с доли).
 */
class FundContributorService
{
    /**
     * @return array{
     *     USER_ID: int,
     *     FUND_ID: int,
     *     SITE_ID: string,
     *     TOTAL_CONTRIBUTED: int,
     *     TOTAL_REFUNDED: int,
     *     TOTAL_ALLOCATED: int,
     *     NET_BALANCE: int,
     *     PAYMENT_COUNT: int,
     *     ALLOCATION_COUNT: int,
     *     TOTAL_CONTRIBUTED_FORMATTED: string,
     *     TOTAL_REFUNDED_FORMATTED: string,
     *     TOTAL_ALLOCATED_FORMATTED: string,
     *     NET_BALANCE_FORMATTED: string,
     *     ITEMS: array<int, array<string, mixed>>
     * }
     */
    public static function getContributorData(int $userId, ?string $siteId = null, int $movementsLimit = 0): array
    {
        $userId = (int)$userId;
        $siteId = PaidAccessCore::normalizeSiteId($siteId);

        if ($userId <= 0) {
            return self::emptyContributorData($userId, $siteId);
        }

        $fund = FundService::getDefaultFundBySiteId($siteId);
        if (!$fund) {
            return self::emptyContributorData($userId, $siteId);
        }

        $fundId = (int)$fund['ID'];
        $totals = FundMovementRepository::sumByUser($fundId, $userId);
        $allocationTotals = FundExpenseAllocationRepository::sumByUser($fundId, $userId);
        $totals['allocated'] = $allocationTotals['amount'];
        $totals['allocation_count'] = $allocationTotals['count'];

        return self::buildContributorData(
            $userId,
            $fundId,
            $siteId,
            $totals,
            $movementsLimit > 0 ? FundMovementRepository::listForUser($fundId, $userId, $movementsLimit) : []
        );
    }

    /**
     * @param array{income: float, expense: float, payment_count: int, allocated?: float, allocation_count?: int} $totals
     * @param array<int, array<string, mixed>> $items
     *
     * @return array{
     *     USER_ID: int,
     *     FUND_ID: int,
     *     SITE_ID: string,
     *     TOTAL_CONTRIBUTED: int,
     *     TOTAL_REFUNDED: int,
     *     TOTAL_ALLOCATED: int,
     *     NET_BALANCE: int,
     *     PAYMENT_COUNT: int,
     *     ALLOCATION_COUNT: int,
     *     TOTAL_CONTRIBUTED_FORMATTED: string,
     *     TOTAL_REFUNDED_FORMATTED: string,
     *     TOTAL_ALLOCATED_FORMATTED: string,
     *     NET_BALANCE_FORMATTED: string,
     *     ITEMS: array<int, array<string, mixed>>
     * }
     */
    public static function buildContributorData(
        int $userId,
        int $fundId,
        string $siteId,
        array $totals,
        array $items = []
    ): array {
        $contributed = FundBalanceService::roundRubles((float)($totals['income'] ?? 0));
        $refunded = FundBalanceService::roundRubles((float)($totals['expense'] ?? 0));
        $allocated = FundBalanceService::roundRubles((float)($totals['allocated'] ?? 0));
        $netBalance = FundBalanceService::roundRubles(
            FundBalanceService::calculateAvailableBalance(
                (float)($totals['income'] ?? 0),
                (float)($totals['expense'] ?? 0) + (float)($totals['allocated'] ?? 0)
            )
        );

        return [
            'USER_ID' => $userId,
            'FUND_ID' => $fundId,
            'SITE_ID' => $siteId,
            'TOTAL_CONTRIBUTED' => $contributed,
            'TOTAL_REFUNDED' => $refunded,
            'TOTAL_ALLOCATED' => $allocated,
            'NET_BALANCE' => $netBalance,
            'PAYMENT_COUNT' => (int)($totals['payment_count'] ?? 0),
            'ALLOCATION_COUNT' => (int)($totals['allocation_count'] ?? 0),
            'TOTAL_CONTRIBUTED_FORMATTED' => FundBalanceService::formatRubles($contributed),
            'TOTAL_REFUNDED_FORMATTED' => FundBalanceService::formatRubles($refunded),
            'TOTAL_ALLOCATED_FORMATTED' => FundBalanceService::formatRubles($allocated),
            'NET_BALANCE_FORMATTED' => FundBalanceService::formatRubles($netBalance),
            'ITEMS' => $items,
        ];
    }

    /**
     * @return array{
     *     USER_ID: int,
     *     FUND_ID: int,
     *     SITE_ID: string,
     *     TOTAL_CONTRIBUTED: int,
     *     TOTAL_REFUNDED: int,
     *     TOTAL_ALLOCATED: int,
     *     NET_BALANCE: int,
     *     PAYMENT_COUNT: int,
     *     ALLOCATION_COUNT: int,
     *     TOTAL_CONTRIBUTED_FORMATTED: string,
     *     TOTAL_REFUNDED_FORMATTED: string,
     *     TOTAL_ALLOCATED_FORMATTED: string,
     *     NET_BALANCE_FORMATTED: string,
     *     ITEMS: array<int, array<string, mixed>>
     * }
     */
    protected static function emptyContributorData(int $userId, string $siteId): array
    {
        return self::buildContributorData($userId, 0, $siteId, [
            'income' => 0.0,
            'expense' => 0.0,
            'payment_count' => 0,
            'allocated' => 0.0,
            'allocation_count' => 0,
        ]);
    }
}
