<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Grid;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SiteTable;
use Bitrix\Main\UI\Filter\Options as FilterOptions;
use Bitrix\Main\UI\PageNavigation;
use Zr\PaidAccess\Admin\DocumentAdminService;
use Zr\PaidAccess\Admin\StatusBadgeRenderer;
use Zr\PaidAccess\Document\RequiredDocumentRepository;
use Zr\PaidAccess\PaidAccessCore;

$moduleId = PaidAccessCore::MODULE_ID;

Loader::includeModule($moduleId);
Loc::loadMessages(__FILE__);

\Bitrix\Main\Page\Asset::getInstance()->addCss('/bitrix/themes/.default/zr.paidaccess.css');

$POST_RIGHT = $APPLICATION->GetGroupRight($moduleId);
if ($POST_RIGHT < 'W') {
    $APPLICATION->AuthForm(GetMessage('ACCESS_DENIED'));
}

$APPLICATION->SetTitle(Loc::getMessage('ZR_PAIDACCESS_DOCUMENTS_TITLE'));

$gridId = 'zr_paidaccess_documents_list';
$gridOptions = new Grid\Options($gridId);
$sort = $gridOptions->GetSorting(['sort' => ['SORT' => 'ASC', 'ID' => 'ASC'], 'vars' => ['by' => 'by', 'order' => 'order']]);
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
    ['id' => 'TITLE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_TITLE'), 'sort' => 'TITLE', 'default' => true],
    ['id' => 'CODE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_CODE'), 'sort' => 'CODE', 'default' => true],
    ['id' => 'SITE_ID', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_SITE'), 'sort' => 'SITE_ID', 'default' => true],
    ['id' => 'CURRENT_VERSION', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_CURRENT_VERSION'), 'default' => true],
    ['id' => 'IS_REQUIRED', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_REQUIRED'), 'sort' => 'IS_REQUIRED', 'default' => true],
    ['id' => 'ACTIVE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_ACTIVE'), 'sort' => 'ACTIVE', 'default' => true],
];

$filterFields = [
    [
        'id' => 'SITE_ID',
        'name' => Loc::getMessage('ZR_PAIDACCESS_COL_SITE'),
        'type' => 'list',
        'items' => $siteFilterItems,
        'default' => true,
    ],
    [
        'id' => 'CODE',
        'name' => Loc::getMessage('ZR_PAIDACCESS_COL_CODE'),
        'type' => 'string',
        'default' => true,
    ],
    [
        'id' => 'TITLE',
        'name' => Loc::getMessage('ZR_PAIDACCESS_COL_TITLE'),
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
$filter = DocumentAdminService::buildDocumentFilter($filterData);

$nav = new PageNavigation($gridId);
$nav->allowAllRecords(true)
    ->setPageSize($navParams['nPageSize'])
    ->initFromUri();

$totalCount = RequiredDocumentRepository::getCount($filter);
$nav->setRecordCount($totalCount);

$items = RequiredDocumentRepository::getList($filter, $sort['sort'], $nav->getLimit(), $nav->getOffset());

$rows = [];
foreach ($items as $item) {
    $id = (int)$item['ID'];
    $editUrl = 'zr_paidaccess_document_edit.php?ID=' . $id . '&lang=' . LANGUAGE_ID;

    $rows[] = [
        'id' => $id,
        'data' => $item,
        'columns' => [
            'ID' => $id,
            'TITLE' => '<a href="' . htmlspecialcharsbx($editUrl) . '">' . htmlspecialcharsbx((string)$item['TITLE']) . '</a>',
            'CODE' => htmlspecialcharsbx((string)$item['CODE']),
            'SITE_ID' => htmlspecialcharsbx((string)$item['SITE_ID']),
            'CURRENT_VERSION' => htmlspecialcharsbx(DocumentAdminService::getCurrentVersionLabel($id)),
            'IS_REQUIRED' => StatusBadgeRenderer::renderYesNo(
                (string)$item['IS_REQUIRED'] === 'Y',
                Loc::getMessage('ZR_PAIDACCESS_YES'),
                Loc::getMessage('ZR_PAIDACCESS_NO')
            ),
            'ACTIVE' => StatusBadgeRenderer::renderYesNo(
                (string)$item['ACTIVE'] === 'Y',
                Loc::getMessage('ZR_PAIDACCESS_YES'),
                Loc::getMessage('ZR_PAIDACCESS_NO')
            ),
        ],
        'actions' => [
            [
                'text' => Loc::getMessage('ZR_PAIDACCESS_EDIT'),
                'default' => true,
                'onclick' => "document.location='" . CUtil::JSEscape($editUrl) . "'",
            ],
        ],
    ];
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>
<div class="zr-paidaccess-admin">
<?php
$aContext = [
    [
        'TEXT' => Loc::getMessage('ZR_PAIDACCESS_DOCUMENT_ADD'),
        'LINK' => 'zr_paidaccess_document_edit.php?lang=' . LANGUAGE_ID,
        'TITLE' => Loc::getMessage('ZR_PAIDACCESS_DOCUMENT_ADD'),
        'ICON' => 'btn_new',
    ],
];

$contextMenu = new CAdminContextMenu($aContext);
$contextMenu->Show();

$APPLICATION->IncludeComponent('bitrix:main.ui.filter', '', [
    'FILTER_ID' => $gridId,
    'GRID_ID' => $gridId,
    'FILTER' => $filterFields,
    'ENABLE_LIVE_SEARCH' => true,
    'ENABLE_LABEL' => true,
]);

$APPLICATION->IncludeComponent('bitrix:main.ui.grid', '', [
    'GRID_ID' => $gridId,
    'COLUMNS' => $columns,
    'ROWS' => $rows,
    'SHOW_ROW_CHECKBOXES' => false,
    'NAV_OBJECT' => $nav,
    'AJAX_MODE' => 'N',
    'PAGE_SIZES' => [
        ['NAME' => '20', 'VALUE' => '20'],
        ['NAME' => '50', 'VALUE' => '50'],
        ['NAME' => '100', 'VALUE' => '100'],
    ],
    'SHOW_TOTAL_COUNTER' => true,
    'TOTAL_ROWS_COUNT' => $totalCount,
]);
?>
</div>
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
