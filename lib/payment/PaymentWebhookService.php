<?php

namespace Zr\PaidAccess\Payment;

use Zr\PaidAccess\Enum\GatewayEventType;
use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\Gateway\GatewayFactory;
use Zr\PaidAccess\Log\ModuleEventLogService;
use Zr\PaidAccess\Notification\SubscriptionNotificationService;
use Zr\PaidAccess\Gateway\GatewayRepository;
use Zr\PaidAccess\Gateway\Provider\GatewayProviderRegistry;
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

        ModuleEventLogService::info(
            'webhook_received',
            'Входящий webhook T-Bank',
            array_merge($requestMeta, [
                'gatewayId' => (int)$gatewayId,
                'orderId' => (string)($payload['OrderId'] ?? ''),
                'bankStatus' => (string)($payload['Status'] ?? ''),
                'paymentId' => (string)($payload['PaymentId'] ?? ''),
            ])
        );

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
        $modulePayment = $orderId !== '' ? PaymentRepository::getByOrderId($orderId) : null;
        $modulePaymentId = is_array($modulePayment) ? (int)$modulePayment['ID'] : 0;

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
                ['orderId' => $orderId, 'gatewayStatus' => $result->gatewayStatus],
                $modulePayment ? (int)$modulePayment['ID'] : null,
                $modulePayment ? (int)$modulePayment['USER_ID'] : null
            );
            self::respondError($result->errorMessage ?: 'Invalid notification', 403);
        }

        if ($result->paid && $orderId !== '') {
            PaymentCompletionService::completeByOrderId(
                $orderId,
                $result->gatewayPaymentId,
                $result->gatewayStatus
            );
        } elseif ($modulePayment && $orderId !== '' && !$result->paid) {
            $internalStatus = (string)$result->internalStatus;
            if ($internalStatus === PaymentStatus::CANCELLED
                && (string)$modulePayment['STATUS'] !== PaymentStatus::CANCELLED
            ) {
                PaymentRepository::update((int)$modulePayment['ID'], [
                    'STATUS' => PaymentStatus::CANCELLED,
                    'DATE_PAID' => null,
                ]);
                ModuleEventLogService::info(
                    'payment_webhook_cancelled',
                    'Платёж отменён шлюзом: ' . $result->gatewayStatus,
                    ['orderId' => $orderId, 'gatewayStatus' => $result->gatewayStatus],
                    (int)$modulePayment['ID'],
                    (int)$modulePayment['USER_ID']
                );
            } elseif ($internalStatus === PaymentStatus::FAILED
                && (string)$modulePayment['STATUS'] === PaymentStatus::PENDING
            ) {
                PaymentRepository::update((int)$modulePayment['ID'], ['STATUS' => PaymentStatus::FAILED]);
                ModuleEventLogService::warning(
                    'payment_webhook_failed',
                    'Платёж отклонён шлюзом: ' . $result->gatewayStatus,
                    ['orderId' => $orderId, 'gatewayStatus' => $result->gatewayStatus],
                    (int)$modulePayment['ID'],
                    (int)$modulePayment['USER_ID']
                );
                SubscriptionNotificationService::onPaymentFailed(
                    (int)$modulePayment['ID'],
                    'Статус в банке: ' . $result->gatewayStatus
                );
            }
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
