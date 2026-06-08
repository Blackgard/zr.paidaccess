<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UI\Extension;
use Zr\PaidAccess\Admin\PaymentAdminService;
use Zr\PaidAccess\Enum\PaymentStatus;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Payment\PaymentRepository;
use Zr\PaidAccess\Subscription\BillingPolicy;

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

Extension::load(['ui.buttons', 'ui.forms', 'ui.alerts', 'ui.notification', 'jquery']);
\Bitrix\Main\Page\Asset::getInstance()->addCss('/bitrix/themes/.default/zr.paidaccess.css');

$id = (int)($request->get('ID') ?? 0);
$isEditMode = $id > 0;

$APPLICATION->SetTitle(
    $isEditMode
        ? Loc::getMessage('ZR_PAIDACCESS_PAYMENT_EDIT_TITLE') . ' #' . $id
        : Loc::getMessage('ZR_PAIDACCESS_PAYMENT_ADD')
);

$prefillUserId = (int)$request->get('USER_ID');

$formValues = [
    'USER_ID' => $prefillUserId > 0 ? $prefillUserId : '',
    'BILLING_PERIOD' => PaymentAdminService::getDefaultBillingPeriod($prefillUserId),
    'AMOUNT' => PaymentAdminService::getDefaultAmount(),
    'CURRENCY' => 'RUB',
    'STATUS' => PaymentStatus::PENDING,
    'DESCRIPTION' => '',
    'ORDER_ID' => '',
    'GATEWAY_CODE' => PaymentAdminService::MANUAL_GATEWAY_CODE,
    'DATE_PAID' => '',
];

$message = null;
$bVarsFromForm = false;

if ($isEditMode) {
    $payment = PaymentRepository::getById($id);
    if (!$payment) {
        require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
        CAdminMessage::ShowMessage(Loc::getMessage('ZR_PAIDACCESS_NOT_FOUND'));
        require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
        return;
    }
    $formValues = array_merge($formValues, $payment);
    if (!empty($payment['DATE_PAID']) && $payment['DATE_PAID'] instanceof \Bitrix\Main\Type\DateTime) {
        $formValues['DATE_PAID'] = $payment['DATE_PAID']->toString();
    }
}

