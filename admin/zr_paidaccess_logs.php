<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Application;
use Bitrix\Main\Grid;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UI\Filter\Options as FilterOptions;
use Bitrix\Main\UI\PageNavigation;
use Zr\PaidAccess\Admin\AdminEntityLinkRenderer;
use Zr\PaidAccess\Admin\AdminJsonResponse;
use Zr\PaidAccess\Admin\AuditContextRenderer;
use Zr\PaidAccess\Admin\EventLogAdminService;
use Zr\PaidAccess\Admin\EventLogContextRenderer;
use Zr\PaidAccess\Admin\GatewayTransactionAdminService;
use Zr\PaidAccess\Admin\GatewayTransactionContextRenderer;
use Zr\PaidAccess\Admin\LogCleanupAdminService;
use Zr\PaidAccess\Admin\StatusBadgeRenderer;
use Zr\PaidAccess\Enum\ModuleLogLevel;
use Zr\PaidAccess\Log\AuditLogService;
use Zr\PaidAccess\Log\ModuleEventLogService;
use Zr\PaidAccess\PaidAccessCore;

$moduleId = PaidAccessCore::MODULE_ID;

Loader::includeModule($moduleId);
Loc::loadMessages(__FILE__);

\Bitrix\Main\Page\Asset::getInstance()->addCss('/bitrix/themes/.default/zr.paidaccess.css');

$POST_RIGHT = $APPLICATION->GetGroupRight($moduleId);
if ($POST_RIGHT < 'R') {
    $APPLICATION->AuthForm(GetMessage('ACCESS_DENIED'));
}

$request = Application::getInstance()->getContext()->getRequest();
$tab = (string)$request->get('tab');
if (!in_array($tab, ['events', 'audit', 'gateway'], true)) {
    $tab = 'events';
}

$APPLICATION->SetTitle(Loc::getMessage('ZR_PAIDACCESS_LOGS_TITLE'));

$gridId = 'zr_paidaccess_event_log';
if ($tab === 'audit') {
    $gridId = 'zr_paidaccess_audit_log';
} elseif ($tab === 'gateway') {
    $gridId = 'zr_paidaccess_gateway_log';
}
$gridOptions = new Grid\Options($gridId);
$sort = $gridOptions->GetSorting(['sort' => ['ID' => 'DESC'], 'vars' => ['by' => 'by', 'order' => 'order']]);
$navParams = $gridOptions->GetNavParams();

