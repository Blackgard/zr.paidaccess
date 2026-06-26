<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Gateway;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Gateway\Dto\InitPaymentResult;

final class InitPaymentResultTest extends TestCase
{
    public function testExtractHttpCodeFromRawResponse(): void
    {
        $raw = json_encode(['Success' => false, 'HttpStatus' => 403, 'Message' => 'Forbidden'], JSON_UNESCAPED_UNICODE);

        $this->assertSame(403, InitPaymentResult::extractHttpCodeFromRaw($raw));
    }

    public function testFailSetsHttpCodeFromRawResponse(): void
    {
        $raw = json_encode(['Success' => false, 'HttpStatus' => 500, 'Message' => 'Error'], JSON_UNESCAPED_UNICODE);
        $result = InitPaymentResult::fail('Error', $raw);

        $this->assertSame(500, $result->getHttpCode());
    }

    public function testGetHttpCodeReturnsExplicitProperty(): void
    {
        $result = InitPaymentResult::fail('Error');
        $result->httpCode = 502;

        $this->assertSame(502, $result->getHttpCode());
    }
}