if ($request->isPost() && check_bitrix_sessid()) {
    $save = $request->getPost('save');
    $apply = $request->getPost('apply');

    if ($save !== null && $save !== '' || $apply !== null && $apply !== '') {
        $postData = [
            'USER_ID' => $request->getPost('USER_ID'),
            'BILLING_PERIOD' => $request->getPost('BILLING_PERIOD'),
            'AMOUNT' => $request->getPost('AMOUNT'),
            'CURRENCY' => $request->getPost('CURRENCY'),
            'STATUS' => $request->getPost('STATUS'),
            'DESCRIPTION' => $request->getPost('DESCRIPTION'),
        ];

        $formValues = array_merge($formValues, $postData);
        $bVarsFromForm = true;

        try {
            $wasNew = !$isEditMode;
            $id = PaymentAdminService::save($postData, $isEditMode ? $id : null);
            $isEditMode = true;

            $payment = PaymentRepository::getById($id);
            if ($payment) {
                $formValues = array_merge($formValues, $payment);
                if (!empty($payment['DATE_PAID']) && $payment['DATE_PAID'] instanceof \Bitrix\Main\Type\DateTime) {
                    $formValues['DATE_PAID'] = $payment['DATE_PAID']->toString();
                }
            }
            $bVarsFromForm = false;

            if ($save !== null && $save !== '') {
                LocalRedirect('zr_paidaccess_payments.php?lang=' . $languageId);
            }

            if ($wasNew) {
                LocalRedirect(
                    $APPLICATION->GetCurPage() . '?ID=' . $id . '&lang=' . $languageId,
                    false,
                    '301 Moved Permanently'
                );
            }

            $message = new CAdminMessage([
                'MESSAGE' => Loc::getMessage('ZR_PAIDACCESS_SAVE_SUCCESS'),
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

if (!$bVarsFromForm && $isEditMode && !$message) {
    $payment = PaymentRepository::getById($id);
    if ($payment) {
        $formValues = array_merge($formValues, $payment);
    }
}

$statusTitles = PaymentAdminService::getStatusTitles();
$userPreview = PaymentAdminService::getUserPreview((int)$formValues['USER_ID']);
$periodFormatHint = BillingPolicy::getBillingPeriodFormatHint();
$periodPlaceholder = BillingPolicy::getBillingPeriodInputPlaceholder();
$periodInputPattern = BillingPolicy::getBillingPeriodInputPattern();
$isPersonalPeriodMode = PaidAccessCore::isPersonalBillingPeriodMode();

$aTabs = [
    ['DIV' => 'main', 'TAB' => Loc::getMessage('ZR_PAIDACCESS_SECTION_MAIN'), 'TITLE' => Loc::getMessage('ZR_PAIDACCESS_SECTION_MAIN')],
];

$tabControl = new CAdminTabControl('tabControl', $aTabs);
$formAction = $APPLICATION->GetCurPage() . ($id > 0 ? '?ID=' . $id . '&lang=' . $languageId : '?lang=' . $languageId);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>

<div class="zr-paidaccess-admin">
<?php
$aContext = [
    [
        'TEXT' => Loc::getMessage('ZR_PAIDACCESS_LIST_BTN'),
        'LINK' => 'zr_paidaccess_payments.php?lang=' . $languageId,
        'TITLE' => Loc::getMessage('ZR_PAIDACCESS_LIST_BTN_TITLE'),
        'ICON' => 'btn_list',
    ],
];
$contextMenu = new CAdminContextMenu($aContext);
$contextMenu->Show();

if ($message) {
    echo $message->Show();
}
?>

<form method="post" action="<?= htmlspecialcharsbx($formAction) ?>" name="zr_paidaccess_payment_form" id="zr_paidaccess_payment_form">
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
        <td colspan="2" align="center">
            <div class="adm-detail-title"><?= Loc::getMessage('ZR_PAIDACCESS_SECTION_MAIN') ?></div>
            <div class="adm-detail-content-item-block">
                <table class="edit-table">
                    <?php if ($isEditMode): ?>
                    <tr>
                        <td width="40%" class="adm-detail-content-cell-l">ID:</td>
                        <td width="60%" class="adm-detail-content-cell-r"><?= (int)$id ?></td>
                    </tr>
                    <?php endif; ?>

                    <tr>
                        <td class="adm-detail-content-cell-l">
                            <?= Loc::getMessage('ZR_PAIDACCESS_FIELD_USER') ?>:<span class="required">*</span>
                        </td>
                        <td class="adm-detail-content-cell-r">
                            <?php if ($isEditMode): ?>
                                <?php
                                $userLabel = $userPreview
                                    ? PaymentAdminService::formatUserLabel($userPreview)
                                    : '[' . (int)$formValues['USER_ID'] . ']';
                                ?>
                                <input type="hidden" name="USER_ID" value="<?= (int)$formValues['USER_ID'] ?>">
                                <a href="/bitrix/admin/user_edit.php?ID=<?= (int)$formValues['USER_ID'] ?>&lang=<?= LANGUAGE_ID ?>" target="_blank">
                                    <?= htmlspecialcharsbx($userLabel) ?>
                                </a>
                            <?php else: ?>
                                <?php
                                echo FindUserID(
                                    'USER_ID',
                                    (int)$formValues['USER_ID'],
                                    '',
                                    'zr_paidaccess_payment_form',
                                    '5',
                                    '',
                                    '...',
                                    ''
                                );
                                ?>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <td class="adm-detail-content-cell-l">
                            <?= Loc::getMessage('ZR_PAIDACCESS_FIELD_PERIOD') ?>
                            (<?= htmlspecialcharsbx($periodFormatHint) ?>):<span class="required">*</span>
                        </td>
                        <td class="adm-detail-content-cell-r">
                            <input type="text" name="BILLING_PERIOD" class="select-field"
                                   value="<?= htmlspecialcharsbx((string)$formValues['BILLING_PERIOD']) ?>"
                                   placeholder="<?= htmlspecialcharsbx($periodPlaceholder) ?>"
                                   pattern="<?= htmlspecialcharsbx($periodInputPattern) ?>" required>
                            <?php if ($isPersonalPeriodMode): ?>
                                <div class="adm-input-description" style="margin-top:8px;">
                                    <?= Loc::getMessage('ZR_PAIDACCESS_FIELD_PERIOD_PERSONAL_HINT') ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <td class="adm-detail-content-cell-l">
                            <?= Loc::getMessage('ZR_PAIDACCESS_FIELD_AMOUNT') ?>:<span class="required">*</span>
                        </td>
                        <td class="adm-detail-content-cell-r">
                            <input type="number" name="AMOUNT" class="select-field" step="0.01" min="0.01"
                                   value="<?= htmlspecialcharsbx((string)$formValues['AMOUNT']) ?>" required>
                        </td>
                    </tr>

                    <tr>
                        <td class="adm-detail-content-cell-l"><?= Loc::getMessage('ZR_PAIDACCESS_FIELD_CURRENCY') ?>:</td>
                        <td class="adm-detail-content-cell-r">
                            <input type="text" name="CURRENCY" class="select-field" maxlength="3"
                                   value="<?= htmlspecialcharsbx((string)$formValues['CURRENCY']) ?>">
                        </td>
                    </tr>

                    <tr>
                        <td class="adm-detail-content-cell-l">
                            <?= Loc::getMessage('ZR_PAIDACCESS_FIELD_STATUS') ?>:<span class="required">*</span>
                        </td>
                        <td class="adm-detail-content-cell-r">
                            <select name="STATUS" class="select-field" required>
                                <?php foreach ($statusTitles as $code => $title): ?>
                                    <option value="<?= htmlspecialcharsbx($code) ?>"
                                        <?= (string)$formValues['STATUS'] === $code ? ' selected' : '' ?>>
                                        <?= htmlspecialcharsbx($title) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="adm-input-description" style="margin-top:8px;">
                                <?= Loc::getMessage('ZR_PAIDACCESS_STATUS_PAID_HINT') ?>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td class="adm-detail-content-cell-l"><?= Loc::getMessage('ZR_PAIDACCESS_FIELD_DESCRIPTION') ?>:</td>
                        <td class="adm-detail-content-cell-r">
                            <input type="text" name="DESCRIPTION" class="select-field" size="60"
                                   value="<?= htmlspecialcharsbx((string)$formValues['DESCRIPTION']) ?>">
                        </td>
                    </tr>

                    <?php if ($isEditMode): ?>
                    <tr>
                        <td class="adm-detail-content-cell-l"><?= Loc::getMessage('ZR_PAIDACCESS_FIELD_ORDER_ID') ?>:</td>
                        <td class="adm-detail-content-cell-r">
                            <input type="text" readonly class="select-field"
                                   value="<?= htmlspecialcharsbx((string)$formValues['ORDER_ID']) ?>">
                        </td>
                    </tr>
                    <tr>
                        <td class="adm-detail-content-cell-l"><?= Loc::getMessage('ZR_PAIDACCESS_FIELD_GATEWAY') ?>:</td>
                        <td class="adm-detail-content-cell-r">
                            <?= htmlspecialcharsbx(
                                $formValues['GATEWAY_CODE'] === PaymentAdminService::MANUAL_GATEWAY_CODE
                                    ? Loc::getMessage('ZR_PAIDACCESS_MANUAL_GATEWAY')
                                    : (string)$formValues['GATEWAY_CODE']
                            ) ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="adm-detail-content-cell-l"><?= Loc::getMessage('ZR_PAIDACCESS_FIELD_DATE_PAID') ?>:</td>
                        <td class="adm-detail-content-cell-r">
                            <input type="text" readonly class="select-field"
                                   value="<?= htmlspecialcharsbx((string)$formValues['DATE_PAID']) ?>">
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </td>
    </tr>

    <?php
    $tabControl->Buttons([
        'back_url' => 'zr_paidaccess_payments.php?lang=' . $languageId,
        'btnSave' => $RIGHT >= 'W',
        'btnApply' => $RIGHT >= 'W',
        'btnCancel' => true,
        'save' => true,
        'apply' => true,
    ]);
    $tabControl->End();
    ?>
</form>
</div>

<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
