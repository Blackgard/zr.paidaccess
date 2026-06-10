<?php

namespace Zr\PaidAccess\Install;

use Bitrix\Main\Config\Option;
use Bitrix\Main\SiteTable;
use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\Fund\FundMovementService;
use Zr\PaidAccess\Fund\FundService;
use Zr\PaidAccess\Gateway\GatewayTestService;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Tables\FundMovementTable;
use Zr\PaidAccess\Tables\FundTable;
use Zr\PaidAccess\Tables\PaymentTable;
use Zr\PaidAccess\Tables\TableInstaller;

class FundInstaller
{
    private const OPTION_BACKFILLED = 'FUND_MOVEMENTS_BACKFILLED';

    public static function ensureSchema(): void
    {
        TableInstaller::ensureFundTables();
        self::ensureDefaultFunds();
        self::backfillMovements();
    }

    public static function ensureDefaultFunds(): void
    {
        $siteIds = ['s1'];
        if (class_exists(SiteTable::class)) {
            $result = SiteTable::getList([
                'filter' => ['=ACTIVE' => 'Y'],
                'select' => ['LID'],
            ]);
            while ($row = $result->fetch()) {
                $lid = trim((string)($row['LID'] ?? ''));
                if ($lid !== '') {
                    $siteIds[] = PaidAccessCore::normalizeSiteId($lid);
                }
            }
        }

        $siteIds = array_values(array_unique($siteIds));
        foreach ($siteIds as $siteId) {
            FundService::ensureDefaultFund($siteId);
        }
    }

    public static function backfillMovements(): void
    {
        if (Option::get(PaidAccessCore::MODULE_ID, self::OPTION_BACKFILLED, 'N') === 'Y') {
            return;
        }

        if (!\Bitrix\Main\Application::getConnection()->isTableExists(PaymentTable::getTableName())
            || !\Bitrix\Main\Application::getConnection()->isTableExists(FundMovementTable::getTableName())
        ) {
            return;
        }

        $result = PaymentTable::getList([
            'filter' => ['=STATUS' => PaymentStatus::PAID],
            'select' => ['ID', 'GATEWAY_ID', 'BILLING_PERIOD', 'USER_ID', 'AMOUNT', 'ORDER_ID', 'DESCRIPTION'],
            'order' => ['ID' => 'ASC'],
        ]);

        while ($payment = $result->fetch()) {
            if (GatewayTestService::isGatewayTestPayment($payment)) {
                continue;
            }

            try {
                FundMovementService::recordPaymentIncome((int)$payment['ID']);
            } catch (\Throwable $e) {
                // Пропускаем единичные ошибки при миграции.
            }
        }

        Option::set(PaidAccessCore::MODULE_ID, self::OPTION_BACKFILLED, 'Y');
    }
}
