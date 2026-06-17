<?php

namespace Zr\PaidAccess\Document;

use Bitrix\Main\Type\DateTime;
use Zr\PaidAccess\Tables\RequiredDocumentVersionTable;

class RequiredDocumentVersionRepository
{
    public static function getById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = RequiredDocumentVersionTable::getByPrimary($id)->fetch();

        return is_array($row) ? $row : null;
    }

    public static function getCurrentByDocumentId(int $documentId): ?array
    {
        if ($documentId <= 0) {
            return null;
        }

        $row = RequiredDocumentVersionTable::getList([
            'filter' => [
                '=DOCUMENT_ID' => $documentId,
                '=IS_CURRENT' => 'Y',
            ],
            'limit' => 1,
        ])->fetch();

        return is_array($row) ? $row : null;
    }

    public static function getLatestByDocumentId(int $documentId): ?array
    {
        if ($documentId <= 0) {
            return null;
        }

        $row = RequiredDocumentVersionTable::getList([
            'filter' => ['=DOCUMENT_ID' => $documentId],
            'order' => ['ID' => 'DESC'],
            'limit' => 1,
        ])->fetch();

        return is_array($row) ? $row : null;
    }

    public static function versionExists(int $documentId, string $version): bool
    {
        if ($documentId <= 0 || $version === '') {
            return false;
        }

        $row = RequiredDocumentVersionTable::getList([
            'filter' => [
                '=DOCUMENT_ID' => $documentId,
                '=VERSION' => $version,
            ],
            'limit' => 1,
        ])->fetch();

        return is_array($row);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getListByDocumentId(int $documentId, array $order = ['ID' => 'DESC']): array
    {
        if ($documentId <= 0) {
            return [];
        }

        $rows = [];
        $result = RequiredDocumentVersionTable::getList([
            'filter' => ['=DOCUMENT_ID' => $documentId],
            'order' => $order,
        ]);

        while ($row = $result->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    public static function add(array $fields): int
    {
        $result = RequiredDocumentVersionTable::add($fields);
        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public static function update(int $id, array $fields): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Некорректный ID версии');
        }

        $result = RequiredDocumentVersionTable::update($id, $fields);
        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    public static function clearCurrentFlag(int $documentId): void
    {
        if ($documentId <= 0) {
            return;
        }

        $result = RequiredDocumentVersionTable::getList([
            'filter' => [
                '=DOCUMENT_ID' => $documentId,
                '=IS_CURRENT' => 'Y',
            ],
            'select' => ['ID'],
        ]);

        while ($row = $result->fetch()) {
            RequiredDocumentVersionTable::update((int)$row['ID'], ['IS_CURRENT' => 'N']);
        }
    }

    public static function getPublishDateFormatted(array $version, bool $withTime = true): string
    {
        $date = $version['DATE_PUBLISH'] ?? null;
        if ($date instanceof DateTime) {
            return $date->format($withTime ? 'd.m.Y H:i' : 'd.m.Y');
        }

        return '';
    }
}
