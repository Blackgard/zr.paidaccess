<?php

namespace Zr\PaidAccess\Fund;

use Zr\PaidAccess\Enum\FundMovementSource;
use Zr\PaidAccess\Enum\FundMovementType;
use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\Fund\Exception\InsufficientFundBalanceException;
use Zr\PaidAccess\Gateway\GatewayTestService;
use Zr\PaidAccess\Log\AuditLogService;
use Zr\PaidAccess\Log\ModuleEventLogService;
use Zr\PaidAccess\Payment\PaymentRepository;
use Zr\PaidAccess\Subscription\SubscriptionAmountBreakdown;

/**
 * Единая точка записи движений средств фонда.
 */
class FundMovementService
{
    public static function recordPaymentIncome(int $paymentId): int
    {
        if ($paymentId <= 0) {
            throw new \InvalidArgumentException('Некорректный ID платежа');
        }

        $existing = FundMovementRepository::findPaymentIncome($paymentId);
        if ($existing) {
            return (int)$existing['ID'];
        }

        $payment = PaymentRepository::getById($paymentId);
        if (!$payment) {
            throw new \RuntimeException('Платёж #' . $paymentId . ' не найден');
        }

        if ((string)$payment['STATUS'] !== PaymentStatus::PAID) {
            throw new \RuntimeException('Платёж #' . $paymentId . ' не в статусе paid');
        }

        if (GatewayTestService::isGatewayTestPayment($payment)) {
            return 0;
        }

        $siteId = FundPaymentSiteResolver::resolveForPayment($payment);
        $fund = FundService::ensureDefaultFund($siteId);
        $fundId = (int)$fund['ID'];
        $amount = SubscriptionAmountBreakdown::resolveFundAmountFromPayment($payment);
        if ($amount <= 0) {
            throw new \RuntimeException('Сумма фондового взноса должна быть больше нуля');
        }

        $userId = (int)($payment['USER_ID'] ?? 0);
        $description = trim((string)($payment['DESCRIPTION'] ?? ''));
        if ($description === '') {
            $description = 'Взнос участника';
        }

        $movementId = FundMovementRepository::create([
            'FUND_ID' => $fundId,
            'TYPE' => FundMovementType::INCOME,
            'AMOUNT' => $amount,
            'DESCRIPTION' => $description,
            'SOURCE' => FundMovementSource::PAYMENT,
            'PAYMENT_ID' => $paymentId,
            'USER_ID' => $userId > 0 ? $userId : null,
            'ORDER_ID' => (string)($payment['ORDER_ID'] ?? ''),
        ]);

        ModuleEventLogService::info(
            'fund_movement_income',
            'Поступление на фонд от платежа #' . $paymentId,
            ['fundId' => $fundId, 'movementId' => $movementId, 'amount' => $amount],
            $paymentId,
            $userId > 0 ? $userId : null
        );

        return $movementId;
    }

    public static function recordPaymentRefund(int $paymentId): int
    {
        if ($paymentId <= 0) {
            throw new \InvalidArgumentException('Некорректный ID платежа');
        }

        $existing = FundMovementRepository::findPaymentRefund($paymentId);
        if ($existing) {
            return (int)$existing['ID'];
        }

        $income = FundMovementRepository::findPaymentIncome($paymentId);
        if (!$income) {
            return 0;
        }

        $payment = PaymentRepository::getById($paymentId);
        $amount = (float)($income['AMOUNT'] ?? 0);
        if ($amount <= 0 && $payment) {
            $amount = SubscriptionAmountBreakdown::resolveFundAmountFromPayment($payment);
        }
        if ($amount <= 0) {
            return 0;
        }

        $fundId = (int)($income['FUND_ID'] ?? 0);
        $userId = (int)($payment['USER_ID'] ?? 0);
        $orderId = (string)($payment['ORDER_ID'] ?? $income['ORDER_ID'] ?? '');

        $movementId = FundMovementRepository::create([
            'FUND_ID' => $fundId,
            'TYPE' => FundMovementType::EXPENSE,
            'AMOUNT' => $amount,
            'DESCRIPTION' => 'Возврат платежа ' . ($orderId !== '' ? $orderId : '#' . $paymentId),
            'SOURCE' => FundMovementSource::REFUND,
            'PAYMENT_ID' => $paymentId,
            'USER_ID' => $userId > 0 ? $userId : null,
            'ORDER_ID' => $orderId,
        ]);

        ModuleEventLogService::info(
            'fund_movement_refund',
            'Списание с фонда: возврат платежа #' . $paymentId,
            ['fundId' => $fundId, 'movementId' => $movementId, 'amount' => $amount],
            $paymentId,
            $userId > 0 ? $userId : null
        );

        return $movementId;
    }

