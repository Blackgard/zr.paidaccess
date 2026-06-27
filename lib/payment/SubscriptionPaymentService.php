<?php

namespace Zr\PaidAccess\Payment;

use Bitrix\Main\UserTable;
use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\Gateway\Contract\DuplicateOrderRecoverableGatewayInterface;
use Zr\PaidAccess\Gateway\Contract\StaleSessionRecoverableGatewayInterface;
use Zr\PaidAccess\Gateway\Dto\InitPaymentRequest;
use Zr\PaidAccess\Gateway\Dto\InitPaymentResult;
use Zr\PaidAccess\Gateway\GatewayFactory;
use Zr\PaidAccess\Log\ModuleEventLogService;
use Zr\PaidAccess\Notification\SubscriptionNotificationService;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\PublicUi\PaymentWidgetPresenter;
use Zr\PaidAccess\Subscription\BillingPolicy;
use Zr\PaidAccess\Subscription\SubscriptionAmountBreakdown;
use Zr\PaidAccess\Subscription\SubscriptionPaymentQuote;
use Zr\PaidAccess\Tools\Logger;

class SubscriptionPaymentService
{
    public static function getCurrentBillingPeriod(int $userId = 0, ?string $siteId = null): string
    {
        return BillingPolicy::getCurrentBillingPeriod($userId, $siteId);
    }

    /**
     * Создать или вернуть pending-платёж за текущий период (без Sale).
     */
    public static function preparePayment(int $userId, ?string $siteId = null): int
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $quote = SubscriptionPaymentQuote::forUser($userId, $siteId);
        $coveredPeriods = $quote->coveredPeriods;
        $billingPeriod = $coveredPeriods !== [] ? $coveredPeriods[count($coveredPeriods) - 1] : '';

        BillingPolicy::assertCanInitPayment($userId, $siteId);

        $gatewayRow = GatewayFactory::getDefaultGatewayRow($siteId);
        if (!$gatewayRow) {
            throw new \RuntimeException('Платёжный шлюз не настроен. Создайте шлюз в админке.');
        }

        $existing = PaymentRepository::findPendingForCoveredPeriods($userId, $coveredPeriods);
        if ($existing) {
            $existingId = (int)$existing['ID'];
            self::syncPendingPayment($existingId, $existing, $quote, $siteId);
            $existing = PaymentRepository::getById($existingId) ?? $existing;
            self::ensureGatewayInit($existingId, $existing, $siteId);

            return $existingId;
        }

        $reopenable = PaymentRepository::findReopenableForCoveredPeriods($userId, $coveredPeriods);
        if ($reopenable) {
            $reopenId = (int)$reopenable['ID'];
            self::reopenForGatewayInit($reopenId, $reopenable, $quote, $siteId);
            $reopenable = PaymentRepository::getById($reopenId) ?? $reopenable;
            self::ensureGatewayInit($reopenId, $reopenable, $siteId);

            return $reopenId;
        }

        $modulePaymentId = PaymentRepository::create(array_merge([
            'USER_ID' => $userId,
            'CURRENCY' => 'RUB',
            'ORDER_ID' => 'PA-TMP-' . $userId . '-' . time(),
            'BILLING_PERIOD' => $billingPeriod,
            'COVERED_PERIODS' => PaymentCoveredPeriods::encode($coveredPeriods),
            'GATEWAY_CODE' => (string)$gatewayRow['PROVIDER'],
            'GATEWAY_ID' => (int)$gatewayRow['ID'],
            'DESCRIPTION' => self::buildPaymentDescription($quote, $siteId),
            'STATUS' => PaymentStatus::PENDING,
        ], $quote->totalBreakdown->toPaymentAmountFields()));

        $orderAccountNumber = 'PA-' . $modulePaymentId . '-' . $billingPeriod;

        PaymentRepository::update($modulePaymentId, [
            'ORDER_ID' => $orderAccountNumber,
        ]);

        $row = PaymentRepository::getById($modulePaymentId);
        self::ensureGatewayInit($modulePaymentId, $row, $siteId);

