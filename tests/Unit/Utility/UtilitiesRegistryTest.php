<?php

namespace Zr\PaidAccess\Tests\Unit\Utility;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Utility\UtilitiesRegistry;

class UtilitiesRegistryTest extends TestCase
{
    public function testMigrationGroupContainsDocumentIblockUtility(): void
    {
        $group = UtilitiesRegistry::findGroup(UtilitiesRegistry::GROUP_MIGRATION);
        $this->assertNotNull($group);
        $this->assertArrayHasKey('document_iblock', $group['utilities']);

        $found = UtilitiesRegistry::findUtility(UtilitiesRegistry::GROUP_MIGRATION, 'document_iblock');
        $this->assertNotNull($found);
        $this->assertStringContainsString('zr_paidaccess_util_document_iblock.php', (string)$found['utility']['page']);
    }

    public function testBuildUtilityUrl(): void
    {
        $url = UtilitiesRegistry::buildUtilityUrl(UtilitiesRegistry::GROUP_MIGRATION, 'document_iblock', 'ru');
        $this->assertSame('zr_paidaccess_util_document_iblock.php?lang=ru', $url);
    }
}
