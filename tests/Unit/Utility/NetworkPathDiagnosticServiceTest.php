<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Utility;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffInitDiagnosticService;
use Zr\PaidAccess\Utility\NetworkPathDiagnosticService;

final class NetworkPathDiagnosticServiceTest extends TestCase
{
    public function testSanitizeHostStripsSchemeAndPath(): void
    {
        $this->assertSame(
            'securepay.tinkoff.ru',
            NetworkPathDiagnosticService::sanitizeHost('https://securepay.tinkoff.ru/v2/Init')
        );
    }

    public function testSanitizeHostRejectsInvalidCharacters(): void
    {
        $this->assertSame('', NetworkPathDiagnosticService::sanitizeHost('host with spaces'));
        $this->assertSame('', NetworkPathDiagnosticService::sanitizeHost('../etc/passwd'));
    }

    public function testFormatHttpClientErrorEncodesArray(): void
    {
        $formatted = NetworkPathDiagnosticService::formatHttpClientError([
            'code' => 28,
            'message' => 'Timeout',
        ]);

        $this->assertStringContainsString('Timeout', $formatted);
        $this->assertStringContainsString('28', $formatted);
        $this->assertNotSame('Array', $formatted);
    }

    public function testBuildSupportPackageIncludesKeyFields(): void
    {
        $report = [
            'generatedAt' => '2026-06-26T17:00:00+03:00',
            'gatewayId' => 1,
            'gatewayName' => 'T-Bank prod',
            'testMode' => false,
            'initUrl' => 'https://securepay.tinkoff.ru/v2/Init',
            'targetHost' => 'securepay.tinkoff.ru',
            'timeoutSeconds' => 40,
            'summary' => [
                'outboundPublicIp' => '203.0.113.10',
                'outboundIpSource' => 'https://api.ipify.org?format=json',
                'dnsResolved' => true,
                'dnsAddresses' => ['1.2.3.4'],
                'tcp443Success' => true,
                'initRequestAt' => '2026-06-26T17:00:05+03:00',
                'initHttpStatus' => 0,
                'initSuccess' => false,
                'initDurationMs' => 40001.2,
                'tracerouteAvailable' => true,
            ],
            'steps' => [
                [
                    'code' => 'traceroute',
                    'title' => 'Traceroute',
                    'data' => ['output' => "1  10.0.0.1\n2  1.2.3.4"],
                ],
                [
                    'code' => 'init',
                    'title' => 'Init',
                    'data' => [
                        'responseRaw' => '{"Success":false}',
                        'httpError' => '{"code":28,"message":"Timeout"}',
                    ],
                ],
            ],
        ];

        $text = TinkoffInitDiagnosticService::buildSupportPackage($report);

        $this->assertStringContainsString('203.0.113.10', $text);
        $this->assertStringContainsString('2026-06-26T17:00:05+03:00', $text);
        $this->assertStringContainsString('securepay.tinkoff.ru', $text);
        $this->assertStringContainsString('10.0.0.1', $text);
        $this->assertStringContainsString('Timeout', $text);
    }
}
