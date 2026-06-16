<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SiteTable;
use Zr\PaidAccess\Admin\DocumentAdminService;
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

$id = (int)($request->get('ID') ?? 0);
$isEditMode = $id > 0;

$APPLICATION->SetTitle(
    $isEditMode
        ? Loc::getMessage('ZR_PAIDACCESS_DOCUMENT_EDIT_TITLE') . ' #' . $id
        : Loc::getMessage('ZR_PAIDACCESS_DOCUMENT_ADD')
);

$formValues = [
    'SITE_ID' => PaidAccessCore::normalizeSiteId(SITE_ID),
    'CODE' => '',
    'TITLE' => '',
    'SORT' => 500,
    'ACTIVE' => 'Y',
    'IS_REQUIRED' => 'Y',
];

$message = null;

if ($isEditMode) {
    $documentRow = RequiredDocumentRepository::getById($id);
    if (!$documentRow) {
        require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
        CAdminMessage::ShowMessage(Loc::getMessage('ZR_PAIDACCESS_NOT_FOUND'));
        require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';

        return;
    }
    $formValues = array_merge($formValues, $documentRow);
}

if ($request->isPost() && check_bitrix_sessid()) {
    $save = $request->getPost('save');
    $apply = $request->getPost('apply');

    if ($save !== null && $save !== '' || $apply !== null && $apply !== '') {
        $postData = [
            'SITE_ID' => $request->getPost('SITE_ID'),
            'CODE' => $request->getPost('CODE'),
            'TITLE' => $request->getPost('TITLE'),
            'SORT' => $request->getPost('SORT'),
            'ACTIVE' => $request->getPost('ACTIVE'),
            'IS_REQUIRED' => $request->getPost('IS_REQUIRED'),
        ];

        $formValues = array_merge($formValues, $postData);

        try {
            $wasNew = !$isEditMode;
            $id = DocumentAdminService::saveDocument($isEditMode ? $id : 0, $postData);
            $isEditMode = true;

            $documentRow = RequiredDocumentRepository::getById($id);
            if ($documentRow) {
                $formValues = array_merge($formValues, $documentRow);
            }

            if ($save !== null && $save !== '') {
                LocalRedirect('zr_paidaccess_documents.php?lang=' . $languageId);
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
        'DIV' => 'versions',
        'TAB' => Loc::getMessage('ZR_PAIDACCESS_TAB_VERSIONS'),
        'TITLE' => Loc::getMessage('ZR_PAIDACCESS_TAB_VERSIONS'),
    ];
}

$tabControl = new CAdminTabControl('tabControl', $aTabs);
$formAction = $APPLICATION->GetCurPage() . ($id > 0 ? '?ID=' . $id . '&lang=' . $languageId : '?lang=' . $languageId);

$versions = $isEditMode ? RequiredDocumentVersionRepository::getListByDocumentId($id) : [];

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

if ($message !== null) {
    echo $message->Show();
}
?>
<form method="post" action="<?= htmlspecialcharsbx($formAction) ?>" name="zr_paidaccess_document_form">
    <?= bitrix_sessid_post() ?>
    <?php $tabControl->Begin(); ?>

    <?php $tabControl->BeginNextTab(); ?>
    <tr>
        <td width="40%"><?= Loc::getMessage('ZR_PAIDACCESS_FIELD_SITE') ?>:</td>
        <td width="60%">
            <select name="SITE_ID">
                <?php foreach ($siteItems as $lid => $label): ?>
                    <option value="<?= htmlspecialcharsbx($lid) ?>"<?= ($formValues['SITE_ID'] ?? '') === $lid ? ' selected' : '' ?>>
                        <?= htmlspecialcharsbx($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('ZR_PAIDACCESS_FIELD_CODE') ?>:</td>
        <td><input type="text" name="CODE" value="<?= htmlspecialcharsbx((string)($formValues['CODE'] ?? '')) ?>" size="40"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('ZR_PAIDACCESS_FIELD_TITLE') ?>:</td>
        <td><input type="text" name="TITLE" value="<?= htmlspecialcharsbx((string)($formValues['TITLE'] ?? '')) ?>" size="60"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('ZR_PAIDACCESS_FIELD_SORT') ?>:</td>
        <td><input type="text" name="SORT" value="<?= (int)($formValues['SORT'] ?? 500) ?>" size="8"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('ZR_PAIDACCESS_FIELD_REQUIRED') ?>:</td>
        <td><input type="checkbox" name="IS_REQUIRED" value="Y"<?= ($formValues['IS_REQUIRED'] ?? 'Y') === 'Y' ? ' checked' : '' ?>></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('ZR_PAIDACCESS_FIELD_ACTIVE') ?>:</td>
        <td><input type="checkbox" name="ACTIVE" value="Y"<?= ($formValues['ACTIVE'] ?? 'Y') === 'Y' ? ' checked' : '' ?>></td>
    </tr>

    <?php if ($isEditMode): ?>
        <?php $tabControl->BeginNextTab(); ?>
        <tr>
            <td colspan="2">
                <a class="adm-btn adm-btn-green" href="zr_paidaccess_document_version_edit.php?DOCUMENT_ID=<?= $id ?>&lang=<?= LANGUAGE_ID ?>">
                    <?= Loc::getMessage('ZR_PAIDACCESS_VERSION_ADD') ?>
                </a>
                <br><br>
                <table class="internal" style="width:100%">
                    <tr class="heading">
                        <td><?= Loc::getMessage('ZR_PAIDACCESS_COL_VERSION') ?></td>
                        <td><?= Loc::getMessage('ZR_PAIDACCESS_COL_PUBLISHED') ?></td>
                        <td><?= Loc::getMessage('ZR_PAIDACCESS_COL_CURRENT') ?></td>
                        <td><?= Loc::getMessage('ZR_PAIDACCESS_COL_FILE') ?></td>
                    </tr>
                    <?php if ($versions === []): ?>
                        <tr><td colspan="4"><?= Loc::getMessage('ZR_PAIDACCESS_NO_VERSIONS') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($versions as $version): ?>
                            <?php
                            $versionUrl = 'zr_paidaccess_document_version_edit.php?ID=' . (int)$version['ID'] . '&DOCUMENT_ID=' . $id . '&lang=' . LANGUAGE_ID;
                            $fileUrl = DocumentVersionService::resolveFileUrl($version);
                            ?>
                            <tr>
                                <td><a href="<?= htmlspecialcharsbx($versionUrl) ?>">v<?= (int)$version['VERSION'] ?></a></td>
                                <td><?= htmlspecialcharsbx(RequiredDocumentVersionRepository::getPublishDateFormatted($version)) ?></td>
                                <td><?= ($version['IS_CURRENT'] ?? 'N') === 'Y' ? Loc::getMessage('ZR_PAIDACCESS_YES') : Loc::getMessage('ZR_PAIDACCESS_NO') ?></td>
                                <td>
                                    <?php if ($fileUrl !== null): ?>
                                        <a href="<?= htmlspecialcharsbx($fileUrl) ?>" target="_blank" rel="noopener noreferrer"><?= Loc::getMessage('ZR_PAIDACCESS_OPEN_FILE') ?></a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </table>
            </td>
        </tr>
    <?php endif; ?>

    <?php
    $tabControl->Buttons([
        'back_url' => 'zr_paidaccess_documents.php?lang=' . $languageId,
    ]);
$tabControl->End();
?>
</form>
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
