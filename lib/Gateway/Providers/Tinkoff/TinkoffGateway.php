<?php

namespace Zr\PaidAccess\Gateway\Providers\Tinkoff;

use Zr\PaidAccess\Gateway\Contract\PaymentGatewayInterface;
use Zr\PaidAccess\Gateway\Dto\InitPaymentRequest;
use Zr\PaidAccess\Gateway\Dto\InitPaymentResult;
use Zr\PaidAccess\Gateway\Dto\WebhookHandleResult;
use Zr\PaidAccess\Gateway\GatewayRepository;
use Zr\PaidAccess\Payment\PaymentRepository;
use Zr\PaidAccess\Tools\Logger;
use Zr\PaidAccess\PaidAccessCore;

class TinkoffGateway implements PaymentGatewayInterface
{
    public const CODE = 'tinkoff';

    private const PAYMENT_BUTTON_CSS = '/local/modules/zr.paidaccess/install/assets/payment-button.css';
    private const TBANK_PAY_LOGO = '/local/modules/zr.paidaccess/install/assets/tbank-pay-logo.svg';

    /** @var bool */
    private static $paymentButtonCssEmitted = false;

    /** @var TinkoffApiClient */
    private $client;

    /** @var TinkoffConfig */
    private $config;

    /** @var int */
    private $gatewayId;

    /**
     * @param array<string, mixed> $gatewayRow
     */
    public function __construct(array $gatewayRow)
    {
        $this->gatewayId = (int)$gatewayRow['ID'];
        $options = GatewayRepository::getOptionsForGateway($gatewayRow);
        $this->config = new TinkoffConfig($options);

        $terminal = $this->config->getTerminalKey();
        $secret = $this->config->getSecretKey();

        if ($terminal === '' || $secret === '') {
            throw new \RuntimeException('Не заданы terminal_key или secret_key в настройках шлюза');
        }

        $this->client = new TinkoffApiClient(
            $terminal,
            $secret,
            $this->config->isTestMode()
        );
    }

