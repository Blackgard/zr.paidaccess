<?php

namespace Zr\PaidAccess\Admin;

use Zr\PaidAccess\Tools\Logger;

class GatewayTransactionContextRenderer
{
    public static function render(?string $requestData, ?string $responseData): string
    {
        $request = self::formatPayload($requestData);
        $response = self::formatPayload($responseData);

        if ($request === '' && $response === '') {
            return '<span class="zr-paidaccess-muted">—</span>';
        }

        $html = '<div class="zr-paidaccess-audit-context">';

        $metaHtml = self::renderMetaSummary($requestData);
        if ($metaHtml !== '') {
            $html .= $metaHtml;
        }

        if ($request !== '') {
            $html .= '<details class="zr-paidaccess-audit-context__details">'
                . '<summary>Запрос</summary>'
                . '<pre class="zr-paidaccess-audit-context__json">' . htmlspecialcharsbx($request) . '</pre>'
                . '</details>';
        }

        if ($response !== '') {
            $html .= '<details class="zr-paidaccess-audit-context__details" open>'
                . '<summary>Ответ</summary>'
                . '<pre class="zr-paidaccess-audit-context__json">' . htmlspecialcharsbx($response) . '</pre>'
                . '</details>';
        }

        $html .= '</div>';

        return $html;
    }

    protected static function renderMetaSummary(?string $raw): string
    {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return '';
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return '';
        }

        if (!empty($decoded['url'])) {
            $apiMethod = !empty($decoded['apiMethod']) ? ' (' . (string)$decoded['apiMethod'] . ')' : '';

            return '<div class="zr-paidaccess-audit-context__title">'
                . 'Исходящий запрос' . htmlspecialcharsbx($apiMethod) . ': <code>'
                . htmlspecialcharsbx((string)$decoded['url']) . '</code>'
                . '</div>';
        }

        if (!isset($decoded['meta']) || !is_array($decoded['meta'])) {
            return '';
        }

        $meta = $decoded['meta'];
        $parts = [];
        if (!empty($meta['source'])) {
            $parts[] = 'Источник: ' . htmlspecialcharsbx((string)$meta['source']);
        }
        if (!empty($meta['requestUrl'])) {
            $parts[] = 'URL: <code>' . htmlspecialcharsbx((string)$meta['requestUrl']) . '</code>';
        } elseif (!empty($meta['requestUri'])) {
            $parts[] = 'URI: <code>' . htmlspecialcharsbx((string)$meta['requestUri']) . '</code>';
        }
        if (!empty($meta['requestMethod'])) {
            $parts[] = 'Метод: ' . htmlspecialcharsbx((string)$meta['requestMethod']);
        }
        if (!empty($meta['remoteAddr'])) {
            $parts[] = 'IP: ' . htmlspecialcharsbx((string)$meta['remoteAddr']);
        }

        if ($parts === []) {
            return '';
        }

        return '<div class="zr-paidaccess-audit-context__title">' . implode(' · ', $parts) . '</div>';
    }

    protected static function formatPayload(?string $raw): string
    {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return '';
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return self::truncate($raw);
        }

        if (isset($decoded['body'])) {
            $decoded = is_array($decoded['body']) ? $decoded['body'] : ['raw' => $decoded['body']];
        }

        $sanitized = Logger::sanitizeParams($decoded);
        $encoded = json_encode($sanitized, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return self::truncate($encoded !== false ? $encoded : $raw);
    }

    protected static function truncate(string $value, int $maxLength = 12000): string
    {
        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        return mb_substr($value, 0, $maxLength) . "\n…";
    }
}
