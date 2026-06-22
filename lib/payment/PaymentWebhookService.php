<?php

namespace Zr\PaidAccess\Payment;

use Zr\PaidAccess\Enum\GatewayEventType;
use Zr\PaidAccess\Gateway\GatewayFactory;
use Zr\PaidAccess\Gateway\GatewayRepository;
use Zr\PaidAccess\Gateway\Provider\GatewayProviderRegistry;
use Zr\PaidAccess\Log\ModuleEventLogService;
use Zr\PaidAccess\Tools\RequestContext;

/**
 * Точка входа HTTP webhook: разбор JSON, шлюз, завершение оплаты.
 */
class PaymentWebhookService
{
    /**
     * @param int $gatewayId ID записи zr_paidaccess_gateway
     */
    public static function processRequest($gatewayId, $rawBody)
    {
        $requestMeta = RequestContext::capture('webhook');
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            ModuleEventLogService::error(
                'webhook_invalid_json',
                'Некорректный JSON webhook',
                array_merge($requestMeta, ['gatewayId' => (int)$gatewayId])
            );
            self::respondError('Invalid JSON', 400);
        }

        try {
            $gateway = GatewayFactory::createById((int)$gatewayId);
        } catch (\Throwable $e) {
            ModuleEventLogService::error('webhook_gateway_unavailable', $e->getMessage(), ['gatewayId' => (int)$gatewayId]);
            self::respondError($e->getMessage(), 503);
        }

        $providerCode = $gateway->getCode();
        $gatewayRow = GatewayRepository::getById((int)$gatewayId);
        $providerMeta = $gatewayRow ? GatewayProviderRegistry::getProvider((string)$gatewayRow['PROVIDER']) : null;

        $result = $gateway->handleWebhook($payload);

        $orderId = $result->orderId;
        $modulePayment = PaymentRepository::findForWebhook(
            $orderId,
            $result->gatewayPaymentId,
            (int)$gatewayId
        );
        $modulePaymentId = is_array($modulePayment) ? (int)$modulePayment['ID'] : 0;

        ModuleEventLogService::info(
            'webhook_received',
            'Входящий webhook платёжного шлюза',
            array_merge($requestMeta, [
                'gatewayId' => (int)$gatewayId,
                'orderId' => $orderId,
                'gatewayStatus' => $result->gatewayStatus,
                'gatewayPaymentId' => $result->gatewayPaymentId,
            ]),
            $modulePaymentId > 0 ? $modulePaymentId : null,
            $modulePayment ? (int)$modulePayment['USER_ID'] : null
        );

        GatewayTransactionRepository::log(
            $modulePaymentId,
            $providerCode,
            GatewayEventType::WEBHOOK,
            RequestContext::wrapPayload('webhook', $payload),
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $result->valid,
            $result->gatewayStatus,
            $result->internalStatus !== '' ? (string)$result->internalStatus : null,
            $result->errorMessage,
            $result->valid ? 200 : 403,
            (int)$gatewayId
        );

        if (!$result->valid) {
            ModuleEventLogService::error(
                'webhook_invalid',
                $result->errorMessage ?: 'Invalid notification',
                [
                    'orderId' => $orderId,
                    'gatewayStatus' => $result->gatewayStatus,
                    'gatewayId' => (int)$gatewayId,
                    'gatewayPaymentId' => $result->gatewayPaymentId,
                ],
                $modulePayment ? (int)$modulePayment['ID'] : null,
                $modulePayment ? (int)$modulePayment['USER_ID'] : null
            );
            self::respondError($result->errorMessage ?: 'Invalid notification', 403);
        }

        if ($result->paid && $modulePayment) {
            PaymentCompletionService::completePayment(
                (int)$modulePayment['ID'],
                $result->gatewayPaymentId,
                $result->gatewayStatus
            );
        } elseif ($modulePayment && !$result->paid) {
            PaymentWebhookStatusService::apply($modulePayment, $result);
        } elseif ($result->valid && !$modulePayment && $orderId !== '') {
            ModuleEventLogService::warning(
                'payment_webhook_not_found',
                'Webhook принят, но платёж не найден по идентификаторам шлюза',
                [
                    'orderId' => $orderId,
                    'gatewayStatus' => $result->gatewayStatus,
                    'gatewayPaymentId' => $result->gatewayPaymentId,
                    'gatewayId' => (int)$gatewayId,
                ]
            );
        }

        self::respondOk($providerMeta);
    }

    private static function respondOk($providerMeta)
    {
        if ($providerMeta) {
            header('Content-Type: ' . $providerMeta->getWebhookOkContentType());
            echo $providerMeta->getWebhookOkBody();
            exit;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true]);
        exit;
    }

    private static function respondError($message, $httpCode)
    {
        http_response_code($httpCode);
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
        exit;
    }
}
