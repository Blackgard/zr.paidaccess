<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\PublicUi;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\PublicUi\PanelSectionRegistry;

final class PanelSectionRegistryTest extends TestCase
{
    public function testLegacyNewsSectionMatchesOldPanel(): void
    {
        $section = PanelSectionRegistry::getSection('news');
        self::assertNotNull($section);
        self::assertSame('hub', $section['type']);

        $tiles = PanelSectionRegistry::resolveTiles($section, '/panel/');
        self::assertSame('/__panel_old/table_news/', $tiles[0]['url']);
        self::assertSame('/__panel_old/add_news/', $tiles[1]['url']);
    }

    public function testProjectsSectionIsPlanned(): void
    {
        $section = PanelSectionRegistry::getSection('projects');
        self::assertNotNull($section);
        self::assertSame('planned', $section['type']);

        $content = PanelSectionRegistry::buildHubContent($section, '/panel/');
        self::assertTrue($content['PLANNED']);
        self::assertNotEmpty($content['PLANNED_FEATURES']);
    }

    public function testBuildPanelUrl(): void
    {
        self::assertSame('/panel/', PanelSectionRegistry::buildPanelUrl('/panel/', 'index'));
        self::assertSame(
            '/panel/?page=payments',
            PanelSectionRegistry::buildPanelUrl('/panel/', 'payments')
        );
    }

    public function testIndexSectionsIncludeCoreBlocks(): void
    {
        $codes = array_column(PanelSectionRegistry::getIndexSections('/panel/'), 'CODE');
        self::assertContains('news', $codes);
        self::assertContains('users', $codes);
        self::assertContains('payments', $codes);
        self::assertContains('projects', $codes);
        self::assertContains('documents', $codes);
    }
}
