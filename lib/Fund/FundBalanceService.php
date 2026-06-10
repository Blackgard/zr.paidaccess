<?php

namespace Zr\PaidAccess\Fund;

class FundBalanceService
{
    public static function getAvailableBalance(int $fundId): float
    {
        $totals = FundMovementRepository::sumByFund($fundId);

        return self::calculateAvailableBalance($totals['income'], $totals['expense']);
    }

    public static function getBalance(int $fundId): int
    {
        return self::roundRubles(self::getAvailableBalance($fundId));
    }

    public static function calculateAvailableBalance(float $totalIncome, float $totalExpense): float
    {
        return $totalIncome - $totalExpense;
    }

    public static function roundRubles(float $amount): int
    {
        return (int) round($amount);
    }

    public static function formatRubles(float $amount): string
    {
        return number_format(self::roundRubles($amount), 0, '.', ' ');
    }
}
