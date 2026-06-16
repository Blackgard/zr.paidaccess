<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Zr\PaidAccess\Admin\DocumentAdminService;
use Zr\PaidAccess\Admin\StatusBadgeRenderer;
use Zr\PaidAccess\Document\DocumentVersionService;
use Zr\PaidAccess\Document\RequiredDocumentRepository;
use Zr\PaidAccess\Document\RequiredDocumentVersionRepository;
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

\Bitrix\Main\Page\Asset::getInstance()->addCss('/bitrix/themes/.default/zr.paidaccess.css');

$documentId = (int)($request->get('DOCUMENT_ID') ?? 0);
$versionId = (int)($request->get('ID') ?? 0);
$isViewMode = $versionId > 0;

$document = RequiredDocumentRepository::getById($documentId);
if ($document === null) {
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
    CAdminMessage::ShowMessage(Loc::getMessage('ZR_PAIDACCESS_DOCUMENT_NOT_FOUND'));
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';

    return;
}

$versionRow = $isViewMode ? RequiredDocumentVersionRepository::getById($versionId) : null;
if ($isViewMode && ($versionRow === null || (int)$versionRow['DOCUMENT_ID'] !== $documentId)) {
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
    CAdminMessage::ShowMessage(Loc::getMessage('ZR_PAIDACCESS_NOT_FOUND'));
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';

    return;
}

$documentTitle = (string)$document['TITLE'];
$APPLICATION->SetTitle(
    $isViewMode
        ? Loc::getMessage('ZR_PAIDACCESS_VERSION_VIEW_TITLE') . ' v' . (int)$versionRow['VERSION'] . ' — ' . $documentTitle
        : Loc::getMessage('ZR_PAIDACCESS_VERSION_ADD_TITLE') . ': ' . $documentTitle
);

$formValues = [
    'BODY_HTML' => '',
];

$message = null;
$backUrl = 'zr_paidaccess_document_edit.php?ID=' . $documentId . '&lang=' . $languageId . '#tab_versions';

if ($isViewMode) {
    $formValues['BODY_HTML'] = (string)($versionRow['BODY_HTML'] ?? '');
}

if (!$isViewMode && $request->isPost() && check_bitrix_sessid()) {
    $save = $request->getPost('save');
    if ($save !== null && $save !== '') {
        $formValues['BODY_HTML'] = (string)$request->getPost('BODY_HTML');

        try {
            $newVersionId = DocumentAdminService::publishVersion($documentId, [
                'BODY_HTML' => $formValues['BODY_HTML'],
            ]);
            LocalRedirect(
                'zr_paidaccess_document_version_edit.php?ID=' . $newVersionId
                . '&DOCUMENT_ID=' . $documentId
                . '&lang=' . $languageId
            );
        } catch (\Throwable $e) {
            $message = new CAdminMessage([
                'MESSAGE' => Loc::getMessage('ZR_PAIDACCESS_SAVE_ERROR') . ': ' . $e->getMessage(),
                'TYPE' => 'ERROR',
            ]);
        }
    }
}

$fileUrl = $isViewMode ? DocumentVersionService::resolveFileUrl($versionRow) : null;
$nextVersion = DocumentVersionService::getNextVersionNumber($documentId);

$aTabs = [
    [
        'DIV' => 'main',
        'TAB' => Loc::getMessage('ZR_PAIDACCESS_TAB_MAIN'),
        'TITLE' => $isViewMode
            ? Loc::getMessage('ZR_PAIDACCESS_VERSION_VIEW_TITLE')
            : Loc::getMessage('ZR_PAIDACCESS_VERSION_ADD_TITLE'),
    ],
];

$tabControl = new CAdminTabControl('tabControl', $aTabs);
$formAction = $APPLICATION->GetCurPage()
    . ($isViewMode
        ? '?ID=' . $versionId . '&DOCUMENT_ID=' . $documentId . '&lang=' . $languageId
        : '?DOCUMENT_ID=' . $documentId . '&lang=' . $languageId);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>

<div class="zr-paidaccess-admin">
<?php
$aContext = [
    [
        'TEXT' => Loc::getMessage('ZR_PAIDACCESS_BACK_TO_DOCUMENT'),
        'LINK' => $backUrl,
        'TITLE' => Loc::getMessage('ZR_PAIDACCESS_BACK_TO_DOCUMENT'),
        'ICON' => 'btn_list',
    ],
];
$contextMenu = new CAdminContextMenu($aContext);
$contextMenu->Show();

if ($message !== null) {
    echo $message->Show();
}
?>

<form method="post"
      enctype="multipart/form-data"
      action="<?= htmlspecialcharsbx($formAction) ?>"
      name="zr_paidaccess_document_version_form">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="lang" value="<?= htmlspecialcharsbx($languageId) ?>">

    <?php
    $tabControl->Begin();
