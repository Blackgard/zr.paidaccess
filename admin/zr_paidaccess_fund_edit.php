<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Grid;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SiteTable;
use Bitrix\Main\UI\Extension;
use Bitrix\Main\UI\Filter\Options as FilterOptions;
use Bitrix\Main\UI\PageNavigation;
use Zr\PaidAccess\Admin\FundAdminService;
use Zr\PaidAccess\Enum\FundMovementSource;
use Zr\PaidAccess\Enum\FundMovementType;
use Zr\PaidAccess\Fund\FundMovementRepository;
use Zr\PaidAccess\Fund\FundRepository;
use Zr\PaidAccess\PaidAccessCore;

$moduleId = PaidAccessCore::MODULE_ID;

if (!Loader::includeModule($moduleId)) {
    return;
}

Loc::loadMessages(__FILE__);

$request = Context::getCurrent()->getRequest();
$languageId = Application::getInstance()->getContext()->getLanguage();

$RIGHT = $APPLICATION->GetGroupRight($moduleId);
if ($RIGHT < 'W') {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}

Extension::load(['ui.buttons', 'ui.forms', 'ui.alerts', 'ui.notification']);
\Bitrix\Main\Page\Asset::getInstance()->addCss('/bitrix/themes/.default/zr.paidaccess.css');

$id = (int)($request->get('ID') ?? 0);
$isEditMode = $id > 0;

$APPLICATION->SetTitle(
    $isEditMode
        ? Loc::getMessage('ZR_PAIDACCESS_FUND_EDIT_TITLE') . ' #' . $id
        : Loc::getMessage('ZR_PAIDACCESS_FUND_ADD')
);

$formValues = [
    'SITE_ID' => PaidAccessCore::normalizeSiteId(SITE_ID),
    'CODE' => 'default',
    'NAME' => Loc::getMessage('ZR_PAIDACCESS_DEFAULT_FUND_NAME'),
    'IS_DEFAULT' => 'Y',
    'ACTIVE' => 'Y',
];

$message = null;
$bVarsFromForm = false;

if ($isEditMode) {
    $fundRow = FundRepository::getById($id);
    if (!$fundRow) {
        require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
        CAdminMessage::ShowMessage(Loc::getMessage('ZR_PAIDACCESS_NOT_FOUND'));
        require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';

        return;
    }
    $formValues = array_merge($formValues, $fundRow);
}

if ($request->isPost() && check_bitrix_sessid()) {
    $save = $request->getPost('save');
    $apply = $request->getPost('apply');

    if ($save !== null && $save !== '' || $apply !== null && $apply !== '') {
        $postData = [
            'SITE_ID' => $request->getPost('SITE_ID'),
            'CODE' => $request->getPost('CODE'),
            'NAME' => $request->getPost('NAME'),
            'IS_DEFAULT' => $request->getPost('IS_DEFAULT') === 'Y' ? 'Y' : 'N',
            'ACTIVE' => $request->getPost('ACTIVE') === 'Y' ? 'Y' : 'N',
        ];

        $formValues = array_merge($formValues, $postData);
        $bVarsFromForm = true;

        try {
            $wasNew = !$isEditMode;
            $id = FundAdminService::saveFund($isEditMode ? $id : 0, $postData);
            $isEditMode = true;

            $fundRow = FundRepository::getById($id);
            if ($fundRow) {
                $formValues = array_merge($formValues, $fundRow);
            }
            $bVarsFromForm = false;

            if ($save !== null && $save !== '') {
                LocalRedirect('zr_paidaccess_funds.php?lang=' . $languageId);
            }

            if ($wasNew) {
                LocalRedirect(
                    $APPLICATION->GetCurPage() . '?ID=' . $id . '&lang=' . $languageId,
                    false,
                    '301 Moved Permanently'
                );
            }

            $message = new CAdminMessage([
                'MESSAGE' => Loc::getMessage('ZR_PAIDACCESS_SAVED'),
                'TYPE' => 'OK',
            ]);
        } catch (\Throwable $e) {
            $message = new CAdminMessage([
                'MESSAGE' => Loc::getMessage('ZR_PAIDACCESS_SAVE_ERROR') . ': ' . $e->getMessage(),
                'TYPE' => 'ERROR',
            ]);
        }
    }
}

$siteItems = [];
$siteResult = SiteTable::getList([
    'select' => ['LID', 'NAME'],
    'order' => ['SORT' => 'ASC'],
]);
while ($site = $siteResult->fetch()) {
    $siteItems[$site['LID']] = '[' . $site['LID'] . '] ' . $site['NAME'];
}

