<?php

namespace Zr\PaidAccess\Document;

use Bitrix\Main\Type\DateTime;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Tables\RequiredDocumentTable;

class RequiredDocumentRepository
{
    public static function getById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = RequiredDocumentTable::getByPrimary($id)->fetch();

        return is_array($row) ? $row : null;
    }

    public static function getByCode(string $code, ?string $siteId = null): ?array
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $row = RequiredDocumentTable::getList([
            'filter' => [
                '=SITE_ID' => $siteId,
                '=CODE' => $code,
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
            'DATE_UPDATE' => $now,
            'SORT' => 500,
            'ACTIVE' => 'Y',
            'IS_REQUIRED' => 'Y',
        ], $fields);

        $result = RequiredDocumentTable::add($data);
        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public static function update(int $id, array $fields): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Некорректный ID документа');
        }

        $fields['DATE_UPDATE'] = new DateTime();

        $result = RequiredDocumentTable::update($id, $fields);
        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, string> $order
     * @return array<int, array<string, mixed>>
     */
    public static function getList(array $filter = [], array $order = ['SORT' => 'ASC', 'ID' => 'ASC'], int $limit = 0, int $offset = 0): array
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
        $result = RequiredDocumentTable::getList($params);
        while ($row = $result->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    public static function getCount(array $filter = []): int
    {
        return (int)RequiredDocumentTable::getCount($filter);
    }

    /**
     * Активные обязательные документы сайта с опубликованной текущей версией.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getRequiredWithCurrentVersion(?string $siteId = null): array
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $documents = self::getList([
            '=SITE_ID' => $siteId,
            '=ACTIVE' => 'Y',
            '=IS_REQUIRED' => 'Y',
        ]);

        $result = [];
        foreach ($documents as $document) {
            $version = RequiredDocumentVersionRepository::getCurrentByDocumentId((int)$document['ID']);
            if ($version === null) {
                continue;
            }

            $result[] = array_merge($document, [
                'CURRENT_VERSION' => $version,
            ]);
        }

        return $result;
    }
}
