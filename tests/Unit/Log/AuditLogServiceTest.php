<?php

namespace Zr\PaidAccess\Tests\Unit\Log;

use Bitrix\Main\Type\DateTime;
use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Log\AuditLogService;

class AuditLogServiceTest extends TestCase
{
    public function testEncodeSnapshotNormalizesDateTime(): void
    {
        $snapshot = AuditLogService::encodeSnapshot([
            'ID' => 42,
            'STATUS' => 'paid',
            'DATE_PAID' => new DateTime('2026-05-10 12:30:00', 'Y-m-d H:i:s'),
        ]);

        $this->assertIsString($snapshot);
        $decoded = json_decode((string)$snapshot, true);
        $this->assertSame(42, $decoded['ID']);
        $this->assertSame('paid', $decoded['STATUS']);
        $this->assertSame('2026-05-10 12:30:00', $decoded['DATE_PAID']);
    }

    public function testEncodeSnapshotReturnsNullForEmptyInput(): void
    {
        $this->assertNull(AuditLogService::encodeSnapshot(null));
    }
}
