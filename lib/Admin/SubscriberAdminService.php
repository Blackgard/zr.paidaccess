<?php

namespace Zr\PaidAccess\Admin;

use Zr\PaidAccess\Access\SubscriberAccessService;
use Zr\PaidAccess\PaidAccessCore;

class SubscriberAdminService extends SubscriberAccessService
{
    /**
     * @return array<string, string>
     */
    public static function getAccessStatusStyles()
    {
        return [
            self::ACCESS_ACTIVE => StatusBadgeRenderer::STYLE_COMPLETED,
            self::ACCESS_PENDING => StatusBadgeRenderer::STYLE_PROGRESS,
            self::ACCESS_UNPAID => StatusBadgeRenderer::STYLE_WARNING,
            self::ACCESS_DEBT => StatusBadgeRenderer::STYLE_DANGER,
            self::ACCESS_FAILED => StatusBadgeRenderer::STYLE_DANGER,
            self::ACCESS_EXPIRED => StatusBadgeRenderer::STYLE_DANGER,
            self::ACCESS_EXEMPT => StatusBadgeRenderer::STYLE_MUTED,
            self::ACCESS_ADMIN => StatusBadgeRenderer::STYLE_INFO,
        ];
    }

    /**
     * @param array<string, mixed> $filterData
     * @return array<string, mixed>
     */
    public static function buildUserFilter(array $filterData)
    {
        $filter = ['=ACTIVE' => 'Y'];

        if (!empty($filterData['USER_ID'])) {
            $filter['=ID'] = (int)$filterData['USER_ID'];
        }

        if (!empty($filterData['LOGIN'])) {
            $filter['%LOGIN'] = $filterData['LOGIN'];
        }

        if (!empty($filterData['EMAIL'])) {
            $filter['%EMAIL'] = $filterData['EMAIL'];
        }

        if (!empty($filterData['NAME'])) {
            $filter[] = [
                'LOGIC' => 'OR',
                ['%NAME' => $filterData['NAME']],
                ['%LAST_NAME' => $filterData['NAME']],
            ];
        }

        $restrictedGroupIds = PaidAccessCore::getAccessRestrictedGroupIds();
        $scope = $filterData['SCOPE'] ?? 'all';

        if ($scope === 'restricted' && $restrictedGroupIds !== []) {
            $filter['@GROUPS.GROUP_ID'] = $restrictedGroupIds;
        }

        return $filter;
    }

    public static function getCreatePaymentUrl($userId, $languageId)
    {
        return 'zr_paidaccess_payment_edit.php?lang=' . urlencode($languageId) . '&USER_ID=' . (int)$userId;
    }
}
