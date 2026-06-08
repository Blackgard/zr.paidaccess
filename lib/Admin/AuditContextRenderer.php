<?php

namespace Zr\PaidAccess\Admin;

use Zr\PaidAccess\Subscription\BillingPolicy;

/**
 * Отображение контекста записей аудита в админке.
 */
class AuditContextRenderer
{
    /**
     * @return array<string, string>
     */
    protected static function actionTitles(): array
    {
        return [
            'create' => 'Создание',
            'update' => 'Изменение',
            'delete' => 'Удаление',
        ];
    }

    public static function actionTitle(string $action): string
    {
        $action = strtolower(trim($action));

        return self::actionTitles()[$action] ?? $action;
    }

    public static function render(
        string $entityType,
        string $action,
        ?string $oldValue,
        ?string $newValue,
        string $languageId
    ): string {
        $type = strtolower(trim($entityType));

        if ($type === 'payment') {
            return self::renderPayment($action, $oldValue, $newValue, $languageId);
        }

        return self::renderRawJson($oldValue, $newValue);
    }

    protected static function renderPayment(
        string $action,
        ?string $oldValue,
        ?string $newValue,
        string $languageId
    ): string {
        $old = self::decode($oldValue);
        $new = self::decode($newValue);
        $action = strtolower(trim($action));

        if ($action === 'delete' && $old !== null) {
            return self::renderPaymentCard($old, 'Удалённый платёж', $languageId);
        }

        if ($action === 'create' && $new !== null) {
            return self::renderPaymentCard($new, null, $languageId);
        }

        if ($action === 'update') {
            return self::renderPaymentUpdate($old, $new, $languageId);
        }

        return self::renderRawJson($oldValue, $newValue);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function renderPaymentCard(array $data, ?string $title, string $languageId): string
    {
        $html = '<div class="zr-paidaccess-audit-context">';
        if ($title !== null && $title !== '') {
            $html .= '<div class="zr-paidaccess-audit-context__title">' . htmlspecialcharsbx($title) . '</div>';
        }

        $html .= '<dl class="zr-paidaccess-audit-context__facts">';
        foreach (self::paymentFacts($data, $languageId) as $label => $value) {
            $html .= '<dt>' . htmlspecialcharsbx($label) . '</dt><dd>' . $value . '</dd>';
        }
        $html .= '</dl>';
        $html .= self::renderDetails(self::prettyJson($data));
        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<string, mixed>|null $old
     * @param array<string, mixed>|null $new
     */
    protected static function renderPaymentUpdate(?array $old, ?array $new, string $languageId): string
    {
        if ($old === null && $new === null) {
            return self::emptyCell();
        }

        $keys = array_unique(array_merge(array_keys($old ?? []), array_keys($new ?? [])));
        $changes = [];

        foreach ($keys as $key) {
            if (!self::isPaymentFieldVisible((string)$key)) {
                continue;
            }

            $oldRaw = ($old ?? [])[$key] ?? null;
            $newRaw = ($new ?? [])[$key] ?? null;
            $oldFormatted = self::formatFieldValue((string)$key, $oldRaw, $languageId);
            $newFormatted = self::formatFieldValue((string)$key, $newRaw, $languageId);

            if ($oldFormatted !== $newFormatted) {
                $changes[(string)$key] = [
                    'old' => $oldFormatted,
                    'new' => $newFormatted,
                ];
            }
        }

        if ($changes === []) {
            return '<span class="zr-paidaccess-muted">Без изменений</span>';
        }

        $html = '<div class="zr-paidaccess-audit-context"><ul class="zr-paidaccess-audit-context__changes">';
        foreach ($changes as $key => $pair) {
            $label = self::paymentFieldLabel((string)$key);
            $html .= '<li><strong>' . htmlspecialcharsbx($label) . ':</strong> '
                . $pair['old'] . ' → ' . $pair['new'] . '</li>';
        }
        $html .= '</ul>';
        $html .= self::renderDetails(
            "Было:\n" . self::prettyJson($old ?? [])
            . "\n\nСтало:\n" . self::prettyJson($new ?? [])
        );
        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    protected static function paymentFacts(array $data, string $languageId): array
    {
        $facts = [];

        foreach (self::paymentFieldOrder() as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $value = self::formatFieldValue($field, $data[$field], $languageId);
            if ($value === self::emptyCell()) {
                continue;
            }

            $facts[self::paymentFieldLabel($field)] = $value;
        }

        return $facts;
    }

    /**
     * @return string[]
     */
    protected static function paymentFieldOrder(): array
    {
        return [
            'ID',
            'USER_ID',
            'STATUS',
            'AMOUNT',
            'CURRENCY',
            'BILLING_PERIOD',
            'ORDER_ID',
            'GATEWAY_CODE',
            'GATEWAY_PAYMENT_ID',
            'DATE_CREATE',
            'DATE_PAID',
            'DESCRIPTION',
        ];
    }

    protected static function isPaymentFieldVisible(string $field): bool
    {
        return in_array($field, self::paymentFieldOrder(), true);
    }

    protected static function paymentFieldLabel(string $field): string
    {
        $labels = [
            'ID' => 'ID',
            'USER_ID' => 'Пользователь',
            'STATUS' => 'Статус',
            'AMOUNT' => 'Сумма',
            'CURRENCY' => 'Валюта',
            'BILLING_PERIOD' => 'Период',
            'ORDER_ID' => 'Заказ',
            'GATEWAY_CODE' => 'Шлюз',
            'GATEWAY_PAYMENT_ID' => 'ID в шлюзе',
            'DATE_CREATE' => 'Создан',
            'DATE_PAID' => 'Оплачен',
            'DESCRIPTION' => 'Описание',
        ];

        return $labels[$field] ?? $field;
    }

    /**
     * @param mixed $value
     */
    protected static function formatFieldValue(string $field, $value, string $languageId): string
    {
        if ($value === null || $value === '') {
            return self::emptyCell();
        }

        if ($field === 'USER_ID') {
            return AdminEntityLinkRenderer::user((int)$value, $languageId);
        }

        if ($field === 'STATUS') {
            return htmlspecialcharsbx(PaymentAdminService::getStatusTitle((string)$value));
        }

        if ($field === 'AMOUNT') {
            return htmlspecialcharsbx(number_format((float)$value, 2, '.', ' ') . ' ₽');
        }

        if ($field === 'CURRENCY') {
            return htmlspecialcharsbx((string)$value);
        }

        if ($field === 'BILLING_PERIOD') {
            return htmlspecialcharsbx(BillingPolicy::formatPeriodLabel((string)$value));
        }

        if (is_scalar($value)) {
            return htmlspecialcharsbx((string)$value);
        }

        return htmlspecialcharsbx(json_encode($value, JSON_UNESCAPED_UNICODE) ?: '');
    }

    protected static function renderRawJson(?string $oldValue, ?string $newValue): string
    {
        $parts = [];

        if ($oldValue !== null && $oldValue !== '') {
            $parts[] = "Было:\n" . self::prettyJson(self::decode($oldValue) ?? $oldValue);
        }

        if ($newValue !== null && $newValue !== '') {
            $parts[] = "Стало:\n" . self::prettyJson(self::decode($newValue) ?? $newValue);
        }

        if ($parts === []) {
            return self::emptyCell();
        }

        return self::renderDetails(implode("\n\n", $parts));
    }

    /**
     * @param array<string, mixed>|string $data
     */
    protected static function renderDetails($data): string
    {
        $text = is_array($data) ? self::prettyJson($data) : (string)$data;
        if ($text === '') {
            return '';
        }

        return '<details class="zr-paidaccess-audit-context__details">'
            . '<summary>Полные данные</summary>'
            . '<pre class="zr-paidaccess-audit-context__json">' . htmlspecialcharsbx($text) . '</pre>'
            . '</details>';
    }

    /**
     * @return array<string, mixed>|null
     */
    protected static function decode(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed>|string $data
     */
    protected static function prettyJson($data): string
    {
        if (is_string($data)) {
            return $data;
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return $json === false ? '' : $json;
    }

    protected static function emptyCell(): string
    {
        return '<span class="zr-paidaccess-muted">—</span>';
    }
}
