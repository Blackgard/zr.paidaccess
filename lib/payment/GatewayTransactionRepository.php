<?php

namespace Zr\PaidAccess\Payment;

use Bitrix\Main\Type\DateTime;
use Zr\PaidAccess\Enum\GatewayEventType;
use Zr\PaidAccess\Gateway\Contract\GatewayPaymentUrlExtractorInterface;
use Zr\PaidAccess\Gateway\Provider\GatewayProviderRegistry;
use Zr\PaidAccess\Tables\GatewayTransactionTable;
use Zr\PaidAccess\Tools\Logger;

class GatewayTransactionRepository
{
    public static function add(array $fields): int
    {
        $data = array_merge([

            'DATE_CREATE' => new DateTime(),

            'SUCCESS' => 'N',

            'PAYMENT_ID' => 0,

            'GATEWAY_ID' => 0,

        ], $fields);

        $data = self::normalizeNullableFields($data);

        $result = GatewayTransactionTable::add($data);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public static function log(
        int $paymentId,
        string $gatewayCode,
        string $eventType,
        ?string $requestData = null,
        ?string $responseData = null,
        bool $success = false,
        ?string $gatewayStatus = null,
        ?string $internalStatus = null,
        ?string $errorMessage = null,
        int $httpCode = 0,
        int $gatewayId = 0
    ): int {
        return self::add([

            'PAYMENT_ID' => $paymentId,

            'GATEWAY_ID' => $gatewayId,

            'GATEWAY_CODE' => $gatewayCode,

            'EVENT_TYPE' => $eventType,

            'REQUEST_DATA' => $requestData,

            'RESPONSE_DATA' => $responseData,

            'SUCCESS' => $success ? 'Y' : 'N',

            'GATEWAY_STATUS' => $gatewayStatus,

            'INTERNAL_STATUS' => $internalStatus,

            'ERROR_MESSAGE' => $errorMessage,

            'HTTP_CODE' => $httpCode,

        ]);
    }

    /**

     * @param array<string, mixed> $requestParams
     * @param array<string, mixed>|string|null $response
     * @param array<string, string> $requestHeaders
     */
    public static function logHttpExchange(
        int $paymentId,
        int $gatewayId,
        string $gatewayCode,
        string $apiMethod,
        string $url,
        array $requestParams,
        $response,
        int $httpStatus,
        array $requestHeaders = [],
        ?string $httpError = null
    ): int {
        if ($gatewayId <= 0 && $paymentId <= 0) {
            return 0;
        }

        $eventType = self::mapApiMethodToEventType($apiMethod);

        $responseBody = is_array($response)

            ? $response

            : ['raw' => (string)$response];

        $success = $httpStatus > 0

            && $httpStatus < 400

            && (!is_array($response) || !empty($response['Success']));

        $gatewayStatus = is_array($response) ? (string)($response['Status'] ?? '') : '';

        $errorMessage = '';

        if (!$success) {
            if ($httpError !== null && $httpError !== '') {
                $errorMessage = (string)$httpError;
            } elseif (is_array($response)) {
                $errorMessage = trim((string)($response['Message'] ?? '') . ' ' . (string)($response['Details'] ?? ''));
            }
        }

        $requestPayload = [
            'direction' => 'outbound',
            'url' => $url,
            'apiMethod' => $apiMethod,
            'httpCode' => $httpStatus,
            'headers' => Logger::sanitizeParams($requestHeaders),
            'params' => Logger::sanitizeParams($requestParams),
        ];

        return self::log(
            $paymentId,
            $gatewayCode,
            $eventType,
            json_encode($requestPayload, JSON_UNESCAPED_UNICODE),
            json_encode(Logger::sanitizeParams($responseBody), JSON_UNESCAPED_UNICODE),
            $success,
            $gatewayStatus !== '' ? $gatewayStatus : null,
            null,
            $errorMessage !== '' ? $errorMessage : null,
            $httpStatus,
            $gatewayId
        );
    }

    public static function mapApiMethodToEventType(string $apiMethod): string
    {
        switch (strtolower(trim($apiMethod))) {
            case 'init':

                return GatewayEventType::INIT;

            case 'getqr':

                return GatewayEventType::GET_QR;

            case 'getstate':
            case 'checkorder':

                return GatewayEventType::STATUS_CHECK;

            case 'cancel':

                return GatewayEventType::CANCEL;

            default:

                return strtolower(trim($apiMethod));
        }
    }

    /**

     * TextField в БД NOT NULL — null заменяем на пустую строку.

     */

    protected static function normalizeNullableFields(array $data): array
    {
        foreach ([

            'REQUEST_DATA',

            'RESPONSE_DATA',

            'GATEWAY_STATUS',

            'INTERNAL_STATUS',

            'ERROR_MESSAGE',

        ] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === null) {
                $data[$field] = '';
            }
        }

        if (!isset($data['HTTP_CODE'])) {
            $data['HTTP_CODE'] = 0;
        }

        if (!isset($data['GATEWAY_ID'])) {
            $data['GATEWAY_ID'] = 0;
        }

        if (!isset($data['PAYMENT_ID'])) {
            $data['PAYMENT_ID'] = 0;
        }

        return $data;
    }

    public static function extractPaymentUrlFromInit(int $paymentId): string
    {
        if ($paymentId <= 0) {
            return '';
        }

        $row = GatewayTransactionTable::getList([

            'filter' => [

                '=PAYMENT_ID' => $paymentId,

                '=EVENT_TYPE' => GatewayEventType::INIT,

                '=SUCCESS' => 'Y',

            ],

            'order' => ['ID' => 'DESC'],

            'limit' => 1,

            'select' => ['RESPONSE_DATA', 'GATEWAY_CODE'],

        ])->fetch();

        if (!is_array($row)) {
            return '';
        }

        $provider = GatewayProviderRegistry::getProvider((string)($row['GATEWAY_CODE'] ?? ''));
        if (!$provider instanceof GatewayPaymentUrlExtractorInterface) {
            return '';
        }

        return $provider->extractPaymentUrl($row['RESPONSE_DATA'] ?? '');
    }
}
