<?php

namespace Zr\PaidAccess\Utility;

use Bitrix\Main\Web\HttpClient;

/**
 * Сетевая диагностика для обращений в техподдержку платёжных шлюзов.
 */
final class NetworkPathDiagnosticService
{
    private const OUTBOUND_IP_ENDPOINTS = [
        'https://api.ipify.org?format=json',
        'https://ifconfig.me/ip',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function collectServerContext(): array
    {
        $hostname = (string)gethostname();
        $localIps = [];

        if ($hostname !== '') {
            $resolved = gethostbynamel($hostname);
            if (is_array($resolved)) {
                $localIps = array_values(array_unique(array_filter($resolved, 'is_string')));
            }
        }

        if ($localIps === [] && !empty($_SERVER['SERVER_ADDR'])) {
            $localIps[] = (string)$_SERVER['SERVER_ADDR'];
        }

        return [
            'hostname' => $hostname,
            'phpVersion' => PHP_VERSION,
            'phpSapi' => PHP_SAPI,
            'os' => PHP_OS_FAMILY,
            'documentRoot' => (string)($_SERVER['DOCUMENT_ROOT'] ?? ''),
            'serverSoftware' => (string)($_SERVER['SERVER_SOFTWARE'] ?? ''),
            'localIps' => $localIps,
            'shellExecAllowed' => self::isShellExecAllowed(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolveHost(string $host, int $timeoutSeconds = 10): array
    {
        $host = self::sanitizeHost($host);
        if ($host === '') {
            return [
                'host' => '',
                'resolved' => false,
                'addresses' => [],
                'error' => 'Некорректное имя хоста',
            ];
        }

        $startedAt = microtime(true);
        $addresses = [];

        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_A + DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (!empty($record['ip'])) {
                        $addresses[] = (string)$record['ip'];
                    }
                    if (!empty($record['ipv6'])) {
                        $addresses[] = (string)$record['ipv6'];
                    }
                }
            }
        }

        if ($addresses === []) {
            $ipv4 = gethostbyname($host);
            if ($ipv4 !== $host && filter_var($ipv4, FILTER_VALIDATE_IP)) {
                $addresses[] = $ipv4;
            }
        }

        $addresses = array_values(array_unique($addresses));

        return [
            'host' => $host,
            'resolved' => $addresses !== [],
            'addresses' => $addresses,
            'durationMs' => round((microtime(true) - $startedAt) * 1000, 2),
            'error' => $addresses === [] ? 'DNS не вернул адреса' : '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detectOutboundPublicIp(int $timeoutSeconds = 10): array
    {
        $startedAt = microtime(true);
        $errors = [];

        foreach (self::OUTBOUND_IP_ENDPOINTS as $endpoint) {
            $client = new HttpClient([
                'socketTimeout' => $timeoutSeconds,
                'streamTimeout' => $timeoutSeconds,
            ]);
            $client->setHeader('Accept', 'application/json, text/plain', true);

            $body = $client->get($endpoint);
            $httpStatus = (int)$client->getStatus();
            $httpError = self::formatHttpClientError($client->getError());

            if ($body === false || $httpStatus >= 400) {
                $errors[] = trim($endpoint . ': HTTP ' . $httpStatus . ($httpError !== '' ? ' (' . $httpError . ')' : ''));
                continue;
            }

            $ip = self::extractIpFromResponse((string)$body);
            if ($ip !== '') {
                return [
                    'detected' => true,
                    'ip' => $ip,
                    'source' => $endpoint,
                    'durationMs' => round((microtime(true) - $startedAt) * 1000, 2),
                    'errors' => $errors,
                ];
            }

            $errors[] = $endpoint . ': не удалось распознать IP в ответе';
        }

        return [
            'detected' => false,
            'ip' => '',
            'source' => '',
            'durationMs' => round((microtime(true) - $startedAt) * 1000, 2),
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function probeTcpConnect(string $host, int $port, int $timeoutSeconds = 10): array
    {
        $host = self::sanitizeHost($host);
        $port = max(1, min(65535, $port));
        $startedAt = microtime(true);

        if ($host === '') {
            return [
                'success' => false,
                'host' => '',
                'port' => $port,
                'durationMs' => 0,
                'error' => 'Некорректное имя хоста',
            ];
        }

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeoutSeconds);
        $durationMs = round((microtime(true) - $startedAt) * 1000, 2);

        if ($socket === false) {
            return [
                'success' => false,
                'host' => $host,
                'port' => $port,
                'durationMs' => $durationMs,
                'error' => trim($errstr !== '' ? $errstr : ('errno ' . $errno)),
            ];
        }

        fclose($socket);

        return [
            'success' => true,
            'host' => $host,
            'port' => $port,
            'durationMs' => $durationMs,
            'error' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function probeHttpsGet(string $url, int $timeoutSeconds = 10): array
    {
        $startedAt = microtime(true);
        $client = new HttpClient([
            'socketTimeout' => $timeoutSeconds,
            'streamTimeout' => $timeoutSeconds,
        ]);
        $client->setHeader('Accept', '*/*', true);
        $client->setHeader('User-Agent', 'zr.paidaccess-network-diagnostic/1.0', true);

        $body = $client->get($url);
        $httpStatus = (int)$client->getStatus();
        $httpError = self::formatHttpClientError($client->getError());

        return [
            'url' => $url,
            'success' => $body !== false && $httpStatus > 0 && $httpStatus < 500,
            'httpStatus' => $httpStatus,
            'httpError' => $httpError,
            'durationMs' => round((microtime(true) - $startedAt) * 1000, 2),
            'responseBytes' => $body === false ? 0 : strlen((string)$body),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function runTraceroute(string $host, int $maxHops = 30): array
    {
        $host = self::sanitizeHost($host);
        if ($host === '') {
            return [
                'available' => false,
                'command' => '',
                'output' => '',
                'error' => 'Некорректное имя хоста',
            ];
        }

        if (!self::isShellExecAllowed()) {
            return [
                'available' => false,
                'command' => '',
                'output' => '',
                'error' => 'На сервере отключены shell_exec/exec — traceroute недоступен из PHP',
            ];
        }

        $maxHops = max(1, min(64, $maxHops));
        $isWindows = DIRECTORY_SEPARATOR === '\\';
        $command = $isWindows
            ? 'tracert -d -h ' . $maxHops . ' ' . escapeshellarg($host)
            : 'traceroute -n -m ' . $maxHops . ' -w 2 ' . escapeshellarg($host) . ' 2>&1';

        $output = self::runShellCommand($command);

        return [
            'available' => true,
            'command' => $command,
            'output' => $output,
            'error' => $output === '' ? 'Команда не вернула вывод' : '',
        ];
    }

    public static function sanitizeHost(string $host): string
    {
        $host = trim(strtolower($host));
        $host = preg_replace('#^https?://#', '', $host) ?? $host;
        $host = explode('/', $host)[0];
        $host = explode(':', $host)[0];

        if ($host === ''
            || strpos($host, '..') !== false
            || $host[0] === '.'
            || substr($host, -1) === '.'
            || !preg_match('/^[a-z0-9.-]+$/', $host)
        ) {
            return '';
        }

        return $host;
    }

    public static function isShellExecAllowed(): bool
    {
        if (!function_exists('shell_exec')) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));

        return !in_array('shell_exec', $disabled, true)
            && !in_array('exec', $disabled, true);
    }

    /**
     * @param mixed $httpError
     */
    public static function formatHttpClientError($httpError): string
    {
        if ($httpError === null || $httpError === '') {
            return '';
        }

        if (is_array($httpError)) {
            $encoded = json_encode($httpError, JSON_UNESCAPED_UNICODE);

            return $encoded !== false ? $encoded : 'Array';
        }

        return (string)$httpError;
    }

    protected static function extractIpFromResponse(string $body): string
    {
        $body = trim($body);
        if ($body === '') {
            return '';
        }

        $decoded = json_decode($body, true);
        if (is_array($decoded) && !empty($decoded['ip']) && is_string($decoded['ip'])) {
            return trim($decoded['ip']);
        }

        if (filter_var($body, FILTER_VALIDATE_IP)) {
            return $body;
        }

        return '';
    }

    protected static function runShellCommand(string $command): string
    {
        if (function_exists('shell_exec')) {
            $output = shell_exec($command);

            return is_string($output) ? trim($output) : '';
        }

        $lines = [];
        if (function_exists('exec')) {
            exec($command, $lines);

            return trim(implode("\n", $lines));
        }

        return '';
    }
}
