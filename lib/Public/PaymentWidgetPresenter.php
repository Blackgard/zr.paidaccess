<?php

namespace Zr\PaidAccess\PublicUi;

use Zr\PaidAccess\Gateway\Dto\InitPaymentResult;
use Zr\PaidAccess\PaidAccessCore;

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

    public const STATUS_POLL_INTERVAL_MS = 3000;

    public const STATUS_POLL_MAX_MS = 900000;

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

    /**
     * JS-опрос статуса платежа и редирект после успешной оплаты.
     */
    public static function buildStatusPollerHtml(
        int $paymentId,
        string $statusUrl,
        string $redirectUrl,
        int $intervalMs = self::STATUS_POLL_INTERVAL_MS,
        int $maxMs = self::STATUS_POLL_MAX_MS
    ): string {
        if ($paymentId <= 0 || $statusUrl === '' || $redirectUrl === '') {
            return '';
        }

        $config = json_encode([
            'paymentId' => $paymentId,
            'statusUrl' => $statusUrl,
            'redirectUrl' => $redirectUrl,
            'intervalMs' => max(1000, $intervalMs),
            'maxMs' => max(5000, $maxMs),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($config === false) {
            return '';
        }

        return '<div class="zr-paidaccess-payment-status" data-zr-paidaccess-poll="1" aria-live="polite">'
            . '<p class="zr-paidaccess-payment-status__hint"><small>После оплаты страница обновится автоматически</small></p>'
            . '</div>'
            . '<script>(function(){'
            . 'var c=' . $config . ';'
            . 'if(!c||!c.paymentId||!c.statusUrl||!c.redirectUrl){return;}'
            . 'var started=Date.now(),timer=null,busy=false;'
            . 'function go(){window.location.replace(c.redirectUrl);}'
            . 'function tick(){'
            . 'if(busy){return;}'
            . 'if(Date.now()-started>c.maxMs){if(timer){clearInterval(timer);}return;}'
            . 'busy=true;'
            . 'var url=c.statusUrl+(c.statusUrl.indexOf("?")>=0?"&":"?")+"payment_id="+encodeURIComponent(c.paymentId);'
            . 'fetch(url,{credentials:"same-origin",cache:"no-store"})'
            . '.then(function(r){return r.json();})'
            . '.then(function(data){if(data&&data.ok&&data.paid){if(timer){clearInterval(timer);}'
            . 'go();}busy=false;})'
            . '.catch(function(){busy=false;});'
            . '}'
            . 'tick();'
            . 'timer=setInterval(tick,c.intervalMs);'
            . '})();</script>';
    }

    public static function buildAlreadyPaidRedirectHtml(string $redirectUrl): string
    {
        $redirectUrl = trim($redirectUrl);
        if ($redirectUrl === '') {
            $redirectUrl = '/';
        }

        return '<p>Оплата уже получена. Перенаправляем…</p>'
            . '<script>window.location.replace(' . json_encode($redirectUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ');</script>';
    }

    /**
     * Абсолютный URL endpoint опроса статуса.
     */
    public static function buildStatusPollUrl(?string $siteId = null): string
    {
        $path = PaymentStatusPollService::getStatusEndpointUrl();
        if ($siteId !== null && $siteId !== '') {
            $path .= (strpos($path, '?') !== false ? '&' : '?') . 'site_id=' . rawurlencode($siteId);
        }

        return PaidAccessCore::toAbsoluteUrl($path, $siteId);
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
