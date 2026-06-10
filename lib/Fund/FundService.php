<?php

namespace Zr\PaidAccess\Fund;

use Zr\PaidAccess\PaidAccessCore;

class FundService
{
    public static function getDefaultFundBySiteId(?string $siteId = null): ?array
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);

        return FundRepository::getDefaultBySiteId($siteId)
            ?? FundRepository::getDefaultBySiteId('s1');
    }

    public static function ensureDefaultFund(?string $siteId = null): array
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $existing = FundRepository::getDefaultBySiteId($siteId);
        if ($existing) {
            return $existing;
        }

        $fundId = FundRepository::create([
            'SITE_ID' => $siteId,
            'CODE' => FundRepository::DEFAULT_CODE,
            'NAME' => 'Учредительный фонд',
            'IS_DEFAULT' => 'Y',
            'ACTIVE' => 'Y',
        ]);

        $fund = FundRepository::getById($fundId);
        if (!$fund) {
            throw new \RuntimeException('Не удалось создать фонд для сайта ' . $siteId);
        }

        return $fund;
    }
}
