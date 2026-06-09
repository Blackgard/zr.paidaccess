<?php

namespace Zr\PaidAccess\Enum;

final class NotificationType
{
    public const PAYMENT_PAID = 'payment_paid';
    public const PAYMENT_FAILED = 'payment_failed';
    public const SUBSCRIPTION_DEBT = 'subscription_debt';
    public const SUBSCRIPTION_EXPIRING = 'subscription_expiring';
    public const ADMIN_ERROR = 'admin_error';
    /** Дедупликация записей в журнале событий (один раз на инцидент). */
    public const EVENT_LOG = 'event_log';
}
