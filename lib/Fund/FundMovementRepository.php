<?php

namespace Zr\PaidAccess\Fund;

use Bitrix\Main\Type\DateTime;
use Zr\PaidAccess\Admin\SubscriberAdminService;
use Zr\PaidAccess\Enum\FundMovementSource;
use Zr\PaidAccess\Enum\FundMovementType;
use Zr\PaidAccess\Tables\FundMovementTable;

class FundMovementRepository
{
    public static function create(array $fields): int
    {
        $data = array_merge([
            'DATE_CREATE' => new DateTime(),
            'CURRENCY' => 'RUB',
        ], $fields);

        $result = FundMovementTable::add($data);
        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public static function findPaymentIncome(int $paymentId): ?array
    {
        if ($paymentId <= 0) {
            return null;
        }

        $row = FundMovementTable::getList([
            'filter' => [
                '=PAYMENT_ID' => $paymentId,
                '=TYPE' => FundMovementType::INCOME,
                '=SOURCE' => FundMovementSource::PAYMENT,
            ],
            'limit' => 1,
        ])->fetch();

        return is_array($row) ? $row : null;
    }

    public static function findPaymentRefund(int $paymentId): ?array
    {
        if ($paymentId <= 0) {
            return null;
        }

        $row = FundMovementTable::getList([
            'filter' => [
                '=PAYMENT_ID' => $paymentId,
                '=TYPE' => FundMovementType::EXPENSE,
                '=SOURCE' => FundMovementSource::REFUND,
            ],
            'limit' => 1,
        ])->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array{income: float, expense: float}
     */
    public static function sumByFund(int $fundId): array
    {
        $income = 0.0;
        $expense = 0.0;

        if ($fundId <= 0) {
            return ['income' => $income, 'expense' => $expense];
        }

        $result = FundMovementTable::getList([
            'filter' => ['=FUND_ID' => $fundId],
            'select' => ['TYPE', 'AMOUNT'],
        ]);

        while ($row = $result->fetch()) {
            $amount = (float)($row['AMOUNT'] ?? 0);
            if ((string)($row['TYPE'] ?? '') === FundMovementType::EXPENSE) {
                $expense += $amount;
            } else {
                $income += $amount;
            }
        }

        return ['income' => $income, 'expense' => $expense];
    }

    public static function countDistinctPayers(int $fundId): int
    {
        if ($fundId <= 0) {
            return 0;
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
            $userIds[(int)$row['USER_ID']] = true;
        }

        return count($userIds);
    }

    public static function getById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = FundMovementTable::getByPrimary($id)->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $filter
     * @return array<int, array<string, mixed>>
     */
    public static function getList(array $filter, array $order = ['DATE_CREATE' => 'DESC', 'ID' => 'DESC'], int $limit = 0, int $offset = 0): array
    {
        $params = [
            'filter' => $filter,
            'order' => $order,
            'select' => [
                '*',
                'USER_NAME' => 'USER.NAME',
                'USER_LAST_NAME' => 'USER.LAST_NAME',
            ],
        ];

        if ($limit > 0) {
            $params['limit'] = $limit;
            $params['offset'] = $offset;
        }

        $rows = [];
        $result = FundMovementTable::getList($params);
        while ($row = $result->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    public static function getCount(array $filter): int
    {
        return (int)FundMovementTable::getCount($filter);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listForWallet(int $fundId): array
    {
        if ($fundId <= 0) {
            return [];
        }

        $items = [];
        $result = FundMovementTable::getList([
            'filter' => ['=FUND_ID' => $fundId],
            'select' => [
                'ID',
                'TYPE',
                'AMOUNT',
                'DESCRIPTION',
                'SOURCE',
                'ORDER_ID',
                'EXTERNAL_REF',
                'DATE_CREATE',
                'USER_NAME' => 'USER.NAME',
                'USER_LAST_NAME' => 'USER.LAST_NAME',
            ],
            'order' => ['DATE_CREATE' => 'DESC', 'ID' => 'DESC'],
        ]);

        while ($row = $result->fetch()) {
            $items[] = self::formatWalletRow($row);
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected static function formatWalletRow(array $row): array
    {
        $type = (string)($row['TYPE'] ?? '');
        $amount = (float)($row['AMOUNT'] ?? 0);
        $isIncome = $type === FundMovementType::INCOME;
        $dateCreate = $row['DATE_CREATE'] ?? null;
        $dateStr = '';

        if ($dateCreate instanceof DateTime) {
            $dateStr = $dateCreate->format('d.m.Y');
        } elseif ($dateCreate instanceof \DateTimeInterface) {
            $dateStr = $dateCreate->format('d.m.Y');
        }

        $transactionRef = (string)($row['ORDER_ID'] ?? '');
        if ($transactionRef === '') {
            $transactionRef = (string)($row['EXTERNAL_REF'] ?? '');
        }
        if ($transactionRef === '') {
            $transactionRef = 'FM-' . (int)($row['ID'] ?? 0);
        }

        $payerName = '';
        if ($isIncome && (string)($row['SOURCE'] ?? '') === FundMovementSource::PAYMENT) {
            $payerName = SubscriberAdminService::formatUserName([
                'NAME' => (string)($row['USER_NAME'] ?? ''),
                'LAST_NAME' => (string)($row['USER_LAST_NAME'] ?? ''),
            ]);
        } else {
            $payerName = (string)($row['DESCRIPTION'] ?? '');
        }

        return [
            'ID' => (int)($row['ID'] ?? 0),
            'TYPE' => $type,
            'TRANSACTION_REF' => $transactionRef,
            'DATE' => $dateStr,
            'PAYER_NAME' => $payerName,
            'AMOUNT' => $amount,
            'AMOUNT_FORMATTED' => FundBalanceService::formatRubles($amount),
            'IS_INCOME' => $isIncome,
        ];
    }
}
