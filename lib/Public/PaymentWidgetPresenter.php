<?php

namespace Zr\PaidAccess\PublicUi;

use Zr\PaidAccess\Gateway\Dto\InitPaymentResult;

/**
 * HTML-виджеты оплаты (QR СБП, кнопка T-Bank).
 * Gateway возвращает данные в InitPaymentResult; разметку собирает presentation layer.
 */
class PaymentWidgetPresenter
{
    /**
     * Внешний HTTP-сервис рендеринга QR-изображения (см. docs/BOUNDARIES.md «QR image rendering»).
     * Запрос выполняет браузер пользователя через &lt;img src&gt;, не PHP-сервер модуля.
     */
    public const QR_IMAGE_SERVICE_URL = 'https://api.qrserver.com/v1/create-qr-code/';

    public const QR_IMAGE_SIZE = '280x280';

    private const PAYMENT_BUTTON_CSS = '/local/modules/zr.paidaccess/install/assets/payment-button.css';
    private const TBANK_PAY_LOGO = '/local/modules/zr.paidaccess/install/assets/tbank-pay-logo.svg';

    /** @var bool */
    private static $paymentButtonCssEmitted = false;

    public static function renderFromResult(InitPaymentResult $result): string
    {
        if (!$result->success) {
            return '';
        }

        if ($result->qrPayload !== '') {
            return self::buildQrHtml($result->qrPayload);
        }

        if ($result->paymentUrl !== '') {
            return self::buildRedirectHtml($result->paymentUrl, $result->autoRedirectPaymentButton);
        }

        return '';
    }

    public static function buildQrImageUrl(string $payload): string
    {
        return self::QR_IMAGE_SERVICE_URL
            . '?size=' . self::QR_IMAGE_SIZE
            . '&data=' . rawurlencode($payload);
    }

    public static function buildQrHtml(string $payload): string
    {
        $src = self::buildQrImageUrl($payload);

        return '<div class="zr-paidaccess-qr">'
            . '<img src="' . htmlspecialcharsbx($src) . '" alt="QR СБП" width="280" height="280">'
            . '<p><small>Отсканируйте QR в приложении банка (СБП)</small></p>'
            . '</div>';
    }

    public static function buildRedirectHtml(string $url, bool $autoRedirect = false): string
    {
        $safeUrl = htmlspecialcharsbx($url);
        $logoUrl = htmlspecialcharsbx(self::TBANK_PAY_LOGO);
        $html = self::emitPaymentButtonStyles()
            . '<div class="zr-paidaccess-pay-action">'
            . '<a class="zr-paidaccess-pay-btn zr-paidaccess-pay-btn--tbank" href="' . $safeUrl . '">'
            . '<img class="zr-paidaccess-pay-btn__logo" src="' . $logoUrl . '" alt="" width="28" height="28" loading="lazy">'
            . '<span class="zr-paidaccess-pay-btn__text">Перейти к оплате</span>'
            . '</a>'
            . '</div>';

        if ($autoRedirect) {
            $html .= '<script>window.location.replace(' . json_encode($url, JSON_UNESCAPED_UNICODE) . ');</script>';
            $html .= '<p><small>Идёт перенаправление на платёжную форму банка…</small></p>';
        }

        return $html;
    }

    private static function emitPaymentButtonStyles(): string
    {
        if (self::$paymentButtonCssEmitted) {
            return '';
        }

        self::$paymentButtonCssEmitted = true;

        return '<link rel="stylesheet" href="' . htmlspecialcharsbx(self::PAYMENT_BUTTON_CSS) . '">';
    }
}
