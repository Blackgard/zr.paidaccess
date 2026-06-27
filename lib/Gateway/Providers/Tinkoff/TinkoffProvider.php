<?php

namespace Zr\PaidAccess\Gateway\Providers\Tinkoff;

use Zr\PaidAccess\Gateway\Contract\GatewayPaymentUrlExtractorInterface;
use Zr\PaidAccess\Gateway\Contract\GatewayReceiptCapableInterface;
use Zr\PaidAccess\Gateway\Contract\PaymentGatewayInterface;
use Zr\PaidAccess\Gateway\Dto\ReceiptDeliveryInfo;
use Zr\PaidAccess\Gateway\Provider\AbstractGatewayProvider;

class TinkoffProvider extends AbstractGatewayProvider implements GatewayReceiptCapableInterface, GatewayPaymentUrlExtractorInterface
{
    public function getCode(): string
    {
        return TinkoffGateway::CODE;
    }

    public function getTitle(): string
    {
        return 'Тинькофф (T-Bank)';
    }

    public function getAdminFields()
    {
        return [
            [
                'code' => 'terminal_key',
                'title' => 'TerminalKey (терминал)',
                'type' => 'text',
                'required' => true,
                'default' => '',
            ],
            [
                'code' => 'secret_key',
                'title' => 'SecretKey (пароль терминала)',
                'type' => 'text',
                'required' => true,
                'default' => '',
            ],
            [
                'code' => 'test_mode',
                'title' => 'Тестовый API (rest-api-test.tinkoff.ru)',
                'type' => 'checkbox',
                'default' => 'Y',
            ],
            [
                'code' => 'enable_taxation',
                'title' => 'Передавать данные для формирования чека (54-ФЗ)',
                'type' => 'select',
                'values' => TinkoffFieldOptions::getYesNoSelect(),
                'default' => '0',
            ],
            [
                'code' => 'receipt_fiscal_note',
                'title' => 'Фискальный чек',
                'type' => 'note',
                'text' => 'При включённой передаче чека T-Bank регистрирует фискальный документ в ОФД '
                    . 'и отправляет его на email покупателя (Receipt.Email в Init), если у пользователя '
                    . 'указан email в профиле. В Init передаются только поля из API T-Bank без EmailCompany и FfdVersion. '
                    . 'Требуется подключённая онлайн-касса в личном кабинете T-Bank.',
            ],
            [
                'code' => 'ffd',
                'title' => 'Чек в формате ФФД 1.2',
                'type' => 'checkbox',
                'default' => 'N',
                'show_if' => ['enable_taxation' => '1'],
            ],
            [
                'code' => 'taxation',
                'title' => 'Система налогообложения',
                'type' => 'select',
                'values' => TinkoffFieldOptions::getTaxationSelect(),
                'default' => 'usn_income',
                'show_if' => ['enable_taxation' => '1'],
            ],
            [
                'code' => 'payment_method',
                'title' => 'Признак способа расчёта',
                'type' => 'select',
                'values' => TinkoffFieldOptions::getPaymentMethodSelect(),
                'default' => 'full_payment',
                'show_if' => ['enable_taxation' => '1'],
            ],
            [
                'code' => 'payment_object',
                'title' => 'Признак предмета расчёта',
                'type' => 'select',
                'values' => TinkoffFieldOptions::getPaymentObjectSelect(),
                'default' => 'service',
                'show_if' => ['enable_taxation' => '1'],
            ],
            [
                'code' => 'item_tax',
                'title' => 'Ставка НДС для взноса',
                'type' => 'select',
                'values' => TinkoffFieldOptions::getVatSelect(),
                'default' => 'none',
                'show_if' => ['enable_taxation' => '1'],
            ],
            [
                'code' => 'delivery_taxation',
                'title' => 'Ставка НДС для доставки',
                'type' => 'select',
                'values' => TinkoffFieldOptions::getVatSelect(),
                'default' => 'none',
                'show_if' => ['enable_taxation' => '1'],
            ],
            [
                'code' => 'language',
                'title' => 'Язык платёжной формы',
                'type' => 'select',
                'values' => TinkoffFieldOptions::getLanguageSelect(),
                'default' => 'ru',
            ],
            [
                'code' => 'redirect',
                'title' => 'Автоперенаправление на платёжную форму',
                'type' => 'checkbox',
                'default' => 'N',
            ],
            [
                'code' => 'webhook_note',
                'title' => 'Notification URL',
                'type' => 'note',
            ],
        ];
    }

    public function createGateway(array $gatewayRow): PaymentGatewayInterface
    {
        return new TinkoffGateway($gatewayRow);
    }

    public function normalizeOptions(array $options, bool $isTest): array
    {
        if ($isTest) {
            $options['test_mode'] = 'Y';
        }

        return $options;
    }

    public function extractPaymentUrl($payload): string
    {
        return TinkoffPaymentUrlResolver::extractFromPayload($payload);
    }

    public function getWebhookOkContentType(): string
    {
        return 'text/plain; charset=utf-8';
    }

    public function getWebhookOkBody(): string
    {
        return 'OK';
    }

    public function getReceiptDeliveryInfo(array $gatewayOptions, ?string $customerEmail = null): ReceiptDeliveryInfo
    {
        $config = new TinkoffConfig($gatewayOptions);
        $enabled = $config->isReceiptEnabled();
        $email = trim((string)$customerEmail);

        return new ReceiptDeliveryInfo(
            $this->getCode(),
            $enabled,
            $enabled ? ReceiptDeliveryInfo::ISSUER_GATEWAY : ReceiptDeliveryInfo::ISSUER_NONE,
            $enabled && $email !== '',
            'Фискальный чек (54-ФЗ) формирует T-Bank при успешной оплате. '
            . 'Email покупателя передаётся в Receipt.Email при Init, если указан в профиле.'
        );
    }
}
