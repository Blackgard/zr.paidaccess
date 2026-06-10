<?php

namespace Zr\PaidAccess\PublicUi;

use Zr\PaidAccess\Fund\FundBalanceService;
use Zr\PaidAccess\Fund\FundMovementRepository;
use Zr\PaidAccess\Fund\FundService;
use Zr\PaidAccess\PaidAccessCore;

/**
 * Публичные данные кошелька учредительного фонда на базе ledger движений.
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
    public static function getWalletData(?string $siteId = null): array
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $fund = FundService::getDefaultFundBySiteId($siteId);
        if (!$fund) {
            return [
                'TOTAL_AMOUNT' => 0,
                'PAYER_COUNT' => 0,
                'ITEMS' => [],
            ];
        }
        $fundId = (int)$fund['ID'];

        return [
            'TOTAL_AMOUNT' => FundBalanceService::getBalance($fundId),
            'PAYER_COUNT' => FundMovementRepository::countDistinctPayers($fundId),
            'ITEMS' => FundMovementRepository::listForWallet($fundId),
        ];
    }

    public static function roundRubles(float $amount): int
    {
        return FundBalanceService::roundRubles($amount);
    }

    public static function formatRubles(float $amount): string
    {
        return FundBalanceService::formatRubles($amount);
    }
}
