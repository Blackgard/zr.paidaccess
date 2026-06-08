<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Grid;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UI\Filter\Options as FilterOptions;
use Bitrix\Main\UI\PageNavigation;
use Bitrix\Main\UserTable;
use Zr\PaidAccess\Admin\StatusBadgeRenderer;
use Zr\PaidAccess\Admin\SubscriberAdminService;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Payment\SubscriptionPaymentService;
use Zr\PaidAccess\Subscription\BillingPolicy;

$moduleId = PaidAccessCore::MODULE_ID;

Loader::includeModule($moduleId);
Loc::loadMessages(__FILE__);

$POST_RIGHT = $APPLICATION->GetGroupRight($moduleId);
if ($POST_RIGHT < 'R') {
    $APPLICATION->AuthForm(GetMessage('ACCESS_DENIED'));
}

\Bitrix\Main\Page\Asset::getInstance()->addCss('/bitrix/themes/.default/zr.paidaccess.css');

$APPLICATION->SetTitle(Loc::getMessage('ZR_PAIDACCESS_SUBSCRIBERS_TITLE'));

$gridId = 'zr_paidaccess_subscribers_list';
$gridOptions = new Grid\Options($gridId);
$sort = $gridOptions->GetSorting(['sort' => ['ID' => 'ASC'], 'vars' => ['by' => 'by', 'order' => 'order']]);
$navParams = $gridOptions->GetNavParams();

$billingPeriod = SubscriptionPaymentService::getCurrentBillingPeriod();
$accessStatusFilterItems = SubscriberAdminService::getAccessStatusTitles();

