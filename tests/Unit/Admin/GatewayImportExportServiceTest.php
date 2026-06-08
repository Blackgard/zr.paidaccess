<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Admin\GatewayImportExportService;
use Zr\PaidAccess\Tests\Support\ReflectionHelper;

final class GatewayImportExportServiceTest extends TestCase
{
    public function testImportFromJsonRejectsInvalidJson(): void
    {
        $result = GatewayImportExportService::importFromJson('{not json');

        $this->assertSame(0, $result['created']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('JSON', $result['errors'][0]);
    }

    public function testImportFromJsonRejectsWrongFormat(): void
    {
        $json = json_encode([
            'format' => 'other.format',
            'version' => 1,
            'gateways' => [['name' => 'x', 'provider' => 'tinkoff']],
        ], JSON_UNESCAPED_UNICODE);

        $result = GatewayImportExportService::importFromJson($json);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('формат', mb_strtolower($result['errors'][0]));
    }

    public function testImportFromJsonRejectsEmptyGateways(): void
    {
        $json = json_encode([
            'format' => GatewayImportExportService::FORMAT,
            'version' => GatewayImportExportService::VERSION,
            'gateways' => [],
        ], JSON_UNESCAPED_UNICODE);

        $result = GatewayImportExportService::importFromJson($json);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('gateways', $result['errors'][0]);
    }

    /**
     * @dataProvider yesNoProvider
     * @param mixed $input
     */
    public function testToYesNo($input, string $expected): void
    {
        $actual = ReflectionHelper::invokeStatic(
            GatewayImportExportService::class,
            'toYesNo',
            $input
        );

        $this->assertSame($expected, $actual);
    }

    public function yesNoProvider(): array
    {
        return [
            'true' => [true, 'Y'],
            'Y' => ['Y', 'Y'],
            '1' => [1, 'Y'],
            'false' => [false, 'N'],
            'N' => ['N', 'N'],
            'zero' => [0, 'N'],
        ];
    }

    public function testBuildMatchKeyIsCaseInsensitiveForName(): void
    {
        $key = ReflectionHelper::invokeStatic(
            GatewayImportExportService::class,
            'buildMatchKey',
            'Tinkoff',
            's1',
            ' Main Gateway '
        );

        $this->assertSame('tinkoff|s1|main gateway', $key);
    }
}
