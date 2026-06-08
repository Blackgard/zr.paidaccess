<?php

namespace Zr\PaidAccess\Subscription;

use Bitrix\Main\Type\DateTime;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Payment\PaymentRepository;

/**
 * Политика биллинга: календарный период, день оплаты, лимит F6 (1 оплата за период).
 */
class BillingPolicy
{
    /**
     * Ключ текущего расчётного периода для пользователя (хранится в BILLING_PERIOD платежа).
     */
    public static function getCurrentBillingPeriod(int $userId = 0, ?string $siteId = null): string
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $now = new \DateTimeImmutable('now');

        if (PaidAccessCore::isPersonalBillingPeriodMode($siteId)) {
            if ($userId <= 0) {
                return '';
            }

            return self::getCurrentAnchorPeriodStart($userId, $now, $siteId)->format('Y-m-d');
        }

        return $now->format('Y-m');
    }

    /**
     * Период по произвольной дате (для админки / ручных платежей).
     */
    public static function resolveBillingPeriodForDate(int $userId, \DateTimeInterface $at, ?string $siteId = null): string
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $immutable = $at instanceof \DateTimeImmutable
            ? $at
            : \DateTimeImmutable::createFromMutable(\DateTime::createFromInterface($at));

        if (PaidAccessCore::isPersonalBillingPeriodMode($siteId)) {
            if ($userId <= 0) {
                return $immutable->format('Y-m');
            }

            return self::getCurrentAnchorPeriodStart($userId, $immutable, $siteId)->format('Y-m-d');
        }

        return $immutable->format('Y-m');
    }

    /**
     * можно ли инициировать новый платёж за текущий период.
     */
    public static function canInitPayment(int $userId, ?string $siteId = null): bool
    {
        try {
            self::assertCanInitPayment($userId, $siteId);
        } catch (\RuntimeException $e) {
            return false;
        }

        return true;
    }

    /**
     * @throws \RuntimeException
     */
    public static function assertCanInitPayment(int $userId, ?string $siteId = null): void
    {
        if ($userId <= 0) {
            throw new \RuntimeException('Не указан пользователь');
        }

        if (!PaidAccessCore::isBillingEnforceOnePayment($siteId)) {
            return;
        }

        $period = self::getCurrentBillingPeriod($userId, $siteId);
        if (PaymentRepository::hasPaidInPeriod($userId, $period)) {
            throw new \RuntimeException(
                'Подписка за период ' . self::formatPeriodLabel($period, $siteId) . ' уже оплачена'
            );
        }
    }

    /**
     * День месяца, когда наступает срок оплаты (billing anchor).
     */
    public static function resolveBillingDay(int $userId, ?string $siteId = null): int
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);

        if (PaidAccessCore::isRegistrationBillingPeriodMode($siteId)) {
            return self::resolveRegistrationBillingDay($userId);
        }

        $source = PaidAccessCore::getBillingAnchorSource($siteId);

        if ($source === PaidAccessCore::BILLING_ANCHOR_SOURCE_FIXED) {
            return PaidAccessCore::getBillingFixedDay($siteId);
        }

        return self::resolveRegistrationBillingDay($userId);
    }

    protected static function resolveRegistrationBillingDay(int $userId): int
    {
        if ($userId <= 0) {
            return (int)date('j');
        }

        $user = \CUser::GetByID($userId)->Fetch();
        $registerDate = (string)($user['DATE_REGISTER'] ?? '');

        if ($registerDate === '') {
            return (int)date('j');
        }

        $day = (int)date('j', strtotime($registerDate));

        return max(1, min(31, $day));
    }

    /**
     * Дата срока оплаты внутри расчётного периода.
     */
    public static function getDueDateForPeriod(int $userId, string $billingPeriod, ?string $siteId = null): \DateTimeImmutable
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $anchorDay = self::resolveBillingDay($userId, $siteId);

        if (PaidAccessCore::isPersonalBillingPeriodMode($siteId)) {
            $start = \DateTimeImmutable::createFromFormat('Y-m-d', $billingPeriod);
            if ($start === false) {
                $start = new \DateTimeImmutable('now');
            }

            return $start->setTime(0, 0, 0);
        }

        if (!preg_match('/^(\d{4})-(\d{2})$/', $billingPeriod, $matches)) {
            return new \DateTimeImmutable('now');
        }

        $year = (int)$matches[1];
        $month = (int)$matches[2];
        $day = self::clampDayToMonth($anchorDay, $year, $month, $siteId);

        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    /**
     * Крайний срок оплаты с учётом льготных дней (после него — долг / блокировка).
     */
    public static function getPaymentDeadline(int $userId, string $billingPeriod, ?string $siteId = null): \DateTimeImmutable
    {
        $due = self::getDueDateForPeriod($userId, $billingPeriod, $siteId);
        $graceDays = PaidAccessCore::getBillingGraceDays($siteId);

        if ($graceDays <= 0) {
            return $due->setTime(23, 59, 59);
        }

        return $due->modify('+' . $graceDays . ' days')->setTime(23, 59, 59);
    }

    /**
     * Окончание доступа после успешной оплаты (PERIOD_END подписки).
     */
    public static function calcSubscriptionPeriodEnd(DateTime $paidAt, int $userId, ?string $siteId = null): DateTime
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $paid = self::toImmutable($paidAt);
        $anchorDay = self::resolveBillingDay($userId, $siteId);

        if (PaidAccessCore::isPersonalBillingPeriodMode($siteId)) {
            $periodStart = self::getCurrentAnchorPeriodStart($userId, $paid, $siteId);
            $nextStart = self::addMonthsKeepingAnchor($periodStart, 1, $anchorDay, $siteId);
            $end = $nextStart->modify('-1 second');
        } else {
            $nextAnchor = self::findNextBillingAnchor($paid, $anchorDay, $siteId);
            $end = $nextAnchor->setTime(23, 59, 59);
        }

        $graceDays = PaidAccessCore::getBillingGraceDays($siteId);
        if ($graceDays > 0) {
            $end = $end->modify('+' . $graceDays . ' days');
        }

        return DateTime::createFromTimestamp($end->getTimestamp());
    }

    public static function formatPeriodLabel(string $billingPeriod, ?string $siteId = null): string
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);

        if ($billingPeriod === 'GT') {
            return 'Тест подключения шлюза';
        }

        if (preg_match('/^(\d{4})-(\d{2})$/', $billingPeriod, $matches)) {
            $months = [
                '01' => 'январь', '02' => 'февраль', '03' => 'март', '04' => 'апрель',
                '05' => 'май', '06' => 'июнь', '07' => 'июль', '08' => 'август',
                '09' => 'сентябрь', '10' => 'октябрь', '11' => 'ноябрь', '12' => 'декабрь',
            ];
            $monthName = $months[$matches[2]] ?? $matches[2];

            return $monthName . ' ' . $matches[1];
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $billingPeriod, $matches)) {
            if (PaidAccessCore::isPersonalBillingPeriodMode($siteId)) {
                $start = \DateTimeImmutable::createFromFormat('Y-m-d', $billingPeriod);
                if ($start !== false) {
                    $anchorDay = (int)$matches[3];
                    $end = self::addMonthsKeepingAnchor($start, 1, $anchorDay, $siteId)->modify('-1 day');

                    return $start->format('d.m.Y') . ' — ' . $end->format('d.m.Y');
                }
            }
        }

        return $billingPeriod;
    }

    public static function getBillingPeriodFormatHint(?string $siteId = null): string
    {
        return PaidAccessCore::isPersonalBillingPeriodMode($siteId) ? 'YYYY-MM-DD' : 'YYYY-MM';
    }

    public static function getBillingPeriodInputPlaceholder(?string $siteId = null): string
    {
        return PaidAccessCore::isPersonalBillingPeriodMode($siteId) ? '2026-06-15' : '2026-06';
    }

    public static function getBillingPeriodInputPattern(?string $siteId = null): string
    {
        return PaidAccessCore::isPersonalBillingPeriodMode($siteId)
            ? '\d{4}-\d{2}(-\d{2})?'
            : '\d{4}-\d{2}';
    }

    /**
     * Нормализация ввода периода в админке (YYYY-MM → YYYY-MM-DD для персонального режима).
     */
    public static function normalizeBillingPeriodInput(string $period, int $userId, ?string $siteId = null): string
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $period = trim($period);

        if ($period === '' || !PaidAccessCore::isPersonalBillingPeriodMode($siteId)) {
            return $period;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $period)) {
            return $period;
        }

        if ($userId > 0 && preg_match('/^(\d{4})-(\d{2})$/', $period, $matches)) {
            $year = (int)$matches[1];
            $month = (int)$matches[2];
            $anchorDay = self::resolveBillingDay($userId, $siteId);
            $day = self::clampDayToMonth($anchorDay, $year, $month, $siteId);

            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        return $period;
    }

    /**
     * @throws \RuntimeException
     */
    public static function assertValidBillingPeriod(string $billingPeriod, ?string $siteId = null): void
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);

        if (PaidAccessCore::isPersonalBillingPeriodMode($siteId)) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $billingPeriod)) {
                throw new \RuntimeException('Период должен быть в формате YYYY-MM-DD');
            }

            return;
        }

        if (!preg_match('/^\d{4}-\d{2}$/', $billingPeriod)) {
            throw new \RuntimeException('Период должен быть в формате YYYY-MM');
        }
    }

    protected static function getCurrentAnchorPeriodStart(
        int $userId,
        \DateTimeImmutable $at,
        ?string $siteId = null
    ): \DateTimeImmutable {
        $anchorDay = self::resolveBillingDay($userId, $siteId);
        $year = (int)$at->format('Y');
        $month = (int)$at->format('m');
        $candidate = self::buildAnchorDate($year, $month, $anchorDay, $siteId);

        if ($at < $candidate) {
            return self::addMonthsKeepingAnchor($candidate, -1, $anchorDay, $siteId);
        }

        return $candidate;
    }

    protected static function findNextBillingAnchor(
        \DateTimeImmutable $from,
        int $anchorDay,
        ?string $siteId = null
    ): \DateTimeImmutable {
        $year = (int)$from->format('Y');
        $month = (int)$from->format('m');
        $candidate = self::buildAnchorDate($year, $month, $anchorDay, $siteId);

        if ($from <= $candidate) {
            return $candidate;
        }

        return self::addMonthsKeepingAnchor($candidate, 1, $anchorDay, $siteId);
    }

    protected static function buildAnchorDate(
        int $year,
        int $month,
        int $anchorDay,
        ?string $siteId = null
    ): \DateTimeImmutable {
        $day = self::clampDayToMonth($anchorDay, $year, $month, $siteId);

        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    protected static function addMonthsKeepingAnchor(
        \DateTimeImmutable $date,
        int $months,
        int $anchorDay,
        ?string $siteId = null
    ): \DateTimeImmutable {
        $target = $date->modify(($months >= 0 ? '+' : '') . $months . ' months');
        $year = (int)$target->format('Y');
        $month = (int)$target->format('m');
        $day = self::clampDayToMonth($anchorDay, $year, $month, $siteId);

        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    /**
     * Bitrix\Main\Type\DateTime не наследует PHP \DateTime — конвертируем через timestamp.
     *
     * @param DateTime|\DateTimeInterface|\DateTimeImmutable $dateTime
     */
    protected static function toImmutable($dateTime): \DateTimeImmutable
    {
        if ($dateTime instanceof \DateTimeImmutable) {
            return $dateTime;
        }

        if ($dateTime instanceof DateTime) {
            return (new \DateTimeImmutable())->setTimestamp($dateTime->getTimestamp());
        }

        if ($dateTime instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($dateTime);
        }

        return new \DateTimeImmutable();
    }

    protected static function clampDayToMonth(int $day, int $year, int $month, ?string $siteId = null): int
    {
        $siteId = PaidAccessCore::normalizeSiteId($siteId);
        $daysInMonth = (int)date('t', mktime(0, 0, 0, $month, 1, $year));

        if ($day <= $daysInMonth) {
            return $day;
        }

        if (PaidAccessCore::getBillingShortMonthPolicy($siteId) === PaidAccessCore::BILLING_SHORT_MONTH_PREVIOUS) {
            return max(1, $daysInMonth - 1);
        }

        return $daysInMonth;
    }
}
