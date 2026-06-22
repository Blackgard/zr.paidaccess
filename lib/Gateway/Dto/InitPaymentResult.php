<?php

namespace Zr\PaidAccess\Gateway\Dto;

/**
 * Ответ шлюза для отображения пользователю.
 * Gateway возвращает данные (`qrPayload`, `paymentUrl`); HTML собирает `PublicUi\PaymentWidgetPresenter`.
 *
 * Порядок аргументов конструктора:
 * success, gatewayPaymentId, paymentUrl, qrPayload, html, errorMessage, rawResponse
 */
class InitPaymentResult
{
    /** @var bool */
    public $success;

    /** @var string */
    public $gatewayPaymentId;

    /** @var string */
    public $paymentUrl;

    /** @var string */
    public $qrPayload;

    /** @var string */
    public $html;

    /** @var string */
    public $errorMessage;

    /** @var string */
    public $rawResponse;

    /** @var bool Автопереход на paymentUrl (кнопка оплаты) */
    public $autoRedirectPaymentButton = false;

    public function __construct(
        $success,
        $gatewayPaymentId = '',
        $paymentUrl = '',
        $qrPayload = '',
        $html = '',
        $errorMessage = '',
        $rawResponse = ''
    ) {
        $this->success = (bool)$success;
        $this->gatewayPaymentId = (string)$gatewayPaymentId;
        $this->paymentUrl = (string)$paymentUrl;
        $this->qrPayload = (string)$qrPayload;
        $this->html = (string)$html;
        $this->errorMessage = (string)$errorMessage;
        $this->rawResponse = (string)$rawResponse;
    }

    public static function fail($message, $raw = '')
    {
        return new self(false, '', '', '', '', $message, $raw);
    }
}