if ($tab === 'gateway') {
    $eventTypeItems = GatewayTransactionAdminService::getEventTypeTitles();
    $filterFields = [
        ['id' => 'GATEWAY_CODE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_GATEWAY_CODE'), 'type' => 'string', 'default' => true],
        ['id' => 'GATEWAY_ID', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_GATEWAY_ID'), 'type' => 'number', 'default' => true],
        ['id' => 'EVENT_TYPE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_EVENT_TYPE'), 'type' => 'list', 'items' => $eventTypeItems, 'default' => true],
        ['id' => 'PAYMENT_ID', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_PAYMENT_ID'), 'type' => 'number', 'default' => true],
        ['id' => 'SUCCESS', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_SUCCESS'), 'type' => 'list', 'items' => ['Y' => 'Да', 'N' => 'Нет'], 'default' => false],
    ];
    $columns = [
        ['id' => 'ID', 'name' => 'ID', 'sort' => 'ID', 'default' => true],
        ['id' => 'DATE_CREATE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_DATE'), 'sort' => 'DATE_CREATE', 'default' => true],
        ['id' => 'EVENT_TYPE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_EVENT_TYPE'), 'sort' => 'EVENT_TYPE', 'default' => true],
        ['id' => 'GATEWAY_CODE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_GATEWAY_CODE'), 'sort' => 'GATEWAY_CODE', 'default' => true],
        ['id' => 'GATEWAY_ID', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_GATEWAY_ID'), 'default' => true],
        ['id' => 'PAYMENT_ID', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_PAYMENT_ID'), 'default' => true],
        ['id' => 'HTTP_CODE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_HTTP_CODE'), 'sort' => 'HTTP_CODE', 'default' => true],
        ['id' => 'SUCCESS', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_SUCCESS'), 'sort' => 'SUCCESS', 'default' => true],
        ['id' => 'GATEWAY_STATUS', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_GATEWAY_STATUS'), 'default' => true],
        ['id' => 'ERROR_MESSAGE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_ERROR'), 'default' => true],
        ['id' => 'PAYLOAD', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_PAYLOAD'), 'default' => true],
    ];
} elseif ($tab === 'audit') {
    $filterFields = [
        ['id' => 'ENTITY_TYPE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_ENTITY_TYPE'), 'type' => 'string', 'default' => true],
        ['id' => 'ENTITY_ID', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_ENTITY_ID'), 'type' => 'number', 'default' => true],
        ['id' => 'ACTION', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_ACTION'), 'type' => 'string', 'default' => true],
    ];
    $columns = [
        ['id' => 'ID', 'name' => 'ID', 'sort' => 'ID', 'default' => true],
        ['id' => 'DATE_CREATE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_DATE'), 'sort' => 'DATE_CREATE', 'default' => true],
        ['id' => 'ENTITY_TYPE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_ENTITY_TYPE'), 'sort' => 'ENTITY_TYPE', 'default' => true],
        ['id' => 'ENTITY_ID', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_ENTITY_ID'), 'sort' => 'ENTITY_ID', 'default' => true],
        ['id' => 'ACTION', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_ACTION'), 'sort' => 'ACTION', 'default' => true],
        ['id' => 'MESSAGE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_MESSAGE'), 'default' => true],
        ['id' => 'CONTEXT', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_CONTEXT'), 'default' => true],
        ['id' => 'ADMIN_USER_ID', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_ADMIN'), 'default' => true],
        ['id' => 'IP', 'name' => 'IP', 'default' => false],
    ];
} else {
    $levelItems = EventLogAdminService::getLevelTitles();
    $filterFields = [
        ['id' => 'LEVEL', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_LEVEL'), 'type' => 'list', 'items' => $levelItems, 'default' => true],
        ['id' => 'CODE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_CODE'), 'type' => 'string', 'default' => true],
        ['id' => 'PAYMENT_ID', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_PAYMENT_ID'), 'type' => 'number', 'default' => true],
        ['id' => 'USER_ID', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_USER_ID'), 'type' => 'number', 'default' => true],
    ];
    $columns = [
        ['id' => 'ID', 'name' => 'ID', 'sort' => 'ID', 'default' => true],
        ['id' => 'DATE_CREATE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_DATE'), 'sort' => 'DATE_CREATE', 'default' => true],
        ['id' => 'LEVEL', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_LEVEL'), 'sort' => 'LEVEL', 'default' => true],
        ['id' => 'CODE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_CODE'), 'sort' => 'CODE', 'default' => true],
        ['id' => 'MESSAGE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_MESSAGE'), 'default' => true],
        ['id' => 'PAYMENT_ID', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_PAYMENT_ID'), 'default' => true],
        ['id' => 'USER_ID', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_USER_ID'), 'default' => true],
        ['id' => 'CONTEXT', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_CONTEXT'), 'default' => false],
    ];
}

$filterOptions = new FilterOptions($gridId, $filterFields);
$filterData = $filterOptions->getFilter($filterFields);

if ($request->isPost() && (string)$request->getPost('action') === 'clear') {
    if ($POST_RIGHT < 'W') {
        AdminJsonResponse::send(['success' => false, 'error' => Loc::getMessage('ACCESS_DENIED')]);
    }

    if (!check_bitrix_sessid()) {
        AdminJsonResponse::send(['success' => false, 'error' => 'Invalid sessid']);
    }

    $scope = (string)$request->getPost('scope');
    $clearFilter = $scope === 'filter' ? $filterData : [];
    $clearFile = $request->getPost('clear_file') === 'Y';

    try {
        $deleted = 0;
        $auditEntityType = 'event_log';

        if ($tab === 'audit') {
            $deleted = EventLogAdminService::clearAuditLog($clearFilter);
            ModuleEventLogService::info(
                'audit_log_cleared',
                Loc::getMessage('ZR_PAIDACCESS_LOGS_CLEAR_AUDIT', ['#COUNT#' => $deleted]),
                ['scope' => $scope, 'deleted' => $deleted]
            );
        } elseif ($tab === 'gateway') {
            $auditEntityType = 'gateway_log';
            $deleted = GatewayTransactionAdminService::clearLog($clearFilter);
        } else {
            $deleted = EventLogAdminService::clearEventLog($clearFilter);
            if ($clearFile) {
                LogCleanupAdminService::clearFileLog();
            }
        }

        if ($tab !== 'audit') {
            AuditLogService::log(
                $auditEntityType,
                0,
                'clear',
                null,
                json_encode([
                    'deleted' => $deleted,
                    'scope' => $scope,
                    'clearFile' => $clearFile && $tab === 'events',
                ], JSON_UNESCAPED_UNICODE),
                Loc::getMessage('ZR_PAIDACCESS_LOGS_CLEAR_AUDIT', ['#COUNT#' => $deleted])
            );
        }

        AdminJsonResponse::send(['success' => true, 'deleted' => $deleted]);
    } catch (\Throwable $e) {
        AdminJsonResponse::send(['success' => false, 'error' => $e->getMessage()]);
    }
}

$nav = new PageNavigation($gridId);
$nav->allowAllRecords(false)
    ->setPageSize((int)($navParams['nPageSize'] ?? 20))
    ->initFromUri();

$order = $sort['sort'] ?? ['ID' => 'DESC'];
$limit = $nav->getLimit();
$offset = $nav->getOffset();

if ($tab === 'audit') {
    $result = EventLogAdminService::getAuditLogRows($filterData, $limit, $offset, $order);
} elseif ($tab === 'gateway') {
    if ((int)$request->get('GATEWAY_ID') > 0 && empty($filterData['GATEWAY_ID'])) {
        $filterData['GATEWAY_ID'] = (int)$request->get('GATEWAY_ID');
    }
    $result = GatewayTransactionAdminService::getRows($filterData, $limit, $offset, $order);
} else {
    $result = EventLogAdminService::getEventLogRows($filterData, $limit, $offset, $order);
}

$nav->setRecordCount($result['total']);

$rows = [];
foreach ($result['rows'] as $item) {
    $columnsData = $item;

    if ($tab === 'gateway') {
        $success = (string)($item['SUCCESS'] ?? 'N') === 'Y';
        $columnsData['EVENT_TYPE'] = htmlspecialcharsbx(
            GatewayTransactionAdminService::eventTypeTitle((string)($item['EVENT_TYPE'] ?? ''))
        );
        $columnsData['GATEWAY_ID'] = AdminEntityLinkRenderer::gateway((int)($item['GATEWAY_ID'] ?? 0), LANGUAGE_ID);
        $columnsData['PAYMENT_ID'] = AdminEntityLinkRenderer::payment((int)($item['PAYMENT_ID'] ?? 0), LANGUAGE_ID);
        $columnsData['HTTP_CODE'] = (int)($item['HTTP_CODE'] ?? 0) > 0
            ? (string)(int)$item['HTTP_CODE']
            : '<span class="zr-paidaccess-muted">—</span>';
        $columnsData['SUCCESS'] = StatusBadgeRenderer::render(
            $success ? 'OK' : 'FAIL',
            $success ? StatusBadgeRenderer::STYLE_COMPLETED : StatusBadgeRenderer::STYLE_DANGER
        );
        $columnsData['GATEWAY_STATUS'] = htmlspecialcharsbx((string)($item['GATEWAY_STATUS'] ?? '')) ?: '<span class="zr-paidaccess-muted">—</span>';
        $columnsData['ERROR_MESSAGE'] = htmlspecialcharsbx((string)($item['ERROR_MESSAGE'] ?? '')) ?: '<span class="zr-paidaccess-muted">—</span>';
        $columnsData['PAYLOAD'] = GatewayTransactionContextRenderer::render(
            (string)($item['REQUEST_DATA'] ?? ''),
            (string)($item['RESPONSE_DATA'] ?? '')
        );
    } elseif ($tab === 'events') {
        if (isset($item['LEVEL'])) {
            $style = $item['LEVEL'] === ModuleLogLevel::ERROR
                ? StatusBadgeRenderer::STYLE_DANGER
                : ($item['LEVEL'] === ModuleLogLevel::WARNING ? StatusBadgeRenderer::STYLE_WARNING : StatusBadgeRenderer::STYLE_MUTED);
            $columnsData['LEVEL'] = StatusBadgeRenderer::render((string)$item['LEVEL'], $style);
        }
        $columnsData['PAYMENT_ID'] = AdminEntityLinkRenderer::payment((int)($item['PAYMENT_ID'] ?? 0), LANGUAGE_ID);
        $columnsData['USER_ID'] = AdminEntityLinkRenderer::user((int)($item['USER_ID'] ?? 0), LANGUAGE_ID);
        $columnsData['CONTEXT'] = EventLogContextRenderer::render(
            (string)($item['CODE'] ?? ''),
            (string)($item['CONTEXT'] ?? '')
        );
    } else {
        $entityType = (string)($item['ENTITY_TYPE'] ?? '');
        $entityId = (int)($item['ENTITY_ID'] ?? 0);
        $action = (string)($item['ACTION'] ?? '');

        $columnsData['ENTITY_TYPE'] = htmlspecialcharsbx(
            AdminEntityLinkRenderer::auditEntityTypeLabel($entityType)
        );

        if ($action === 'delete' && strtolower($entityType) === 'payment' && $entityId > 0) {
            $columnsData['ENTITY_ID'] = htmlspecialcharsbx('#' . $entityId)
                . ' <span class="zr-paidaccess-muted">(удалён)</span>';
        } else {
            $columnsData['ENTITY_ID'] = AdminEntityLinkRenderer::auditEntity(
                $entityType,
                $entityId,
                LANGUAGE_ID
            );
        }

        $columnsData['ACTION'] = htmlspecialcharsbx(AuditContextRenderer::actionTitle($action));
        $columnsData['CONTEXT'] = AuditContextRenderer::render(
            $entityType,
            $action,
            (string)($item['OLD_VALUE'] ?? ''),
            (string)($item['NEW_VALUE'] ?? ''),
            LANGUAGE_ID
        );
        $columnsData['ADMIN_USER_ID'] = AdminEntityLinkRenderer::user((int)($item['ADMIN_USER_ID'] ?? 0), LANGUAGE_ID);
    }

    $rows[] = [
        'id' => (string)$item['ID'],
        'columns' => $columnsData,
    ];
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$lang = LANGUAGE_ID;
$eventsUrl = 'zr_paidaccess_logs.php?lang=' . urlencode($lang) . '&tab=events';
$auditUrl = 'zr_paidaccess_logs.php?lang=' . urlencode($lang) . '&tab=audit';
$gatewayUrl = 'zr_paidaccess_logs.php?lang=' . urlencode($lang) . '&tab=gateway';
?>
<p>
    <?php if ($tab === 'events'): ?>
        <b><?= Loc::getMessage('ZR_PAIDACCESS_LOGS_TAB_EVENTS') ?></b>
    <?php else: ?>
        <a href="<?= htmlspecialcharsbx($eventsUrl) ?>"><?= Loc::getMessage('ZR_PAIDACCESS_LOGS_TAB_EVENTS') ?></a>
    <?php endif; ?>
    |
    <?php if ($tab === 'gateway'): ?>
        <b><?= Loc::getMessage('ZR_PAIDACCESS_LOGS_TAB_GATEWAY') ?></b>
    <?php else: ?>
        <a href="<?= htmlspecialcharsbx($gatewayUrl) ?>"><?= Loc::getMessage('ZR_PAIDACCESS_LOGS_TAB_GATEWAY') ?></a>
    <?php endif; ?>
    |
    <?php if ($tab === 'audit'): ?>
        <b><?= Loc::getMessage('ZR_PAIDACCESS_LOGS_TAB_AUDIT') ?></b>
    <?php else: ?>
        <a href="<?= htmlspecialcharsbx($auditUrl) ?>"><?= Loc::getMessage('ZR_PAIDACCESS_LOGS_TAB_AUDIT') ?></a>
    <?php endif; ?>
</p>
<?php if ($POST_RIGHT >= 'W'): ?>
    <p class="zr-paidaccess-log-actions">
        <button type="button" class="adm-btn" id="zr_paidaccess_clear_all">
            <?= Loc::getMessage('ZR_PAIDACCESS_LOGS_CLEAR_ALL') ?>
        </button>
        <button type="button" class="adm-btn" id="zr_paidaccess_clear_filter">
            <?= Loc::getMessage('ZR_PAIDACCESS_LOGS_CLEAR_FILTER') ?>
        </button>
        <?php if ($tab === 'events'): ?>
            <label class="zr-paidaccess-log-actions__file">
                <input type="checkbox" id="zr_clear_file_log" value="Y">
                <?= Loc::getMessage('ZR_PAIDACCESS_LOGS_CLEAR_FILE') ?>
            </label>
        <?php endif; ?>
    </p>
    <script>
        (function () {
            var confirmAll = '<?= CUtil::JSEscape(Loc::getMessage('ZR_PAIDACCESS_LOGS_CLEAR_CONFIRM_ALL', ['#COUNT#' => (int)$result['total']])) ?>';
            var confirmFilter = '<?= CUtil::JSEscape(Loc::getMessage('ZR_PAIDACCESS_LOGS_CLEAR_CONFIRM_FILTER', ['#COUNT#' => (int)$result['total']])) ?>';

            function clearLog(scope, message) {
                if (!confirm(message)) {
                    return;
                }

                var clearFileEl = BX('zr_clear_file_log');
                var clearFile = clearFileEl && clearFileEl.checked ? 'Y' : 'N';

                BX.ajax({
                    method: 'POST',
                    url: window.location.href,
                    dataType: 'json',
                    data: {
                        sessid: BX.bitrix_sessid(),
                        action: 'clear',
                        scope: scope,
                        clear_file: clearFile
                    },
                    onsuccess: function (response) {
                        if (response && response.success) {
                            window.location.reload();
                            return;
                        }
                        alert((response && response.error) ? response.error : 'Ошибка очистки журнала');
                    },
                    onfailure: function () {
                        alert('Ошибка запроса');
                    }
                });
            }

            var btnAll = BX('zr_paidaccess_clear_all');
            if (btnAll) {
                BX.bind(btnAll, 'click', function () {
                    clearLog('all', confirmAll);
                });
            }

            var btnFilter = BX('zr_paidaccess_clear_filter');
            if (btnFilter) {
                BX.bind(btnFilter, 'click', function () {
                    clearLog('filter', confirmFilter);
                });
            }
        })();
    </script>
<?php endif; ?>
<?php

$APPLICATION->IncludeComponent(
    'bitrix:main.ui.filter',
    '',
    [
        'FILTER_ID' => $gridId,
        'GRID_ID' => $gridId,
        'FILTER' => $filterFields,
        'ENABLE_LIVE_SEARCH' => true,
        'ENABLE_LABEL' => true,
    ]
);

$APPLICATION->IncludeComponent(
    'bitrix:main.ui.grid',
    '',
    [
        'GRID_ID' => $gridId,
        'COLUMNS' => $columns,
        'ROWS' => $rows,
        'SHOW_ROW_CHECKBOXES' => false,
        'NAV_OBJECT' => $nav,
        'AJAX_MODE' => 'Y',
        'AJAX_ID' => $gridId,
        'PAGE_SIZES' => [
            ['NAME' => '20', 'VALUE' => '20'],
            ['NAME' => '50', 'VALUE' => '50'],
            ['NAME' => '100', 'VALUE' => '100'],
        ],
        'TOTAL_ROWS_COUNT' => $result['total'],
        'SHOW_TOTAL_COUNTER' => true,
        'SHOW_PAGESIZE' => true,
        'SHOW_NAVIGATION_PANEL' => true,
    ]
);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
