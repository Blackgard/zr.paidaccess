<?php

/**
 * @link https://alexandr-zr.ru/
 * @author Alexandr Drachenin <alexdrachenin98@gmail.com>
 * @copyright Copyright (c) 2020-2026 ZR studio (https://alexandr-zr.ru/)
 */

namespace Zr\PaidAccess;

use Bitrix\Main\Config\Option;

/**
 * Только чтение/запись опций модуля (суффикс _{SITE_ID}).
 * Бизнес-логика доступа и подписки — в Zr\PaidAccess\Access\*.
 */
class PaidAccessCore
{
    public const MODULE_ID = 'zr.paidaccess';
    public const OPTION_MODULE_ACTIVE = 'MODULE_ACTIVE';
    public const OPTION_ACCESS_RESTRICTED_GROUPS = 'ACCESS_RESTRICTED_GROUPS';
    public const OPTION_ACCESS_BLOCK_TEMPLATE = 'ACCESS_BLOCK_TEMPLATE';
    public const OPTION_SUBSCRIPTION_AMOUNT = 'SUBSCRIPTION_AMOUNT';
    /** Режим расчётного периода: calendar_month | anchor_month | registration */
    public const OPTION_BILLING_PERIOD_MODE = 'BILLING_PERIOD_MODE';
    /** Источник дня оплаты: registration | fixed */
    public const OPTION_BILLING_ANCHOR_SOURCE = 'BILLING_ANCHOR_SOURCE';
    /** Фиксированный день оплаты (1–31), если BILLING_ANCHOR_SOURCE = fixed */
    public const OPTION_BILLING_FIXED_DAY = 'BILLING_FIXED_DAY';
    /** Политика для коротких месяцев при дне 29–31: last_day | previous */
    public const OPTION_BILLING_SHORT_MONTH_POLICY = 'BILLING_SHORT_MONTH_POLICY';
    /** не более одной успешной оплаты за период */
    public const OPTION_BILLING_ENFORCE_ONE_PAYMENT = 'BILLING_ENFORCE_ONE_PAYMENT';
    /** Льготные дни после срока оплаты до блокировки */
    public const OPTION_BILLING_GRACE_DAYS = 'BILLING_GRACE_DAYS';
    /** Информационное письмо с сайта после оплаты (не фискальный чек) */
    public const OPTION_PAYMENT_EMAIL_NOTIFY = 'PAYMENT_EMAIL_NOTIFY';
    /** Текст ошибки на странице оплаты (для пользователя) */
    public const OPTION_PAYMENT_PAGE_ERROR_TEXT = 'PAYMENT_PAGE_ERROR_TEXT';
    /** Способ оплаты на сайте: qr_sbp | payment_button */
    public const OPTION_PAYMENT_WIDGET_MODE = 'PAYMENT_WIDGET_MODE';
    /** Назначение платежа в банке; поддерживает плейсхолдер {SITE_NAME} */
    public const OPTION_PAYMENT_DESCRIPTION = 'PAYMENT_DESCRIPTION';
    /** Письмо пользователю при ошибке оплаты */
    public const OPTION_MAIL_NOTIFY_PAYMENT_FAILED = 'MAIL_NOTIFY_PAYMENT_FAILED';
    /** Письмо при переходе в статус «долг» */
    public const OPTION_MAIL_NOTIFY_SUBSCRIPTION_DEBT = 'MAIL_NOTIFY_SUBSCRIPTION_DEBT';
    /** Напоминание об окончании оплаченного периода */
    public const OPTION_MAIL_NOTIFY_SUBSCRIPTION_EXPIRING = 'MAIL_NOTIFY_SUBSCRIPTION_EXPIRING';
    /** За сколько дней до PERIOD_END отправлять напоминание */
    public const OPTION_MAIL_SUBSCRIPTION_EXPIRING_DAYS = 'MAIL_SUBSCRIPTION_EXPIRING_DAYS';
    /** Уведомлять администратора об ошибках модуля */
    public const OPTION_ERROR_NOTIFY_ENABLED = 'ERROR_NOTIFY_ENABLED';
    /** Email администратора для ошибок (через запятую) */
    public const OPTION_ERROR_NOTIFY_EMAIL = 'ERROR_NOTIFY_EMAIL';
    public const OPTION_LOGGING_ACTIVE = 'LOGGING_ACTIVE';
    public const OPTION_LOG_PATH = 'LOG_PATH';
    public const OPTION_LOG_LEVEL = 'LOG_LEVEL';
    /** Сумма тестового платежа шлюза (руб.), для проверки эквайринга */
    public const OPTION_GATEWAY_TEST_AMOUNT = 'GATEWAY_TEST_AMOUNT';
    public const MAIL_EVENT_PAYMENT_PAID = 'ZR_PAIDACCESS_PAYMENT_PAID';
    public const MAIL_EVENT_PAYMENT_FAILED = 'ZR_PAIDACCESS_PAYMENT_FAILED';
    public const MAIL_EVENT_SUBSCRIPTION_DEBT = 'ZR_PAIDACCESS_SUBSCRIPTION_DEBT';
    public const MAIL_EVENT_SUBSCRIPTION_EXPIRING = 'ZR_PAIDACCESS_SUBSCRIPTION_EXPIRING';
    public const MAIL_EVENT_ADMIN_ERROR = 'ZR_PAIDACCESS_ADMIN_ERROR';

