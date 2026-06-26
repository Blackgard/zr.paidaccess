<?php

namespace Zr\PaidAccess\Gateway\Providers\Tinkoff;

use Bitrix\Main\Web\HttpClient;
use Zr\PaidAccess\Payment\GatewayTransactionRepository;
use Zr\PaidAccess\Tools\Logger;

class TinkoffApiClient
{
    public const PROVIDER_CODE = 'tinkoff';

    private const API_PROD = 'https://securepay.tinkoff.ru/v2/';
    private const API_TEST = 'https://rest-api-test.tinkoff.ru/v2/';

    /** @var string */
    private $terminalKey;

    /** @var string */
    private $secretKey;

    /** @var bool */
    private $testMode;

    /** @var int */
    private $logPaymentId = 0;

    /** @var int */
    private $logGatewayId = 0;

    public function __construct($terminalKey, $secretKey, $testMode = false)
    {
        $this->terminalKey = (string)$terminalKey;
        $this->secretKey = (string)$secretKey;
        $this->testMode = (bool)$testMode;
    }

    public function setLogContext(int $paymentId, int $gatewayId): void
    {
        $this->logPaymentId = max(0, $paymentId);
        $this->logGatewayId = max(0, $gatewayId);
    }

    public function init(array $params)
    {
        return $this->request('Init', $params);
    }

    public function getQr(array $params)
    {
        return $this->request('GetQr', $params);
    }

    public function getState(array $params)
    {
        return $this->request('GetState', $params);
    }

    public function checkOrder(array $params)
    {
        return $this->request('CheckOrder', $params);
    }

    public function cancel(array $params)
    {
        return $this->request('Cancel', $params);
    }

    /**
     * Проверка Token входящего webhook.
     *
     * @see https://developer.tbank.ru/eacq/intro/developer/notification#проверить-токен-уведомлений
     */
    public function verifyNotificationToken(array $payload)
    {
        if (empty($payload['Token'])) {
            return false;
        }

        $received = (string)$payload['Token'];
        $expected = $this->buildNotificationToken($payload);

        return hash_equals($expected, $received);
    }

    /**
     * Поля, участвующие в подписи webhook (для диагностики).
     *
     * @return list<string>
     */
    public function getNotificationTokenFieldNames(array $payload): array
    {
        $data = $this->normalizeNotificationPayload($payload);
        ksort($data);

        return array_keys($data);
    }

    public function buildNotificationToken(array $payload): string
    {
        return $this->buildToken($this->normalizeNotificationPayload($payload));
    }

