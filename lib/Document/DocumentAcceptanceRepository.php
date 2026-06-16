<?php

namespace Zr\PaidAccess\Document;

use Bitrix\Main\Type\DateTime;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Tables\DocumentAcceptanceTable;

class DocumentAcceptanceRepository
{
    public static function hasAcceptance(int $userId, int $versionId): bool
    {
        if ($userId <= 0 || $versionId <= 0) {
            return false;
        }

        $row = DocumentAcceptanceTable::getList([
            'filter' => [
                '=USER_ID' => $userId,
                '=VERSION_ID' => $versionId,
            ],
            'select' => ['ID'],
            'limit' => 1,
        ])->fetch();

        return is_array($row);
    }

    /**
     * @param int[] $versionIds
     * @return int[]
     */
    public static function getAcceptedVersionIds(int $userId, array $versionIds): array
    {
        $userId = (int)$userId;
        $versionIds = array_values(array_unique(array_filter(array_map('intval', $versionIds))));
        if ($userId <= 0 || $versionIds === []) {
            return [];
        }

        $accepted = [];
        $result = DocumentAcceptanceTable::getList([
            'filter' => [
                '=USER_ID' => $userId,
                '@VERSION_ID' => $versionIds,
            ],
            'select' => ['VERSION_ID'],
        ]);

        while ($row = $result->fetch()) {
            $accepted[] = (int)$row['VERSION_ID'];
        }

        return $accepted;
    }

    public static function record(int $userId, int $documentId, int $versionId, ?string $siteId = null): int
    {
        $userId = (int)$userId;
        $documentId = (int)$documentId;
        $versionId = (int)$versionId;
        $siteId = PaidAccessCore::normalizeSiteId($siteId);

        if ($userId <= 0 || $documentId <= 0 || $versionId <= 0) {
            throw new \InvalidArgumentException('Некорректные данные согласия');
        }

        if (self::hasAcceptance($userId, $versionId)) {
            return 0;
        }

        $result = DocumentAcceptanceTable::add([
            'USER_ID' => $userId,
            'DOCUMENT_ID' => $documentId,
            'VERSION_ID' => $versionId,
            'SITE_ID' => $siteId,
            'DATE_ACCEPT' => new DateTime(),
        ]);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getHistoryByUserAndDocument(int $userId, int $documentId): array
    {
        if ($userId <= 0 || $documentId <= 0) {
            return [];
        }

        $rows = [];
        $result = DocumentAcceptanceTable::getList([
            'filter' => [
                '=USER_ID' => $userId,
                '=DOCUMENT_ID' => $documentId,
            ],
            'order' => ['DATE_ACCEPT' => 'DESC'],
        ]);

        while ($row = $result->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getHistoryByVersionId(int $versionId, int $limit = 50, int $offset = 0): array
    {
        if ($versionId <= 0) {
            return [];
        }

        $rows = [];
        $result = DocumentAcceptanceTable::getList([
            'filter' => ['=VERSION_ID' => $versionId],
            'order' => ['DATE_ACCEPT' => 'DESC'],
            'limit' => $limit,
            'offset' => $offset,
        ]);

        while ($row = $result->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }
}