    /**
     * @param array<string, mixed> $context adminUserId, externalRef, source (admin|system)
     */
    public static function recordExpense(int $fundId, float $amount, string $description, array $context = []): int
    {
        self::validateExpenseAmount($amount);

        $fund = FundRepository::getById($fundId);
        if (!$fund) {
            throw new \RuntimeException('Фонд #' . $fundId . ' не найден');
        }

        $available = FundBalanceService::getAvailableBalance($fundId);
        if ($available < $amount) {
            throw new InsufficientFundBalanceException(
                'Недостаточно средств на фонде. Доступно: ' . FundBalanceService::formatRubles($available) . ' ₽'
            );
        }

        $description = trim($description);
        if ($description === '') {
            throw new \InvalidArgumentException('Укажите назначение списания');
        }

        $source = (string)($context['source'] ?? FundMovementSource::ADMIN);
        if (!FundMovementSource::isValid($source) || !in_array($source, [FundMovementSource::ADMIN, FundMovementSource::SYSTEM], true)) {
            $source = FundMovementSource::ADMIN;
        }

        $adminUserId = (int)($context['adminUserId'] ?? 0);
        $externalRef = trim((string)($context['externalRef'] ?? ''));

        $movementId = FundMovementRepository::create([
            'FUND_ID' => $fundId,
            'TYPE' => FundMovementType::EXPENSE,
            'AMOUNT' => $amount,
            'DESCRIPTION' => mb_substr($description, 0, 512),
            'SOURCE' => $source,
            'ADMIN_USER_ID' => $adminUserId > 0 ? $adminUserId : null,
            'EXTERNAL_REF' => $externalRef !== '' ? mb_substr($externalRef, 0, 64) : null,
        ]);

        AuditLogService::log(
            'fund_movement',
            $movementId,
            'expense',
            null,
            AuditLogService::encodeSnapshot([
                'fundId' => $fundId,
                'amount' => $amount,
                'description' => $description,
                'externalRef' => $externalRef,
            ]),
            'Списание с фонда',
            $adminUserId > 0 ? $adminUserId : null
        );

        ModuleEventLogService::info(
            'fund_movement_expense',
            'Списание с фонда #' . $fundId . ': ' . $description,
            ['fundId' => $fundId, 'movementId' => $movementId, 'amount' => $amount]
        );

        return $movementId;
    }

    public static function validateExpenseAmount(float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Сумма списания должна быть больше нуля');
        }
    }

    public static function validateIncomeAmount(float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Сумма поступления должна быть больше нуля');
        }
    }

    /**
     * Ручное поступление на фонд (админка / корректировка).
     *
     * @param array<string, mixed> $context adminUserId, externalRef, source (admin|system)
     */
    public static function recordManualIncome(int $fundId, float $amount, string $description, array $context = []): int
    {
        self::validateIncomeAmount($amount);

        $fund = FundRepository::getById($fundId);
        if (!$fund) {
            throw new \RuntimeException('Фонд #' . $fundId . ' не найден');
        }

        $description = trim($description);
        if ($description === '') {
            throw new \InvalidArgumentException('Укажите назначение поступления');
        }

        $source = (string)($context['source'] ?? FundMovementSource::ADMIN);
        if (!FundMovementSource::isValid($source) || !in_array($source, [FundMovementSource::ADMIN, FundMovementSource::SYSTEM], true)) {
            $source = FundMovementSource::ADMIN;
        }

        $adminUserId = (int)($context['adminUserId'] ?? 0);
        $externalRef = trim((string)($context['externalRef'] ?? ''));

        $movementId = FundMovementRepository::create([
            'FUND_ID' => $fundId,
            'TYPE' => FundMovementType::INCOME,
            'AMOUNT' => $amount,
            'DESCRIPTION' => mb_substr($description, 0, 512),
            'SOURCE' => $source,
            'ADMIN_USER_ID' => $adminUserId > 0 ? $adminUserId : null,
            'EXTERNAL_REF' => $externalRef !== '' ? mb_substr($externalRef, 0, 64) : null,
        ]);

        AuditLogService::log(
            'fund_movement',
            $movementId,
            'income',
            null,
            AuditLogService::encodeSnapshot([
                'fundId' => $fundId,
                'amount' => $amount,
                'description' => $description,
                'externalRef' => $externalRef,
            ]),
            'Ручное поступление на фонд',
            $adminUserId > 0 ? $adminUserId : null
        );

        ModuleEventLogService::info(
            'fund_movement_income_manual',
            'Поступление на фонд #' . $fundId . ': ' . $description,
            ['fundId' => $fundId, 'movementId' => $movementId, 'amount' => $amount]
        );

        return $movementId;
    }

    /**
     * Безопасный вызов для платёжного контура (не прерывает оплату).
     */
    public static function tryRecordPaymentIncome(int $paymentId): void
    {
        if ($paymentId <= 0) {
            return;
        }

        try {
            self::recordPaymentIncome($paymentId);
        } catch (\Throwable $e) {
            ModuleEventLogService::error(
                'fund_movement_income_failed',
                $e->getMessage(),
                ['paymentId' => $paymentId]
            );
        }
    }

    public static function tryRecordPaymentRefund(int $paymentId): void
    {
        if ($paymentId <= 0) {
            return;
        }

        try {
            self::recordPaymentRefund($paymentId);
        } catch (\Throwable $e) {
            ModuleEventLogService::error(
                'fund_movement_refund_failed',
                $e->getMessage(),
                ['paymentId' => $paymentId]
            );
        }
    }
}
