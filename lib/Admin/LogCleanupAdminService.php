<?php

namespace Zr\PaidAccess\Admin;

use Bitrix\Main\ORM\Data\DataManager;
use Zr\PaidAccess\PaidAccessCore;

/**
 * Очистка журналов модуля в админке.
 */
class LogCleanupAdminService
{
    private const BATCH_SIZE = 500;

    /**
     * @param class-string<DataManager> $tableClass
     */
    public static function deleteByFilter(string $tableClass, array $ormFilter): int
    {
        $deleted = 0;

        while (true) {
            $ids = [];
            $result = $tableClass::getList([
                'filter' => $ormFilter,
                'select' => ['ID'],
                'limit' => self::BATCH_SIZE,
            ]);

            while ($row = $result->fetch()) {
                $ids[] = (int)$row['ID'];
            }

            if ($ids === []) {
                break;
            }

            foreach ($ids as $id) {
                $tableClass::delete($id);
                $deleted++;
            }
        }

        return $deleted;
    }

    public static function clearFileLog(?string $siteId = null): bool
    {
        $relativePath = PaidAccessCore::getLogPath($siteId);
        if ($relativePath === '' || $relativePath[0] !== '/') {
            return false;
        }

        $absolutePath = $_SERVER['DOCUMENT_ROOT'] . $relativePath;
        if (!is_file($absolutePath)) {
            return true;
        }

        return file_put_contents($absolutePath, '') !== false;
    }
}
