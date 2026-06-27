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

    public function testBuildNotificationTokenMatchesTbankDocumentationExample(): void
    {
        $client = new TinkoffApiClient('1234567890DEMO', '11111111111');

        $token = $client->buildNotificationToken([
            'TerminalKey' => '1234567890DEMO',
            'OrderId' => '000000',
            'Success' => true,
            'Status' => 'AUTHORIZED',
            'PaymentId' => '0000000',
            'ErrorCode' => '0',
            'Amount' => '1111',
            'CardId' => '000000',
            'Pan' => '200000******0000',
            'ExpDate' => '1111',
            'RebillId' => '000000',
        ]);

        $this->assertSame(
            '1c0964277d0213349243065a0d5b838b8e90d2d25f740d0f2767836e710e80c8',
            $token
        );
    }

    public function testVerifyNotificationTokenIgnoresNullRootFields(): void
    {
        $client = new TinkoffApiClient('TerminalKey', 'SecretKey');

        $base = [
            'TerminalKey' => 'TerminalKey',
            'OrderId' => 'PA-4-2026-06-08',
            'Success' => 'true',
            'Status' => 'CONFIRMED',
            'PaymentId' => '8648510120',
            'ErrorCode' => '0',
            'Amount' => 140000,
        ];

        $payload = $base;
        $payload['Success'] = true;
        $payload['CardId'] = null;
        $payload['Token'] = $client->buildNotificationToken($base);

        $this->assertTrue($client->verifyNotificationToken($payload));
    }

    public function testParseResponseExplainsTestApi403(): void
    {
        $html = '<html><head><title>403 Forbidden</title></head><body><center><h1>403 Forbidden</h1></center></body></html>';

        $result = TinkoffApiClient::parseResponse(403, $html, true);

        $this->assertFalse($result['Success']);
        $this->assertStringContainsString('whitelist', (string)$result['Message']);
        $this->assertStringContainsString('openapi@tbank.ru', (string)$result['Details']);
    }

    public function testVerifyNotificationTokenWithIntegerBankIds(): void
    {
        $client = new TinkoffApiClient('TerminalKey', 'SecretKey');

        $payload = [
            'TerminalKey' => 'TerminalKey',
            'OrderId' => 'PA-6-2026-06-08',
            'Success' => true,
            'Status' => 'REFUNDED',
            'PaymentId' => 8640548572,
            'ErrorCode' => '0',
            'Amount' => 140000,
            'CardId' => 682206508,
            'Pan' => '500000******0108',
            'ExpDate' => '1230',
        ];

        $payload['Token'] = $client->buildNotificationToken($payload);

        $this->assertTrue($client->verifyNotificationToken($payload));
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
        $this->assertSame(200, $result['HttpStatus']);
    }

    public function testParseResponseIncludesHttpStatusForServerError(): void
    {
        $result = TinkoffApiClient::parseResponse(
            500,
            '{"Success":false,"Message":"Internal error"}',
            false
        );

        $this->assertFalse($result['Success']);
        $this->assertSame(500, $result['HttpStatus']);
    }

    public function testGetOutboundRequestHeaders(): void
    {
        $headers = TinkoffApiClient::getOutboundRequestHeaders();

        $this->assertSame('application/json', $headers['Content-Type']);
        $this->assertSame('application/json', $headers['Accept']);
    }

    public function testHttpTimeoutMatchesBankRecommendation(): void
    {
        $this->assertSame(40, TinkoffApiClient::HTTP_TIMEOUT_SECONDS);
    }
}
