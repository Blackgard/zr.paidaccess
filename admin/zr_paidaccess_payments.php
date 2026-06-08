<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Application;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\Grid;
use Bitrix\Main\Grid\Panel\Types;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\UI\Filter\Options as FilterOptions;
use Bitrix\Main\UI\PageNavigation;
use Zr\PaidAccess\Admin\AdminJsonResponse;
use Zr\PaidAccess\Admin\PaymentAdminService;
use Zr\PaidAccess\Admin\StatusBadgeRenderer;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Tables\PaymentTable;
use Zr\PaidAccess\Tables\SubscriptionTable;

$moduleId = PaidAccessCore::MODULE_ID;

Loader::includeModule($moduleId);
Loc::loadMessages(__FILE__);

\Bitrix\Main\Page\Asset::getInstance()->addCss('/bitrix/themes/.default/zr.paidaccess.css');

$POST_RIGHT = $APPLICATION->GetGroupRight($moduleId);
if ($POST_RIGHT < 'W') {
    $APPLICATION->AuthForm(GetMessage('ACCESS_DENIED'));
}

$request = Application::getInstance()->getContext()->getRequest();
if ($request->isPost() && $request->getPost('action') === 'delete') {
    if (!check_bitrix_sessid()) {
        AdminJsonResponse::send(['success' => false, 'error' => 'Invalid sessid']);
    }

    $ids = $request->getPost('ids');
    if (!is_array($ids)) {
        $ids = $ids !== null && $ids !== '' ? [$ids] : [];
    }

    $errors = [];
    foreach ($ids as $id) {
        $id = (int)$id;
        if ($id <= 0) {
            continue;
        }
        try {
            PaymentAdminService::delete($id);
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    if ($errors !== []) {
        AdminJsonResponse::send(['success' => false, 'error' => implode('; ', $errors)]);
    }

    AdminJsonResponse::send(['success' => true]);
}

$APPLICATION->SetTitle(Loc::getMessage('ZR_PAIDACCESS_PAYMENTS_TITLE'));

$gridId = 'zr_paidaccess_payments_list';
$gridOptions = new Grid\Options($gridId);
$sort = $gridOptions->GetSorting(['sort' => ['ID' => 'DESC'], 'vars' => ['by' => 'by', 'order' => 'order']]);
$navParams = $gridOptions->GetNavParams();

$statusFilterItems = PaymentAdminService::getStatusTitles();

$filterFields = [
    ['id' => 'ID', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_ID'), 'type' => 'number', 'default' => true],
    ['id' => 'USER_ID', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_USER') . ' (ID)', 'type' => 'number', 'default' => true],
    [
        'id' => 'STATUS',
        'name' => Loc::getMessage('ZR_PAIDACCESS_COL_STATUS'),
        'type' => 'list',
        'items' => $statusFilterItems,
        'params' => ['multiple' => 'N'],
        'default' => true,
    ],
    ['id' => 'BILLING_PERIOD', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_PERIOD'), 'type' => 'string', 'default' => true],
    ['id' => 'ORDER_ID', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_ORDER'), 'type' => 'string', 'default' => true],
    [
        'id' => 'DATE_CREATE',
        'name' => Loc::getMessage('ZR_PAIDACCESS_COL_CREATED'),
        'type' => 'date',
        'default' => true,
    ],
];

$filterOption = new FilterOptions($gridId);
$filterData = $filterOption->getFilter($filterFields);

if ($request->get('USER_ID')) {
    $filterData['USER_ID'] = (int)$request->get('USER_ID');
}

$filter = PaymentAdminService::buildGridFilter($filterData);

$columns = [
    ['id' => 'ID', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_ID'), 'sort' => 'ID', 'default' => true],
    ['id' => 'USER', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_USER'), 'sort' => 'USER_ID', 'default' => true],
    ['id' => 'EMAIL', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_EMAIL'), 'sort' => false, 'default' => true],
    ['id' => 'BILLING_PERIOD', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_PERIOD'), 'sort' => 'BILLING_PERIOD', 'default' => true],
    ['id' => 'AMOUNT', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_AMOUNT'), 'sort' => 'AMOUNT', 'default' => true],
    ['id' => 'STATUS', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_STATUS'), 'sort' => 'STATUS', 'default' => true],
    ['id' => 'ORDER_ID', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_ORDER'), 'sort' => 'ORDER_ID', 'default' => true],
    ['id' => 'GATEWAY_CODE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_GATEWAY'), 'sort' => 'GATEWAY_CODE', 'default' => true],
    ['id' => 'SUBSCRIPTION', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_SUBSCRIPTION'), 'sort' => false, 'default' => true],
    ['id' => 'DATE_CREATE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_CREATED'), 'sort' => 'DATE_CREATE', 'default' => true],
    ['id' => 'DATE_PAID', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_PAID'), 'sort' => 'DATE_PAID', 'default' => true],
];

$nav = new PageNavigation($gridId);
$nav->allowAllRecords(true)
    ->setPageSize($navParams['nPageSize'])
    ->initFromUri();

$list = PaymentTable::getList([
    'order' => $sort['sort'],
    'count_total' => true,
    'offset' => $nav->getOffset(),
    'limit' => $nav->getLimit(),
    'filter' => $filter,
    'runtime' => [
        new ReferenceField(
            'SUBSCRIPTION',
            SubscriptionTable::class,
            Join::on('this.USER_ID', 'ref.USER_ID'),
            ['join_type' => 'LEFT']
        ),
    ],
    'select' => [
        '*',
        'USER_LOGIN' => 'USER.LOGIN',
        'USER_NAME' => 'USER.NAME',
        'USER_LAST_NAME' => 'USER.LAST_NAME',
        'USER_EMAIL' => 'USER.EMAIL',
        'SUB_PERIOD_END' => 'SUBSCRIPTION.PERIOD_END',
        'SUB_STATUS' => 'SUBSCRIPTION.STATUS',
    ],
]);

$nav->setRecordCount($list->getCount());

$rows = [];
while ($item = $list->fetch()) {
    $id = (int)$item['ID'];
    $userId = (int)$item['USER_ID'];
    $editUrl = 'zr_paidaccess_payment_edit.php?ID=' . $id . '&lang=' . LANGUAGE_ID;
    $userEditUrl = '/bitrix/admin/user_edit.php?ID=' . $userId . '&lang=' . LANGUAGE_ID;

    $userName = trim(($item['USER_NAME'] ?? '') . ' ' . ($item['USER_LAST_NAME'] ?? ''));
    if ($userName === '') {
        $userName = (string)($item['USER_LOGIN'] ?? '');
    }

    $userHtml = '<a href="' . htmlspecialcharsbx($userEditUrl) . '" target="_blank">'
        . htmlspecialcharsbx('[' . $userId . '] ' . $userName) . '</a>';

    $subEnd = '';
    if (!empty($item['SUB_PERIOD_END'])) {
        if ($item['SUB_PERIOD_END'] instanceof \Bitrix\Main\Type\DateTime) {
            $subEnd = $item['SUB_PERIOD_END']->toString();
        } else {
            $subEnd = (string)$item['SUB_PERIOD_END'];
        }
    }

    $amountHtml = number_format((float)$item['AMOUNT'], 2, '.', ' ') . ' ' . htmlspecialcharsbx($item['CURRENCY']);

    $dateCreate = $item['DATE_CREATE'] instanceof \Bitrix\Main\Type\DateTime
        ? $item['DATE_CREATE']->toString() : (string)$item['DATE_CREATE'];
    $datePaid = '';
    if (!empty($item['DATE_PAID'])) {
        $datePaid = $item['DATE_PAID'] instanceof \Bitrix\Main\Type\DateTime
            ? $item['DATE_PAID']->toString() : (string)$item['DATE_PAID'];
    }

    $gatewayLabel = $item['GATEWAY_CODE'] === PaymentAdminService::MANUAL_GATEWAY_CODE
        ? 'manual' : htmlspecialcharsbx($item['GATEWAY_CODE']);

    $deleteConfirm = CUtil::JSEscape(Loc::getMessage('ZR_PAIDACCESS_DELETE_CONFIRM_ONE'));

    $rows[] = [
        'id' => $id,
        'data' => $item,
        'columns' => [
            'ID' => $id,
            'USER' => $userHtml,
            'EMAIL' => htmlspecialcharsbx((string)($item['USER_EMAIL'] ?? '')),
            'BILLING_PERIOD' => htmlspecialcharsbx((string)$item['BILLING_PERIOD']),
            'AMOUNT' => $amountHtml,
            'STATUS' => StatusBadgeRenderer::renderPaymentStatus((string)$item['STATUS']),
            'ORDER_ID' => '<a href="' . htmlspecialcharsbx($editUrl) . '">' . htmlspecialcharsbx($item['ORDER_ID']) . '</a>',
            'GATEWAY_CODE' => $gatewayLabel,
            'SUBSCRIPTION' => htmlspecialcharsbx($subEnd),
            'DATE_CREATE' => htmlspecialcharsbx($dateCreate),
            'DATE_PAID' => htmlspecialcharsbx($datePaid),
        ],
        'actions' => [
            ['text' => GetMessage('MAIN_EDIT'), 'default' => true, 'href' => $editUrl],
            [
                'text' => GetMessage('MAIN_DELETE'),
                'onclick' => 'zrPaidaccessPaymentDelete([' . $id . '], "' . $deleteConfirm . '")',
            ],
        ],
    ];
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
    'SHOW_CHECK_ALL_CHECKBOXES' => true,
    'SHOW_ROW_CHECKBOXES' => true,
    'SHOW_ROW_ACTIONS_MENU' => true,
    'SHOW_GRID_SETTINGS_MENU' => true,
    'SHOW_NAVIGATION_PANEL' => true,
    'SHOW_PAGINATION' => true,
    'SHOW_SELECTED_COUNTER' => true,
    'SHOW_TOTAL_COUNTER' => true,
    'SHOW_PAGESIZE' => true,
    'SHOW_ACTION_PANEL' => true,
    'ALLOW_COLUMNS_SORT' => true,
    'ALLOW_COLUMNS_RESIZE' => true,
    'ALLOW_HORIZONTAL_SCROLL' => true,
    'ALLOW_SORT' => true,
    'ALLOW_PIN_HEADER' => true,
    'ACTION_PANEL' => [
        'GROUPS' => [
            [
                'ITEMS' => [
                    [
                        'TYPE' => Types::BUTTON,
                        'ID' => 'action_delete_selected',
                        'NAME' => 'action_delete_selected',
                        'TEXT' => Loc::getMessage('ZR_PAIDACCESS_DELETE_SELECTED'),
                        'TITLE' => Loc::getMessage('ZR_PAIDACCESS_DELETE_SELECTED_TITLE'),
                        'CLASS' => 'ui-btn ui-btn-light-border',
                    ],
                ],
            ],
        ],
    ],
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
$aContext = [
    [
        'TEXT' => Loc::getMessage('ZR_PAIDACCESS_PAYMENT_ADD'),
        'LINK' => 'zr_paidaccess_payment_edit.php?lang=' . LANGUAGE_ID,
        'TITLE' => Loc::getMessage('ZR_PAIDACCESS_PAYMENT_ADD'),
        'ICON' => 'btn_new',
    ],
];
$contextMenu = new CAdminContextMenu($aContext);
$contextMenu->Show();

$APPLICATION->IncludeComponent('bitrix:main.ui.filter', '', $filterConfig, false);
$APPLICATION->IncludeComponent('bitrix:main.ui.grid', '', $gridParameters);
?>
</div>

<script>
function zrPaidaccessPaymentDelete(ids, confirmMessage) {
    if (!ids || !ids.length) {
        BX.UI.Notification.Center.notify({
            content: '<?= CUtil::JSEscape(Loc::getMessage('ZR_PAIDACCESS_NO_SELECTED')) ?>',
            position: 'top-right'
        });
        return;
    }
    if (!confirm(confirmMessage)) {
        return;
    }
    BX.ajax({
        url: '<?= CUtil::JSEscape($APPLICATION->GetCurPage()) ?>',
        method: 'POST',
        dataType: 'json',
        data: { sessid: BX.bitrix_sessid(), action: 'delete', ids: ids },
        onsuccess: function(response) {
            if (response && response.success) {
                var grid = BX.Main.gridManager.getById('<?= CUtil::JSEscape($gridId) ?>');
                if (grid && grid.instance) {
                    grid.instance.reload();
                } else {
                    window.location.reload();
                }
                return;
            }
            BX.UI.Notification.Center.notify({
                content: (response && response.error) ? response.error : '<?= CUtil::JSEscape(Loc::getMessage('ZR_PAIDACCESS_DELETE_ERROR')) ?>',
                position: 'top-right'
            });
        }
    });
}
BX.ready(function() {
    var btn = BX('action_delete_selected');
    if (!btn) return;
    BX.bind(btn, 'click', function(e) {
        e.preventDefault();
        var grid = BX.Main.gridManager.getById('<?= CUtil::JSEscape($gridId) ?>');
        if (!grid || !grid.instance) return;
        zrPaidaccessPaymentDelete(
            grid.instance.getRows().getSelectedIds(),
            '<?= CUtil::JSEscape(Loc::getMessage('ZR_PAIDACCESS_DELETE_CONFIRM')) ?>'
        );
    });
});
</script>

<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
