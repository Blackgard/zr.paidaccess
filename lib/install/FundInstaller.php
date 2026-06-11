<?php

namespace Zr\PaidAccess\Install;

use Bitrix\Main\Config\Option;
use Bitrix\Main\SiteTable;
use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\Fund\FundMovementRepository;
use Zr\PaidAccess\Fund\FundMovementService;
use Zr\PaidAccess\Fund\FundService;
use Zr\PaidAccess\Gateway\GatewayTestService;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Subscription\SubscriptionAmountBreakdown;
use Zr\PaidAccess\Tables\FundMovementTable;
use Zr\PaidAccess\Tables\FundTable;
use Zr\PaidAccess\Tables\PaymentTable;
use Zr\PaidAccess\Tables\TableInstaller;

class FundInstaller
{
    private const OPTION_BACKFILLED = 'FUND_MOVEMENTS_BACKFILLED';
    private const OPTION_MOVEMENTS_CORRECTED = 'FUND_MOVEMENTS_AMOUNT_CORRECTED';

    public static function ensureSchema(): void
    {
        TableInstaller::ensureFundTables();
        self::ensureDefaultFunds();
        self::backfillMovements();
        self::correctMovementAmounts();
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

    /**
     * Корректировка сумм движений: в ledger только фондовый взнос, не полная оплата.
     */
    public static function correctMovementAmounts(): void
    {
        if (Option::get(PaidAccessCore::MODULE_ID, self::OPTION_MOVEMENTS_CORRECTED, 'N') === 'Y') {
            return;
        }

        if (!\Bitrix\Main\Application::getConnection()->isTableExists(PaymentTable::getTableName())
            || !\Bitrix\Main\Application::getConnection()->isTableExists(FundMovementTable::getTableName())
        ) {
            return;
        }

        $paymentFields = \Bitrix\Main\Application::getConnection()->getTableFields(PaymentTable::getTableName());
        if (!isset($paymentFields['FUND_AMOUNT'])) {
            return;
        }

        $result = PaymentTable::getList([
            'filter' => ['=STATUS' => PaymentStatus::PAID],
            'select' => ['ID', 'AMOUNT', 'FUND_AMOUNT', 'TAX_AMOUNT', 'MAINTENANCE_AMOUNT'],
        ]);

        while ($payment = $result->fetch()) {
            if (GatewayTestService::isGatewayTestPayment($payment)) {
                continue;
            }

            $fundAmount = SubscriptionAmountBreakdown::resolveFundAmountFromPayment($payment);
            $chargeAmount = (float)($payment['AMOUNT'] ?? 0);
            if ($fundAmount <= 0 || $chargeAmount <= 0 || abs($fundAmount - $chargeAmount) < 0.01) {
                continue;
            }

            $income = FundMovementRepository::findPaymentIncome((int)$payment['ID']);
            if (!$income) {
                continue;
            }

            $movementAmount = (float)($income['AMOUNT'] ?? 0);
            if (abs($movementAmount - $chargeAmount) < 0.01 && abs($movementAmount - $fundAmount) >= 0.01) {
                FundMovementTable::update((int)$income['ID'], ['AMOUNT' => $fundAmount]);
            }

            $refund = FundMovementRepository::findPaymentRefund((int)$payment['ID']);
            if ($refund) {
                $refundAmount = (float)($refund['AMOUNT'] ?? 0);
                if (abs($refundAmount - $chargeAmount) < 0.01 && abs($refundAmount - $fundAmount) >= 0.01) {
                    FundMovementTable::update((int)$refund['ID'], ['AMOUNT' => $fundAmount]);
                }
            }
        }

        Option::set(PaidAccessCore::MODULE_ID, self::OPTION_MOVEMENTS_CORRECTED, 'Y');
    }
}
