<?php

namespace Zr\PaidAccess\Gateway\Providers\Tinkoff;

/**
 * Списки значений для полей настроек Тинькофф.
 */
class TinkoffFieldOptions
{
    public static function getYesNoSelect()
    {
        return [
            '0' => 'Нет',
            '1' => 'Да',
        ];
    }

    public static function getTaxationSelect()
    {
        return [
            'osn' => 'Общая СН',
            'usn_income' => 'Упрощённая СН (доходы)',
            'usn_income_outcome' => 'Упрощённая СН (доходы минус расходы)',
            'esn' => 'Единый сельскохозяйственный налог',
            'patent' => 'Патентная СН',
        ];
    }

    public static function getPaymentMethodSelect()
    {
        return [
            'full_prepayment' => 'Полная предоплата',
            'prepayment' => 'Предоплата',
            'advance' => 'Аванс',
            'full_payment' => 'Полный расчёт',
            'partial_payment' => 'Частичный расчёт и кредит',
            'credit' => 'Передача в кредит',
            'credit_payment' => 'Оплата кредита',
        ];
    }

    public static function getPaymentObjectSelect()
    {
        return [
            'commodity' => 'Товар',
            'excise' => 'Подакцизный товар',
            'job' => 'Работа',
            'service' => 'Услуга',
            'payment' => 'Платёж',
            'another' => 'Иное',
        ];
    }

    public static function getVatSelect()
    {
        return [
            'none' => 'Без НДС',
            'vat0' => 'НДС 0%',
            'vat5' => 'НДС 5%',
            'vat7' => 'НДС 7%',
            'vat10' => 'НДС 10%',
            'vat20' => 'НДС 20%',
            'vat22' => 'НДС 22%',
        ];
    }

    public static function getLanguageSelect()
    {
        return [
            'ru' => 'Русский',
            'en' => 'Английский',
        ];
    }
}