$filterFields = [
    [
        'id' => 'SCOPE',
        'name' => Loc::getMessage('ZR_PAIDACCESS_FILTER_SCOPE'),
        'type' => 'list',
        'items' => [
            'all' => Loc::getMessage('ZR_PAIDACCESS_SCOPE_ALL'),
            'restricted' => Loc::getMessage('ZR_PAIDACCESS_SCOPE_RESTRICTED'),
        ],
        'default' => true,
    ],
    ['id' => 'USER_ID', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_ID'), 'type' => 'number', 'default' => true],
    ['id' => 'LOGIN', 'name' => Loc::getMessage('ZR_PAIDACCESS_FILTER_LOGIN'), 'type' => 'string', 'default' => true],
    ['id' => 'EMAIL', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_EMAIL'), 'type' => 'string', 'default' => true],
    ['id' => 'NAME', 'name' => Loc::getMessage('ZR_PAIDACCESS_FILTER_NAME'), 'type' => 'string', 'default' => true],
    [
        'id' => 'ACCESS_STATUS',
        'name' => Loc::getMessage('ZR_PAIDACCESS_FILTER_ACCESS'),
        'type' => 'list',
        'items' => $accessStatusFilterItems,
        'params' => ['multiple' => 'N'],
        'default' => true,
    ],
];

$filterOption = new FilterOptions($gridId);
$filterData = $filterOption->getFilter($filterFields);

if (($filterData['SCOPE'] ?? '') === '') {
    $filterData['SCOPE'] = 'all';
}

$userFilter = SubscriberAdminService::buildUserFilter($filterData);
$accessStatusFilter = (string)($filterData['ACCESS_STATUS'] ?? '');

$columns = [
    ['id' => 'ID', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_ID'), 'sort' => 'ID', 'default' => true],
    ['id' => 'USER', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_USER'), 'sort' => 'LAST_NAME', 'default' => true],
    ['id' => 'EMAIL', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_EMAIL'), 'sort' => 'EMAIL', 'default' => true],
    ['id' => 'ACCESS_STATUS', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_ACCESS'), 'sort' => false, 'default' => true],
    ['id' => 'PAYMENT_STATUS', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_PAYMENT'), 'sort' => false, 'default' => true],
    ['id' => 'BILLING_PERIOD', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_PERIOD'), 'sort' => false, 'default' => true],
    ['id' => 'PERIOD_END', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_SUB_UNTIL'), 'sort' => false, 'default' => true],
    ['id' => 'LAST_PAYMENT', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_LAST_PAYMENT'), 'sort' => false, 'default' => false],
];

/**
 * @return array<string, mixed>
 */
$buildRow = static function (array $user, array $subscriptions, array $payments, array $lastPaidPayments) use ($billingPeriod) {
    $userPeriod = SubscriptionPaymentService::getCurrentBillingPeriod((int)$user['ID']);
    $userPeriodLabel = BillingPolicy::formatPeriodLabel($userPeriod);
    $userId = (int)$user['ID'];
    $subscription = $subscriptions[$userId] ?? null;
    $payment = $payments[$userId] ?? null;
    $accessStatus = SubscriberAdminService::resolveAccessStatus($userId, $subscription, $payment);

    $userName = SubscriberAdminService::formatUserName($user);
    $createPaymentUrl = SubscriberAdminService::getCreatePaymentUrl($userId, LANGUAGE_ID);
    $userEditUrl = '/bitrix/admin/user_edit.php?ID=' . $userId . '&lang=' . LANGUAGE_ID;
    $paymentsUrl = 'zr_paidaccess_payments.php?lang=' . LANGUAGE_ID . '&apply_filter=Y&USER_ID=' . $userId;

    $userHtml = '<a href="' . htmlspecialcharsbx($createPaymentUrl) . '" title="'
        . htmlspecialcharsbx(Loc::getMessage('ZR_PAIDACCESS_ACTION_CREATE_PAYMENT')) . '">'
        . htmlspecialcharsbx($userName) . '</a>'
        . ' <a href="' . htmlspecialcharsbx($userEditUrl) . '" target="_blank" style="opacity:0.5;font-size:11px;">#'
        . $userId . '</a>';

    $paymentStatusHtml = $payment
        ? StatusBadgeRenderer::renderPaymentStatus((string)$payment['STATUS'])
        : StatusBadgeRenderer::render(Loc::getMessage('ZR_PAIDACCESS_PAYMENT_NONE'), StatusBadgeRenderer::STYLE_MUTED);

    $lastPaymentHtml = '—';
    if (is_array($payment)) {
        $lastPaymentHtml = '<a href="zr_paidaccess_payment_edit.php?ID=' . (int)$payment['ID']
            . '&lang=' . LANGUAGE_ID . '">' . (int)$payment['ID'] . '</a>';
    } elseif (is_array($subscription) && (int)($subscription['LAST_PAYMENT_ID'] ?? 0) > 0) {
        $lpId = (int)$subscription['LAST_PAYMENT_ID'];
        $lastPaymentHtml = '<a href="zr_paidaccess_payment_edit.php?ID=' . $lpId . '&lang=' . LANGUAGE_ID . '">' . $lpId . '</a>';
    }

    return [
        'id' => $userId,
        'access_status' => $accessStatus,
        'columns' => [
            'ID' => $userId,
            'USER' => $userHtml,
            'EMAIL' => htmlspecialcharsbx((string)($user['EMAIL'] ?? '')),
            'ACCESS_STATUS' => StatusBadgeRenderer::renderAccessStatus($accessStatus),
            'PAYMENT_STATUS' => $paymentStatusHtml,
            'BILLING_PERIOD' => htmlspecialcharsbx($userPeriodLabel),
            'PERIOD_END' => htmlspecialcharsbx(SubscriberAdminService::formatPeriodEnd(
                SubscriberAdminService::resolveDisplayPeriodEnd(
                    $userId,
                    $subscription,
                    $payment,
                    $lastPaidPayments[$userId] ?? null
                )
            )),
            'LAST_PAYMENT' => $lastPaymentHtml,
        ],
        'actions' => [
            [
                'text' => Loc::getMessage('ZR_PAIDACCESS_ACTION_CREATE_PAYMENT'),
                'default' => true,
                'href' => $createPaymentUrl,
            ],
            [
                'text' => Loc::getMessage('ZR_PAIDACCESS_ACTION_PAYMENTS'),
                'href' => $paymentsUrl,
            ],
            [
                'text' => GetMessage('MAIN_EDIT'),
                'href' => $userEditUrl,
            ],
        ],
    ];
};

$rows = [];

if ($accessStatusFilter !== '') {
    $userResult = UserTable::getList([
        'filter' => $userFilter,
        'select' => ['ID', 'LOGIN', 'NAME', 'LAST_NAME', 'EMAIL'],
        'order' => $sort['sort'],
    ]);

    $allUsers = [];
    $allUserIds = [];
    while ($user = $userResult->fetch()) {
        $allUsers[] = $user;
        $allUserIds[] = (int)$user['ID'];
    }

    $subscriptions = SubscriberAdminService::loadSubscriptionsByUserIds($allUserIds);
    $payments = SubscriberAdminService::loadCurrentPeriodPaymentsByUserIds($allUserIds, $billingPeriod);
    $lastPaidPayments = SubscriberAdminService::loadLastPaidPaymentsByUserIds($allUserIds);

    $matched = [];
    foreach ($allUsers as $user) {
        $row = $buildRow($user, $subscriptions, $payments, $lastPaidPayments);
        if ($row['access_status'] !== $accessStatusFilter) {
            continue;
        }
        unset($row['access_status']);
        $matched[] = $row;
    }

    $nav = new PageNavigation($gridId);
    $nav->allowAllRecords(true)
        ->setPageSize($navParams['nPageSize'])
        ->initFromUri();
    $nav->setRecordCount(count($matched));

    $rows = array_slice($matched, $nav->getOffset(), $nav->getLimit());
} else {
    $nav = new PageNavigation($gridId);
    $nav->allowAllRecords(true)
        ->setPageSize($navParams['nPageSize'])
        ->initFromUri();

    $userResult = UserTable::getList([
        'filter' => $userFilter,
        'select' => ['ID', 'LOGIN', 'NAME', 'LAST_NAME', 'EMAIL'],
        'order' => $sort['sort'],
        'count_total' => true,
        'offset' => $nav->getOffset(),
        'limit' => $nav->getLimit(),
    ]);

    $nav->setRecordCount($userResult->getCount());

    $pageUserIds = [];
    $pageUsers = [];
    while ($user = $userResult->fetch()) {
        $pageUsers[] = $user;
        $pageUserIds[] = (int)$user['ID'];
    }

    $subscriptions = SubscriberAdminService::loadSubscriptionsByUserIds($pageUserIds);
    $payments = SubscriberAdminService::loadCurrentPeriodPaymentsByUserIds($pageUserIds, $billingPeriod);
    $lastPaidPayments = SubscriberAdminService::loadLastPaidPaymentsByUserIds($pageUserIds);

    foreach ($pageUsers as $user) {
        $row = $buildRow($user, $subscriptions, $payments, $lastPaidPayments);
        unset($row['access_status']);
        $rows[] = $row;
    }
}

$gridParameters = [
    'GRID_ID' => $gridId,
    'COLUMNS' => $columns,
    'ROWS' => $rows,
    'NAV_OBJECT' => $nav,
    'AJAX_MODE' => 'Y',
    'AJAX_ID' => \CAjax::getComponentID('bitrix:main.ui.grid', '.default', ''),
    'PAGE_SIZES' => [
        ['NAME' => '20', 'VALUE' => '20'],
        ['NAME' => '50', 'VALUE' => '50'],
        ['NAME' => '100', 'VALUE' => '100'],
    ],
    'AJAX_OPTION_JUMP' => 'N',
    'SHOW_ROW_CHECKBOXES' => false,
    'SHOW_ROW_ACTIONS_MENU' => true,
    'SHOW_GRID_SETTINGS_MENU' => true,
    'SHOW_NAVIGATION_PANEL' => true,
    'SHOW_PAGINATION' => true,
    'SHOW_TOTAL_COUNTER' => true,
    'SHOW_PAGESIZE' => true,
    'ALLOW_COLUMNS_SORT' => true,
    'ALLOW_COLUMNS_RESIZE' => true,
    'ALLOW_HORIZONTAL_SCROLL' => true,
    'ALLOW_SORT' => true,
    'ALLOW_PIN_HEADER' => true,
];

$filterConfig = [
    'FILTER_ID' => $gridId,
    'GRID_ID' => $gridId,
    'FILTER' => $filterFields,
    'ENABLE_LABEL' => true,
    'ENABLE_LIVE_SEARCH' => true,
];

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>

<div class="zr-paidaccess-admin">
<?php
$APPLICATION->IncludeComponent('bitrix:main.ui.filter', '', $filterConfig, false);
$APPLICATION->IncludeComponent('bitrix:main.ui.grid', '', $gridParameters);
?>
</div>

<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
