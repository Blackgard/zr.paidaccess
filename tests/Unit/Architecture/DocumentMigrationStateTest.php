<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

final class DocumentMigrationStateTest extends TestCase
{
    public function testDocumentIblockMigrationDoesNotPersistFormStateInOptions(): void
    {
        $moduleRoot = dirname(__DIR__, 3);
        $serviceContent = (string)file_get_contents($moduleRoot . '/lib/Admin/DocumentIblockMigrationService.php');
        $adminPageContent = (string)file_get_contents($moduleRoot . '/admin/zr_paidaccess_util_document_iblock.php');

        $this->assertStringNotContainsString('Bitrix\Main\Config\Option', $serviceContent);
        $this->assertStringNotContainsString('Option::', $serviceContent);
        $this->assertStringNotContainsString('document_iblock_migration_', $serviceContent);
        $this->assertStringNotContainsString('saveMapping', $serviceContent . $adminPageContent);
        $this->assertStringNotContainsString('loadSaved', $serviceContent . $adminPageContent);
    }
}