        return $modulePaymentId;
    }

    /**
     * @param array<string, mixed> $payment
     */
    private static function syncPendingPayment(
        int $paymentId,
        array $payment,
        SubscriptionPaymentQuote $quote,
        ?string $siteId
    ): void {
        $coveredPeriods = $quote->coveredPeriods;
        $billingPeriod = $coveredPeriods !== [] ? $coveredPeriods[count($coveredPeriods) - 1] : '';
        $storedPeriods = PaymentCoveredPeriods::fromPaymentRow($payment);
        $needsUpdate = !PaymentRepository::coveredPeriodsEqual($storedPeriods, $coveredPeriods)
            || abs((float)($payment['AMOUNT'] ?? 0) - $quote->totalBreakdown->chargeTotal) > 0.01;

        if (!$needsUpdate) {
            return;
        }

        PaymentRepository::update($paymentId, array_merge([
            'BILLING_PERIOD' => $billingPeriod,
            'COVERED_PERIODS' => PaymentCoveredPeriods::encode($coveredPeriods),
            'DESCRIPTION' => self::buildPaymentDescription($quote, $siteId),
            'GATEWAY_PAYMENT_ID' => null,
            'GATEWAY_PAYMENT_URL' => null,
        ], $quote->totalBreakdown->toPaymentAmountFields()));
    }

    /**
     * Повторный Init в банке для платежа без PaymentId шлюза (из админки или API).
     */
    public static function retryGatewayInit(int $paymentId, ?string $siteId = null): void
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $payment = PaymentRepository::getById($paymentId);

        if (!$payment || !self::canRetryGatewayInit($payment)) {
            throw new \RuntimeException(
                'Платёж нельзя повторно инициализировать в банке: нужен статус pending/failed/cancelled '
                . 'и пустой PaymentId шлюза'
            );
        }

        $quote = SubscriptionPaymentQuote::forUser((int)$payment['USER_ID'], $siteId);
        $status = (string)($payment['STATUS'] ?? '');

        if (in_array($status, [PaymentStatus::FAILED, PaymentStatus::CANCELLED], true)) {
            self::reopenForGatewayInit($paymentId, $payment, $quote, $siteId);
        } elseif ($status === PaymentStatus::PENDING) {
            self::syncPendingPayment($paymentId, $payment, $quote, $siteId);
        }

        $payment = PaymentRepository::getById($paymentId) ?? $payment;
        self::ensureGatewayInit($paymentId, $payment, $siteId);
    }

    /**
     * @param array<string, mixed>|null $payment
     */
    public static function canRetryGatewayInit(?array $payment): bool
    {
        if (!$payment) {
            return false;
        }

        if (trim((string)($payment['GATEWAY_PAYMENT_ID'] ?? '')) !== '') {
            return false;
        }

        $gatewayCode = trim((string)($payment['GATEWAY_CODE'] ?? ''));
        if ($gatewayCode === '' || $gatewayCode === 'manual') {
            return false;
        }

        return in_array((string)($payment['STATUS'] ?? ''), [
            PaymentStatus::PENDING,
            PaymentStatus::FAILED,
            PaymentStatus::CANCELLED,
        ], true);
    }

    /**
     * @param array<string, mixed> $payment
     */
    private static function reopenForGatewayInit(
        int $paymentId,
        array $payment,
        SubscriptionPaymentQuote $quote,
        ?string $siteId
    ): void {
        $previousStatus = (string)($payment['STATUS'] ?? '');
        $coveredPeriods = $quote->coveredPeriods;
        $billingPeriod = $coveredPeriods !== [] ? $coveredPeriods[count($coveredPeriods) - 1] : '';

        PaymentRepository::update($paymentId, array_merge([
            'STATUS' => PaymentStatus::PENDING,
            'BILLING_PERIOD' => $billingPeriod,
            'COVERED_PERIODS' => PaymentCoveredPeriods::encode($coveredPeriods),
            'DESCRIPTION' => self::buildPaymentDescription($quote, $siteId),
            'GATEWAY_PAYMENT_ID' => null,
            'GATEWAY_PAYMENT_URL' => null,
            'DATE_PAID' => null,
        ], $quote->totalBreakdown->toPaymentAmountFields()));

        ModuleEventLogService::info(
            'payment_reopened_for_init',
            'Платёж повторно открыт для Init в банке',
            [
                'billingPeriod' => $billingPeriod,
                'coveredPeriods' => $coveredPeriods,
                'previousStatus' => $previousStatus,
                'orderId' => (string)($payment['ORDER_ID'] ?? ''),
            ],
            $paymentId,
            (int)$payment['USER_ID'],
            $siteId
        );
    }

    private static function buildPaymentDescription(SubscriptionPaymentQuote $quote, ?string $siteId): string
    {
        $base = PaidAccessCore::getPaymentDescription($siteId);
        if (!$quote->isArrearsPayment) {
            return $base;
        }

        return $base . ' (' . $quote->periodCount . ' периода)';
    }

    /**
     * QR СБП / ссылка на оплату (HTML).
     */
    public static function renderPaymentWidget(int $modulePaymentId, ?string $siteId = null): void
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);

        $modulePayment = PaymentRepository::getById($modulePaymentId);
        if (!$modulePayment) {
            ModuleEventLogService::error('payment_widget_not_found', 'Платёж не найден', [], $modulePaymentId, null, $siteId);
            PaymentPageErrorRenderer::render($siteId);

            return;
        }

        if (PaymentStatus::isPaidLike((string)$modulePayment['STATUS'])) {
            echo '<p>Оплата уже получена. Обновите страницу.</p>';

            return;
        }

        $gatewayPaymentId = (string)($modulePayment['GATEWAY_PAYMENT_ID'] ?? '');
        if ($gatewayPaymentId === '') {
            echo '<p>Платёж в банке ещё не создан. Обновите страницу.</p>';

            return;
        }

        try {
            $gateway = self::resolveGateway($modulePayment, $siteId);
            $widgetMode = PaidAccessCore::getPaymentWidgetMode($siteId);
            $request = self::buildFetchFormRequest($modulePayment, $widgetMode);

            Logger::info(
                'renderPaymentWidget',
                [
                    'paymentId' => $modulePaymentId,
                    'userId' => (int)$modulePayment['USER_ID'],
                    'gatewayPaymentId' => $gatewayPaymentId,
                    'widgetMode' => $widgetMode,
                    'orderId' => (string)$modulePayment['ORDER_ID'],
                ],
                (string)$modulePayment['GATEWAY_CODE'],
                $siteId
            );

            $result = $gateway->fetchPaymentForm($gatewayPaymentId, $request);

            if (
                !$result->success
                && $gateway instanceof StaleSessionRecoverableGatewayInterface
                && $gateway->isStalePaymentSessionFailure($result)
            ) {
                $recycledPaymentId = self::recycleStalePendingPayment(
                    $modulePaymentId,
                    $modulePayment,
                    $gateway,
                    $siteId
                );
                if ($recycledPaymentId !== null && $recycledPaymentId !== $modulePaymentId) {
                    $modulePaymentId = $recycledPaymentId;
                    $modulePayment = PaymentRepository::getById($modulePaymentId) ?? $modulePayment;
                    $gatewayPaymentId = (string)($modulePayment['GATEWAY_PAYMENT_ID'] ?? '');
                    if ($gatewayPaymentId !== '') {
                        $result = $gateway->fetchPaymentForm(
                            $gatewayPaymentId,
                            self::buildFetchFormRequest($modulePayment, $widgetMode)
                        );
                    }
                }
            }

            if (
                !$result->success
                && $gateway instanceof DuplicateOrderRecoverableGatewayInterface
                && $gateway->isDuplicateOrderError($result)
                && self::handleDuplicateOrderIdError(
                    $modulePaymentId,
                    $modulePayment,
                    $result,
                    $gateway,
                    $request,
                    $siteId
                )
            ) {
                $modulePayment = PaymentRepository::getById($modulePaymentId) ?? $modulePayment;
                $gatewayPaymentId = (string)($modulePayment['GATEWAY_PAYMENT_ID'] ?? '');
                if ($gatewayPaymentId !== '') {
                    $result = $gateway->fetchPaymentForm(
                        $gatewayPaymentId,
                        self::buildFetchFormRequest($modulePayment, $widgetMode)
                    );
                }
            }

            if ($result->success) {
                $html = PaymentWidgetPresenter::renderFromResult($result);
                if ($html !== '') {
                    if ($result->paymentUrl !== '' && trim((string)($modulePayment['GATEWAY_PAYMENT_URL'] ?? '')) === '') {
                        PaymentRepository::update($modulePaymentId, ['GATEWAY_PAYMENT_URL' => $result->paymentUrl]);
                    }

                    echo $html;

                    return;
                }
            }

            $errorMessage = $result->errorMessage ?: 'Не удалось получить форму оплаты';
            $httpCode = $result->getHttpCode();
            Logger::warning(
                'renderPaymentWidget failed',
                [
                    'paymentId' => $modulePaymentId,
                    'error' => $errorMessage,
                    'httpCode' => $httpCode > 0 ? $httpCode : null,
                    'rawResponse' => Logger::sanitizeParams(
                        is_array(json_decode((string)$result->rawResponse, true))
                            ? json_decode((string)$result->rawResponse, true)
                            : ['raw' => (string)$result->rawResponse]
                    ),
                ],
                (string)$modulePayment['GATEWAY_CODE'],
                $siteId
            );
            ModuleEventLogService::error(
                'payment_widget_form_failed',
                $errorMessage,
                array_filter([
                    'gatewayPaymentId' => $gatewayPaymentId,
                    'httpCode' => $httpCode > 0 ? $httpCode : null,
                ]),
                $modulePaymentId,
                (int)$modulePayment['USER_ID'],
                $siteId
            );
            PaymentPageErrorRenderer::render($siteId);
        } catch (\Throwable $e) {
            ModuleEventLogService::error(
                'payment_widget_exception',
                $e->getMessage(),
                ['trace' => $e->getFile() . ':' . $e->getLine()],
                $modulePaymentId,
                (int)($modulePayment['USER_ID'] ?? 0),
                $siteId
            );
            PaymentPageErrorRenderer::render($siteId);
        }
    }

    /**
     * @param array<string, mixed> $modulePayment
     */
    private static function ensureGatewayInit(int $modulePaymentId, ?array $modulePayment, ?string $siteId): void
    {
        if (!$modulePayment) {
            $modulePayment = PaymentRepository::getById($modulePaymentId);
        }

        if (!$modulePayment || (string)($modulePayment['GATEWAY_PAYMENT_ID'] ?? '') !== '') {
            return;
        }

        if ((string)($modulePayment['STATUS'] ?? '') === PaymentStatus::FAILED) {
            throw new \RuntimeException('Платёж помечен как неуспешный');
        }

        $gateway = self::resolveGateway($modulePayment, $siteId);
        $request = self::buildInitRequest($modulePayment);

        Logger::info(
            'ensureGatewayInit',
            [
                'paymentId' => $modulePaymentId,
                'orderId' => (string)$modulePayment['ORDER_ID'],
                'amount' => (float)$modulePayment['AMOUNT'],
            ],
            (string)$modulePayment['GATEWAY_CODE'],
            $siteId
        );

        $result = $gateway->initPayment($request);

        if (!$result->success) {
            $errorMessage = $result->errorMessage ?: 'Ошибка Init в банке';
            $isDuplicateOrderError = $gateway instanceof DuplicateOrderRecoverableGatewayInterface
                && $gateway->isDuplicateOrderError($result);
            if (
                $isDuplicateOrderError
                && self::handleDuplicateOrderIdError(
                    $modulePaymentId,
                    $modulePayment,
                    $result,
                    $gateway,
                    $request,
                    $siteId
                )
            ) {
                return;
            }

            if (
                $gateway instanceof StaleSessionRecoverableGatewayInterface
                && $gateway->isStalePaymentSessionFailure($result)
            ) {
                self::closeStalePayment($modulePaymentId, $modulePayment, $errorMessage, $siteId);
                throw new \RuntimeException($errorMessage);
            }

            if (!$isDuplicateOrderError) {
                self::markPaymentFailed(
                    $modulePaymentId,
                    $modulePayment,
                    $errorMessage,
                    $siteId,
                    'payment_init_failed',
                    $result->getHttpCode()
                );
            }

            throw new \RuntimeException($errorMessage);
        }

        PaymentRepository::update($modulePaymentId, [
            'GATEWAY_PAYMENT_ID' => $result->gatewayPaymentId,
            'GATEWAY_PAYMENT_URL' => $result->paymentUrl,
        ]);
    }

    /**
     * @param array<string, mixed> $modulePayment
     * @return int|null ID нового pending-платежа или null, если пересоздание не выполнено
     */
    private static function recycleStalePendingPayment(
        int $modulePaymentId,
        array $modulePayment,
        $gateway,
        ?string $siteId
    ): ?int {
        if ((string)($modulePayment['STATUS'] ?? '') !== PaymentStatus::PENDING) {
            return null;
        }

        if (!$gateway instanceof StaleSessionRecoverableGatewayInterface
            || !PaymentCancellationService::canCancel($modulePayment)
        ) {
            return null;
        }

        try {
            PaymentCancellationService::cancel($modulePaymentId);
            $newPaymentId = self::preparePayment((int)$modulePayment['USER_ID'], $siteId);
        } catch (\Throwable $e) {
            self::closeStalePayment($modulePaymentId, $modulePayment, $e->getMessage(), $siteId);

            try {
                $newPaymentId = self::preparePayment((int)$modulePayment['USER_ID'], $siteId);
            } catch (\Throwable $retryException) {
                ModuleEventLogService::error(
                    'payment_stale_session_recycle_failed',
                    $retryException->getMessage(),
                    ['paymentId' => $modulePaymentId, 'previousError' => $e->getMessage()],
                    $modulePaymentId,
                    (int)($modulePayment['USER_ID'] ?? 0),
                    $siteId
                );

                return null;
            }
        }

        ModuleEventLogService::info(
            'payment_stale_session_recycled',
            'Просроченная сессия банка закрыта, создан новый pending-платёж',
            [
                'previousPaymentId' => $modulePaymentId,
                'newPaymentId' => $newPaymentId,
                'orderId' => (string)($modulePayment['ORDER_ID'] ?? ''),
            ],
            $newPaymentId,
            (int)$modulePayment['USER_ID'],
            $siteId
        );

        return $newPaymentId;
    }

    /**
     * @param array<string, mixed> $modulePayment
     */
    private static function closeStalePayment(
        int $paymentId,
        array $modulePayment,
        string $reason,
        ?string $siteId
    ): void {
        if (PaymentCancellationService::canCancel($modulePayment)) {
            try {
                PaymentCancellationService::cancel($paymentId);

                return;
            } catch (\Throwable $e) {
                $reason = trim($reason . ' ' . $e->getMessage());
            }
        }

        PaymentCancellationService::closeStaleSession($paymentId, $reason, $siteId);
    }

    /**
     * @param array<string, mixed> $modulePayment
     */
    private static function handleDuplicateOrderIdError(
        int $modulePaymentId,
        array $modulePayment,
        InitPaymentResult $initResult,
        $gateway,
        InitPaymentRequest $request,
        ?string $siteId
    ): bool {
        $policy = PaidAccessCore::getPaymentDuplicateOrderPolicy($siteId);
        $errorMessage = trim($initResult->errorMessage) !== ''
            ? $initResult->errorMessage
            : 'Заказ с таким order_id уже существует в банке';

        if ($policy === PaidAccessCore::PAYMENT_DUPLICATE_ORDER_REUSE
            && $gateway instanceof DuplicateOrderRecoverableGatewayInterface
        ) {
            $recovered = $gateway->recoverDuplicateOrder($request);
            if ($recovered->success && $recovered->gatewayPaymentId !== '') {
                PaymentRepository::update($modulePaymentId, [
                    'GATEWAY_PAYMENT_ID' => $recovered->gatewayPaymentId,
                    'GATEWAY_PAYMENT_URL' => $recovered->paymentUrl,
                ]);
                ModuleEventLogService::info(
                    'payment_duplicate_order_reused',
                    'Привязан существующий платёж шлюза: ' . $recovered->gatewayPaymentId,
                    ['orderId' => (string)$modulePayment['ORDER_ID']],
                    $modulePaymentId,
                    (int)$modulePayment['USER_ID'],
                    $siteId
                );

                return true;
            }

            ModuleEventLogService::warning(
                'payment_duplicate_order_reuse_failed',
                $recovered->errorMessage ?: 'Не удалось привязать существующий платёж шлюза',
                ['orderId' => (string)$modulePayment['ORDER_ID']],
                $modulePaymentId,
                (int)$modulePayment['USER_ID'],
                $siteId
            );
            $policy = PaidAccessCore::PAYMENT_DUPLICATE_ORDER_FAIL;
        }

        if ($policy === PaidAccessCore::PAYMENT_DUPLICATE_ORDER_IGNORE) {
            ModuleEventLogService::warning(
                'payment_duplicate_order_ignored',
                $errorMessage,
                ['orderId' => (string)$modulePayment['ORDER_ID']],
                $modulePaymentId,
                (int)$modulePayment['USER_ID'],
                $siteId
            );

            return false;
        }

        self::markPaymentFailed(
            $modulePaymentId,
            $modulePayment,
            $errorMessage,
            $siteId,
            'payment_duplicate_order_failed'
        );

        return false;
    }

    /**
     * @param array<string, mixed> $modulePayment
     */
    private static function markPaymentFailed(
        int $modulePaymentId,
        array $modulePayment,
        string $errorMessage,
        ?string $siteId,
        string $logEvent,
        int $httpCode = 0
    ): void {
        if ((string)($modulePayment['STATUS'] ?? '') === PaymentStatus::FAILED) {
            return;
        }

        PaymentRepository::update($modulePaymentId, ['STATUS' => PaymentStatus::FAILED]);
        ModuleEventLogService::error(
            $logEvent,
            $errorMessage,
            array_filter([
                'orderId' => (string)$modulePayment['ORDER_ID'],
                'gatewayCode' => (string)$modulePayment['GATEWAY_CODE'],
                'httpCode' => $httpCode > 0 ? $httpCode : null,
            ]),
            $modulePaymentId,
            (int)$modulePayment['USER_ID'],
            $siteId
        );
        SubscriptionNotificationService::onPaymentFailed($modulePaymentId, $errorMessage, $siteId);
    }

    /**
     * @param array<string, mixed>|null $modulePayment
     */
    private static function resolveGateway($modulePayment, $siteId = null)
    {
        if (is_array($modulePayment) && (int)($modulePayment['GATEWAY_ID'] ?? 0) > 0) {
            return GatewayFactory::createById((int)$modulePayment['GATEWAY_ID']);
        }

        return GatewayFactory::create($siteId);
    }

    /**
     * @param array<string, mixed> $modulePayment
     */
    private static function buildInitRequest(array $modulePayment): InitPaymentRequest
    {
        $userId = (int)$modulePayment['USER_ID'];
        $email = null;
        $phone = null;

        $user = UserTable::getByPrimary($userId, ['select' => ['EMAIL', 'PERSONAL_PHONE']])->fetch();
        if ($user) {
            $email = $user['EMAIL'] ?: null;
            $phone = $user['PERSONAL_PHONE'] ?: null;
        }

        return new InitPaymentRequest(
            (string)$modulePayment['ORDER_ID'],
            (float)$modulePayment['AMOUNT'],
            (string)$modulePayment['CURRENCY'],
            (string)($modulePayment['DESCRIPTION'] ?? 'Подписка'),
            $userId,
            $email,
            $phone
        );
    }

    /**
     * @param array<string, mixed> $modulePayment
     */
    private static function resolveStoredPaymentUrl(array $modulePayment): string
    {
        $url = trim((string)($modulePayment['GATEWAY_PAYMENT_URL'] ?? ''));
        if ($url !== '') {
            return $url;
        }

        $paymentId = (int)($modulePayment['ID'] ?? 0);
        if ($paymentId <= 0) {
            return '';
        }

        $url = GatewayTransactionRepository::extractPaymentUrlFromInit($paymentId);
        if ($url !== '') {
            PaymentRepository::update($paymentId, ['GATEWAY_PAYMENT_URL' => $url]);
        }

        return $url;
    }

    /**
     * @param array<string, mixed> $modulePayment
     */
    private static function buildInitRequestWithStoredUrl(array $modulePayment): InitPaymentRequest
    {
        $request = self::buildInitRequest($modulePayment);
        $request->paymentUrl = self::resolveStoredPaymentUrl($modulePayment);

        return $request;
    }

    /**
     * @param array<string, mixed> $modulePayment
     */
    private static function buildFetchFormRequest(array $modulePayment, string $paymentWidgetMode): InitPaymentRequest
    {
        $request = self::buildInitRequestWithStoredUrl($modulePayment);
        $request->paymentWidgetMode = $paymentWidgetMode;

        return $request;
    }
}
