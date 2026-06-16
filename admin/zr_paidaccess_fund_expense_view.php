<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UI\Extension;
use Zr\PaidAccess\Admin\FundAdminService;
use Zr\PaidAccess\Admin\SubscriberAdminService;
use Zr\PaidAccess\Enum\FundMovementSource;
use Zr\PaidAccess\Enum\FundMovementType;
use Zr\PaidAccess\Fund\FundBalanceService;
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
if ($RIGHT < 'R') {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}

Extension::load(['ui.buttons', 'ui.forms']);
\Bitrix\Main\Page\Asset::getInstance()->addCss('/bitrix/themes/.default/zr.paidaccess.css');

$movementId = (int)($request->get('ID') ?? 0);
$movement = $movementId > 0 ? FundMovementRepository::getById($movementId) : null;

if (!$movement) {
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
    CAdminMessage::ShowMessage(Loc::getMessage('ZR_PAIDACCESS_MOVEMENT_NOT_FOUND'));
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';

    return;
}

$fundId = (int)($movement['FUND_ID'] ?? 0);
$fund = FundRepository::getById($fundId);
if (!$fund) {
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
    CAdminMessage::ShowMessage(Loc::getMessage('ZR_PAIDACCESS_FUND_NOT_FOUND'));
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';

    return;
}

$allocations = FundAdminService::getMovementAllocations($movementId);
$type = (string)($movement['TYPE'] ?? '');
$amount = (float)($movement['AMOUNT'] ?? 0);
$isExpense = $type === FundMovementType::EXPENSE;

$APPLICATION->SetTitle(
    Loc::getMessage('ZR_PAIDACCESS_EXPENSE_VIEW_TITLE') . ' #' . $movementId
);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>

<div class="zr-paidaccess-admin">
<?php
$aContext = [
    [
        'TEXT' => Loc::getMessage('ZR_PAIDACCESS_BACK_TO_FUND'),
        'LINK' => 'zr_paidaccess_fund_edit.php?ID=' . $fundId . '&lang=' . $languageId . '#tab_movements',
        'TITLE' => Loc::getMessage('ZR_PAIDACCESS_BACK_TO_FUND'),
        'ICON' => 'btn_list',
    ],
];

$contextMenu = new CAdminContextMenu($aContext);
$contextMenu->Show();
?>

<div class="adm-detail-title"><?= Loc::getMessage('ZR_PAIDACCESS_EXPENSE_VIEW_TITLE') ?> #<?= $movementId ?></div>

<div class="adm-info-message-wrap" style="margin: 12px 0;">
    <div class="adm-info-message">
        <?= Loc::getMessage('ZR_PAIDACCESS_FUND_LABEL') ?>:
        <strong><?= htmlspecialcharsbx((string)$fund['NAME']) ?></strong>
        (<?= htmlspecialcharsbx((string)$fund['SITE_ID']) ?>)
        <br>
        <?= Loc::getMessage('ZR_PAIDACCESS_COL_DATE') ?>:
        <strong><?= htmlspecialcharsbx(FundAdminService::formatMovementDate($movement)) ?></strong>
        <br>
        <?= Loc::getMessage('ZR_PAIDACCESS_COL_AMOUNT') ?>:
        <strong style="color:#c62828;">−<?= htmlspecialcharsbx(number_format($amount, 2, '.', ' ') . ' ₽') ?></strong>
        <br>
        <?= Loc::getMessage('ZR_PAIDACCESS_COL_DESCRIPTION') ?>:
        <strong><?= htmlspecialcharsbx((string)($movement['DESCRIPTION'] ?? '')) ?></strong>
    </div>
</div>

<?php if ($isExpense && (string)($movement['SOURCE'] ?? '') === FundMovementSource::ADMIN): ?>
    <h3><?= Loc::getMessage('ZR_PAIDACCESS_EXPENSE_PARTICIPANTS_TITLE') ?></h3>

    <?php if ($allocations === []): ?>
        <div class="adm-info-message-wrap adm-info-message-yellow">
            <div class="adm-info-message"><?= Loc::getMessage('ZR_PAIDACCESS_EXPENSE_NO_PARTICIPANTS') ?></div>
        </div>
    <?php else: ?>
        <table class="internal" style="width:100%;">
            <tr class="heading">
                <td><?= Loc::getMessage('ZR_PAIDACCESS_COL_USER') ?></td>
                <td><?= Loc::getMessage('ZR_PAIDACCESS_COL_USER_ID') ?></td>
                <td><?= Loc::getMessage('ZR_PAIDACCESS_COL_SHARE_AMOUNT') ?></td>
            </tr>
            <?php foreach ($allocations as $row): ?>
                <?php
                $userId = (int)($row['USER_ID'] ?? 0);
                $userName = SubscriberAdminService::formatUserName([
                    'NAME' => (string)($row['USER_NAME'] ?? ''),
                    'LAST_NAME' => (string)($row['USER_LAST_NAME'] ?? ''),
                ]);
                if ($userName === '') {
                    $userName = (string)($row['USER_LOGIN'] ?? ('#' . $userId));
                }
                $share = (float)($row['AMOUNT'] ?? 0);
                ?>
                <tr>
                    <td><?= htmlspecialcharsbx($userName) ?></td>
                    <td><?= $userId ?></td>
                    <td><?= htmlspecialcharsbx(FundBalanceService::formatRubles($share)) ?> ₽</td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
<?php else: ?>
    <div class="adm-info-message-wrap">
        <div class="adm-info-message"><?= Loc::getMessage('ZR_PAIDACCESS_EXPENSE_NOT_ALLOCATED') ?></div>
    </div>
<?php endif; ?>
</div>

<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