    /**
     * Поля для подписи webhook: корневые скаляры, без Token, без вложенных Data/Receipt, без null.
     *
     * @see https://developer.tbank.ru/eacq/intro/developer/notification#проверить-токен-уведомлений
     *
     * @return array<string, scalar>
     */
    private function normalizeNotificationPayload(array $payload): array
    {
        $data = $payload;
        unset($data['Token']);

        foreach ($data as $key => $value) {
            if ($value === null || is_array($value) || is_object($value)) {
                unset($data[$key]);
            }
        }

        if (array_key_exists('Success', $data)) {
            $data['Success'] = filter_var($data['Success'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        }

        return $data;
    }

    private function request($method, array $params)
    {
        $params['TerminalKey'] = $this->terminalKey;
        $params['Token'] = $this->buildToken($params);

        $url = ($this->testMode ? self::API_TEST : self::API_PROD) . $method;
        $startedAt = microtime(true);

        $client = new HttpClient(['socketTimeout' => 15, 'streamTimeout' => 15]);
        $client->setHeader('Content-Type', 'application/json', true);
        $client->setHeader('Accept', 'application/json', true);

        $response = $client->post($url, json_encode($params, JSON_UNESCAPED_UNICODE));
        $durationMs = (microtime(true) - $startedAt) * 1000;
        $httpStatus = (int)$client->getStatus();
        $httpError = (string)$client->getError();
        $responseBody = $response === false ? '' : (string)$response;

        if ($response === false) {
            $httpCode = $httpStatus > 0 ? $httpStatus : 0;
            $result = [
                'Success' => false,
                'Message' => 'HTTP error',
                'Details' => $httpError,
                'HttpStatus' => $httpCode,
            ];

            Logger::logHttpExchange(
                self::PROVIDER_CODE,
                (string)$method,
                $url,
                $params,
                $result,
                $httpCode,
                $durationMs,
                $httpError,
                ['testMode' => $this->testMode]
            );

            $this->persistGatewayTransaction(
                (string)$method,
                $url,
                $params,
                $result,
                $httpCode,
                $httpError !== '' ? $httpError : null
            );

            return $result;
        }

        $httpCode = $httpStatus > 0 ? $httpStatus : 200;
        $result = self::parseResponse($httpCode, $responseBody, $this->testMode);

        Logger::logHttpExchange(
            self::PROVIDER_CODE,
            (string)$method,
            $url,
            $params,
            $result,
            $httpCode,
            $durationMs,
            $httpError !== '' ? $httpError : null,
            [
                'testMode' => $this->testMode,
                'apiBase' => $this->testMode ? 'test' : 'prod',
            ]
        );

        $this->persistGatewayTransaction(
            (string)$method,
            $url,
            $params,
            $result,
            $httpCode,
            $httpError !== '' ? $httpError : null
        );

        return $result;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $result
     */
    private function persistGatewayTransaction(
        string $method,
        string $url,
        array $params,
        array $result,
        int $httpStatus,
        ?string $httpError
    ): void {
        if ($this->logGatewayId <= 0 && $this->logPaymentId <= 0) {
            return;
        }

        try {
            GatewayTransactionRepository::logHttpExchange(
                $this->logPaymentId,
                $this->logGatewayId,
                self::PROVIDER_CODE,
                $method,
                $url,
                $params,
                $result,
                $httpStatus,
                $httpError
            );
        } catch (\Throwable $e) {
            // не прерываем оплату из-за ошибки журнала
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function parseResponse(int $httpStatus, string $body, bool $testMode = false): array
    {
        if ($httpStatus >= 400) {
            return self::buildHttpErrorResult($httpStatus, $body, $testMode);
        }

        $decoded = json_decode($body, true);

        if (is_array($decoded)) {
            if (!isset($decoded['HttpStatus'])) {
                $decoded['HttpStatus'] = $httpStatus > 0 ? $httpStatus : 200;
            }

            return $decoded;
        }

        return [
            'Success' => false,
            'Message' => 'Invalid JSON',
            'Details' => self::truncateBody($body),
            'HttpStatus' => $httpStatus > 0 ? $httpStatus : 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildHttpErrorResult(int $httpStatus, string $body, bool $testMode): array
    {
        if ($httpStatus === 403 && $testMode) {
            return [
                'Success' => false,
                'Message' => 'T-Bank отклонил запрос (HTTP 403): IP не в whitelist тестовой среды',
                'Details' => 'Запросите добавление IP сервера в whitelist через openapi@tbank.ru '
                    . '(ИНН, название организации, rest-api-test.tinkoff.ru) '
                    . 'или отключите «Тестовый режим» в настройках шлюза и укажите боевой терминал.',
                'HttpStatus' => 403,
            ];
        }

        if ($httpStatus === 403) {
            return [
                'Success' => false,
                'Message' => 'T-Bank отклонил запрос (HTTP 403)',
                'Details' => 'Проверьте TerminalKey и SecretKey, ограничения IP в личном кабинете T-Bank.',
                'HttpStatus' => 403,
            ];
        }

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $decoded['HttpStatus'] = $httpStatus;

            return $decoded;
        }

        return [
            'Success' => false,
            'Message' => 'HTTP ' . $httpStatus,
            'Details' => self::truncateBody($body),
            'HttpStatus' => $httpStatus,
        ];
    }

    private static function truncateBody(string $body, int $maxLength = 200): string
    {
        $snippet = preg_replace('/\s+/', ' ', trim(strip_tags($body)));
        if ($snippet === null || $snippet === '') {
            return 'Пустой ответ API';
        }

        if (mb_strlen($snippet) <= $maxLength) {
            return $snippet;
        }

        return mb_substr($snippet, 0, $maxLength) . '…';
    }

    /**
     * SHA-256 подпись исходящих запросов и webhook: корневые поля + Password, ksort, concat, hash.
     *
     * @see https://developer.tbank.ru/eacq/intro/developer/token
     */
    public function buildToken(array $params)
    {
        $params['Password'] = html_entity_decode($this->secretKey, ENT_QUOTES, 'UTF-8');
        ksort($params);

        $concat = '';
        foreach ($params as $key => $value) {
            if ($key === 'Token' || $value === null || is_array($value) || is_object($value)) {
                continue;
            }
            $concat .= (string)$value;
        }

        return hash('sha256', $concat);
    }
}
