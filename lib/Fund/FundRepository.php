<?php

namespace Zr\PaidAccess\Fund;

use Bitrix\Main\Type\DateTime;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Tables\FundTable;

class FundRepository
{
    public const DEFAULT_CODE = 'default';

    public static function getById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = FundTable::getByPrimary($id)->fetch();

        return is_array($row) ? $row : null;
    }

    public static function getDefaultBySiteId(?string $siteId = null): ?array
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);

        $row = FundTable::getList([
            'filter' => [
                '=SITE_ID' => $siteId,
                '=CODE' => self::DEFAULT_CODE,
                '=IS_DEFAULT' => 'Y',
                '=ACTIVE' => 'Y',
            ],
            'limit' => 1,
        ])->fetch();

        return is_array($row) ? $row : null;
    }

    public static function create(array $fields): int
    {
        $now = new DateTime();
        $data = array_merge([
            'DATE_CREATE' => $now,
            'CURRENCY' => 'RUB',
            'IS_DEFAULT' => 'Y',
            'ACTIVE' => 'Y',
            'CODE' => self::DEFAULT_CODE,
        ], $fields);

        $result = FundTable::add($data);
        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public static function update(int $id, array $fields): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Некорректный ID фонда');
        }

        $result = FundTable::update($id, $fields);
        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, string> $order
     * @return array<int, array<string, mixed>>
     */
    public static function getList(array $filter = [], array $order = ['ID' => 'DESC'], int $limit = 0, int $offset = 0): array
    {
        $params = [
            'filter' => $filter,
            'order' => $order,
        ];

        if ($limit > 0) {
            $params['limit'] = $limit;
            $params['offset'] = $offset;
        }

        $rows = [];
        $result = FundTable::getList($params);
        while ($row = $result->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    public static function getCount(array $filter = []): int
    {
        return (int)FundTable::getCount($filter);
    }

    public static function findBySiteAndCode(string $siteId, string $code): ?array
    {
        $row = FundTable::getList([
            'filter' => [
                '=SITE_ID' => PaidAccessCore::normalizeSiteId($siteId),
                '=CODE' => trim($code),
            ],
            'limit' => 1,
        ])->fetch();

        return is_array($row) ? $row : null;
    }
}
