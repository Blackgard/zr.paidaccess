<?php

namespace Zr\PaidAccess\Gateway\Providers\Tinkoff;

use Bitrix\Main\Web\HttpClient;
use Zr\PaidAccess\Gateway\Dto\InitPaymentRequest;
use Zr\PaidAccess\Gateway\GatewayRepository;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Tools\Logger;
use Zr\PaidAccess\Utility\NetworkPathDiagnosticService;

/**
 * Диагностика Init T-Bank: IP, DNS, traceroute, пробный Init с таймаутом 40 с.
 */
final class TinkoffInitDiagnosticService
{
    public const DEFAULT_TIMEOUT_SECONDS = 40;

    private const API_PROD = 'https://securepay.tinkoff.ru/v2/';
    private const API_TEST = 'https://rest-api-test.tinkoff.ru/v2/';

    /**
     * @param array{
     *     runInit?: bool,
     *     runTraceroute?: bool,
     *     email?: string,
     *     timeoutSeconds?: int
     * } $options
     * @return array<string, mixed>
     */
    public static function run(int $gatewayId, ?string $siteId = null, array $options = []): array
    {
        $runInit = !array_key_exists('runInit', $options) || !empty($options['runInit']);
        $runTraceroute = !array_key_exists('runTraceroute', $options) || !empty($options['runTraceroute']);
        $timeoutSeconds = max(5, (int)($options['timeoutSeconds'] ?? self::DEFAULT_TIMEOUT_SECONDS));
        $siteId = PaidAccessCore::normalizeSiteId($siteId);

        $gatewayRow = GatewayRepository::getById($gatewayId);
        if (!$gatewayRow) {
            throw new \InvalidArgumentException('Шлюз #' . $gatewayId . ' не найден');
        }

        if ((string)($gatewayRow['PROVIDER'] ?? '') !== TinkoffGateway::CODE) {
            throw new \InvalidArgumentException('Диагностика Init доступна только для шлюза T-Bank (tinkoff)');
        }

        $optionsMap = GatewayRepository::getOptionsForGateway($gatewayRow);
        $config = new TinkoffConfig($optionsMap);
        $terminal = $config->getTerminalKey();
        $secret = $config->getSecretKey();

        if ($terminal === '' || $secret === '') {
            throw new \RuntimeException('В настройках шлюза не заданы terminal_key или secret_key');
        }

        $apiBase = $config->isTestMode() ? self::API_TEST : self::API_PROD;
        $initUrl = $apiBase . 'Init';
        $targetHost = NetworkPathDiagnosticService::sanitizeHost(parse_url($apiBase, PHP_URL_HOST) ?: 'securepay.tinkoff.ru');

        $report = [
            'generatedAt' => date('c'),
            'gatewayId' => $gatewayId,
            'gatewayName' => (string)($gatewayRow['NAME'] ?? ''),
            'gatewayCode' => TinkoffGateway::CODE,
            'siteId' => $siteId,
            'testMode' => $config->isTestMode(),
            'apiBaseUrl' => $apiBase,
            'initUrl' => $initUrl,
            'targetHost' => $targetHost,
            'timeoutSeconds' => $timeoutSeconds,
            'steps' => [],
            'summary' => [],
            'supportPackage' => '',
        ];

        self::addStep($report, 'server', 'Окружение сервера', NetworkPathDiagnosticService::collectServerContext());
        self::addStep($report, 'dns', 'DNS ' . $targetHost, NetworkPathDiagnosticService::resolveHost($targetHost, $timeoutSeconds));
        self::addStep(
            $report,
            'outbound_ip',
            'Исходящий публичный IP',
            NetworkPathDiagnosticService::detectOutboundPublicIp($timeoutSeconds)
        );
        self::addStep(
            $report,
            'tcp_443',
            'TCP :443 → ' . $targetHost,
            NetworkPathDiagnosticService::probeTcpConnect($targetHost, 443, $timeoutSeconds)
        );
        self::addStep(
            $report,
            'https_probe',
            'HTTPS GET ' . rtrim($apiBase, '/'),
            NetworkPathDiagnosticService::probeHttpsGet(rtrim($apiBase, '/'), $timeoutSeconds)
        );

        if ($runTraceroute) {
            self::addStep(
                $report,
                'traceroute',
                'Traceroute → ' . $targetHost,
                NetworkPathDiagnosticService::runTraceroute($targetHost)
            );
        }

        if ($runInit) {
            $email = trim((string)($options['email'] ?? ''));
            if ($email === '') {
                $email = 'diagnostic@example.invalid';
            }

            $orderId = self::buildDiagnosticOrderId($gatewayId);
            $amount = PaidAccessCore::getSubscriptionChargeTotal($siteId);
            if ($amount <= 0) {
                $amount = 1.0;
            }

            $initRequest = new InitPaymentRequest(
                $orderId,
                $amount,
                'RUB',
                'Диагностика Init T-Bank (zr.paidaccess)',
                0,
                $email,
                null
            );

            $initBody = TinkoffReceiptBuilder::buildInitBody($initRequest, $config);
            $client = new TinkoffApiClient($terminal, $secret, $config->isTestMode());
            $initBody['TerminalKey'] = $terminal;
            $initBody['Token'] = $client->buildToken($initBody);

            $initStep = self::executeInitProbe(
                $initUrl,
                $initBody,
                $timeoutSeconds,
                TinkoffApiClient::getOutboundRequestHeaders()
            );
            $initStep['orderId'] = $orderId;
            $initStep['amountRub'] = $amount;
            $initStep['requestBody'] = Logger::sanitizeParams($initBody);

            self::addStep($report, 'init', 'POST Init', $initStep);
        }

        $report['summary'] = self::buildSummary($report);
        $report['supportPackage'] = self::buildSupportPackage($report);

        return $report;
    }

