<?php

namespace Zr\PaidAccess\Tools;

/**
 * Метаданные входящего HTTP-запроса для журналов модуля.
 */
final class RequestContext
{
    /**
     * @return array<string, string>
     */
    public static function capture(string $source): array
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
        $fullUrl = $host !== '' ? $scheme . '://' . $host . $uri : $uri;

        return [
            'source' => $source,
            'requestMethod' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
            'requestUri' => $uri,
            'requestUrl' => $fullUrl,
            'remoteAddr' => self::resolveClientIp(),
            'userAgent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            'capturedAt' => date('c'),
        ];
    }

    public static function resolveClientIp(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            $value = trim((string)($_SERVER[$key] ?? ''));
            if ($value === '') {
                continue;
            }

            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $parts = explode(',', $value);
                $value = trim((string)($parts[0] ?? ''));
            }

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed>|string|null $body
     */
    public static function wrapPayload(string $source, $body): string
    {
        $encoded = json_encode([
            'meta' => self::capture($source),
            'body' => $body,
        ], JSON_UNESCAPED_UNICODE);

        return $encoded !== false ? $encoded : '';
    }
}
