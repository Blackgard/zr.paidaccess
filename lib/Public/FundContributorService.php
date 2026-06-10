<?php

namespace Zr\PaidAccess\PublicUi;

use Zr\PaidAccess\Fund\FundBalanceService;
use Zr\PaidAccess\Fund\FundMovementRepository;
use Zr\PaidAccess\Fund\FundService;
use Zr\PaidAccess\PaidAccessCore;

/**
 * Персональная статистика участника по учредительному фонду (ledger).
 *
 * TOTAL_CONTRIBUTED — сумма всех поступлений пользователя в фонд.
 * NET_BALANCE — чистый вклад (поступления − возвраты по его платежам).
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
     *     NET_BALANCE: int,
     *     PAYMENT_COUNT: int,
     *     TOTAL_CONTRIBUTED_FORMATTED: string,
     *     TOTAL_REFUNDED_FORMATTED: string,
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

        return self::buildContributorData(
            $userId,
            $fundId,
            $siteId,
            $totals,
            $movementsLimit > 0 ? FundMovementRepository::listForUser($fundId, $userId, $movementsLimit) : []
        );
    }

    /**
     * @param array{income: float, expense: float, payment_count: int} $totals
     * @param array<int, array<string, mixed>> $items
     *
     * @return array{
     *     USER_ID: int,
     *     FUND_ID: int,
     *     SITE_ID: string,
     *     TOTAL_CONTRIBUTED: int,
     *     TOTAL_REFUNDED: int,
     *     NET_BALANCE: int,
     *     PAYMENT_COUNT: int,
     *     TOTAL_CONTRIBUTED_FORMATTED: string,
     *     TOTAL_REFUNDED_FORMATTED: string,
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
        $netBalance = FundBalanceService::roundRubles(
            FundBalanceService::calculateAvailableBalance(
                (float)($totals['income'] ?? 0),
                (float)($totals['expense'] ?? 0)
            )
        );

        return [
            'USER_ID' => $userId,
            'FUND_ID' => $fundId,
            'SITE_ID' => $siteId,
            'TOTAL_CONTRIBUTED' => $contributed,
            'TOTAL_REFUNDED' => $refunded,
            'NET_BALANCE' => $netBalance,
            'PAYMENT_COUNT' => (int)($totals['payment_count'] ?? 0),
            'TOTAL_CONTRIBUTED_FORMATTED' => FundBalanceService::formatRubles($contributed),
            'TOTAL_REFUNDED_FORMATTED' => FundBalanceService::formatRubles($refunded),
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
     *     NET_BALANCE: int,
     *     PAYMENT_COUNT: int,
     *     TOTAL_CONTRIBUTED_FORMATTED: string,
     *     TOTAL_REFUNDED_FORMATTED: string,
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
        ]);
    }
}