    /**
     * @param array<string, mixed> $report
     * @param array<string, mixed> $data
     */
    private static function addStep(array &$report, string $code, string $title, array $data): void
    {
        $report['steps'][] = [
            'code' => $code,
            'title' => $title,
            'capturedAt' => date('c'),
            'data' => $data,
        ];
    }

    /**
     * @param array<string, mixed> $initBody
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private static function executeInitProbe(
        string $url,
        array $initBody,
        int $timeoutSeconds,
        array $headers
    ): array {
        $startedAt = microtime(true);
        $requestAt = date('c');
        $requestJson = json_encode($initBody, JSON_UNESCAPED_UNICODE);
        if ($requestJson === false) {
            $requestJson = '{}';
        }

        $client = new HttpClient([
            'socketTimeout' => $timeoutSeconds,
            'streamTimeout' => $timeoutSeconds,
        ]);

        foreach ($headers as $headerName => $headerValue) {
            $client->setHeader($headerName, $headerValue, true);
        }

        $responseBody = $client->post($url, $requestJson);
        $durationMs = round((microtime(true) - $startedAt) * 1000, 2);
        $httpStatus = (int)$client->getStatus();
        $httpError = NetworkPathDiagnosticService::formatHttpClientError($client->getError());
        $rawBody = $responseBody === false ? '' : (string)$responseBody;

        if ($responseBody === false) {
            $parsed = [
                'Success' => false,
                'Message' => 'HTTP error',
                'Details' => $httpError,
                'HttpStatus' => $httpStatus > 0 ? $httpStatus : 0,
            ];
        } else {
            $parsed = TinkoffApiClient::parseResponse(
                $httpStatus > 0 ? $httpStatus : 200,
                $rawBody,
                strpos($url, 'rest-api-test.tinkoff.ru') !== false
            );
        }

        return [
            'requestAt' => $requestAt,
            'url' => $url,
            'httpStatus' => $httpStatus,
            'httpError' => $httpError,
            'durationMs' => $durationMs,
            'timeoutSeconds' => $timeoutSeconds,
            'requestHeaders' => $headers,
            'responseRaw' => self::truncate($rawBody, 8000),
            'response' => Logger::sanitizeParams($parsed),
            'success' => !empty($parsed['Success']),
        ];
    }

    private static function buildDiagnosticOrderId(int $gatewayId): string
    {
        $suffix = date('YmdHis');
        $orderId = 'DIAG-G' . max(1, $gatewayId) . '-' . $suffix;

        return mb_substr($orderId, 0, 36);
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    private static function buildSummary(array $report): array
    {
        $summary = [
            'outboundPublicIp' => '',
            'outboundIpSource' => '',
            'dnsResolved' => false,
            'dnsAddresses' => [],
            'tcp443Success' => false,
            'initRequestAt' => '',
            'initHttpStatus' => 0,
            'initSuccess' => false,
            'initDurationMs' => 0,
            'tracerouteAvailable' => false,
        ];

        foreach ($report['steps'] as $step) {
            if (!is_array($step['data'] ?? null)) {
                continue;
            }

            $data = $step['data'];
            switch ($step['code'] ?? '') {
                case 'outbound_ip':
                    $summary['outboundPublicIp'] = (string)($data['ip'] ?? '');
                    $summary['outboundIpSource'] = (string)($data['source'] ?? '');
                    break;
                case 'dns':
                    $summary['dnsResolved'] = !empty($data['resolved']);
                    $summary['dnsAddresses'] = is_array($data['addresses'] ?? null) ? $data['addresses'] : [];
                    break;
                case 'tcp_443':
                    $summary['tcp443Success'] = !empty($data['success']);
                    break;
                case 'traceroute':
                    $summary['tracerouteAvailable'] = !empty($data['available']);
                    break;
                case 'init':
                    $summary['initRequestAt'] = (string)($data['requestAt'] ?? '');
                    $summary['initHttpStatus'] = (int)($data['httpStatus'] ?? 0);
                    $summary['initSuccess'] = !empty($data['success']);
                    $summary['initDurationMs'] = (float)($data['durationMs'] ?? 0);
                    break;
            }
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function buildSupportPackage(array $report): string
    {
        $lines = [];
        $lines[] = 'Диагностика Init T-Bank (zr.paidaccess)';
        $lines[] = 'Сформировано: ' . (string)($report['generatedAt'] ?? date('c'));
        $lines[] = 'Шлюз: #' . (int)($report['gatewayId'] ?? 0) . ' ' . (string)($report['gatewayName'] ?? '');
        $lines[] = 'Режим: ' . (!empty($report['testMode']) ? 'тестовый' : 'боевой');
        $lines[] = 'URL Init: ' . (string)($report['initUrl'] ?? '');
        $lines[] = 'Таймаут HTTP: ' . (int)($report['timeoutSeconds'] ?? self::DEFAULT_TIMEOUT_SECONDS) . ' с';
        $lines[] = '';

        $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
        $lines[] = 'Исходящий публичный IP: ' . ((string)($summary['outboundPublicIp'] ?? '') ?: 'не определён');
        if (!empty($summary['outboundIpSource'])) {
            $lines[] = 'Источник IP: ' . (string)$summary['outboundIpSource'];
        }

        if (!empty($summary['initRequestAt'])) {
            $lines[] = 'Время запроса Init (UTC+offset сервера): ' . (string)$summary['initRequestAt'];
        }

        $lines[] = 'DNS ' . (string)($report['targetHost'] ?? '') . ': '
            . (!empty($summary['dnsResolved']) ? implode(', ', (array)($summary['dnsAddresses'] ?? [])) : 'не разрешён');
        $lines[] = 'TCP :443: ' . (!empty($summary['tcp443Success']) ? 'OK' : 'FAIL');

        if (!empty($summary['initRequestAt'])) {
            $lines[] = 'Init HTTP: ' . (int)($summary['initHttpStatus'] ?? 0)
                . ', Success: ' . (!empty($summary['initSuccess']) ? 'true' : 'false')
                . ', ' . (float)($summary['initDurationMs'] ?? 0) . ' ms';
        }

        $lines[] = '';
        $lines[] = '--- Traceroute / tracert ---';

        $tracerouteOutput = self::findStepOutput($report, 'traceroute');
        $lines[] = $tracerouteOutput !== '' ? $tracerouteOutput : 'Traceroute недоступен или не выполнялся';

        $lines[] = '';
        $lines[] = '--- Init: ответ ---';
        $initResponse = self::findStepField($report, 'init', 'responseRaw');
        $lines[] = $initResponse !== '' ? $initResponse : 'Init не выполнялся или пустой ответ';

        $initError = self::findStepField($report, 'init', 'httpError');
        if ($initError !== '') {
            $lines[] = '';
            $lines[] = 'HTTP/cURL ошибка Init: ' . $initError;
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $report
     */
    private static function findStepOutput(array $report, string $code): string
    {
        foreach ($report['steps'] as $step) {
            if (($step['code'] ?? '') !== $code || !is_array($step['data'] ?? null)) {
                continue;
            }

            return trim((string)($step['data']['output'] ?? ''));
        }

        return '';
    }

    /**
     * @param array<string, mixed> $report
     */
    private static function findStepField(array $report, string $code, string $field): string
    {
        foreach ($report['steps'] as $step) {
            if (($step['code'] ?? '') !== $code || !is_array($step['data'] ?? null)) {
                continue;
            }

            $value = $step['data'][$field] ?? '';
            if (is_scalar($value)) {
                return trim((string)$value);
            }
        }

        return '';
    }

    private static function truncate(string $value, int $maxLength): string
    {
        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return mb_substr($value, 0, $maxLength) . '…';
    }
}
