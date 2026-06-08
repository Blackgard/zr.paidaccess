<?php

namespace Zr\PaidAccess\Gateway\Provider;

use Zr\PaidAccess\Gateway\Contract\GatewayProviderInterface;

abstract class AbstractGatewayProvider implements GatewayProviderInterface
{
    public function getDefaultOptions()
    {
        $defaults = [];
        foreach ($this->getAdminFields() as $field) {
            $code = (string)($field['code'] ?? '');
            if ($code === '' || ($field['type'] ?? '') === 'note') {
                continue;
            }
            $defaults[$code] = isset($field['default']) ? $field['default'] : '';
        }

        return $defaults;
    }

    public function validateOptions(array $options)
    {
        $errors = [];
        foreach ($this->getAdminFields() as $field) {
            if (empty($field['required'])) {
                continue;
            }
            $code = (string)($field['code'] ?? '');
            if ($code === '' || ($field['type'] ?? '') === 'note') {
                continue;
            }
            if (!isset($options[$code]) || trim((string)$options[$code]) === '') {
                $errors[] = 'Заполните поле: ' . ($field['title'] ?? $code);
            }
        }

        return $errors;
    }

    public function getWebhookOkContentType(): string
    {
        return 'application/json; charset=utf-8';
    }

    public function getWebhookOkBody(): string
    {
        return json_encode(['success' => true]);
    }
}
