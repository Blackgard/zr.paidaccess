<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Application;
use Bitrix\Main\Grid;
use Bitrix\Main\Grid\Panel\Types;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SiteTable;
use Bitrix\Main\UI\Filter\Options as FilterOptions;
use Bitrix\Main\UI\PageNavigation;
use Zr\PaidAccess\Admin\AdminJsonResponse;
use Zr\PaidAccess\Admin\GatewayImportExportService;
use Zr\PaidAccess\Admin\StatusBadgeRenderer;
use Zr\PaidAccess\Gateway\GatewayRepository;
use Zr\PaidAccess\Gateway\Provider\GatewayProviderRegistry;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Tables\GatewayTable;

$moduleId = PaidAccessCore::MODULE_ID;

Loader::includeModule($moduleId);
Loc::loadMessages(__FILE__);

\Bitrix\Main\Page\Asset::getInstance()->addCss('/bitrix/themes/.default/zr.paidaccess.css');

$POST_RIGHT = $APPLICATION->GetGroupRight($moduleId);
if ($POST_RIGHT < 'W') {
    $APPLICATION->AuthForm(GetMessage('ACCESS_DENIED'));
}

$request = Application::getInstance()->getContext()->getRequest();

if ($request->isPost() && $request->getPost('action') === 'export') {
    if (!check_bitrix_sessid()) {
        AdminJsonResponse::send(['success' => false, 'error' => 'Invalid sessid']);
    }

    $ids = $request->getPost('ids');
    if (!is_array($ids)) {
        $ids = $ids !== null && $ids !== '' ? [$ids] : [];
    }
    $ids = array_values(array_filter(array_map('intval', $ids)));

    $json = GatewayImportExportService::exportToJson($ids);
    $fileName = 'zr-paidaccess-gateways-' . date('Y-m-d_His') . '.json';

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . strlen($json));
    echo $json;
    exit;
}

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
            GatewayRepository::delete($id);
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    if ($errors !== []) {
        AdminJsonResponse::send(['success' => false, 'error' => implode('; ', $errors)]);
    }

    AdminJsonResponse::send(['success' => true]);
}

$APPLICATION->SetTitle(Loc::getMessage('ZR_PAIDACCESS_GATEWAYS_TITLE'));

$gridId = 'zr_paidaccess_gateways_list';
$gridOptions = new Grid\Options($gridId);
$sort = $gridOptions->GetSorting(['sort' => ['ID' => 'DESC'], 'vars' => ['by' => 'by', 'order' => 'order']]);
$navParams = $gridOptions->GetNavParams();

