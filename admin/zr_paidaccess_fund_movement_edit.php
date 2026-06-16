<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UI\Extension;
use Zr\PaidAccess\Admin\FundAdminService;
use Zr\PaidAccess\Enum\FundMovementSource;
use Zr\PaidAccess\Enum\FundMovementType;
use Zr\PaidAccess\Fund\FundRepository;
use Zr\PaidAccess\Options\ModuleOptionsProvider;
use Zr\PaidAccess\PaidAccessCore;

$moduleId = PaidAccessCore::MODULE_ID;

if (!Loader::includeModule($moduleId)) {
    return;
}

Loc::loadMessages(__FILE__);

$request = Context::getCurrent()->getRequest();
$languageId = Application::getInstance()->getContext()->getLanguage();

global $USER;

$RIGHT = $APPLICATION->GetGroupRight($moduleId);
if ($RIGHT < 'W') {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}

Extension::load(['ui.buttons', 'ui.forms', 'ui.alerts', 'ui.notification']);
\Bitrix\Main\Page\Asset::getInstance()->addCss('/bitrix/themes/.default/zr.paidaccess.css');

$fundId = (int)($request->get('FUND_ID') ?? 0);
$fund = $fundId > 0 ? FundRepository::getById($fundId) : null;

if (!$fund) {
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
    CAdminMessage::ShowMessage(Loc::getMessage('ZR_PAIDACCESS_FUND_REQUIRED'));
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';

    return;
}

$APPLICATION->SetTitle(Loc::getMessage('ZR_PAIDACCESS_MOVEMENT_ADD_TITLE'));

$formValues = [
    'TYPE' => FundMovementType::EXPENSE,
    'AMOUNT' => '',
    'DESCRIPTION' => '',
    'EXTERNAL_REF' => '',
];

$message = null;
$bVarsFromForm = false;

if ($request->isPost() && check_bitrix_sessid()) {
    $save = $request->getPost('save');
    $apply = $request->getPost('apply');

    if ($save !== null && $save !== '' || $apply !== null && $apply !== '') {
        $postData = [
            'TYPE' => (string)$request->getPost('TYPE'),
            'AMOUNT' => $request->getPost('AMOUNT'),
            'DESCRIPTION' => $request->getPost('DESCRIPTION'),
            'EXTERNAL_REF' => $request->getPost('EXTERNAL_REF'),
        ];

        $formValues = array_merge($formValues, $postData);
        $bVarsFromForm = true;

        try {
            $movementId = FundAdminService::createManualMovement($fundId, $postData, (int)$USER->GetID());

            if ($save !== null && $save !== '') {
                if ((string)$postData['TYPE'] === FundMovementType::EXPENSE) {
                    LocalRedirect('zr_paidaccess_fund_expense_view.php?ID=' . $movementId . '&lang=' . $languageId);
                }
                LocalRedirect('zr_paidaccess_fund_edit.php?ID=' . $fundId . '&lang=' . $languageId . '#tab_movements');
            }

            if ((string)$postData['TYPE'] === FundMovementType::EXPENSE) {
                LocalRedirect('zr_paidaccess_fund_expense_view.php?ID=' . $movementId . '&lang=' . $languageId);
            }

            LocalRedirect(
                'zr_paidaccess_fund_edit.php?ID=' . $fundId . '&lang=' . $languageId . '#tab_movements',
                false,
                '301 Moved Permanently'
            );
        } catch (\Throwable $e) {
            $message = new CAdminMessage([
                'MESSAGE' => Loc::getMessage('ZR_PAIDACCESS_SAVE_ERROR') . ': ' . $e->getMessage(),
                'TYPE' => 'ERROR',
            ]);
        }
    }
}

$typeTitles = FundAdminService::getMovementTypeTitles();
$formAction = $APPLICATION->GetCurPage() . '?FUND_ID=' . $fundId . '&lang=' . $languageId;

