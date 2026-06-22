<?php

declare(strict_types=1);

namespace Zr\PaidAccess\Tests\Unit\PublicUi;

use PHPUnit\Framework\TestCase;
use Zr\PaidAccess\Gateway\Dto\InitPaymentResult;
use Zr\PaidAccess\PublicUi\PaymentWidgetPresenter;

final class PaymentWidgetPresenterTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetPaymentButtonCssFlag();
    }

    public function testBuildQrHtmlContainsImageAndHint(): void
    {
        $html = PaymentWidgetPresenter::buildQrHtml('https://qr.example/payload');

        $this->assertStringContainsString('zr-paidaccess-qr', $html);
        $this->assertStringContainsString(PaymentWidgetPresenter::QR_IMAGE_SERVICE_URL, $html);
        $this->assertStringContainsString('QR СБП', $html);
    }

    public function testBuildQrImageUrlEncodesPayload(): void
    {
        $url = PaymentWidgetPresenter::buildQrImageUrl('https://qr.example/payload?x=1');

        $this->assertStringStartsWith(PaymentWidgetPresenter::QR_IMAGE_SERVICE_URL, $url);
        $this->assertStringContainsString('size=' . PaymentWidgetPresenter::QR_IMAGE_SIZE, $url);
        $this->assertStringContainsString(rawurlencode('https://qr.example/payload?x=1'), $url);
    }

    public function testRenderFromResultUsesQrPayload(): void
    {
        $result = new InitPaymentResult(true, 'gp-1', '', 'payload-data');
        $html = PaymentWidgetPresenter::renderFromResult($result);

        $this->assertStringContainsString('zr-paidaccess-qr', $html);
    }

    public function testRenderFromResultUsesPaymentUrlWithAutoRedirect(): void
    {
        $result = new InitPaymentResult(true, 'gp-1', 'https://pay.example/form');
        $result->autoRedirectPaymentButton = true;
        $html = PaymentWidgetPresenter::renderFromResult($result);

        $this->assertStringContainsString('zr-paidaccess-pay-btn--tbank', $html);
        $this->assertStringContainsString('window.location.replace', $html);
    }

    public function testBuildRedirectHtmlContainsPayButton(): void
    {
        $html = PaymentWidgetPresenter::buildRedirectHtml('https://pay.example/form', false);

        $this->assertStringContainsString('zr-paidaccess-pay-btn--tbank', $html);
        $this->assertStringContainsString('tbank-pay-logo.svg', $html);
        $this->assertStringContainsString('payment-button.css', $html);
        $this->assertStringContainsString('https://pay.example/form', $html);
        $this->assertStringContainsString('Перейти к оплате', $html);
        $this->assertStringNotContainsString('window.location.replace', $html);
    }

    public function testBuildRedirectHtmlWithAutoRedirect(): void
    {
        $html = PaymentWidgetPresenter::buildRedirectHtml('https://pay.example/form', true);

        $this->assertStringContainsString('window.location.replace', $html);
        $this->assertStringContainsString('перенаправление', $html);
    }

    public function testBuildRedirectHtmlEmitsStylesheetOnlyOnce(): void
    {
        $this->resetPaymentButtonCssFlag();

        $first = PaymentWidgetPresenter::buildRedirectHtml('https://pay.example/one', false);
        $second = PaymentWidgetPresenter::buildRedirectHtml('https://pay.example/two', false);

        $this->assertSame(1, substr_count($first . $second, 'payment-button.css'));
        $this->assertStringContainsString('zr-paidaccess-pay-action', $first);
        $this->assertStringNotContainsString('payment-button.css', $second);
    }

    private function resetPaymentButtonCssFlag(): void
    {
        $property = new \ReflectionProperty(PaymentWidgetPresenter::class, 'paymentButtonCssEmitted');
        $property->setAccessible(true);
        $property->setValue(null, false);
    }
}