$providers = GatewayProviderRegistry::getProviders();
$providerFilterItems = [];
foreach ($providers as $code => $info) {
    $providerFilterItems[$code] = $info['title'];
}

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
    ['id' => 'PROVIDER', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_PROVIDER'), 'sort' => 'PROVIDER', 'default' => true],
    ['id' => 'SITE_ID', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_SITE'), 'sort' => 'SITE_ID', 'default' => true],
    ['id' => 'IS_DEFAULT', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_DEFAULT'), 'sort' => 'IS_DEFAULT', 'default' => true],
    ['id' => 'IS_TEST', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_IS_TEST'), 'sort' => 'IS_TEST', 'default' => true],
    ['id' => 'TEST_PASSED', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_TEST_STATUS'), 'sort' => 'TEST_PASSED', 'default' => true],
    ['id' => 'ACTIVE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_ACTIVE'), 'sort' => 'ACTIVE', 'default' => true],
    ['id' => 'SORT', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_SORT'), 'sort' => 'SORT', 'default' => true],
];

$filterFields = [
    [
        'id' => 'ID',
        'name' => Loc::getMessage('ZR_PAIDACCESS_COL_ID'),
        'type' => 'number',
        'default' => true,
    ],
    [
        'id' => 'NAME',
        'name' => Loc::getMessage('ZR_PAIDACCESS_COL_NAME'),
        'type' => 'string',
        'default' => true,
    ],
    [
        'id' => 'PROVIDER',
        'name' => Loc::getMessage('ZR_PAIDACCESS_COL_PROVIDER'),
        'type' => 'list',
        'items' => $providerFilterItems,
        'params' => ['multiple' => 'N'],
        'default' => true,
    ],
    [
        'id' => 'SITE_ID',
        'name' => Loc::getMessage('ZR_PAIDACCESS_COL_SITE'),
        'type' => 'list',
        'items' => $siteFilterItems,
        'params' => ['multiple' => 'N'],
        'default' => true,
    ],
    [
        'id' => 'IS_DEFAULT',
        'name' => Loc::getMessage('ZR_PAIDACCESS_COL_DEFAULT'),
        'type' => 'list',
        'items' => [
            'Y' => Loc::getMessage('ZR_PAIDACCESS_YES'),
            'N' => Loc::getMessage('ZR_PAIDACCESS_NO'),
        ],
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

$filter = [];
$filterOption = new FilterOptions($gridId);
$filterData = $filterOption->getFilter($filterFields);

foreach ($filterData as $key => $value) {
    if ($value === '' || $value === null) {
        continue;
    }

    switch ($key) {
        case 'PROVIDER':
            $filter['=PROVIDER'] = $value;
            break;
        case 'SITE_ID':
            $filter['=SITE_ID'] = $value;
            break;
        case 'ACTIVE':
            $filter['=ACTIVE'] = $value;
            break;
        case 'IS_DEFAULT':
            $filter['=IS_DEFAULT'] = $value;
            break;
        case 'NAME':
            $filter['%NAME'] = $value;
            break;
    }
}

if (!empty($filterData['ID_from']) || !empty($filterData['ID_to'])) {
    if (!empty($filterData['ID_from']) && !empty($filterData['ID_to'])) {
        $filter['><ID'] = [(int)$filterData['ID_from'], (int)$filterData['ID_to']];
    } elseif (!empty($filterData['ID_from'])) {
        $filter['>=ID'] = (int)$filterData['ID_from'];
    } else {
        $filter['<=ID'] = (int)$filterData['ID_to'];
    }
}

$nav = new PageNavigation($gridId);
$nav->allowAllRecords(true)
    ->setPageSize($navParams['nPageSize'])
    ->initFromUri();

$list = GatewayTable::getList([
    'order' => $sort['sort'],
    'count_total' => true,
    'offset' => $nav->getOffset(),
    'limit' => $nav->getLimit(),
    'filter' => $filter,
]);

$nav->setRecordCount($list->getCount());

$rows = [];
while ($item = $list->fetch()) {
    $id = (int)$item['ID'];
    $editUrl = 'zr_paidaccess_gateway_edit.php?ID=' . $id . '&lang=' . LANGUAGE_ID;

    $providerTitle = isset($providers[$item['PROVIDER']]['title'])
        ? $providers[$item['PROVIDER']]['title']
        : $item['PROVIDER'];

    $siteDisplay = $item['SITE_ID'] !== ''
        ? htmlspecialcharsbx($item['SITE_ID'])
        : Loc::getMessage('ZR_PAIDACCESS_ALL_SITES');

    $nameHtml = '<a href="' . htmlspecialcharsbx($editUrl) . '">'
        . htmlspecialcharsbx($item['NAME'])
        . '</a>';

    $deleteConfirm = CUtil::JSEscape(Loc::getMessage('ZR_PAIDACCESS_DELETE_CONFIRM_ONE'));

    $rows[] = [
        'id' => $id,
        'data' => $item,
        'columns' => [
            'ID' => $id,
            'NAME' => $nameHtml,
            'PROVIDER' => htmlspecialcharsbx($providerTitle),
            'SITE_ID' => $siteDisplay,
            'IS_DEFAULT' => StatusBadgeRenderer::renderYesNo(
                $item['IS_DEFAULT'] === 'Y',
                Loc::getMessage('ZR_PAIDACCESS_YES'),
                Loc::getMessage('ZR_PAIDACCESS_NO')
            ),
            'IS_TEST' => StatusBadgeRenderer::renderYesNo(
                ($item['IS_TEST'] ?? 'N') === 'Y',
                Loc::getMessage('ZR_PAIDACCESS_YES'),
                Loc::getMessage('ZR_PAIDACCESS_NO')
            ),
            'TEST_PASSED' => ($item['IS_TEST'] ?? 'N') === 'Y'
                ? ((($item['TEST_PASSED'] ?? 'N') === 'Y')
                    ? StatusBadgeRenderer::render(
                        Loc::getMessage('ZR_PAIDACCESS_TEST_PASSED'),
                        StatusBadgeRenderer::STYLE_COMPLETED
                    )
                    : StatusBadgeRenderer::render(
                        Loc::getMessage('ZR_PAIDACCESS_TEST_PENDING'),
                        StatusBadgeRenderer::STYLE_WARNING
                    ))
                : StatusBadgeRenderer::render(
                    Loc::getMessage('ZR_PAIDACCESS_NOT_TEST_GATEWAY'),
                    StatusBadgeRenderer::STYLE_MUTED
                ),
            'ACTIVE' => StatusBadgeRenderer::renderYesNo(
                $item['ACTIVE'] === 'Y',
                Loc::getMessage('ZR_PAIDACCESS_YES'),
                Loc::getMessage('ZR_PAIDACCESS_NO')
            ),
            'SORT' => (int)$item['SORT'],
        ],
        'actions' => [
            [
                'text' => GetMessage('MAIN_EDIT'),
                'default' => true,
                'href' => $editUrl,
            ],
            [
                'text' => GetMessage('MAIN_DELETE'),
                'onclick' => 'zrPaidaccessGatewayDelete([' . $id . '], "' . $deleteConfirm . '")',
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
        ['NAME' => '5', 'VALUE' => '5'],
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
                        'ID' => 'action_export_selected',
                        'NAME' => 'action_export_selected',
                        'TEXT' => Loc::getMessage('ZR_PAIDACCESS_EXPORT_SELECTED'),
                        'TITLE' => Loc::getMessage('ZR_PAIDACCESS_EXPORT_SELECTED_TITLE'),
                        'CLASS' => 'ui-btn ui-btn-light-border',
                    ],
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
        'TEXT' => Loc::getMessage('ZR_PAIDACCESS_GATEWAY_ADD'),
        'LINK' => 'zr_paidaccess_gateway_edit.php?lang=' . LANGUAGE_ID,
        'TITLE' => Loc::getMessage('ZR_PAIDACCESS_GATEWAY_ADD'),
        'ICON' => 'btn_new',
    ],
    [
        'TEXT' => Loc::getMessage('ZR_PAIDACCESS_EXPORT_ALL'),
        'ONCLICK' => 'zrPaidaccessGatewayExport([]); return false;',
        'TITLE' => Loc::getMessage('ZR_PAIDACCESS_EXPORT_ALL_TITLE'),
    ],
    [
        'TEXT' => Loc::getMessage('ZR_PAIDACCESS_IMPORT'),
        'LINK' => 'zr_paidaccess_gateway_import.php?lang=' . LANGUAGE_ID,
        'TITLE' => Loc::getMessage('ZR_PAIDACCESS_IMPORT'),
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

<script>
function zrPaidaccessGatewayExport(ids) {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= CUtil::JSEscape($APPLICATION->GetCurPage()) ?>';
    form.style.display = 'none';

    var fields = {
        sessid: BX.bitrix_sessid(),
        action: 'export'
    };

    Object.keys(fields).forEach(function(key) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = fields[key];
        form.appendChild(input);
    });

    if (ids && ids.length) {
        ids.forEach(function(id) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = id;
            form.appendChild(input);
        });
    }

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function zrPaidaccessGatewayDelete(ids, confirmMessage) {
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
        data: {
            sessid: BX.bitrix_sessid(),
            action: 'delete',
            ids: ids
        },
        onsuccess: function(response) {
            if (response && response.success) {
                var grid = zrPaidaccessGatewayGetGrid();
                if (grid) {
                    grid.reload();
                } else {
                    window.location.reload();
                }
                return;
            }

            BX.UI.Notification.Center.notify({
                content: (response && response.error) ? response.error : '<?= CUtil::JSEscape(Loc::getMessage('ZR_PAIDACCESS_DELETE_ERROR')) ?>',
                position: 'top-right'
            });
        },
        onfailure: function() {
            BX.UI.Notification.Center.notify({
                content: '<?= CUtil::JSEscape(Loc::getMessage('ZR_PAIDACCESS_DELETE_ERROR')) ?>',
                position: 'top-right'
            });
        }
    });
}

function zrPaidaccessGatewayGetGrid() {
    var gridId = '<?= CUtil::JSEscape($gridId) ?>';
    if (!BX.Main.gridManager || !BX.Main.gridManager.getById(gridId)) {
        return null;
    }
    return BX.Main.gridManager.getById(gridId).instance;
}

BX.ready(function() {
    var exportBtn = BX('action_export_selected');
    if (exportBtn) {
        BX.bind(exportBtn, 'click', function(e) {
            e.preventDefault();
            var grid = zrPaidaccessGatewayGetGrid();
            if (!grid) {
                return;
            }
            var selectedIds = grid.getRows().getSelectedIds();
            if (!selectedIds || !selectedIds.length) {
                BX.UI.Notification.Center.notify({
                    content: '<?= CUtil::JSEscape(Loc::getMessage('ZR_PAIDACCESS_NO_SELECTED')) ?>',
                    position: 'top-right'
                });
                return;
            }
            zrPaidaccessGatewayExport(selectedIds);
        });
    }

    var deleteBtn = BX('action_delete_selected');
    if (!deleteBtn) {
        return;
    }

    BX.bind(deleteBtn, 'click', function(e) {
        e.preventDefault();
        var grid = zrPaidaccessGatewayGetGrid();
        if (!grid) {
            return;
        }

        var selectedIds = grid.getRows().getSelectedIds();
        zrPaidaccessGatewayDelete(
            selectedIds,
            '<?= CUtil::JSEscape(Loc::getMessage('ZR_PAIDACCESS_DELETE_CONFIRM')) ?>'
        );
    });
});
</script>

<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