$fundSiteId = (string)($fund['SITE_ID'] ?? '');
$allocationMode = PaidAccessCore::getFundExpenseAllocationMode($fundSiteId);
$allocationModeTitles = ModuleOptionsProvider::getFundExpenseAllocationModeOptions();
$allocationModeLabel = $allocationModeTitles[$allocationMode] ?? $allocationMode;
if ($allocationMode === PaidAccessCore::FUND_EXPENSE_ALLOCATION_MODE_RANDOM) {
    $allocationModeLabel .= ' (N=' . PaidAccessCore::getFundExpenseRandomParticipantCount($fundSiteId) . ')';
}
$settingsUrl = '/bitrix/admin/settings.php?mid=zr.paidaccess&lang=' . urlencode($languageId);
$isExpenseSelected = (string)$formValues['TYPE'] === FundMovementType::EXPENSE;

$aTabs = [
    [
        'DIV' => 'main',
        'TAB' => Loc::getMessage('ZR_PAIDACCESS_TAB_MOVEMENT_FORM'),
        'TITLE' => Loc::getMessage('ZR_PAIDACCESS_TAB_MOVEMENT_FORM'),
    ],
];
$tabControl = new CAdminTabControl('tabControl', $aTabs);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>

<div class="zr-paidaccess-admin">
<?php
$aContext = [
    [
        'TEXT' => Loc::getMessage('ZR_PAIDACCESS_BACK_TO_FUND'),
        'LINK' => 'zr_paidaccess_fund_edit.php?ID=' . $fundId . '&lang=' . $languageId,
        'TITLE' => Loc::getMessage('ZR_PAIDACCESS_BACK_TO_FUND'),
        'ICON' => 'btn_list',
    ],
];

$contextMenu = new CAdminContextMenu($aContext);
$contextMenu->Show();

if ($message) {
    echo $message->Show();
}
?>

<form method="post"
      action="<?= htmlspecialcharsbx($formAction) ?>"
      name="zr_paidaccess_fund_movement_form"
      id="zr_paidaccess_fund_movement_form">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="lang" value="<?= htmlspecialcharsbx($languageId) ?>">
    <input type="hidden" name="FUND_ID" value="<?= $fundId ?>">

    <?php
    $tabControl->Begin();
