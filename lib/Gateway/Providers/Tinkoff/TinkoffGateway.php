<?php

namespace Zr\PaidAccess\Gateway\Providers\Tinkoff;

use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\Gateway\Contract\DuplicateOrderRecoverableGatewayInterface;
use Zr\PaidAccess\Gateway\Contract\GatewayCancellableInterface;
use Zr\PaidAccess\Gateway\Contract\GatewayWebhookDebugVerifierInterface;
use Zr\PaidAccess\Gateway\Contract\PaymentGatewayInterface;
use Zr\PaidAccess\Gateway\Contract\StaleSessionRecoverableGatewayInterface;
use Zr\PaidAccess\Gateway\Dto\InitPaymentRequest;
use Zr\PaidAccess\Gateway\Dto\InitPaymentResult;
use Zr\PaidAccess\Gateway\Dto\WebhookHandleResult;
use Zr\PaidAccess\Gateway\GatewayRepository;
use Zr\PaidAccess\Payment\PaymentRepository;
use Zr\PaidAccess\Tools\Logger;

class TinkoffGateway implements PaymentGatewayInterface, DuplicateOrderRecoverableGatewayInterface, GatewayCancellableInterface, GatewayWebhookDebugVerifierInterface, StaleSessionRecoverableGatewayInterface
{
    public const CODE = 'tinkoff';

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

    public function getWebhookDebugInfo(array $payload): array
    {
        return [
            'configuredTerminalKey' => $this->config->getTerminalKey(),
            'payloadTerminalKey' => trim((string)($payload['TerminalKey'] ?? '')),
            'tokenFields' => $this->client->getNotificationTokenFieldNames($payload),
            'expectedToken' => $this->client->buildNotificationToken($payload),
            'receivedToken' => (string)($payload['Token'] ?? ''),
        ];
    }

    public function recoverDuplicateOrder(InitPaymentRequest $request): InitPaymentResult
    {
        $this->bindClientLogContext($request);

        return TinkoffDuplicateOrderRecovery::recover($this->client, $request);
    }

    public function isDuplicateOrderError(InitPaymentResult $result): bool
    {
        return TinkoffInitError::isDuplicateOrderIdError($result);
    }

    public function isStalePaymentSessionFailure(InitPaymentResult $result): bool
    {
        if ($result->success) {
            return false;
        }

        $message = (string)$result->errorMessage;
        if (stripos($message, 'Платёж недоступен для оплаты') !== false) {
            return true;
        }

        $raw = $this->decodeGatewayPayload($result->rawResponse);
        if ($raw !== null && TinkoffPaymentUrlResolver::isStaleGatewayResponse($raw)) {
            return true;
        }

        return stripos($message, 'HTTP error') !== false;
    }

    public function isIgnorableCancelFailure(array $gatewayResponse, string $internalPaymentStatus): bool
    {
        if (PaymentStatus::grantsAccess($internalPaymentStatus)) {
            return false;
        }

        if (!in_array($internalPaymentStatus, [PaymentStatus::PENDING, PaymentStatus::FAILED], true)) {
            return false;
        }

        return TinkoffPaymentUrlResolver::isStaleGatewayResponse($gatewayResponse)
            || TinkoffPaymentUrlResolver::isHttpErrorResponse($gatewayResponse);
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

        $result = new InitPaymentResult(true, $paymentId, $paymentUrl, '', '', '', $raw);
        $result->httpCode = (int)($response['HttpStatus'] ?? 200);

        return $result;
    }

    public function fetchPaymentForm(string $gatewayPaymentId, InitPaymentRequest $request): InitPaymentResult
    {
        if ($gatewayPaymentId === '') {
            return InitPaymentResult::fail('Пустой PaymentId');
        }

        $widgetMode = $this->resolveWidgetMode($request);
        Logger::info('fetchPaymentForm start', [
            'gatewayId' => $this->gatewayId,
            'gatewayPaymentId' => $gatewayPaymentId,
            'orderId' => $request->orderId,
            'widgetMode' => $widgetMode,
            'redirectEnabled' => $this->config->isRedirectEnabled(),
            'testMode' => $this->config->isTestMode(),
        ], self::CODE);

        if ($request->isPaymentButtonWidgetMode()) {
            return $this->fetchPaymentButtonForm($gatewayPaymentId, $request);
        }

        return $this->fetchQrForm($gatewayPaymentId, $request);
    }

    private function resolveWidgetMode(InitPaymentRequest $request): string
    {
        return $request->paymentWidgetMode !== '' ? $request->paymentWidgetMode : 'qr_sbp';
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
        $result = new InitPaymentResult(
            true,
            $gatewayPaymentId,
            $paymentUrl,
            '',
            '',
            '',
            $rawResponse !== '' ? $rawResponse : json_encode(['PaymentURL' => $paymentUrl], JSON_UNESCAPED_UNICODE)
        );
        $result->autoRedirectPaymentButton = $this->config->isRedirectEnabled();

        return $result;
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
                '',
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
                '',
                '',
                $init->rawResponse
            );
        }

        if (!$init->success && $init->rawResponse !== '') {
            return InitPaymentResult::fail(
                $init->errorMessage !== '' ? $init->errorMessage : 'Init failed',
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

    /**
     * @return array<string, mixed>|null
     */
    private function decodeGatewayPayload(string $rawResponse): ?array
    {
        if ($rawResponse === '') {
            return null;
        }

        $decoded = json_decode($rawResponse, true);

        return is_array($decoded) ? $decoded : null;
    }
}
