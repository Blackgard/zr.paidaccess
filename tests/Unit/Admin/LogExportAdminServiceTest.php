<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Admin\LogExportAdminService;
use Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffApiClient;

final class LogExportAdminServiceTest extends TestCase
{
    public function testBuildFileNameContainsTab(): void
    {
        $fileName = LogExportAdminService::buildFileName('gateway');

        $this->assertStringContainsString('gateway', $fileName);
        $this->assertStringEndsWith('.json', $fileName);
    }

    public function testExportLimitIsPositive(): void
    {
        $this->assertGreaterThan(0, LogExportAdminService::EXPORT_LIMIT);
    }

    public function testTinkoffOutboundHeadersAreLogged(): void
    {
        $headers = TinkoffApiClient::getOutboundRequestHeaders();

        $this->assertSame('application/json', $headers['Content-Type']);
        $this->assertSame('application/json', $headers['Accept']);
    }
}
