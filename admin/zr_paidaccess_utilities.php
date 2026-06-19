<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Zr\PaidAccess\PaidAccessCore;
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

$APPLICATION->SetTitle(Loc::getMessage('ZR_PAIDACCESS_UTIL_INDEX_TITLE'));

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$groups = UtilitiesRegistry::getGroups();
?>
<div class="zr-paidaccess-admin zr-paidaccess-utilities-index">
    <p class="adm-info-message-wrap">
        <span class="adm-info-message"><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_INDEX_HINT') ?></span>
    </p>

    <?php foreach ($groups as $group): ?>
        <section class="zr-utilities-group">
            <header class="zr-utilities-group__header">
                <h2 class="zr-utilities-group__title"><?= htmlspecialcharsbx((string)$group['title']) ?></h2>
                <p class="zr-utilities-group__description"><?= htmlspecialcharsbx((string)$group['description']) ?></p>
            </header>

            <div class="zr-utilities-group__list">
                <?php foreach ($group['utilities'] as $utility): ?>
                    <?php
                    $url = (string)$utility['page'] . '?lang=' . LANGUAGE_ID;
                    ?>
                    <article class="zr-utilities-card">
                        <h3 class="zr-utilities-card__title">
                            <a href="<?= htmlspecialcharsbx($url) ?>"><?= htmlspecialcharsbx((string)$utility['title']) ?></a>
                        </h3>
                        <p class="zr-utilities-card__description"><?= htmlspecialcharsbx((string)$utility['description']) ?></p>
                        <p class="zr-utilities-card__actions">
                            <a href="<?= htmlspecialcharsbx($url) ?>" class="adm-btn adm-btn-green">
                                <?= Loc::getMessage('ZR_PAIDACCESS_UTIL_OPEN') ?>
                            </a>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
