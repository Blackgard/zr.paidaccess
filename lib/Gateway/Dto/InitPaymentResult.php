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

    /** @var int HTTP-код ответа T-Bank (200, 403, 500 и т.д.) */
    public $httpCode = 0;

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
        $result = new self(false, '', '', '', '', $message, $raw);
        $result->httpCode = self::extractHttpCodeFromRaw($raw);

        return $result;
    }

    public function getHttpCode(): int
    {
        if ($this->httpCode > 0) {
            return $this->httpCode;
        }

        return self::extractHttpCodeFromRaw($this->rawResponse);
    }

    public static function extractHttpCodeFromRaw(string $rawResponse): int
    {
        if ($rawResponse === '') {
            return 0;
        }

        $decoded = json_decode($rawResponse, true);
        if (!is_array($decoded)) {
            return 0;
        }

        return (int)($decoded['HttpStatus'] ?? $decoded['httpCode'] ?? 0);
    }
}
