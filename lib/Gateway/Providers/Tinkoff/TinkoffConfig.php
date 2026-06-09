<?php

namespace Zr\PaidAccess\Gateway\Providers\Tinkoff;

class TinkoffConfig
{
    /** @var array<string, mixed> */
    private $options;

    public function __construct(array $options)
    {
        $this->options = $options;
    }

    public function get($key, $default = '')
    {
        return isset($this->options[$key]) ? $this->options[$key] : $default;
    }

    public function isReceiptEnabled()
    {
        return (string)$this->get('enable_taxation', '0') === '1';
    }

    public function isFfd12()
    {
        return $this->get('ffd', 'N') === 'Y';
    }

    public function isRedirectEnabled()
    {
        return $this->get('redirect', 'N') === 'Y';
    }

    public function isTestMode()
    {
        return $this->get('test_mode', 'Y') === 'Y';
    }

    public function getTerminalKey()
    {
        return trim((string)$this->get('terminal_key', ''));
    }

    public function getSecretKey()
    {
        return trim((string)$this->get('secret_key', ''));
    }

    public function getEmailCompany()
    {
        return (string)$this->get('email_company', '');
    }

    public function getTaxation()
    {
        return (string)$this->get('taxation', 'usn_income');
    }

    public function getPaymentMethod()
    {
        return (string)$this->get('payment_method', 'full_payment');
    }

    public function getPaymentObject()
    {
        return (string)$this->get('payment_object', 'service');
    }

    public function getItemTax()
    {
        return (string)$this->get('item_tax', 'none');
    }

    public function getDeliveryTaxation()
    {
        return (string)$this->get('delivery_taxation', 'none');
    }

    public function getLanguage()
    {
        return (string)$this->get('language', 'ru');
    }
}