$tabControl->BeginNextTab();
?>

    <tr>
        <td colspan="2">
            <div class="adm-detail-content-item-block">
                <table class="edit-table" style="width:100%;">
                    <tr>
                        <td width="40%" class="adm-detail-content-cell-l">
                            <?= Loc::getMessage('ZR_PAIDACCESS_FIELD_DOCUMENT') ?>:
                        </td>
                        <td width="60%" class="adm-detail-content-cell-r">
                            <strong><?= htmlspecialcharsbx($documentTitle) ?></strong>
                            <div class="adm-input-description">
                                <?= htmlspecialcharsbx((string)$document['CODE']) ?>
                                · <?= htmlspecialcharsbx((string)$document['SITE_ID']) ?>
                            </div>
                        </td>
                    </tr>

                    <?php if ($isViewMode): ?>
                        <tr>
                            <td class="adm-detail-content-cell-l">
                                <?= Loc::getMessage('ZR_PAIDACCESS_COL_VERSION') ?>:
                            </td>
                            <td class="adm-detail-content-cell-r">
                                <strong>v<?= (int)$versionRow['VERSION'] ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <td class="adm-detail-content-cell-l">
                                <?= Loc::getMessage('ZR_PAIDACCESS_COL_PUBLISHED') ?>:
                            </td>
                            <td class="adm-detail-content-cell-r">
                                <?= htmlspecialcharsbx(RequiredDocumentVersionRepository::getPublishDateFormatted($versionRow)) ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="adm-detail-content-cell-l">
                                <?= Loc::getMessage('ZR_PAIDACCESS_COL_CURRENT') ?>:
                            </td>
                            <td class="adm-detail-content-cell-r">
                                <?= StatusBadgeRenderer::renderYesNo(
                                    ($versionRow['IS_CURRENT'] ?? 'N') === 'Y',
                                    Loc::getMessage('ZR_PAIDACCESS_YES'),
                                    Loc::getMessage('ZR_PAIDACCESS_NO')
                                ) ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="adm-detail-content-cell-l">
                                <?= Loc::getMessage('ZR_PAIDACCESS_COL_FILE') ?>:
                            </td>
                            <td class="adm-detail-content-cell-r">
                                <?php if ($fileUrl !== null): ?>
                                    <a href="<?= htmlspecialcharsbx($fileUrl) ?>" target="_blank" rel="noopener noreferrer">
                                        <?= Loc::getMessage('ZR_PAIDACCESS_OPEN_FILE') ?>
                                    </a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (($formValues['BODY_HTML'] ?? '') !== ''): ?>
                            <tr>
                                <td class="adm-detail-content-cell-l" style="vertical-align:top;">
                                    <?= Loc::getMessage('ZR_PAIDACCESS_FIELD_BODY') ?>:
                                </td>
                                <td class="adm-detail-content-cell-r">
                                    <div class="zr-document-version-body-preview">
                                        <?= $formValues['BODY_HTML'] ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php else: ?>
                        <tr>
                            <td class="adm-detail-content-cell-l">
                                <?= Loc::getMessage('ZR_PAIDACCESS_NEXT_VERSION') ?>:
                            </td>
                            <td class="adm-detail-content-cell-r">
                                <strong>v<?= $nextVersion ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <td class="adm-detail-content-cell-l">
                                <?= Loc::getMessage('ZR_PAIDACCESS_FIELD_FILE') ?>:
                            </td>
                            <td class="adm-detail-content-cell-r">
                                <input type="file"
                                       name="VERSION_FILE"
                                       accept=".pdf,.doc,.docx,.txt,application/pdf">
                                <div class="adm-input-description">
                                    <?= Loc::getMessage('ZR_PAIDACCESS_FIELD_FILE_HINT') ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="adm-detail-content-cell-l" style="vertical-align:top;">
                                <?= Loc::getMessage('ZR_PAIDACCESS_FIELD_BODY') ?>:
                            </td>
                            <td class="adm-detail-content-cell-r">
                                <textarea name="BODY_HTML"
                                          class="adm-input"
                                          rows="8"
                                          style="width:100%;max-width:640px;"><?= htmlspecialcharsbx((string)$formValues['BODY_HTML']) ?></textarea>
                                <div class="adm-input-description">
                                    <?= Loc::getMessage('ZR_PAIDACCESS_FIELD_BODY_HINT') ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" class="adm-detail-content-cell-r">
                                <div class="adm-info-message-wrap" style="margin-top:4px;">
                                    <div class="adm-info-message">
                                        <?= Loc::getMessage('ZR_PAIDACCESS_VERSION_PUBLISH_HINT') ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </td>
    </tr>

    <?php
    $tabControl->EndTab();

if ($isViewMode) {
    $tabControl->Buttons([
        'back_url' => $backUrl,
        'btnSave' => false,
        'btnApply' => false,
        'btnCancel' => true,
    ]);
} else {
    $tabControl->Buttons([
        'back_url' => $backUrl,
        'btnSave' => false,
        'btnApply' => false,
        'btnCancel' => false,
    ]);
    ?>
    <div class="adm-detail-content-btns-wrap" style="margin-left:12px;">
        <input type="submit"
               name="save"
               value="<?= Loc::getMessage('ZR_PAIDACCESS_PUBLISH_VERSION') ?>"
               class="adm-btn-save">
        <a href="<?= htmlspecialcharsbx($backUrl) ?>" class="adm-btn"><?= Loc::getMessage('MAIN_ADMIN_MENU_LIST') ?></a>
    </div>
    <?php
}

$tabControl->End();
?>
</form>
</div>

<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
