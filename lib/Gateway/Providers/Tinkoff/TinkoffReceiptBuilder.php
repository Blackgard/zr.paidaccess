<?php

namespace Zr\PaidAccess\Gateway\Providers\Tinkoff;

use Zr\PaidAccess\Gateway\Dto\InitPaymentRequest;

class TinkoffReceiptBuilder
{
    private const DESCRIPTION_MAX_LENGTH = 140;
    private const ITEM_NAME_MAX_LENGTH = 128;

    public static function buildInitBody(InitPaymentRequest $request, TinkoffConfig $config)
    {
        $amountKopecks = $request->getAmountKopecks();
        $description = self::truncate($request->description, self::DESCRIPTION_MAX_LENGTH);

        $body = [
            'Amount' => $amountKopecks,
            'OrderId' => $request->orderId,
            'Description' => $description,
        ];

        if ($config->getLanguage() === 'en') {
            $body['Language'] = 'en';
        }

        if (!$config->isReceiptEnabled()) {
            $data = self::buildData($request);
            if ($data !== []) {
                $body['DATA'] = $data;
            }

            return $body;
        }

        $item = [
            'Name' => self::truncate($description, self::ITEM_NAME_MAX_LENGTH),
            'Price' => $amountKopecks,
            'Quantity' => 1,
            'Amount' => $amountKopecks,
            'Tax' => trim($config->getItemTax()),
            'PaymentMethod' => trim($config->getPaymentMethod()),
            'PaymentObject' => trim($config->getPaymentObject()),
        ];

        if ($config->isFfd12()) {
            $item['MeasurementUnit'] = 'шт';
        }

        $receipt = [
            'Taxation' => $config->getTaxation(),
            'Items' => [$item],
        ];

        if ($request->email) {
            $receipt['Email'] = $request->email;
        }
        if ($request->phone) {
            $receipt['Phone'] = $request->phone;
        }

        $body['Receipt'] = $receipt;

        return $body;
    }

    /**
     * @return array<string, string>
     */
    private static function buildData(InitPaymentRequest $request): array
    {
        $data = [];
        if ($request->email) {
            $data['Email'] = $request->email;
        }
        if ($request->phone) {
            $data['Phone'] = $request->phone;
        }

        return $data;
    }

    private static function truncate(string $value, int $maxLength): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) <= $maxLength) {
            return $value;
        }

        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }
}