    public const BILLING_PERIOD_MODE_CALENDAR_MONTH = 'calendar_month';
    public const BILLING_PERIOD_MODE_ANCHOR_MONTH = 'anchor_month';
    /** Персональный период; день срока оплаты всегда от даты регистрации */
    public const BILLING_PERIOD_MODE_REGISTRATION = 'registration';
    public const BILLING_ANCHOR_SOURCE_REGISTRATION = 'registration';
    public const BILLING_ANCHOR_SOURCE_FIXED = 'fixed';
    public const BILLING_SHORT_MONTH_LAST_DAY = 'last_day';
    public const BILLING_SHORT_MONTH_PREVIOUS = 'previous';

    public const DEFAULT_SUBSCRIPTION_AMOUNT = '1000';
    public const DEFAULT_BILLING_PERIOD_MODE = self::BILLING_PERIOD_MODE_CALENDAR_MONTH;
    public const DEFAULT_BILLING_ANCHOR_SOURCE = self::BILLING_ANCHOR_SOURCE_REGISTRATION;
    public const DEFAULT_BILLING_FIXED_DAY = '1';
    public const DEFAULT_BILLING_SHORT_MONTH_POLICY = self::BILLING_SHORT_MONTH_LAST_DAY;
    public const DEFAULT_BILLING_ENFORCE_ONE_PAYMENT = 'Y';
    public const DEFAULT_BILLING_GRACE_DAYS = '0';
    public const DEFAULT_PAYMENT_EMAIL_NOTIFY = 'Y';
    public const DEFAULT_PAYMENT_PAGE_ERROR_TEXT = 'Не удалось сформировать платёж. Пожалуйста, свяжитесь с администрацией сайта или попробуйте позже.';
    public const PAYMENT_WIDGET_MODE_QR_SBP = 'qr_sbp';
    public const PAYMENT_WIDGET_MODE_PAYMENT_BUTTON = 'payment_button';
    public const DEFAULT_PAYMENT_WIDGET_MODE = self::PAYMENT_WIDGET_MODE_QR_SBP;
    public const DEFAULT_PAYMENT_DESCRIPTION = 'Ежемесячный взнос подписки — {SITE_NAME}';
    public const DEFAULT_MAIL_NOTIFY_PAYMENT_FAILED = 'Y';
    public const DEFAULT_MAIL_NOTIFY_SUBSCRIPTION_DEBT = 'Y';
    public const DEFAULT_MAIL_NOTIFY_SUBSCRIPTION_EXPIRING = 'Y';
    public const DEFAULT_MAIL_SUBSCRIPTION_EXPIRING_DAYS = '3';
    public const DEFAULT_ERROR_NOTIFY_ENABLED = 'Y';
    public const DEFAULT_ERROR_NOTIFY_EMAIL = '';
    public const DEFAULT_LOGGING_ACTIVE = 'N';
    public const DEFAULT_LOG_PATH = '/upload/logs/zr.paidaccess.log';
    public const DEFAULT_LOG_LEVEL = 'error';
    public const DEFAULT_GATEWAY_TEST_AMOUNT = '1';
    public const TEMPLATES_RELATIVE_PATH = '/local/php_interface/zr.paidaccess';
    public const DEFAULT_BLOCK_TEMPLATE = 'template_need_paid.php';

