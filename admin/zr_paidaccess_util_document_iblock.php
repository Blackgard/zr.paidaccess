<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SiteTable;
use Zr\PaidAccess\Admin\DocumentIblockMigrationService;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Utility\DocumentMigrationMapping;
use Zr\PaidAccess\Utility\DocumentMigrationTargetFields;
use Zr\PaidAccess\Utility\IblockIntrospectionService;
use Zr\PaidAccess\Utility\UtilitiesRegistry;

$moduleId = PaidAccessCore::MODULE_ID;

Loader::includeModule($moduleId);

$moduleRoot = dirname(__DIR__);
Loc::loadMessages($moduleRoot . '/lang/' . LANGUAGE_ID . '/admin/' . basename(__FILE__));

\Bitrix\Main\Page\Asset::getInstance()->addCss('/bitrix/themes/.default/zr.paidaccess.css');

$RIGHT = $APPLICATION->GetGroupRight($moduleId);
if ($RIGHT < 'W') {
    $APPLICATION->AuthForm(GetMessage('ACCESS_DENIED'));
}

$utilityMeta = UtilitiesRegistry::findUtility(UtilitiesRegistry::GROUP_MIGRATION, 'document_iblock');
if ($utilityMeta === null) {
    LocalRedirect(UtilitiesRegistry::buildIndexUrl(LANGUAGE_ID));
}

$request = Context::getCurrent()->getRequest();

$siteOptions = [];
$siteResult = SiteTable::getList([
    'select' => ['LID', 'NAME'],
    'order' => ['SORT' => 'ASC'],
]);
while ($site = $siteResult->fetch()) {
    $siteOptions[$site['LID']] = '[' . $site['LID'] . '] ' . $site['NAME'];
}

$siteId = PaidAccessCore::normalizeSiteId((string)$request->getPost('site_id') ?: (string)$request->get('site_id'));
if ($siteId === '' && $siteOptions !== []) {
    $siteId = (string)array_key_first($siteOptions);
}

$iblockOptions = IblockIntrospectionService::getActiveIblockOptions();
$iblockId = (int)$request->getPost('iblock_id');
if ($iblockId <= 0) {
    $iblockId = (int)$request->get('iblock_id');
}
if ($iblockId <= 0) {
    $iblockId = DocumentIblockMigrationService::loadSavedIblockId($siteId);
}

$mapping = DocumentIblockMigrationService::loadSavedMapping($siteId);
if ($request->isPost() && check_bitrix_sessid()) {
    $postedMapping = DocumentMigrationMapping::fromRequest($request->getPostList()->toArray());
    if ($postedMapping !== []) {
        $mapping = DocumentMigrationMapping::mergeWithDefaults($postedMapping);
    }
}

$schema = $iblockId > 0 ? IblockIntrospectionService::getSchema($iblockId) : [
    'iblock' => null,
    'fields' => [],
    'properties' => [],
    'sources' => [],
];
$sourceOptions = $schema['sources'];
$sourceOptions = array_merge(['' => Loc::getMessage('ZR_PAIDACCESS_UTIL_MAP_SKIP')], $sourceOptions);

$message = null;
$messageType = 'OK';
$preview = null;
$migrateResult = null;

if ($request->isPost() && check_bitrix_sessid()) {
    if ($request->getPost('load_schema') !== null) {
        DocumentIblockMigrationService::saveMapping($siteId, $iblockId, $mapping);
        $message = Loc::getMessage('ZR_PAIDACCESS_UTIL_SCHEMA_LOADED');
    }

    if ($request->getPost('save_mapping') !== null) {
        DocumentIblockMigrationService::saveMapping($siteId, $iblockId, $mapping);
        $message = Loc::getMessage('ZR_PAIDACCESS_UTIL_MAPPING_SAVED');
    }

    if ($request->getPost('preview_migration') !== null) {
        $preview = DocumentIblockMigrationService::preview($iblockId, $siteId, $mapping, 15);
        if ($preview['errors'] !== []) {
            $message = implode('; ', $preview['errors']);
            $messageType = 'ERROR';
        }
    }

    if ($request->getPost('run_migration') !== null) {
        global $USER;
        $adminUserId = is_object($USER) && $USER->IsAuthorized() ? (int)$USER->GetID() : null;
        $migrateResult = DocumentIblockMigrationService::migrate(
            $iblockId,
            $siteId,
            $mapping,
            $request->getPost('skip_existing') === 'Y',
            $adminUserId
        );

        if ($migrateResult['errors'] !== []) {
            $message = Loc::getMessage('ZR_PAIDACCESS_UTIL_MIGRATE_PARTIAL')
                . ' ' . implode('; ', $migrateResult['errors']);
            $messageType = $migrateResult['created_documents'] > 0 ? 'OK' : 'ERROR';
        } else {
            $message = Loc::getMessage('ZR_PAIDACCESS_UTIL_MIGRATE_SUCCESS');
            $messageType = 'OK';
        }
    }
}

