<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Gateway;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Gateway\Dto\InitPaymentRequest;
use Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffConfig;
use Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffReceiptBuilder;

final class TinkoffReceiptBuilderTest extends TestCase
{
    public function testInitWithoutReceiptContainsOnlyAllowedRootFields(): void
    {
        $request = new InitPaymentRequest(
            'PA-1-2026-06-08',
            1430.0,
            'RUB',
            'Ежемесячный взнос подписки',
            1,
            'buyer@example.com',
            '+79990000000'
        );

        $body = TinkoffReceiptBuilder::buildInitBody($request, $this->createConfig([
            'enable_taxation' => '0',
        ]));

        $this->assertSame([
            'Amount' => 143000,
            'OrderId' => 'PA-1-2026-06-08',
            'Description' => 'Ежемесячный взнос подписки',
            'DATA' => [
                'Email' => 'buyer@example.com',
                'Phone' => '+79990000000',
            ],
        ], $body);
    }

    public function testInitWithFfd105ReceiptDoesNotSendUnsupportedFields(): void
    {
        $request = new InitPaymentRequest(
            'PA-2-2026-06-08',
            1430.0,
            'RUB',
            'Ежемесячный взнос подписки',
            1,
            'buyer@example.com'
        );

        $body = TinkoffReceiptBuilder::buildInitBody($request, $this->createConfig([
            'enable_taxation' => '1',
            'ffd' => 'N',
            'payment_object' => 'service',
        ]));

        $this->assertArrayNotHasKey('DATA', $body);
        $this->assertArrayHasKey('Receipt', $body);
        $this->assertArrayNotHasKey('EmailCompany', $body['Receipt']);
        $this->assertArrayNotHasKey('FfdVersion', $body['Receipt']);
        $this->assertSame('buyer@example.com', $body['Receipt']['Email']);
        $this->assertArrayNotHasKey('MeasurementUnit', $body['Receipt']['Items'][0]);
    }

    public function testInitWithFfd12ReceiptUsesDocumentedMeasurementUnit(): void
    {
        $request = new InitPaymentRequest(
            'PA-3-2026-06-08',
            1430.0,
            'RUB',
            'Ежемесячный взнос подписки',
            1,
            'buyer@example.com'
        );

        $body = TinkoffReceiptBuilder::buildInitBody($request, $this->createConfig([
            'enable_taxation' => '1',
            'ffd' => 'Y',
            'payment_object' => 'payment',
        ]));

        $this->assertArrayNotHasKey('EmailCompany', $body['Receipt']);
        $this->assertArrayNotHasKey('FfdVersion', $body['Receipt']);
        $this->assertSame('шт', $body['Receipt']['Items'][0]['MeasurementUnit']);
    }

    public function testDescriptionIsTruncatedToApiLimit(): void
    {
        $longDescription = str_repeat('А', 200);
        $request = new InitPaymentRequest('PA-4-2026-06-08', 100.0, 'RUB', $longDescription, 1);

        $body = TinkoffReceiptBuilder::buildInitBody($request, $this->createConfig([
            'enable_taxation' => '0',
        ]));

        $this->assertSame(140, mb_strlen((string)$body['Description']));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createConfig(array $options): TinkoffConfig
    {
        return new TinkoffConfig(array_merge([
            'taxation' => 'usn_income',
            'payment_method' => 'full_payment',
            'payment_object' => 'service',
            'item_tax' => 'none',
            'email_company' => 'company@example.com',
        ], $options));
    }
}
