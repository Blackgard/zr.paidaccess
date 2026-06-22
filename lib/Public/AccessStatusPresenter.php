<?php

namespace Zr\PaidAccess\PublicUi;

use Zr\PaidAccess\Access\SubscriberAccessService;

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
            SubscriberAccessService::ACCESS_ACTIVE => 'Доступ активен',
            SubscriberAccessService::ACCESS_PENDING => 'Ожидает оплаты',
            SubscriberAccessService::ACCESS_UNPAID => 'Не оплачено',
            SubscriberAccessService::ACCESS_DEBT => 'Просрочена оплата',
            SubscriberAccessService::ACCESS_FAILED => 'Ошибка оплаты',
            SubscriberAccessService::ACCESS_EXPIRED => 'Подписка истекла',
            SubscriberAccessService::ACCESS_EXEMPT => 'Без проверки',
            SubscriberAccessService::ACCESS_ADMIN => 'Администратор',
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
            SubscriberAccessService::ACCESS_DEBT,
            SubscriberAccessService::ACCESS_FAILED,
            SubscriberAccessService::ACCESS_EXPIRED,
            SubscriberAccessService::ACCESS_UNPAID,
        ], true);
    }

    public static function getRowCssClass(string $accessStatus): string
    {
        return self::isDebtHighlight($accessStatus) ? 'zr-paidaccess-row--debt' : '';
    }

    public static function getBadgeCssClass(string $accessStatus): string
    {
        if ($accessStatus === SubscriberAccessService::ACCESS_ACTIVE) {
            return 'zr-paidaccess-badge--ok';
        }
        if ($accessStatus === SubscriberAccessService::ACCESS_PENDING) {
            return 'zr-paidaccess-badge--pending';
        }
        if (self::isDebtHighlight($accessStatus)) {
            return 'zr-paidaccess-badge--debt';
        }
        if ($accessStatus === SubscriberAccessService::ACCESS_EXEMPT || $accessStatus === SubscriberAccessService::ACCESS_ADMIN) {
            return 'zr-paidaccess-badge--muted';
        }

        return 'zr-paidaccess-badge--default';
    }

    public static function getSortPriority(string $accessStatus): int
    {
        $map = [
            SubscriberAccessService::ACCESS_DEBT => 10,
            SubscriberAccessService::ACCESS_FAILED => 20,
            SubscriberAccessService::ACCESS_EXPIRED => 30,
            SubscriberAccessService::ACCESS_UNPAID => 40,
            SubscriberAccessService::ACCESS_PENDING => 50,
            SubscriberAccessService::ACCESS_ACTIVE => 60,
            SubscriberAccessService::ACCESS_EXEMPT => 70,
            SubscriberAccessService::ACCESS_ADMIN => 80,
        ];

        return $map[$accessStatus] ?? 90;
    }
}
