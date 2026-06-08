<?php

namespace Zr\PaidAccess\PublicUi;

use Zr\PaidAccess\Admin\SubscriberAdminService;

/**
 * Подписи и CSS-классы статуса доступа для публичных компонентов.
 */
class AccessStatusPresenter
{
    /**
     * @return array<string, string>
     */
    public static function getPublicLabels(): array
    {
        return [
            SubscriberAdminService::ACCESS_ACTIVE => 'Доступ активен',
            SubscriberAdminService::ACCESS_PENDING => 'Ожидает оплаты',
            SubscriberAdminService::ACCESS_UNPAID => 'Не оплачено',
            SubscriberAdminService::ACCESS_DEBT => 'Просрочена оплата',
            SubscriberAdminService::ACCESS_FAILED => 'Ошибка оплаты',
            SubscriberAdminService::ACCESS_EXPIRED => 'Подписка истекла',
            SubscriberAdminService::ACCESS_EXEMPT => 'Без проверки',
            SubscriberAdminService::ACCESS_ADMIN => 'Администратор',
        ];
    }

    public static function getPublicLabel(string $accessStatus): string
    {
        $labels = self::getPublicLabels();

        return $labels[$accessStatus] ?? $accessStatus;
    }

    public static function isDebtHighlight(string $accessStatus): bool
    {
        return in_array($accessStatus, [
            SubscriberAdminService::ACCESS_DEBT,
            SubscriberAdminService::ACCESS_FAILED,
            SubscriberAdminService::ACCESS_EXPIRED,
            SubscriberAdminService::ACCESS_UNPAID,
        ], true);
    }

    public static function getRowCssClass(string $accessStatus): string
    {
        return self::isDebtHighlight($accessStatus) ? 'zr-paidaccess-row--debt' : '';
    }

    public static function getBadgeCssClass(string $accessStatus): string
    {
        if ($accessStatus === SubscriberAdminService::ACCESS_ACTIVE) {
            return 'zr-paidaccess-badge--ok';
        }
        if ($accessStatus === SubscriberAdminService::ACCESS_PENDING) {
            return 'zr-paidaccess-badge--pending';
        }
        if (self::isDebtHighlight($accessStatus)) {
            return 'zr-paidaccess-badge--debt';
        }
        if ($accessStatus === SubscriberAdminService::ACCESS_EXEMPT || $accessStatus === SubscriberAdminService::ACCESS_ADMIN) {
            return 'zr-paidaccess-badge--muted';
        }

        return 'zr-paidaccess-badge--default';
    }

    public static function getSortPriority(string $accessStatus): int
    {
        $map = [
            SubscriberAdminService::ACCESS_DEBT => 10,
            SubscriberAdminService::ACCESS_FAILED => 20,
            SubscriberAdminService::ACCESS_EXPIRED => 30,
            SubscriberAdminService::ACCESS_UNPAID => 40,
            SubscriberAdminService::ACCESS_PENDING => 50,
            SubscriberAdminService::ACCESS_ACTIVE => 60,
            SubscriberAdminService::ACCESS_EXEMPT => 70,
            SubscriberAdminService::ACCESS_ADMIN => 80,
        ];

        return $map[$accessStatus] ?? 90;
    }
}
