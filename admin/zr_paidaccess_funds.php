<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Grid;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SiteTable;
use Bitrix\Main\UI\Filter\Options as FilterOptions;
use Bitrix\Main\UI\PageNavigation;
use Zr\PaidAccess\Admin\FundAdminService;
use Zr\PaidAccess\Admin\StatusBadgeRenderer;
use Zr\PaidAccess\Fund\FundRepository;
use Zr\PaidAccess\PaidAccessCore;

$moduleId = PaidAccessCore::MODULE_ID;

Loader::includeModule($moduleId);
Loc::loadMessages(__FILE__);

\Bitrix\Main\Page\Asset::getInstance()->addCss('/bitrix/themes/.default/zr.paidaccess.css');

$POST_RIGHT = $APPLICATION->GetGroupRight($moduleId);
if ($POST_RIGHT < 'W') {
    $APPLICATION->AuthForm(GetMessage('ACCESS_DENIED'));
}

$APPLICATION->SetTitle(Loc::getMessage('ZR_PAIDACCESS_FUNDS_TITLE'));

$gridId = 'zr_paidaccess_funds_list';
$gridOptions = new Grid\Options($gridId);
$sort = $gridOptions->GetSorting(['sort' => ['ID' => 'DESC'], 'vars' => ['by' => 'by', 'order' => 'order']]);
$navParams = $gridOptions->GetNavParams();

$siteFilterItems = ['' => Loc::getMessage('ZR_PAIDACCESS_ALL_SITES')];
$siteResult = SiteTable::getList([
    'select' => ['LID', 'NAME'],
    'order' => ['SORT' => 'ASC'],
]);
while ($site = $siteResult->fetch()) {
    $siteFilterItems[$site['LID']] = '[' . $site['LID'] . '] ' . $site['NAME'];
}

$columns = [
    ['id' => 'ID', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_ID'), 'sort' => 'ID', 'default' => true],
    ['id' => 'NAME', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_NAME'), 'sort' => 'NAME', 'default' => true],
    ['id' => 'CODE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_CODE'), 'sort' => 'CODE', 'default' => true],
    ['id' => 'SITE_ID', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_SITE'), 'sort' => 'SITE_ID', 'default' => true],
    ['id' => 'BALANCE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_BALANCE'), 'default' => true],
    ['id' => 'IS_DEFAULT', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_DEFAULT'), 'sort' => 'IS_DEFAULT', 'default' => true],
    ['id' => 'ACTIVE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_ACTIVE'), 'sort' => 'ACTIVE', 'default' => true],
];

$filterFields = [
    [
        'id' => 'SITE_ID',
        'name' => Loc::getMessage('ZR_PAIDACCESS_COL_SITE'),
        'type' => 'list',
        'items' => $siteFilterItems,
        'params' => ['multiple' => 'N'],
        'default' => true,
    ],
    [
        'id' => 'CODE',
        'name' => Loc::getMessage('ZR_PAIDACCESS_COL_CODE'),
        'type' => 'string',
        'default' => true,
    ],
    [
        'id' => 'NAME',
        'name' => Loc::getMessage('ZR_PAIDACCESS_COL_NAME'),
        'type' => 'string',
        'default' => true,
    ],
    [
        'id' => 'ACTIVE',
        'name' => Loc::getMessage('ZR_PAIDACCESS_COL_ACTIVE'),
        'type' => 'list',
        'items' => [
            'Y' => Loc::getMessage('ZR_PAIDACCESS_YES'),
            'N' => Loc::getMessage('ZR_PAIDACCESS_NO'),
        ],
        'default' => true,
    ],
];

$filterOption = new FilterOptions($gridId);
$filterData = $filterOption->getFilter($filterFields);
$filter = FundAdminService::buildFundFilter($filterData);

$nav = new PageNavigation($gridId);
$nav->allowAllRecords(true)
    ->setPageSize($navParams['nPageSize'])
    ->initFromUri();

$totalCount = FundRepository::getCount($filter);
$nav->setRecordCount($totalCount);

$items = FundRepository::getList($filter, $sort['sort'], $nav->getLimit(), $nav->getOffset());

$rows = [];
foreach ($items as $item) {
    $id = (int)$item['ID'];
    $editUrl = 'zr_paidaccess_fund_edit.php?ID=' . $id . '&lang=' . LANGUAGE_ID;
    $movementUrl = 'zr_paidaccess_fund_movement_edit.php?FUND_ID=' . $id . '&lang=' . LANGUAGE_ID;

    $nameHtml = '<a href="' . htmlspecialcharsbx($editUrl) . '">'
        . htmlspecialcharsbx((string)$item['NAME'])
        . '</a>';

    $rows[] = [
        'id' => $id,
        'data' => $item,
        'columns' => [
            'ID' => $id,
            'NAME' => $nameHtml,
            'CODE' => htmlspecialcharsbx((string)$item['CODE']),
            'SITE_ID' => htmlspecialcharsbx((string)$item['SITE_ID']),
            'BALANCE' => '<strong>' . htmlspecialcharsbx(FundAdminService::getFundBalanceFormatted($id)) . '</strong>',
            'IS_DEFAULT' => StatusBadgeRenderer::renderYesNo(
                ($item['IS_DEFAULT'] ?? 'N') === 'Y',
                Loc::getMessage('ZR_PAIDACCESS_YES'),
                Loc::getMessage('ZR_PAIDACCESS_NO')
            ),
            'ACTIVE' => StatusBadgeRenderer::renderYesNo(
                ($item['ACTIVE'] ?? 'Y') === 'Y',
                Loc::getMessage('ZR_PAIDACCESS_YES'),
                Loc::getMessage('ZR_PAIDACCESS_NO')
            ),
        ],
        'actions' => [
            [
                'text' => GetMessage('MAIN_EDIT'),
                'default' => true,
                'href' => $editUrl,
            ],
            [
                'text' => Loc::getMessage('ZR_PAIDACCESS_ADD_MOVEMENT'),
                'href' => $movementUrl,
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
$aContext = [
    [
        'TEXT' => Loc::getMessage('ZR_PAIDACCESS_FUND_ADD'),
        'LINK' => 'zr_paidaccess_fund_edit.php?lang=' . LANGUAGE_ID,
        'TITLE' => Loc::getMessage('ZR_PAIDACCESS_FUND_ADD'),
        'ICON' => 'btn_new',
    ],
];

$contextMenu = new CAdminContextMenu($aContext);
$contextMenu->Show();

$APPLICATION->IncludeComponent(
    'bitrix:main.ui.filter',
    '',
    $filterConfig,
    false
);

$APPLICATION->IncludeComponent(
    'bitrix:main.ui.grid',
    '',
    $gridParameters
);
?>
</div>

<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
