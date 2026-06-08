<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\Gateway;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffGateway;

final class TinkoffGatewayWidgetTest extends TestCase
{
    public function testBuildQrHtmlContainsImageAndHint(): void
    {
        $html = TinkoffGateway::buildQrHtml('https://qr.example/payload');

        $this->assertStringContainsString('zr-paidaccess-qr', $html);
        $this->assertStringContainsString('create-qr-code', $html);
        $this->assertStringContainsString('QR СБП', $html);
    }

    public function testBuildRedirectHtmlContainsPayButton(): void
    {
        $html = TinkoffGateway::buildRedirectHtml('https://pay.example/form', false);

        $this->assertStringContainsString('zr-paidaccess-pay-btn--tbank', $html);
        $this->assertStringContainsString('tbank-pay-logo.svg', $html);
        $this->assertStringContainsString('payment-button.css', $html);
        $this->assertStringContainsString('https://pay.example/form', $html);
        $this->assertStringContainsString('Перейти к оплате', $html);
        $this->assertStringNotContainsString('window.location.replace', $html);
    }

    public function testBuildRedirectHtmlWithAutoRedirect(): void
    {
        $html = TinkoffGateway::buildRedirectHtml('https://pay.example/form', true);

        $this->assertStringContainsString('window.location.replace', $html);
        $this->assertStringContainsString('перенаправление', $html);
    }

    public function testBuildRedirectHtmlEmitsStylesheetOnlyOnce(): void
    {
        $this->resetPaymentButtonCssFlag();

        $first = TinkoffGateway::buildRedirectHtml('https://pay.example/one', false);
        $second = TinkoffGateway::buildRedirectHtml('https://pay.example/two', false);

        $this->assertSame(1, substr_count($first . $second, 'payment-button.css'));
        $this->assertStringContainsString('zr-paidaccess-pay-action', $first);
        $this->assertStringNotContainsString('payment-button.css', $second);
    }

    private function resetPaymentButtonCssFlag(): void
    {
        $property = new \ReflectionProperty(TinkoffGateway::class, 'paymentButtonCssEmitted');
        $property->setAccessible(true);
        $property->setValue(null, false);
    }
}