$aTabs = [
    ['DIV' => 'main', 'TAB' => Loc::getMessage('ZR_PAIDACCESS_TAB_MAIN'), 'TITLE' => Loc::getMessage('ZR_PAIDACCESS_TAB_MAIN')],
];

if ($isEditMode) {
    $aTabs[] = [
        'DIV' => 'movements',
        'TAB' => Loc::getMessage('ZR_PAIDACCESS_TAB_MOVEMENTS'),
        'TITLE' => Loc::getMessage('ZR_PAIDACCESS_TAB_MOVEMENTS'),
    ];
}

$tabControl = new CAdminTabControl('tabControl', $aTabs);
$formAction = $APPLICATION->GetCurPage() . ($id > 0 ? '?ID=' . $id . '&lang=' . $languageId : '?lang=' . $languageId);

$movementGridId = 'zr_paidaccess_fund_movements_' . $id;
$movementRows = [];
$movementNav = null;
$movementFilterConfig = null;
$movementGridParameters = null;

if ($isEditMode) {
    $movementGridOptions = new Grid\Options($movementGridId);
    $movementSort = $movementGridOptions->GetSorting([
        'sort' => ['DATE_CREATE' => 'DESC', 'ID' => 'DESC'],
        'vars' => ['by' => 'by', 'order' => 'order'],
    ]);
    $movementNavParams = $movementGridOptions->GetNavParams();

    $movementFilterFields = [
        [
            'id' => 'TYPE',
            'name' => Loc::getMessage('ZR_PAIDACCESS_COL_MOVEMENT_TYPE'),
            'type' => 'list',
            'items' => ['' => ''] + FundAdminService::getMovementTypeTitles(),
            'default' => true,
        ],
        [
            'id' => 'SOURCE',
            'name' => Loc::getMessage('ZR_PAIDACCESS_COL_MOVEMENT_SOURCE'),
            'type' => 'list',
            'items' => ['' => ''] + FundAdminService::getMovementSourceTitles(),
            'default' => true,
        ],
    ];

    $movementFilterOption = new FilterOptions($movementGridId);
    $movementFilterData = $movementFilterOption->getFilter($movementFilterFields);
    $movementFilter = FundAdminService::buildMovementFilter($id, $movementFilterData);

    $movementNav = new PageNavigation($movementGridId);
    $movementNav->allowAllRecords(true)
        ->setPageSize($movementNavParams['nPageSize'])
        ->initFromUri();

    $movementTotal = FundMovementRepository::getCount($movementFilter);
    $movementNav->setRecordCount($movementTotal);

    $movementItems = FundMovementRepository::getList(
        $movementFilter,
        $movementSort['sort'],
        $movementNav->getLimit(),
        $movementNav->getOffset()
    );

    $movementIds = array_map(static fn (array $row): int => (int)($row['ID'] ?? 0), $movementItems);
    $allocationsByMovement = FundAdminService::getAllocationsGroupedByMovementIds($movementIds);

    foreach ($movementItems as $movement) {
        $movementId = (int)$movement['ID'];
        $type = (string)($movement['TYPE'] ?? '');
        $amount = (float)($movement['AMOUNT'] ?? 0);
        $amountFormatted = number_format($amount, 0, '.', ' ') . ' ₽';
        $isIncome = $type === FundMovementType::INCOME;
        $source = (string)($movement['SOURCE'] ?? '');
        $allocations = $allocationsByMovement[$movementId] ?? [];
        $participantsHtml = '—';
        if ($type === FundMovementType::EXPENSE && $source === FundMovementSource::ADMIN) {
            $summary = FundAdminService::formatMovementParticipantsSummary($movementId, $allocations);
            $participantsHtml = '<a href="zr_paidaccess_fund_expense_view.php?ID=' . $movementId
                . '&lang=' . htmlspecialcharsbx($languageId) . '">'
                . htmlspecialcharsbx($summary) . '</a>';
        }

        $movementRows[] = [
            'id' => $movementId,
            'data' => $movement,
            'columns' => [
                'ID' => $movementId,
                'DATE_CREATE' => htmlspecialcharsbx(FundAdminService::formatMovementDate($movement)),
                'TYPE' => htmlspecialcharsbx(FundAdminService::getMovementTypeTitle($type)),
                'AMOUNT' => '<span style="color:' . ($isIncome ? '#2e7d32' : '#c62828') . ';">'
                    . ($isIncome ? '+' : '−') . htmlspecialcharsbx($amountFormatted) . '</span>',
                'SOURCE' => htmlspecialcharsbx(FundAdminService::getMovementSourceTitle((string)($movement['SOURCE'] ?? ''))),
                'DESCRIPTION' => htmlspecialcharsbx((string)($movement['DESCRIPTION'] ?? '')),
                'REFERENCE' => htmlspecialcharsbx(FundAdminService::formatMovementReference($movement)),
                'PARTICIPANTS' => $participantsHtml,
            ],
        ];
    }

    $movementFilterConfig = [
        'FILTER_ID' => $movementGridId,
        'GRID_ID' => $movementGridId,
        'FILTER' => $movementFilterFields,
        'ENABLE_LABEL' => true,
        'ENABLE_LIVE_SEARCH' => false,
    ];

    $movementGridParameters = [
        'GRID_ID' => $movementGridId,
        'COLUMNS' => [
            ['id' => 'ID', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_ID'), 'sort' => 'ID', 'default' => true],
            ['id' => 'DATE_CREATE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_DATE'), 'sort' => 'DATE_CREATE', 'default' => true],
            ['id' => 'TYPE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_MOVEMENT_TYPE'), 'sort' => 'TYPE', 'default' => true],
            ['id' => 'AMOUNT', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_AMOUNT'), 'sort' => 'AMOUNT', 'default' => true],
            ['id' => 'SOURCE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_MOVEMENT_SOURCE'), 'sort' => 'SOURCE', 'default' => true],
            ['id' => 'DESCRIPTION', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_DESCRIPTION'), 'default' => true],
            ['id' => 'PARTICIPANTS', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_PARTICIPANTS'), 'default' => true],
            ['id' => 'REFERENCE', 'name' => Loc::getMessage('ZR_PAIDACCESS_COL_REFERENCE'), 'default' => false],
        ],
        'ROWS' => $movementRows,
        'NAV_OBJECT' => $movementNav,
        'AJAX_MODE' => 'Y',
        'AJAX_ID' => \CAjax::getComponentID('bitrix:main.ui.grid', '.default', ''),
        'PAGE_SIZES' => [
            ['NAME' => '20', 'VALUE' => '20'],
            ['NAME' => '50', 'VALUE' => '50'],
        ],
        'AJAX_OPTION_JUMP' => 'N',
        'SHOW_ROW_ACTIONS_MENU' => false,
        'SHOW_GRID_SETTINGS_MENU' => true,
        'SHOW_NAVIGATION_PANEL' => true,
        'SHOW_PAGINATION' => true,
        'SHOW_TOTAL_COUNTER' => true,
        'SHOW_PAGESIZE' => true,
        'ALLOW_COLUMNS_SORT' => true,
        'ALLOW_SORT' => true,
    ];
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>

<div class="zr-paidaccess-admin">
<?php
$aContext = [
    [
        'TEXT' => Loc::getMessage('ZR_PAIDACCESS_LIST_BTN'),
        'LINK' => 'zr_paidaccess_funds.php?lang=' . $languageId,
        'TITLE' => Loc::getMessage('ZR_PAIDACCESS_LIST_BTN'),
        'ICON' => 'btn_list',
    ],
];

if ($isEditMode) {
    $aContext[] = [
        'TEXT' => Loc::getMessage('ZR_PAIDACCESS_ADD_MOVEMENT'),
        'LINK' => 'zr_paidaccess_fund_movement_edit.php?FUND_ID=' . $id . '&lang=' . $languageId,
        'TITLE' => Loc::getMessage('ZR_PAIDACCESS_ADD_MOVEMENT'),
        'ICON' => 'btn_new',
    ];
}

$contextMenu = new CAdminContextMenu($aContext);
$contextMenu->Show();

if ($message) {
    echo $message->Show();
}
?>

<form method="post" action="<?= htmlspecialcharsbx($formAction) ?>" name="zr_paidaccess_fund_form">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="lang" value="<?= htmlspecialcharsbx($languageId) ?>">
    <?php if ($id > 0): ?>
        <input type="hidden" name="ID" value="<?= $id ?>">
    <?php endif; ?>

    <?php
    $tabControl->Begin();
$tabControl->BeginNextTab();
?>

    <tr>
        <td colspan="2">
            <table class="edit-table" style="width:100%;">
                <?php if ($isEditMode): ?>
                <tr>
                    <td width="40%" class="adm-detail-content-cell-l">ID:</td>
                    <td width="60%" class="adm-detail-content-cell-r"><?= (int)$id ?></td>
                </tr>
                <tr>
                    <td class="adm-detail-content-cell-l"><?= Loc::getMessage('ZR_PAIDACCESS_COL_BALANCE') ?>:</td>
                    <td class="adm-detail-content-cell-r">
                        <strong><?= htmlspecialcharsbx(FundAdminService::getFundBalanceFormatted($id)) ?></strong>
                    </td>
                </tr>
                <?php endif; ?>

                <tr>
                    <td class="adm-detail-content-cell-l">
                        <?= Loc::getMessage('ZR_PAIDACCESS_FIELD_SITE') ?>:<span class="required">*</span>
                    </td>
                    <td class="adm-detail-content-cell-r">
                        <?php if ($isEditMode): ?>
                            <input type="hidden" name="SITE_ID" value="<?= htmlspecialcharsbx((string)$formValues['SITE_ID']) ?>">
                            <?= htmlspecialcharsbx((string)$formValues['SITE_ID']) ?>
                        <?php else: ?>
                            <select name="SITE_ID" class="adm-select" required>
                                <?php foreach ($siteItems as $lid => $label): ?>
                                    <option value="<?= htmlspecialcharsbx($lid) ?>"
                                        <?= (string)$formValues['SITE_ID'] === $lid ? ' selected' : '' ?>>
                                        <?= htmlspecialcharsbx($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <td class="adm-detail-content-cell-l">
                        <?= Loc::getMessage('ZR_PAIDACCESS_COL_CODE') ?>:<span class="required">*</span>
                    </td>
                    <td class="adm-detail-content-cell-r">
                        <?php if ($isEditMode): ?>
                            <input type="hidden" name="CODE" value="<?= htmlspecialcharsbx((string)$formValues['CODE']) ?>">
                            <code><?= htmlspecialcharsbx((string)$formValues['CODE']) ?></code>
                        <?php else: ?>
                            <input type="text" name="CODE" class="adm-input" maxlength="64" required
                                   value="<?= htmlspecialcharsbx((string)$formValues['CODE']) ?>">
                            <div class="adm-input-description"><?= Loc::getMessage('ZR_PAIDACCESS_FIELD_CODE_HINT') ?></div>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <td class="adm-detail-content-cell-l">
                        <?= Loc::getMessage('ZR_PAIDACCESS_COL_NAME') ?>:<span class="required">*</span>
                    </td>
                    <td class="adm-detail-content-cell-r">
                        <input type="text" name="NAME" class="adm-input" maxlength="255" required
                               value="<?= htmlspecialcharsbx((string)$formValues['NAME']) ?>">
                    </td>
                </tr>

                <tr>
                    <td class="adm-detail-content-cell-l"><?= Loc::getMessage('ZR_PAIDACCESS_FIELD_DEFAULT') ?>:</td>
                    <td class="adm-detail-content-cell-r">
                        <input type="checkbox" name="IS_DEFAULT" value="Y"
                            <?= ($formValues['IS_DEFAULT'] ?? 'N') === 'Y' ? ' checked' : '' ?>>
                    </td>
                </tr>

                <tr>
                    <td class="adm-detail-content-cell-l"><?= Loc::getMessage('ZR_PAIDACCESS_FIELD_ACTIVE') ?>:</td>
                    <td class="adm-detail-content-cell-r">
                        <input type="checkbox" name="ACTIVE" value="Y"
                            <?= ($formValues['ACTIVE'] ?? 'Y') === 'Y' ? ' checked' : '' ?>>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <?php
$tabControl->EndTab();

if ($isEditMode && $movementGridParameters !== null) {
    $tabControl->BeginNextTab();
    ?>
        <tr>
            <td colspan="2">
                <?php
            $APPLICATION->IncludeComponent(
                'bitrix:main.ui.filter',
                '',
                $movementFilterConfig,
                false
            );

    $APPLICATION->IncludeComponent(
        'bitrix:main.ui.grid',
        '',
        $movementGridParameters
    );
    ?>
            </td>
        </tr>
        <?php
        $tabControl->EndTab();
}

$tabControl->Buttons([
    'back_url' => 'zr_paidaccess_funds.php?lang=' . $languageId,
]);
$tabControl->End();
?>
</form>
</div>

<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