$tabControl->BeginNextTab();
?>

    <tr>
        <td colspan="2">
            <div class="zr-paidaccess-fund-summary">
                <div class="zr-paidaccess-fund-summary__main">
                    <div class="zr-paidaccess-fund-summary__label"><?= Loc::getMessage('ZR_PAIDACCESS_FUND_LABEL') ?></div>
                    <div class="zr-paidaccess-fund-summary__name"><?= htmlspecialcharsbx((string)$fund['NAME']) ?></div>
                    <div class="zr-paidaccess-fund-summary__meta">
                        <?= htmlspecialcharsbx((string)$fund['SITE_ID']) ?>
                        / <?= htmlspecialcharsbx((string)$fund['CODE']) ?>
                    </div>
                </div>
                <div class="zr-paidaccess-fund-summary__balance">
                    <span class="zr-paidaccess-fund-summary__balance-label">
                        <?= Loc::getMessage('ZR_PAIDACCESS_COL_BALANCE') ?>
                    </span>
                    <span class="zr-paidaccess-fund-summary__balance-value">
                        <?= htmlspecialcharsbx(FundAdminService::getFundBalanceFormatted($fundId)) ?>
                    </span>
                </div>
            </div>

            <div class="adm-detail-content-item-block">
                <table class="edit-table">
                    <tr>
                        <td width="40%" class="adm-detail-content-cell-l">
                            <?= Loc::getMessage('ZR_PAIDACCESS_COL_MOVEMENT_TYPE') ?>:<span class="required">*</span>
                        </td>
                        <td width="60%" class="adm-detail-content-cell-r">
                            <select name="TYPE" id="zr_movement_type" class="select-field" required>
                                <?php foreach ($typeTitles as $code => $title): ?>
                                    <option value="<?= htmlspecialcharsbx($code) ?>"
                                        <?= (string)$formValues['TYPE'] === $code ? ' selected' : '' ?>>
                                        <?= htmlspecialcharsbx($title) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <td class="adm-detail-content-cell-l">
                            <?= Loc::getMessage('ZR_PAIDACCESS_COL_AMOUNT') ?>:<span class="required">*</span>
                        </td>
                        <td class="adm-detail-content-cell-r">
                            <input type="number"
                                   name="AMOUNT"
                                   class="select-field zr-paidaccess-field-amount"
                                   step="0.01"
                                   min="0.01"
                                   required
                                   placeholder="0.00"
                                   value="<?= htmlspecialcharsbx((string)$formValues['AMOUNT']) ?>">
                        </td>
                    </tr>

                    <tr>
                        <td class="adm-detail-content-cell-l">
                            <?= Loc::getMessage('ZR_PAIDACCESS_COL_DESCRIPTION') ?>:<span class="required">*</span>
                        </td>
                        <td class="adm-detail-content-cell-r">
                            <textarea name="DESCRIPTION"
                                      class="select-field"
                                      rows="3"
                                      maxlength="512"
                                      required
                                      placeholder="<?= Loc::getMessage('ZR_PAIDACCESS_FIELD_DESCRIPTION_PLACEHOLDER') ?>"><?= htmlspecialcharsbx((string)$formValues['DESCRIPTION']) ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <td class="adm-detail-content-cell-l"><?= Loc::getMessage('ZR_PAIDACCESS_FIELD_EXTERNAL_REF') ?>:</td>
                        <td class="adm-detail-content-cell-r">
                            <input type="text"
                                   name="EXTERNAL_REF"
                                   class="select-field"
                                   maxlength="64"
                                   value="<?= htmlspecialcharsbx((string)$formValues['EXTERNAL_REF']) ?>">
                            <div class="adm-input-description">
                                <?= Loc::getMessage('ZR_PAIDACCESS_FIELD_EXTERNAL_REF_HINT') ?>
                            </div>
                        </td>
                    </tr>
                </table>

                <div id="zr-expense-allocation-callout"
                     class="zr-paidaccess-callout<?= $isExpenseSelected ? '' : ' is-hidden' ?>">
                    <div class="zr-paidaccess-callout__title">
                        <?= Loc::getMessage('ZR_PAIDACCESS_EXPENSE_ALLOCATION_TITLE') ?>
                    </div>
                    <p class="zr-paidaccess-callout__text">
                        <?= Loc::getMessage('ZR_PAIDACCESS_EXPENSE_ALLOCATION_HINT') ?>
                    </p>
                    <p class="zr-paidaccess-callout__meta">
                        <?= Loc::getMessage('ZR_PAIDACCESS_EXPENSE_ALLOCATION_MODE') ?>:
                        <strong><?= htmlspecialcharsbx($allocationModeLabel) ?></strong>
                        ·
                        <a href="<?= htmlspecialcharsbx($settingsUrl) ?>" target="_blank">
                            <?= Loc::getMessage('ZR_PAIDACCESS_LINK_MODULE_SETTINGS') ?>
                        </a>
                    </p>
                </div>
            </div>
        </td>
    </tr>

    <?php
$tabControl->EndTab();
$tabControl->Buttons([
    'back_url' => 'zr_paidaccess_fund_edit.php?ID=' . $fundId . '&lang=' . $languageId . '#tab_movements',
    'btnSave' => true,
    'btnApply' => true,
    'btnCancel' => true,
    'save' => true,
    'apply' => true,
]);
$tabControl->End();
?>
</form>
</div>

<script>
(function () {
    var form = document.getElementById('zr_paidaccess_fund_movement_form');
    if (!form) {
        return;
    }

    var typeSelect = form.elements['TYPE'];
    var callout = document.getElementById('zr-expense-allocation-callout');
    if (!typeSelect || !callout) {
        return;
    }

    var expenseType = <?= json_encode(FundMovementType::EXPENSE, JSON_UNESCAPED_UNICODE) ?>;

    function syncExpenseCallout() {
        callout.classList.toggle('is-hidden', typeSelect.value !== expenseType);
    }

    typeSelect.addEventListener('change', syncExpenseCallout);
    syncExpenseCallout();
})();
</script>

<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
