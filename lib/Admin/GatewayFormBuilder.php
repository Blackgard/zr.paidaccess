<?php

namespace Zr\PaidAccess\Admin;

use Zr\PaidAccess\Gateway\Provider\GatewayProviderRegistry;

/**
 * Рендер полей провайдера в админ-форме.
 */
class GatewayFormBuilder
{
    /**
     * @param array<string, mixed> $values
     */
    public static function renderProviderFields($provider, array $values, $gatewayId = 0)
    {
        $fields = GatewayProviderRegistry::getFieldsForProvider($provider);
        if (empty($fields)) {
            return '<tr><td colspan="2">Выберите провайдера и сохраните, чтобы увидеть поля.</td></tr>';
        }

        $html = '';
        foreach ($fields as $field) {
            $code = (string)($field['code'] ?? '');
            if ($code === '') {
                continue;
            }

            $type = (string)($field['type'] ?? 'text');
            $title = htmlspecialcharsbx((string)($field['title'] ?? $code));
            $value = isset($values[$code]) ? $values[$code] : (isset($field['default']) ? $field['default'] : '');

            $showIfAttr = '';
            if (!empty($field['show_if']) && is_array($field['show_if'])) {
                foreach ($field['show_if'] as $depCode => $depVal) {
                    $showIfAttr .= ' data-zr-show-if-' . htmlspecialcharsbx($depCode) . '="'
                        . htmlspecialcharsbx((string)$depVal) . '"';
                }
            }

            if ($type === 'note') {
                if ($code === 'webhook_note') {
                    $noteText = self::buildWebhookNote($gatewayId);
                } else {
                    $noteText = htmlspecialcharsbx((string)($field['text'] ?? ''));
                }
                $html .= '<tr class="zr-gateway-field-row"' . $showIfAttr . '><td colspan="2">'
                    . '<div class="adm-info-message-wrap"><div class="adm-info-message">'
                    . $noteText
                    . '</div></div></td></tr>';
                continue;
            }

            $inputHtml = self::renderInput($type, $code, $value, $field);
            $requiredMark = !empty($field['required']) ? ' <span class="required">*</span>' : '';

            $html .= '<tr class="zr-gateway-field-row"' . $showIfAttr . '>'
                . '<td width="40%" class="adm-detail-content-cell-l">' . $title . $requiredMark . ':</td>'
                . '<td width="60%" class="adm-detail-content-cell-r">' . $inputHtml . '</td>'
                . '</tr>';
        }

        return $html;
    }

    private static function buildWebhookNote($gatewayId)
    {
        $host = htmlspecialcharsbx($_SERVER['HTTP_HOST'] ?? 'ВАШ-ДОМЕН');
        if ((int)$gatewayId > 0) {
            $url = '/local/modules/zr.paidaccess/tools/webhook.php?id=' . (int)$gatewayId;
            $lang = defined('LANGUAGE_ID') ? (string)LANGUAGE_ID : 'ru';
            $logsUrl = 'zr_paidaccess_logs.php?lang=' . rawurlencode($lang)
                . '&tab=gateway&GATEWAY_ID=' . (int)$gatewayId;

            return 'Notification URL в T-Bank:<br><code>https://' . $host . $url . '</code><br><br>'
                . '<a href="' . htmlspecialcharsbx($logsUrl) . '">Журнал запросов этого шлюза</a> '
                . '(Init, GetState, GetQr, webhook).';
        }

        return 'Notification URL появится после сохранения шлюза (нужен ID записи).';
    }

    /**
     * @param array<string, mixed> $field
     */
    private static function renderInput($type, $code, $value, array $field)
    {
        $name = 'OPTIONS[' . htmlspecialcharsbx($code) . ']';

        if ($type === 'checkbox') {
            $checked = ($value === 'Y' || $value === '1' || $value === true) ? ' checked' : '';

            return '<input type="hidden" name="' . $name . '" value="N">'
                . '<input type="checkbox" name="' . $name . '" value="Y"' . $checked . '>';
        }

        if ($type === 'select' && !empty($field['values'])) {
            $html = '<select name="' . $name . '" class="select-field">';
            foreach ($field['values'] as $optVal => $optTitle) {
                $selected = ((string)$optVal === (string)$value) ? ' selected' : '';
                $html .= '<option value="' . htmlspecialcharsbx((string)$optVal) . '"' . $selected . '>'
                    . htmlspecialcharsbx((string)$optTitle) . '</option>';
            }
            $html .= '</select>';

            return $html;
        }

        $size = (int)($field['size'] ?? 50);

        return '<input type="text" name="' . $name . '" value="'
            . htmlspecialcharsbx((string)$value) . '" size="' . $size . '">';
    }

    /**
     * @return array<string, mixed>
     */
    public static function collectOptionsFromRequest($provider, $requestOptions)
    {
        if (!is_array($requestOptions)) {
            $requestOptions = [];
        }

        $result = [];
        foreach (GatewayProviderRegistry::getFieldsForProvider($provider) as $field) {
            $code = (string)($field['code'] ?? '');
            if ($code === '' || ($field['type'] ?? '') === 'note') {
                continue;
            }

            if (($field['type'] ?? '') === 'checkbox') {
                $result[$code] = (isset($requestOptions[$code]) && $requestOptions[$code] === 'Y') ? 'Y' : 'N';
            } elseif (array_key_exists($code, $requestOptions)) {
                $result[$code] = $requestOptions[$code];
            }
        }

        return $result;
    }

    public static function validateRequired($provider, array $options)
    {
        $instance = GatewayProviderRegistry::getProvider($provider);
        if ($instance) {
            return $instance->validateOptions($options);
        }

        return ['Неизвестный провайдер'];
    }
}
