<?php

namespace Zr\PaidAccess\Admin;

/**
 * Ссылки на сущности в админке модуля.
 */
class AdminEntityLinkRenderer
{
    public static function payment(int $paymentId, string $languageId): string
    {
        if ($paymentId <= 0) {
            return self::emptyCell();
        }

        $url = 'zr_paidaccess_payment_edit.php?ID=' . $paymentId . '&lang=' . rawurlencode($languageId);

        return self::renderLink($url, '#' . $paymentId, 'Открыть платёж');
    }

    public static function gateway(int $gatewayId, string $languageId): string
    {
        if ($gatewayId <= 0) {
            return self::emptyCell();
        }

        $url = 'zr_paidaccess_gateway_edit.php?ID=' . $gatewayId . '&lang=' . rawurlencode($languageId);

        return self::renderLink($url, '#' . $gatewayId, 'Открыть шлюз');
    }

    public static function user(int $userId, string $languageId, ?string $label = null): string
    {
        if ($userId <= 0) {
            return self::emptyCell();
        }

        $url = '/bitrix/admin/user_edit.php?ID=' . $userId . '&lang=' . rawurlencode($languageId);
        $text = $label !== null && $label !== '' ? $label : (string)$userId;

        return self::renderLink($url, $text, 'Открыть пользователя', true);
    }

    public static function auditEntity(string $entityType, int $entityId, string $languageId): string
    {
        if ($entityId <= 0) {
            return self::emptyCell();
        }

        $type = strtolower(trim($entityType));

        if ($type === 'payment') {
            return self::payment($entityId, $languageId);
        }

        if ($type === 'gateway') {
            $url = 'zr_paidaccess_gateway_edit.php?ID=' . $entityId . '&lang=' . rawurlencode($languageId);

            return self::renderLink($url, '#' . $entityId, 'Открыть шлюз');
        }

        return htmlspecialcharsbx($entityType) . ' #' . $entityId;
    }

    public static function auditEntityTypeLabel(string $entityType): string
    {
        $type = strtolower(trim($entityType));
        $labels = [
            'payment' => 'Платёж',
            'gateway' => 'Шлюз',
            'subscription' => 'Подписка',
        ];

        return $labels[$type] ?? $entityType;
    }

    protected static function renderLink(string $url, string $text, string $title, bool $newTab = false): string
    {
        $attrs = 'href="' . htmlspecialcharsbx($url) . '" title="' . htmlspecialcharsbx($title) . '"';
        if ($newTab) {
            $attrs .= ' target="_blank"';
        }

        return '<a ' . $attrs . '>' . htmlspecialcharsbx($text) . '</a>';
    }

    protected static function emptyCell(): string
    {
        return '<span class="zr-paidaccess-muted">—</span>';
    }
}