    /** Группы по умолчанию для проверки подписки (ID через запятую) */
    public const DEFAULT_ACCESS_RESTRICTED_GROUPS = '2';

    public static function normalizeSiteId(?string $siteId = null): string
    {
        $siteId = $siteId ?? (defined('SITE_ID') ? (string) SITE_ID : 's1');
        if ($siteId === 'ru') {
            return 's1';
        }

        return $siteId;
    }

    public static function getOptionByCode(string $code, $default = '', $siteId = null): string
    {
        $siteLid = self::normalizeSiteId($siteId);

        return Option::get(self::MODULE_ID, $code . '_' . $siteLid, (string) $default, $siteLid);
    }

    public static function isModuleActive(?string $siteId = null): bool
    {
        return self::getOptionByCode(self::OPTION_MODULE_ACTIVE, 'Y', $siteId) === 'Y';
    }

    /**
     * @return int[]
     */
    public static function getAccessRestrictedGroupIds(?string $siteId = null): array
    {
        $raw = self::getOptionByCode(self::OPTION_ACCESS_RESTRICTED_GROUPS, self::DEFAULT_ACCESS_RESTRICTED_GROUPS, $siteId);
        if ($raw === '') {
            return [];
        }
        $ids = preg_split('/[,\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public static function getAccessBlockTemplate(?string $siteId = null): string
    {
        return self::getOptionByCode(
            self::OPTION_ACCESS_BLOCK_TEMPLATE,
            self::DEFAULT_BLOCK_TEMPLATE,
            $siteId
        );
    }

    public static function getSubscriptionAmount(?string $siteId = null): float
    {
        $raw = self::getOptionByCode(self::OPTION_SUBSCRIPTION_AMOUNT, self::DEFAULT_SUBSCRIPTION_AMOUNT, $siteId);
        $amount = (float)str_replace(',', '.', $raw);

        return $amount > 0 ? $amount : (float)self::DEFAULT_SUBSCRIPTION_AMOUNT;
    }

    public static function getBillingPeriodMode(?string $siteId = null): string
    {
        $mode = self::getOptionByCode(
            self::OPTION_BILLING_PERIOD_MODE,
            self::DEFAULT_BILLING_PERIOD_MODE,
            $siteId
        );

        $allowed = [
            self::BILLING_PERIOD_MODE_CALENDAR_MONTH,
            self::BILLING_PERIOD_MODE_ANCHOR_MONTH,
            self::BILLING_PERIOD_MODE_REGISTRATION,
        ];

        if (in_array($mode, $allowed, true)) {
            return $mode;
        }

        return self::BILLING_PERIOD_MODE_CALENDAR_MONTH;
    }

    /**
     * Персональный расчётный период (ключ YYYY-MM-DD), в отличие от календарного месяца.
     */
    public static function isPersonalBillingPeriodMode(?string $siteId = null): bool
    {
        return in_array(self::getBillingPeriodMode($siteId), [
            self::BILLING_PERIOD_MODE_ANCHOR_MONTH,
            self::BILLING_PERIOD_MODE_REGISTRATION,
        ], true);
    }

    public static function isRegistrationBillingPeriodMode(?string $siteId = null): bool
    {
        return self::getBillingPeriodMode($siteId) === self::BILLING_PERIOD_MODE_REGISTRATION;
    }

    public static function getBillingAnchorSource(?string $siteId = null): string
    {
        $source = self::getOptionByCode(
            self::OPTION_BILLING_ANCHOR_SOURCE,
            self::DEFAULT_BILLING_ANCHOR_SOURCE,
            $siteId
        );

        return $source === self::BILLING_ANCHOR_SOURCE_FIXED
            ? self::BILLING_ANCHOR_SOURCE_FIXED
            : self::BILLING_ANCHOR_SOURCE_REGISTRATION;
    }

    public static function getBillingFixedDay(?string $siteId = null): int
    {
        $raw = (int)self::getOptionByCode(self::OPTION_BILLING_FIXED_DAY, self::DEFAULT_BILLING_FIXED_DAY, $siteId);

        return max(1, min(31, $raw));
    }

    public static function getBillingShortMonthPolicy(?string $siteId = null): string
    {
        $policy = self::getOptionByCode(
            self::OPTION_BILLING_SHORT_MONTH_POLICY,
            self::DEFAULT_BILLING_SHORT_MONTH_POLICY,
            $siteId
        );

        return $policy === self::BILLING_SHORT_MONTH_PREVIOUS
            ? self::BILLING_SHORT_MONTH_PREVIOUS
            : self::BILLING_SHORT_MONTH_LAST_DAY;
    }

    public static function isBillingEnforceOnePayment(?string $siteId = null): bool
    {
        return self::getOptionByCode(
            self::OPTION_BILLING_ENFORCE_ONE_PAYMENT,
            self::DEFAULT_BILLING_ENFORCE_ONE_PAYMENT,
            $siteId
        ) === 'Y';
    }

    public static function getBillingGraceDays(?string $siteId = null): int
    {
        $raw = (int)self::getOptionByCode(self::OPTION_BILLING_GRACE_DAYS, self::DEFAULT_BILLING_GRACE_DAYS, $siteId);

        return max(0, $raw);
    }

    public static function isPaymentEmailNotifyEnabled(?string $siteId = null): bool
    {
        return self::getOptionByCode(
            self::OPTION_PAYMENT_EMAIL_NOTIFY,
            self::DEFAULT_PAYMENT_EMAIL_NOTIFY,
            $siteId
        ) === 'Y';
    }

    public static function getPaymentPageErrorText(?string $siteId = null): string
    {
        $text = trim(self::getOptionByCode(
            self::OPTION_PAYMENT_PAGE_ERROR_TEXT,
            self::DEFAULT_PAYMENT_PAGE_ERROR_TEXT,
            $siteId
        ));

        return $text !== '' ? $text : self::DEFAULT_PAYMENT_PAGE_ERROR_TEXT;
    }

    public static function getSiteDisplayName(?string $siteId = null): string
    {
        $siteId = self::normalizeSiteId($siteId);

        if (class_exists(\CSite::class)) {
            $site = \CSite::GetByID($siteId)->Fetch();
            if (is_array($site)) {
                $name = trim((string)($site['NAME'] ?? ''));
                if ($name !== '') {
                    return $name;
                }

                $serverName = trim((string)($site['SERVER_NAME'] ?? ''));
                if ($serverName !== '') {
                    return $serverName;
                }
            }
        }

        return 'Сайт';
    }

    public static function getPaymentDescription(?string $siteId = null): string
    {
        $template = trim(self::getOptionByCode(
            self::OPTION_PAYMENT_DESCRIPTION,
            self::DEFAULT_PAYMENT_DESCRIPTION,
            $siteId
        ));

        if ($template === '') {
            $template = self::DEFAULT_PAYMENT_DESCRIPTION;
        }

        return str_replace('{SITE_NAME}', self::getSiteDisplayName($siteId), $template);
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public static function enrichMailFields(array $fields, ?string $siteId = null): array
    {
        $fields['SITE_NAME'] = self::getSiteDisplayName($siteId);

        return $fields;
    }

    public static function getPaymentWidgetMode(?string $siteId = null): string
    {
        $mode = self::getOptionByCode(
            self::OPTION_PAYMENT_WIDGET_MODE,
            self::DEFAULT_PAYMENT_WIDGET_MODE,
            $siteId
        );

        if ($mode === self::PAYMENT_WIDGET_MODE_PAYMENT_BUTTON) {
            return self::PAYMENT_WIDGET_MODE_PAYMENT_BUTTON;
        }

        return self::PAYMENT_WIDGET_MODE_QR_SBP;
    }

    public static function isPaymentWidgetQrMode(?string $siteId = null): bool
    {
        return self::getPaymentWidgetMode($siteId) === self::PAYMENT_WIDGET_MODE_QR_SBP;
    }

    public static function isPaymentWidgetButtonMode(?string $siteId = null): bool
    {
        return self::getPaymentWidgetMode($siteId) === self::PAYMENT_WIDGET_MODE_PAYMENT_BUTTON;
    }

    public static function getGatewayTestAmount(?string $siteId = null): float
    {
        $raw = self::getOptionByCode(self::OPTION_GATEWAY_TEST_AMOUNT, self::DEFAULT_GATEWAY_TEST_AMOUNT, $siteId);
        $amount = (float)str_replace(',', '.', $raw);

        return $amount > 0 ? $amount : (float)self::DEFAULT_GATEWAY_TEST_AMOUNT;
    }

    public static function isMailNotifyPaymentFailedEnabled(?string $siteId = null): bool
    {
        return self::getOptionByCode(
            self::OPTION_MAIL_NOTIFY_PAYMENT_FAILED,
            self::DEFAULT_MAIL_NOTIFY_PAYMENT_FAILED,
            $siteId
        ) === 'Y';
    }

    public static function isMailNotifySubscriptionDebtEnabled(?string $siteId = null): bool
    {
        return self::getOptionByCode(
            self::OPTION_MAIL_NOTIFY_SUBSCRIPTION_DEBT,
            self::DEFAULT_MAIL_NOTIFY_SUBSCRIPTION_DEBT,
            $siteId
        ) === 'Y';
    }

    public static function isMailNotifySubscriptionExpiringEnabled(?string $siteId = null): bool
    {
        return self::getOptionByCode(
            self::OPTION_MAIL_NOTIFY_SUBSCRIPTION_EXPIRING,
            self::DEFAULT_MAIL_NOTIFY_SUBSCRIPTION_EXPIRING,
            $siteId
        ) === 'Y';
    }

    public static function getMailSubscriptionExpiringDays(?string $siteId = null): int
    {
        $raw = (int)self::getOptionByCode(
            self::OPTION_MAIL_SUBSCRIPTION_EXPIRING_DAYS,
            self::DEFAULT_MAIL_SUBSCRIPTION_EXPIRING_DAYS,
            $siteId
        );

        return max(0, $raw);
    }

    public static function isErrorNotifyEnabled(?string $siteId = null): bool
    {
        return self::getOptionByCode(
            self::OPTION_ERROR_NOTIFY_ENABLED,
            self::DEFAULT_ERROR_NOTIFY_ENABLED,
            $siteId
        ) === 'Y';
    }

    public static function getErrorNotifyEmails(?string $siteId = null): string
    {
        return trim(self::getOptionByCode(
            self::OPTION_ERROR_NOTIFY_EMAIL,
            self::DEFAULT_ERROR_NOTIFY_EMAIL,
            $siteId
        ));
    }

    public static function isLoggingActive(?string $siteId = null): bool
    {
        return self::getOptionByCode(
            self::OPTION_LOGGING_ACTIVE,
            self::DEFAULT_LOGGING_ACTIVE,
            $siteId
        ) === 'Y';
    }

    public static function getLogPath(?string $siteId = null): string
    {
        $path = trim(self::getOptionByCode(
            self::OPTION_LOG_PATH,
            self::DEFAULT_LOG_PATH,
            $siteId
        ));

        return $path !== '' ? $path : self::DEFAULT_LOG_PATH;
    }

    public static function getLogLevel(?string $siteId = null): string
    {
        $level = strtolower(self::getOptionByCode(
            self::OPTION_LOG_LEVEL,
            self::DEFAULT_LOG_LEVEL,
            $siteId
        ));

        if (!in_array($level, ['debug', 'info', 'warning', 'error'], true)) {
            return self::DEFAULT_LOG_LEVEL;
        }

        return $level;
    }
}
