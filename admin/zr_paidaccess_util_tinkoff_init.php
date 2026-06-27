<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SiteTable;
use Zr\PaidAccess\Admin\TinkoffInitDiagnosticAdminService;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Utility\UtilitiesRegistry;

global $APPLICATION;

$moduleId = PaidAccessCore::MODULE_ID;

Loader::includeModule($moduleId);

$moduleRoot = dirname(__DIR__);
Loc::loadMessages($moduleRoot . '/lang/' . LANGUAGE_ID . '/admin/' . basename(__FILE__));

\Bitrix\Main\Page\Asset::getInstance()->addCss('/bitrix/themes/.default/zr.paidaccess.css');

$RIGHT = $APPLICATION->GetGroupRight($moduleId);
if ($RIGHT < 'W') {
    $APPLICATION->AuthForm(GetMessage('ACCESS_DENIED'));
}

$utilityMeta = UtilitiesRegistry::findUtility(UtilitiesRegistry::GROUP_DIAGNOSTICS, 'tinkoff_init');
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

$gatewayOptions = TinkoffInitDiagnosticAdminService::getTinkoffGatewayOptions();

$siteId = PaidAccessCore::normalizeSiteId((string)$request->getPost('site_id') ?: (string)$request->get('site_id'));
if ($siteId === '' && $siteOptions !== []) {
    $siteId = (string)array_key_first($siteOptions);
}

$gatewayId = (int)$request->getPost('gateway_id');
if ($gatewayId <= 0) {
    $gatewayId = (int)$request->get('gateway_id');
}
if ($gatewayId <= 0 && $gatewayOptions !== []) {
    $gatewayId = (int)array_key_first($gatewayOptions);
}

$email = trim((string)$request->getPost('email'));
$runInit = $request->getPost('run_init') === 'Y';
$runTraceroute = $request->getPost('run_traceroute') === 'Y';

if (!$request->isPost()) {
    $runInit = true;
    $runTraceroute = true;
}

$report = null;
$errorMessage = '';

if ($request->isPost() && check_bitrix_sessid() && $request->getPost('run_diagnostic') !== null) {
    if ($gatewayId <= 0) {
        $errorMessage = Loc::getMessage('ZR_PAIDACCESS_UTIL_TINKOFF_INIT_ERR_GATEWAY');
    } else {
        try {
            $report = TinkoffInitDiagnosticAdminService::run($gatewayId, $siteId, [
                'runInit' => $runInit,
                'runTraceroute' => $runTraceroute,
                'email' => $email,
                'timeoutSeconds' => TinkoffInitDiagnosticAdminService::DEFAULT_TIMEOUT_SECONDS,
            ]);
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
        }
    }
}