    public function getGatewayId()
    {
        return $this->gatewayId;
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function initPayment(InitPaymentRequest $request): InitPaymentResult
    {
        $this->bindClientLogContext($request);
        $body = TinkoffReceiptBuilder::buildInitBody($request, $this->config);
        $response = $this->client->init($body);
        $raw = json_encode($response, JSON_UNESCAPED_UNICODE);

        if (empty($response['Success'])) {
            return InitPaymentResult::fail(
                (string)($response['Message'] ?? 'Init failed') . ' ' . ($response['Details'] ?? ''),
                $raw
            );
        }

        $paymentId = (string)($response['PaymentId'] ?? '');
        $paymentUrl = TinkoffPaymentUrlResolver::extractFromArray($response);

        return new InitPaymentResult(true, $paymentId, $paymentUrl, '', '', '', $raw);
    }

    public function fetchPaymentForm(string $gatewayPaymentId, InitPaymentRequest $request): InitPaymentResult
    {
        if ($gatewayPaymentId === '') {
            return InitPaymentResult::fail('Пустой PaymentId');
        }

        $widgetMode = PaidAccessCore::getPaymentWidgetMode();
        Logger::info('fetchPaymentForm start', [
            'gatewayId' => $this->gatewayId,
            'gatewayPaymentId' => $gatewayPaymentId,
            'orderId' => $request->orderId,
            'widgetMode' => $widgetMode,
            'redirectEnabled' => $this->config->isRedirectEnabled(),
            'testMode' => $this->config->isTestMode(),
        ], self::CODE);

        if (PaidAccessCore::isPaymentWidgetButtonMode()) {
            return $this->fetchPaymentButtonForm($gatewayPaymentId, $request);
        }

        return $this->fetchQrForm($gatewayPaymentId, $request);
    }

    protected function fetchPaymentButtonForm(string $gatewayPaymentId, InitPaymentRequest $request): InitPaymentResult
    {
        $this->bindClientLogContext($request);
        $knownUrl = trim((string)($request->paymentUrl ?? ''));
        if ($knownUrl !== '') {
            Logger::info('fetchPaymentButtonForm: PaymentURL from storage', [
                'gatewayPaymentId' => $gatewayPaymentId,
                'paymentUrl' => $knownUrl,
            ], self::CODE);

            return $this->buildButtonResult($gatewayPaymentId, $knownUrl);
        }

        $state = $this->client->getState(['PaymentId' => $gatewayPaymentId]);
        $stateRaw = json_encode($state, JSON_UNESCAPED_UNICODE);
        $paymentUrl = TinkoffPaymentUrlResolver::extractFromArray(is_array($state) ? $state : []);

        if ($paymentUrl !== '') {
            Logger::info('fetchPaymentButtonForm: PaymentURL from GetState', [
                'gatewayPaymentId' => $gatewayPaymentId,
                'paymentUrl' => $paymentUrl,
            ], self::CODE);

            return $this->buildButtonResult($gatewayPaymentId, $paymentUrl, $stateRaw);
        }

        if (!empty($state['Success'])) {
            $status = (string)($state['Status'] ?? '');

            if (TinkoffPaymentUrlResolver::isAwaitingPayment($status)) {
                return InitPaymentResult::fail(
                    'Платёж в банке создан, но ссылка на оплату недоступна. '
                    . 'Отмените платёж в админке и откройте страницу оплаты снова.',
                    $stateRaw
                );
            }

            return InitPaymentResult::fail(
                'Платёж недоступен для оплаты (статус: ' . $status . ').',
                $stateRaw
            );
        }

        $init = $this->initPayment($request);
        if ($init->success && $init->paymentUrl !== '') {
            Logger::info('fetchPaymentButtonForm: PaymentURL from Init', [
                'gatewayPaymentId' => $init->gatewayPaymentId,
                'paymentUrl' => $init->paymentUrl,
            ], self::CODE);

            return $this->buildButtonResult($init->gatewayPaymentId, $init->paymentUrl, $init->rawResponse);
        }

        return InitPaymentResult::fail(
            $init->errorMessage !== '' ? $init->errorMessage : 'Не удалось получить ссылку на оплату',
            $init->rawResponse !== '' ? $init->rawResponse : $stateRaw
        );
    }

    private function buildButtonResult(string $gatewayPaymentId, string $paymentUrl, string $rawResponse = ''): InitPaymentResult
    {
        return new InitPaymentResult(
            true,
            $gatewayPaymentId,
            $paymentUrl,
            '',
            self::buildRedirectHtml($paymentUrl, $this->config->isRedirectEnabled()),
            '',
            $rawResponse !== '' ? $rawResponse : json_encode(['PaymentURL' => $paymentUrl], JSON_UNESCAPED_UNICODE)
        );
    }

    protected function fetchQrForm(string $gatewayPaymentId, InitPaymentRequest $request): InitPaymentResult
    {
        $this->bindClientLogContext($request);
        Logger::info('fetchQrForm start', [
            'gatewayPaymentId' => $gatewayPaymentId,
            'orderId' => $request->orderId,
        ], self::CODE);
        $qrResponse = $this->client->getQr([
            'PaymentId' => $gatewayPaymentId,
            'DataType' => 'PAYLOAD',
        ]);

        $qrRaw = json_encode($qrResponse, JSON_UNESCAPED_UNICODE);

        if (!empty($qrResponse['Success']) && !empty($qrResponse['Data'])) {
            $payload = (string)$qrResponse['Data'];

            return new InitPaymentResult(
                true,
                $gatewayPaymentId,
                '',
                $payload,
                self::buildQrHtml($payload),
                '',
                $qrRaw
            );
        }

        $state = $this->client->getState(['PaymentId' => $gatewayPaymentId]);
        if (!empty($state['Success'])) {
            $status = (string)($state['Status'] ?? '');

            if (TinkoffPaymentUrlResolver::isAwaitingPayment($status)) {
                return InitPaymentResult::fail(
                    (string)($qrResponse['Message'] ?? 'GetQr failed'),
                    $qrRaw
                );
            }

            return InitPaymentResult::fail(
                'Платёж недоступен для оплаты (статус: ' . $status . ').',
                $qrRaw
            );
        }

        $init = $this->initPayment($request);
        if ($init->success && $init->paymentUrl !== '') {
            return new InitPaymentResult(
                true,
                $init->gatewayPaymentId,
                $init->paymentUrl,
                '',
                self::buildRedirectHtml($init->paymentUrl, false),
                '',
                $init->rawResponse
            );
        }

        return InitPaymentResult::fail(
            (string)($qrResponse['Message'] ?? 'GetQr failed'),
            $qrRaw
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelPayment(string $gatewayPaymentId, int $modulePaymentId, ?float $amountRub = null): array
    {
        $this->client->setLogContext($modulePaymentId, $this->gatewayId);

        $params = ['PaymentId' => $gatewayPaymentId];
        if ($amountRub !== null && $amountRub > 0) {
            $params['Amount'] = (int)round($amountRub * 100);
        }

        return $this->client->cancel($params);
    }

    public function handleWebhook(array $payload): WebhookHandleResult
    {
        $payloadTerminal = trim((string)($payload['TerminalKey'] ?? ''));
        $configuredTerminal = $this->config->getTerminalKey();
        if ($payloadTerminal !== '' && $configuredTerminal !== '' && !hash_equals($configuredTerminal, $payloadTerminal)) {
            return new WebhookHandleResult(
                false,
                false,
                '',
                '',
                '',
                'TerminalKey в webhook (' . $payloadTerminal . ') не совпадает с настройкой шлюза (' . $configuredTerminal . ')'
            );
        }

        if (!$this->client->verifyNotificationToken($payload)) {
            $fields = implode(', ', $this->client->getNotificationTokenFieldNames($payload));

            return new WebhookHandleResult(
                false,
                false,
                '',
                '',
                '',
                'Invalid Token (поля подписи: ' . $fields . ')'
            );
        }

        $orderId = (string)($payload['OrderId'] ?? '');
        $paymentId = (string)($payload['PaymentId'] ?? '');
        $status = (string)($payload['Status'] ?? '');
        $paid = TinkoffStatusMapper::isPaidStatus($status);
        $internalStatus = TinkoffStatusMapper::toInternal($status);

        return new WebhookHandleResult(true, $paid, $orderId, $paymentId, $status, '', $internalStatus);
    }

    public static function buildQrHtml($payload)
    {
        $src = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' . rawurlencode($payload);

        return '<div class="zr-paidaccess-qr">'
            . '<img src="' . htmlspecialcharsbx($src) . '" alt="QR СБП" width="280" height="280">'
            . '<p><small>Отсканируйте QR в приложении банка (СБП)</small></p>'
            . '</div>';
    }

    public static function buildRedirectHtml($url, $autoRedirect = false)
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

    protected function bindClientLogContext(InitPaymentRequest $request): void
    {
        $paymentId = 0;
        if ($request->orderId !== '') {
            $row = PaymentRepository::getByOrderId($request->orderId);
            if (is_array($row)) {
                $paymentId = (int)($row['ID'] ?? 0);
            }
        }

        $this->client->setLogContext($paymentId, $this->gatewayId);
    }
}
