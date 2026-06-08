<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Gateway;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffApiClient;

final class TinkoffApiClientTest extends TestCase
{
    public function testBuildTokenIsDeterministic(): void
    {
        $client = new TinkoffApiClient('TerminalKey', 'SecretKey');

        $params = [
            'Amount' => 100000,
            'OrderId' => 'PA-1-2026-05',
            'TerminalKey' => 'TerminalKey',
        ];

        $first = $client->buildToken($params);
        $second = $client->buildToken($params);

        $this->assertSame($first, $second);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first);
    }

    public function testBuildTokenSkipsNestedValues(): void
    {
        $client = new TinkoffApiClient('TerminalKey', 'SecretKey');

        $withNested = $client->buildToken([
            'Amount' => 100,
            'DATA' => ['Phone' => '+79990000000'],
            'TerminalKey' => 'TerminalKey',
        ]);

        $withoutNested = $client->buildToken([
            'Amount' => 100,
            'TerminalKey' => 'TerminalKey',
        ]);

        $this->assertSame($withoutNested, $withNested);
    }

    public function testVerifyNotificationTokenAcceptsValidToken(): void
    {
        $client = new TinkoffApiClient('TerminalKey', 'SecretKey');

        $payload = [
            'TerminalKey' => 'TerminalKey',
            'OrderId' => 'PA-42-2026-05',
            'Success' => true,
            'Status' => 'CONFIRMED',
            'PaymentId' => '999',
            'Amount' => 100000,
        ];

        $payload['Token'] = $client->buildToken([
            'TerminalKey' => 'TerminalKey',
            'OrderId' => 'PA-42-2026-05',
            'Success' => 'true',
            'Status' => 'CONFIRMED',
            'PaymentId' => '999',
            'Amount' => 100000,
        ]);

        $this->assertTrue($client->verifyNotificationToken($payload));
    }

    public function testVerifyNotificationTokenRejectsTamperedToken(): void
    {
        $client = new TinkoffApiClient('TerminalKey', 'SecretKey');

        $payload = [
            'TerminalKey' => 'TerminalKey',
            'OrderId' => 'PA-42-2026-05',
            'Success' => true,
            'Status' => 'CONFIRMED',
            'Token' => 'deadbeef',
        ];

        $this->assertFalse($client->verifyNotificationToken($payload));
    }

    public function testVerifyNotificationTokenRequiresTokenField(): void
    {
        $client = new TinkoffApiClient('TerminalKey', 'SecretKey');

        $this->assertFalse($client->verifyNotificationToken(['OrderId' => 'x']));
    }

    public function testParseResponseExplainsTestApi403(): void
    {
        $html = '<html><head><title>403 Forbidden</title></head><body><center><h1>403 Forbidden</h1></center></body></html>';

        $result = TinkoffApiClient::parseResponse(403, $html, true);

        $this->assertFalse($result['Success']);
        $this->assertStringContainsString('whitelist', (string)$result['Message']);
        $this->assertStringContainsString('openapi@tbank.ru', (string)$result['Details']);
    }

    public function testParseResponseReturnsJsonBodyForSuccessfulHttp(): void
    {
        $result = TinkoffApiClient::parseResponse(
            200,
            '{"Success":true,"PaymentId":"123"}',
            false
        );

        $this->assertTrue($result['Success']);
        $this->assertSame('123', $result['PaymentId']);
    }
}
