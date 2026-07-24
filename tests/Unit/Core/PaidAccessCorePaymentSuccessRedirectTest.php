<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Core;

use Bitrix\Main\Config\Option;
use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\PaidAccessCore;

final class PaidAccessCorePaymentSuccessRedirectTest extends TestCase
{
    protected function setUp(): void
    {
        Option::reset();
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_SERVER['HTTPS'] = 'on';
        unset($_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['SERVER_PORT']);
    }

    public function testEmptyOptionFallsBackToHome(): void
    {
        $this->assertSame('/', PaidAccessCore::getPaymentSuccessRedirectUrl('s1'));
    }

    public function testConfiguredPathIsReturned(): void
    {
        Option::set(
            PaidAccessCore::MODULE_ID,
            PaidAccessCore::OPTION_PAYMENT_SUCCESS_REDIRECT_URL . '_s1',
            '/cabinet/',
            's1'
        );

        $this->assertSame('/cabinet/', PaidAccessCore::getPaymentSuccessRedirectUrl('s1'));
        $this->assertSame(
            'https://example.test/cabinet/',
            PaidAccessCore::getPaymentSuccessRedirectAbsoluteUrl('s1')
        );
    }

    public function testAbsoluteUrlIsKept(): void
    {
        Option::set(
            PaidAccessCore::MODULE_ID,
            PaidAccessCore::OPTION_PAYMENT_SUCCESS_REDIRECT_URL . '_s1',
            'https://pay.example/thanks',
            's1'
        );

        $this->assertSame(
            'https://pay.example/thanks',
            PaidAccessCore::getPaymentSuccessRedirectAbsoluteUrl('s1')
        );
    }

    public function testToAbsoluteUrlBuildsFromHost(): void
    {
        $this->assertSame('https://example.test/', PaidAccessCore::toAbsoluteUrl('/'));
        $this->assertSame('https://example.test/foo', PaidAccessCore::toAbsoluteUrl('foo'));
    }
}
