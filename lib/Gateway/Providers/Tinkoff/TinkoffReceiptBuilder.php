<?php

namespace Zr\PaidAccess\Gateway\Providers\Tinkoff;

use Zr\PaidAccess\Gateway\Dto\InitPaymentRequest;

class TinkoffReceiptBuilder
{
    public static function buildInitBody(InitPaymentRequest $request, TinkoffConfig $config)
    {
        $amountKopecks = $request->getAmountKopecks();
        $body = [
            'Amount' => $amountKopecks,
            'OrderId' => $request->orderId,
            'Description' => $request->description,
        ];

        $data = [];
        if ($request->email) {
            $data['Email'] = $request->email;
        }
        if ($request->phone) {
            $data['Phone'] = $request->phone;
        }
        if (!empty($data)) {
            $body['DATA'] = $data;
        }

        if ($config->getLanguage() === 'en') {
            $body['Language'] = 'en';
        }

        if (!$config->isReceiptEnabled()) {
            return $body;
        }

        $item = [
            'Name' => mb_substr($request->description, 0, 64, 'UTF-8'),
            'Price' => $amountKopecks,
            'Quantity' => 1,
            'Amount' => $amountKopecks,
            'PaymentMethod' => trim($config->getPaymentMethod()),
            'PaymentObject' => trim($config->getPaymentObject()),
            'Tax' => trim($config->getItemTax()),
        ];

        if ($config->isFfd12()) {
            $item['MeasurementUnit'] = 'pc';
        }

        $emailCompany = mb_substr($config->getEmailCompany(), 0, 64, 'UTF-8');
        if ($emailCompany === '') {
            $emailCompany = 'none';
        }

        $receipt = [
            'EmailCompany' => $emailCompany,
            'Taxation' => $config->getTaxation(),
            'Items' => [$item],
        ];

        if ($request->email) {
            $receipt['Email'] = $request->email;
        }
        if ($request->phone) {
            $receipt['Phone'] = $request->phone;
        }

        if ($config->isFfd12()) {
            $receipt['FfdVersion'] = '1.2';
        }

        $body['Receipt'] = $receipt;

        return $body;
    }
}
