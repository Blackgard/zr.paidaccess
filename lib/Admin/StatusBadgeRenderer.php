<?php

namespace Zr\PaidAccess\Admin;

use Zr\PaidAccess\Enum\PaymentStatus;

/**
 * Единые цветные бейджи статусов в админке.
 */
class StatusBadgeRenderer
{
    public const STYLE_COMPLETED = 'status-completed';
    public const STYLE_PROGRESS = 'status-progress';
    public const STYLE_WARNING = 'status-warning';
    public const STYLE_DANGER = 'status-danger';
    public const STYLE_MUTED = 'status-muted';
    public const STYLE_INFO = 'status-info';

    public static function render($text, $styleClass)
    {
        return '<div class="status-container"><div class="status-badge ' . htmlspecialcharsbx($styleClass) . '">'
            . '<span class="status-icon"></span>'
            . htmlspecialcharsbx($text)
            . '</div></div>';
    }

    public static function renderYesNo($isYes, $yesLabel, $noLabel)
    {
        return self::render(
            $isYes ? $yesLabel : $noLabel,
            $isYes ? self::STYLE_COMPLETED : self::STYLE_PROGRESS
        );
    }

    public static function renderPaymentStatus($status)
    {
        $status = (string)$status;
        $map = [
            PaymentStatus::PAID => [PaymentAdminService::getStatusTitle($status), self::STYLE_COMPLETED],
            PaymentStatus::AUTHORIZED => [PaymentAdminService::getStatusTitle($status), self::STYLE_INFO],
            PaymentStatus::PENDING => [PaymentAdminService::getStatusTitle($status), self::STYLE_PROGRESS],
            PaymentStatus::FAILED => [PaymentAdminService::getStatusTitle($status), self::STYLE_DANGER],
            PaymentStatus::REFUNDED => [PaymentAdminService::getStatusTitle($status), self::STYLE_MUTED],
            PaymentStatus::CANCELLED => [PaymentAdminService::getStatusTitle($status), self::STYLE_WARNING],
        ];

        if (isset($map[$status])) {
            return self::render($map[$status][0], $map[$status][1]);
        }

        return self::render($status !== '' ? $status : '—', self::STYLE_MUTED);
    }

    public static function renderAccessStatus($accessStatus)
    {
        $titles = SubscriberAdminService::getAccessStatusTitles();
        $styles = SubscriberAdminService::getAccessStatusStyles();
        $title = isset($titles[$accessStatus]) ? $titles[$accessStatus] : $accessStatus;
        $style = isset($styles[$accessStatus]) ? $styles[$accessStatus] : self::STYLE_MUTED;

        return self::render($title, $style);
    }
}