$groupTitle = (string)$utilityMeta['group']['title'];
$utilityTitle = (string)$utilityMeta['utility']['title'];
$APPLICATION->SetTitle($groupTitle . ' — ' . $utilityTitle);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$indexUrl = UtilitiesRegistry::buildIndexUrl(LANGUAGE_ID);
?>
<div class="zr-paidaccess-admin">
<?php
$aContext = [
    [
        'TEXT' => Loc::getMessage('ZR_PAIDACCESS_UTIL_BACK_TO_INDEX'),
        'LINK' => $indexUrl,
        'TITLE' => Loc::getMessage('ZR_PAIDACCESS_UTIL_BACK_TO_INDEX'),
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
?>
    <div class="zr-utilities-breadcrumb">
        <a href="<?= htmlspecialcharsbx($indexUrl) ?>"><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_INDEX_TITLE') ?></a>
        <span class="zr-utilities-breadcrumb__sep">/</span>
        <span><?= htmlspecialcharsbx($groupTitle) ?></span>
        <span class="zr-utilities-breadcrumb__sep">/</span>
        <strong><?= htmlspecialcharsbx($utilityTitle) ?></strong>
    </div>

    <p class="adm-info-message-wrap">
        <span class="adm-info-message"><?= htmlspecialcharsbx((string)$utilityMeta['utility']['description']) ?></span>
    </p>

    <form method="post" action="<?= $APPLICATION->GetCurPageParam('lang=' . LANGUAGE_ID, ['lang']) ?>">
        <?= bitrix_sessid_post() ?>

        <div class="adm-detail-content-item-block">
            <div class="adm-detail-title"><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_STEP_SOURCE') ?></div>
            <p class="zr-utilities-step-hint"><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_STEP_SOURCE_HINT') ?></p>
            <table class="adm-detail-content-table edit-table">
                <tr>
                    <td width="40%" class="adm-detail-content-cell-l"><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_SITE') ?>:</td>
                    <td width="60%" class="adm-detail-content-cell-r">
                        <select name="site_id" class="typeselect" style="min-width: 280px;">
                            <?php foreach ($siteOptions as $lid => $label): ?>
                                <option value="<?= htmlspecialcharsbx($lid) ?>"<?= $siteId === $lid ? ' selected' : '' ?>>
                                    <?= htmlspecialcharsbx($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="adm-detail-content-cell-l"><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_IBLOCK') ?>:</td>
                    <td class="adm-detail-content-cell-r">
                        <?php if ($iblockOptions === []): ?>
                            <span class="required"><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_IBLOCK_UNAVAILABLE') ?></span>
                        <?php else: ?>
                            <select name="iblock_id" class="typeselect" style="min-width: 420px;">
                                <option value=""><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_IBLOCK_SELECT') ?></option>
                                <?php foreach ($iblockOptions as $id => $label): ?>
                                    <option value="<?= (int)$id ?>"<?= $iblockId === (int)$id ? ' selected' : '' ?>>
                                        <?= htmlspecialcharsbx($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <p class="zr-utilities-form-actions">
                <input type="submit" name="load_schema" class="adm-btn-save" value="<?= Loc::getMessage('ZR_PAIDACCESS_UTIL_LOAD_SCHEMA') ?>">
            </p>
        </div>

        <?php if ($iblockId > 0 && $schema['iblock'] !== null): ?>
            <div class="adm-detail-content-item-block">
                <div class="adm-detail-title"><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_STEP_MAPPING') ?></div>
                <p class="adm-info-message-wrap">
                    <span class="adm-info-message"><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_MAPPING_HINT') ?></span>
                </p>

                <table class="adm-list-table" style="width:100%;">
                <thead>
                <tr class="adm-list-table-header">
                    <td class="adm-list-table-cell"><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_TARGET_FIELD') ?></td>
                    <td class="adm-list-table-cell"><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_SOURCE_FIELD') ?></td>
                </tr>
                </thead>
                <tbody>
                <?php foreach (DocumentMigrationTargetFields::getAll() as $targetCode => $meta): ?>
                    <?php
                    $inputName = 'map_' . str_replace('.', '_', $targetCode);
                    $selected = (string)($mapping[$targetCode] ?? '');
                    $groupLabel = $meta['group'] === DocumentMigrationTargetFields::GROUP_DOCUMENT
                        ? Loc::getMessage('ZR_PAIDACCESS_UTIL_GROUP_DOCUMENT')
                        : Loc::getMessage('ZR_PAIDACCESS_UTIL_GROUP_VERSION');
                    ?>
                    <tr class="adm-list-table-row">
                        <td class="adm-list-table-cell">
                            <strong><?= htmlspecialcharsbx($meta['label']) ?></strong>
                            <?php if (!empty($meta['required'])): ?><span class="required">*</span><?php endif; ?>
                            <br><span class="adm-gray"><?= htmlspecialcharsbx($groupLabel) ?></span>
                        </td>
                        <td class="adm-list-table-cell">
                            <select name="<?= htmlspecialcharsbx($inputName) ?>" style="min-width: 420px;">
                                <?php foreach ($sourceOptions as $sourceId => $sourceLabel): ?>
                                    <option value="<?= htmlspecialcharsbx((string)$sourceId) ?>"<?= $selected === (string)$sourceId ? ' selected' : '' ?>>
                                        <?= htmlspecialcharsbx($sourceLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <p class="zr-utilities-form-actions">
                <input type="submit" name="save_mapping" class="adm-btn" value="<?= Loc::getMessage('ZR_PAIDACCESS_UTIL_SAVE_MAPPING') ?>">
                <input type="submit" name="preview_migration" class="adm-btn" value="<?= Loc::getMessage('ZR_PAIDACCESS_UTIL_PREVIEW') ?>">
            </p>
            </div>

            <div class="adm-detail-content-item-block">
                <div class="adm-detail-title"><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_STEP_RUN') ?></div>
                <p>
                    <label>
                        <input type="checkbox" name="skip_existing" value="Y" checked>
                        <?= Loc::getMessage('ZR_PAIDACCESS_UTIL_SKIP_EXISTING') ?>
                    </label>
                </p>
                <p class="zr-utilities-form-actions">
                    <input type="submit" name="run_migration" class="adm-btn-save"
                           onclick="return confirm('<?= CUtil::JSEscape(Loc::getMessage('ZR_PAIDACCESS_UTIL_RUN_CONFIRM')) ?>');"
                           value="<?= Loc::getMessage('ZR_PAIDACCESS_UTIL_RUN') ?>">
                </p>
            </div>
        <?php else: ?>
            <div class="adm-detail-content-item-block zr-utilities-step-locked">
                <div class="adm-detail-title"><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_STEP_MAPPING') ?></div>
                <p class="zr-utilities-step-hint"><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_STEP_MAPPING_LOCKED') ?></p>
            </div>
        <?php endif; ?>
    </form>

    <?php if (is_array($preview) && $preview['items'] !== []): ?>
        <div class="adm-detail-content-item-block">
        <div class="adm-detail-title"><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_PREVIEW_TITLE') ?> (<?= (int)$preview['total'] ?>)</div>
        <table class="adm-list-table" style="width:100%;">
            <thead>
            <tr class="adm-list-table-header">
                <td class="adm-list-table-cell">ID</td>
                <td class="adm-list-table-cell"><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_COL_TITLE') ?></td>
                <td class="adm-list-table-cell">CODE</td>
                <td class="adm-list-table-cell"><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_COL_FILE') ?></td>
                <td class="adm-list-table-cell"><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_COL_DATE_CREATE') ?></td>
                <td class="adm-list-table-cell"><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_COL_DATE_PUBLISH') ?></td>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($preview['items'] as $item): ?>
                <tr class="adm-list-table-row">
                    <td class="adm-list-table-cell"><?= (int)$item['ELEMENT_ID'] ?></td>
                    <td class="adm-list-table-cell"><?= htmlspecialcharsbx((string)$item['TITLE']) ?></td>
                    <td class="adm-list-table-cell"><?= htmlspecialcharsbx((string)$item['CODE']) ?></td>
                    <td class="adm-list-table-cell">
                        <?= (int)$item['FILE_ID'] > 0 ? '#' . (int)$item['FILE_ID'] : '—' ?>
                        <?= !empty($item['HAS_BODY']) ? ' + HTML' : '' ?>
                    </td>
                    <td class="adm-list-table-cell"><?= htmlspecialcharsbx((string)$item['DATE_CREATE']) ?></td>
                    <td class="adm-list-table-cell"><?= htmlspecialcharsbx((string)$item['DATE_PUBLISH']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>

    <?php if (is_array($migrateResult)): ?>
        <div class="adm-detail-content-item-block">
        <div class="adm-detail-title"><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_RESULT_TITLE') ?></div>
        <ul class="zr-utilities-result-list">
            <li><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_RESULT_CREATED') ?>: <?= (int)$migrateResult['created_documents'] ?></li>
            <li><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_RESULT_SKIPPED') ?>: <?= (int)$migrateResult['skipped'] ?></li>
        </ul>
        </div>
    <?php endif; ?>
</div>
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
