<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Gateway;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Gateway\Contract\DuplicateOrderRecoverableGatewayInterface;
use Zr\PaidAccess\Gateway\Contract\GatewayCancellableInterface;
use Zr\PaidAccess\Gateway\Contract\GatewayPaymentUrlExtractorInterface;
use Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffGateway;
use Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffProvider;

final class TinkoffProviderBoundaryTest extends TestCase
{
    public function testProviderNormalizesTestGatewayOptions(): void
    {
        $provider = new TinkoffProvider();

        $this->assertSame(
            ['terminal_key' => 'terminal', 'test_mode' => 'Y'],
            $provider->normalizeOptions(['terminal_key' => 'terminal', 'test_mode' => 'N'], true)
        );

        $this->assertSame(
            ['terminal_key' => 'terminal', 'test_mode' => 'N'],
            $provider->normalizeOptions(['terminal_key' => 'terminal', 'test_mode' => 'N'], false)
        );
    }

    public function testProviderExtractsPaymentUrl(): void
    {
        $provider = new TinkoffProvider();

        $this->assertInstanceOf(GatewayPaymentUrlExtractorInterface::class, $provider);
        $this->assertSame(
            'https://pay.example/form',
            $provider->extractPaymentUrl(json_encode(['PaymentURL' => 'https://pay.example/form']))
        );
    }

    public function testGatewayExposesGenericCapabilities(): void
    {
        $interfaces = class_implements(TinkoffGateway::class);

        $this->assertContains(DuplicateOrderRecoverableGatewayInterface::class, $interfaces);
        $this->assertContains(GatewayCancellableInterface::class, $interfaces);
    }
}
