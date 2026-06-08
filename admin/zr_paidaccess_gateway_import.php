<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Zr\PaidAccess\Admin\GatewayImportExportService;
use Zr\PaidAccess\PaidAccessCore;

$moduleId = PaidAccessCore::MODULE_ID;

Loader::includeModule($moduleId);
Loc::loadMessages(__FILE__);

\Bitrix\Main\Page\Asset::getInstance()->addCss('/bitrix/themes/.default/zr.paidaccess.css');

$RIGHT = $APPLICATION->GetGroupRight($moduleId);
if ($RIGHT < 'W') {
    $APPLICATION->AuthForm(GetMessage('ACCESS_DENIED'));
}

$request = Context::getCurrent()->getRequest();
$message = null;
$messageType = 'OK';
$importResult = null;

if ($request->isPost() && check_bitrix_sessid() && $request->getPost('import') !== null) {
    $file = $request->getFile('import_file');
    $mode = (string)$request->getPost('import_mode');
    $preserveTestState = $request->getPost('preserve_test_state') === 'Y';

    if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $message = Loc::getMessage('ZR_PAIDACCESS_IMPORT_FILE_ERROR');
        $messageType = 'ERROR';
    } else {
        $json = (string)file_get_contents($file['tmp_name']);
        $importResult = GatewayImportExportService::importFromJson($json, $mode, $preserveTestState);

        if ($importResult['errors'] !== []) {
            $message = Loc::getMessage('ZR_PAIDACCESS_IMPORT_PARTIAL')
                . ' ' . implode('; ', $importResult['errors']);
            $messageType = $importResult['created'] + $importResult['updated'] > 0 ? 'OK' : 'ERROR';
        } else {
            $message = Loc::getMessage('ZR_PAIDACCESS_IMPORT_SUCCESS');
            $messageType = 'OK';
        }
    }
}

$APPLICATION->SetTitle(Loc::getMessage('ZR_PAIDACCESS_IMPORT_TITLE'));

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$listUrl = 'zr_paidaccess_gateways.php?lang=' . LANGUAGE_ID;
?>
<div class="zr-paidaccess-admin">
<?php
$aContext = [
    [
        'TEXT' => Loc::getMessage('ZR_PAIDACCESS_BACK_TO_LIST'),
        'LINK' => $listUrl,
        'TITLE' => Loc::getMessage('ZR_PAIDACCESS_BACK_TO_LIST'),
        'ICON' => 'btn_list',
    ],
];
$contextMenu = new CAdminContextMenu($aContext);
$contextMenu->Show();

if ($message !== null) {
    CAdminMessage::ShowMessage([
        'MESSAGE' => $message,
        'TYPE' => $messageType,
        'HTML' => true,
    ]);
}

if (is_array($importResult)) {
    ?>
    <div class="adm-info-message-wrap adm-info-message-green">
        <div class="adm-info-message">
            <?= Loc::getMessage('ZR_PAIDACCESS_IMPORT_STATS') ?>:
            <?= (int)$importResult['created'] ?> <?= Loc::getMessage('ZR_PAIDACCESS_IMPORT_CREATED') ?>,
            <?= (int)$importResult['updated'] ?> <?= Loc::getMessage('ZR_PAIDACCESS_IMPORT_UPDATED') ?>,
            <?= (int)$importResult['skipped'] ?> <?= Loc::getMessage('ZR_PAIDACCESS_IMPORT_SKIPPED') ?>
        </div>
    </div>
    <?php
}
?>

<form method="post" enctype="multipart/form-data" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>?lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>
    <table class="adm-detail-content-table edit-table">
        <tr>
            <td width="40%" class="adm-detail-content-cell-l"><?= Loc::getMessage('ZR_PAIDACCESS_IMPORT_FILE') ?>:</td>
            <td width="60%" class="adm-detail-content-cell-r">
                <input type="file" name="import_file" accept=".json,application/json" required>
            </td>
        </tr>
        <tr>
            <td class="adm-detail-content-cell-l"><?= Loc::getMessage('ZR_PAIDACCESS_IMPORT_MODE') ?>:</td>
            <td class="adm-detail-content-cell-r">
                <select name="import_mode">
                    <option value="<?= GatewayImportExportService::MODE_UPSERT ?>">
                        <?= Loc::getMessage('ZR_PAIDACCESS_IMPORT_MODE_UPSERT') ?>
                    </option>
                    <option value="<?= GatewayImportExportService::MODE_CREATE ?>">
                        <?= Loc::getMessage('ZR_PAIDACCESS_IMPORT_MODE_CREATE') ?>
                    </option>
                    <option value="<?= GatewayImportExportService::MODE_SKIP_EXISTING ?>">
                        <?= Loc::getMessage('ZR_PAIDACCESS_IMPORT_MODE_SKIP') ?>
                    </option>
                </select>
            </td>
        </tr>
        <tr>
            <td class="adm-detail-content-cell-l"><?= Loc::getMessage('ZR_PAIDACCESS_IMPORT_PRESERVE_TEST') ?>:</td>
            <td class="adm-detail-content-cell-r">
                <input type="checkbox" name="preserve_test_state" value="Y" id="preserve_test_state">
                <label for="preserve_test_state"><?= Loc::getMessage('ZR_PAIDACCESS_IMPORT_PRESERVE_TEST_LABEL') ?></label>
            </td>
        </tr>
    </table>

    <p class="adm-info-message">
        <?= Loc::getMessage('ZR_PAIDACCESS_IMPORT_NOTE') ?>
    </p>

    <input type="submit" name="import" value="<?= Loc::getMessage('ZR_PAIDACCESS_IMPORT_SUBMIT') ?>" class="adm-btn-save">
</form>
</div>
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