$groupTitle = (string)$utilityMeta['group']['title'];
$utilityTitle = (string)$utilityMeta['utility']['title'];
$APPLICATION->SetTitle($groupTitle . ' — ' . $utilityTitle);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$indexUrl = UtilitiesRegistry::buildIndexUrl(LANGUAGE_ID);
?>
<div class="zr-paidaccess-admin zr-paidaccess-util-tinkoff-init">
    <p><a href="<?= htmlspecialcharsbx($indexUrl) ?>">&larr; <?= Loc::getMessage('ZR_PAIDACCESS_UTIL_BACK') ?></a></p>

    <p class="adm-info-message-wrap">
        <span class="adm-info-message"><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_TINKOFF_INIT_HINT') ?></span>
    </p>

    <?php if ($errorMessage !== ''): ?>
        <div class="adm-info-message-wrap adm-info-message-red">
            <span class="adm-info-message"><?= htmlspecialcharsbx($errorMessage) ?></span>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= $APPLICATION->GetCurPageParam('', ['run']) ?>">
        <?= bitrix_sessid_post() ?>

        <table class="adm-detail-content-table edit-table">
            <tr>
                <td width="40%"><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_TINKOFF_INIT_GATEWAY') ?>:</td>
                <td width="60%">
                    <select name="gateway_id" required>
                        <?php foreach ($gatewayOptions as $id => $label): ?>
                            <option value="<?= (int)$id ?>"<?= (int)$id === $gatewayId ? ' selected' : '' ?>>
                                <?= htmlspecialcharsbx($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_TINKOFF_INIT_SITE') ?>:</td>
                <td>
                    <select name="site_id">
                        <?php foreach ($siteOptions as $lid => $label): ?>
                            <option value="<?= htmlspecialcharsbx($lid) ?>"<?= $lid === $siteId ? ' selected' : '' ?>>
                                <?= htmlspecialcharsbx($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_TINKOFF_INIT_EMAIL') ?>:</td>
                <td>
                    <input type="email" name="email" value="<?= htmlspecialcharsbx($email) ?>" size="40"
                           placeholder="user@example.com">
                </td>
            </tr>
            <tr>
                <td><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_TINKOFF_INIT_OPTIONS') ?>:</td>
                <td>
                    <input type="hidden" name="run_init" value="N">
                    <input type="hidden" name="run_traceroute" value="N">
                    <label>
                        <input type="checkbox" name="run_init" value="Y"<?= $runInit ? ' checked' : '' ?>>
                        <?= Loc::getMessage('ZR_PAIDACCESS_UTIL_TINKOFF_INIT_OPT_INIT') ?>
                    </label><br>
                    <label>
                        <input type="checkbox" name="run_traceroute" value="Y"<?= $runTraceroute ? ' checked' : '' ?>>
                        <?= Loc::getMessage('ZR_PAIDACCESS_UTIL_TINKOFF_INIT_OPT_TRACEROUTE') ?>
                    </label>
                </td>
            </tr>
        </table>

        <p>
            <input type="submit" name="run_diagnostic" value="<?= Loc::getMessage('ZR_PAIDACCESS_UTIL_TINKOFF_INIT_RUN') ?>"
                   class="adm-btn adm-btn-green">
        </p>
    </form>

    <?php if (is_array($report)): ?>
        <?php
        $supportPackage = (string)($report['supportPackage'] ?? '');
        $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
        ?>
        <section class="zr-paidaccess-audit-context">
            <h2><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_TINKOFF_INIT_SUMMARY') ?></h2>
            <dl class="zr-paidaccess-audit-context__facts">
                <dt><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_TINKOFF_INIT_OUTBOUND_IP') ?></dt>
                <dd><?= htmlspecialcharsbx((string)($summary['outboundPublicIp'] ?? '') ?: '—') ?></dd>
                <dt><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_TINKOFF_INIT_INIT_TIME') ?></dt>
                <dd><?= htmlspecialcharsbx((string)($summary['initRequestAt'] ?? '') ?: '—') ?></dd>
                <dt>Init HTTP</dt>
                <dd><?= (int)($summary['initHttpStatus'] ?? 0) ?> / Success: <?= !empty($summary['initSuccess']) ? 'Y' : 'N' ?></dd>
            </dl>

            <h3><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_TINKOFF_INIT_SUPPORT') ?></h3>
            <textarea id="zr_tinkoff_support_package" class="zr-paidaccess-util-support" rows="18" readonly><?= htmlspecialcharsbx($supportPackage) ?></textarea>
            <p>
                <button type="button" class="adm-btn" onclick="navigator.clipboard.writeText(document.getElementById('zr_tinkoff_support_package').value)">
                    <?= Loc::getMessage('ZR_PAIDACCESS_UTIL_TINKOFF_INIT_COPY') ?>
                </button>
            </p>

            <h3><?= Loc::getMessage('ZR_PAIDACCESS_UTIL_TINKOFF_INIT_STEPS') ?></h3>
            <?php foreach ((array)($report['steps'] ?? []) as $step): ?>
                <details class="zr-paidaccess-audit-context__details">
                    <summary><?= htmlspecialcharsbx((string)($step['title'] ?? '')) ?></summary>
                    <pre class="zr-paidaccess-audit-context__json"><?php
                        $json = json_encode($step['data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                echo htmlspecialcharsbx($json !== false ? $json : '{}');
                ?></pre>
                </details>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</div>
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
