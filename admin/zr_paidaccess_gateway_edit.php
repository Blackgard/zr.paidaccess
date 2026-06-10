<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SiteTable;
use Bitrix\Main\UI\Extension;
use Zr\PaidAccess\Admin\AdminJsonResponse;
use Zr\PaidAccess\Admin\GatewayFormBuilder;
use Zr\PaidAccess\Admin\StatusBadgeRenderer;
use Zr\PaidAccess\Gateway\GatewayRepository;
use Zr\PaidAccess\Gateway\GatewayTestService;
use Zr\PaidAccess\Gateway\Provider\GatewayProviderRegistry;
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

Extension::load(['ui.buttons', 'ui.forms', 'ui.alerts', 'ui.notification', 'jquery']);

\Bitrix\Main\Page\Asset::getInstance()->addCss('/bitrix/themes/.default/zr.paidaccess.css');

$id = (int)($request->get('ID') ?? 0);
$isEditMode = $id > 0;

global $USER;

if ($request->isPost() && check_bitrix_sessid()) {
    $ajaxAction = (string)$request->getPost('ajax_action');

    if ($ajaxAction === 'create_test_payment' && $id > 0 && $RIGHT >= 'W') {
        try {
            $result = GatewayTestService::createTestPayment($id, (int)$USER->GetID());
            AdminJsonResponse::send([
                'success' => true,
                'paymentId' => $result['paymentId'],
                'orderId' => $result['orderId'],
                'amount' => $result['amount'],
                'html' => $result['qrHtml'],
            ]);
        } catch (\Throwable $e) {
            AdminJsonResponse::send(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    if ($ajaxAction === 'mark_test_passed' && $id > 0 && $RIGHT >= 'W') {
        try {
            $gateway = GatewayRepository::getById($id);
            if (!$gateway || ($gateway['IS_TEST'] ?? 'N') !== 'Y') {
                throw new \RuntimeException('Доступно только для тестового шлюза');
            }

            GatewayRepository::markTestPassed($id, (int)($gateway['TEST_MODULE_PAYMENT_ID'] ?? 0));
            AdminJsonResponse::send(['success' => true]);
        } catch (\Throwable $e) {
            AdminJsonResponse::send(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}

$APPLICATION->SetTitle(
    $isEditMode
        ? Loc::getMessage('ZR_PAIDACCESS_GATEWAY_EDIT_TITLE') . ' #' . $id
        : Loc::getMessage('ZR_PAIDACCESS_GATEWAY_ADD')
);

$formValues = [
    'NAME' => '',
    'PROVIDER' => '',
    'SITE_ID' => '',
    'ACTIVE' => 'Y',
    'IS_DEFAULT' => 'N',
    'IS_TEST' => 'N',
    'TEST_PASSED' => 'N',
    'TEST_PASSED_AT' => null,
    'TEST_MODULE_PAYMENT_ID' => 0,
    'SORT' => 500,
];

$optionsValues = [];
$message = null;
$bVarsFromForm = false;

if ($isEditMode) {
    $gatewayRow = GatewayRepository::getById($id);
    if (!$gatewayRow) {
        require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
        CAdminMessage::ShowMessage(Loc::getMessage('ZR_PAIDACCESS_NOT_FOUND'));
        require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';

        return;
    }
    $formValues = array_merge($formValues, $gatewayRow);
    $optionsValues = GatewayRepository::getOptionsForGateway($gatewayRow);
}

if ($request->isPost() && check_bitrix_sessid()) {
    $save = $request->getPost('save');
    $apply = $request->getPost('apply');

    if ($save !== null && $save !== '' || $apply !== null && $apply !== '') {
        $provider = trim((string)$request->getPost('PROVIDER'));
        $options = GatewayFormBuilder::collectOptionsFromRequest($provider, $request->getPost('OPTIONS'));
        $errors = GatewayFormBuilder::validateRequired($provider, $options);

        if ($provider === '' || !GatewayProviderRegistry::hasProvider($provider)) {
            $errors[] = 'Выберите провайдера';
        }

        if (trim((string)$request->getPost('NAME')) === '') {
            $errors[] = 'Укажите название шлюза';
        }

        $isTest = $request->getPost('IS_TEST') === 'Y';

        $formValues = [
            'NAME' => trim((string)$request->getPost('NAME')),
            'PROVIDER' => $provider,
            'SITE_ID' => trim((string)$request->getPost('SITE_ID')),
            'ACTIVE' => $request->getPost('ACTIVE') === 'Y' ? 'Y' : 'N',
            'IS_DEFAULT' => $isTest ? 'N' : ($request->getPost('IS_DEFAULT') === 'Y' ? 'Y' : 'N'),
            'IS_TEST' => $isTest ? 'Y' : 'N',
            'SORT' => (int)$request->getPost('SORT'),
        ];
        $optionsValues = array_merge(
            GatewayProviderRegistry::getDefaultOptionsForProvider($provider),
            $options
        );
        $bVarsFromForm = true;

        if (empty($errors)) {
            $fields = array_merge($formValues, ['OPTIONS' => $options]);

            try {
                $wasNew = !$isEditMode;

                if ($isEditMode) {
                    GatewayRepository::update($id, $fields);
                } else {
                    $id = GatewayRepository::add($fields);
                    $isEditMode = true;
                }

                $gatewayRow = GatewayRepository::getById($id);
                if ($gatewayRow) {
                    $formValues = array_merge($formValues, $gatewayRow);
                    $optionsValues = GatewayRepository::getOptionsForGateway($gatewayRow);
                }
                $bVarsFromForm = false;

                if ($save !== null && $save !== '') {
                    LocalRedirect('zr_paidaccess_gateways.php?lang=' . $languageId);
                }

                if ($wasNew) {
                    LocalRedirect(
                        $APPLICATION->GetCurPage() . '?ID=' . $id . '&lang=' . $languageId,
                        false,
                        '301 Moved Permanently'
                    );
                }

                $saveMessage = Loc::getMessage('ZR_PAIDACCESS_SAVE_SUCCESS');
                if ($formValues['ACTIVE'] === 'Y'
                    && $formValues['IS_TEST'] !== 'Y'
                    && !GatewayRepository::hasPassedTestGateway($formValues['SITE_ID'])
                ) {
                    $saveMessage .= '<br>' . Loc::getMessage('ZR_PAIDACCESS_WARN_PROD_WITHOUT_TEST');
                }

                $message = new CAdminMessage([
                    'MESSAGE' => $saveMessage,
                    'TYPE' => 'OK',
                ]);
            } catch (\Throwable $e) {
                $message = new CAdminMessage([
                    'MESSAGE' => Loc::getMessage('ZR_PAIDACCESS_SAVE_ERROR') . ': ' . $e->getMessage(),
                    'TYPE' => 'ERROR',
                ]);
            }
        } else {
            $message = new CAdminMessage([
                'MESSAGE' => implode('<br>', $errors),
                'TYPE' => 'ERROR',
            ]);
        }
    }
}

if (!$bVarsFromForm && $isEditMode && !$message) {
    $gatewayRow = GatewayRepository::getById($id);
    if ($gatewayRow) {
        $formValues = array_merge($formValues, $gatewayRow);
        $optionsValues = GatewayRepository::getOptionsForGateway($gatewayRow);
    }
}

$provider = (string)$formValues['PROVIDER'];

$providers = GatewayProviderRegistry::getProviderSelect();
$sites = ['' => Loc::getMessage('ZR_PAIDACCESS_ALL_SITES')];
$siteResult = SiteTable::getList([
    'select' => ['LID', 'NAME'],
    'filter' => ['=ACTIVE' => 'Y'],
    'order' => ['SORT' => 'ASC'],
]);
while ($site = $siteResult->fetch()) {
    $sites[$site['LID']] = '[' . $site['LID'] . '] ' . $site['NAME'];
}

$aTabs = [
    [
        'DIV' => 'main',
        'TAB' => Loc::getMessage('ZR_PAIDACCESS_TAB_MAIN'),
        'TITLE' => Loc::getMessage('ZR_PAIDACCESS_TAB_MAIN'),
    ],
    [
        'DIV' => 'options',
        'TAB' => Loc::getMessage('ZR_PAIDACCESS_TAB_OPTIONS'),
        'TITLE' => Loc::getMessage('ZR_PAIDACCESS_TAB_OPTIONS'),
    ],
    [
        'DIV' => 'test',
        'TAB' => Loc::getMessage('ZR_PAIDACCESS_TAB_TEST'),
        'TITLE' => Loc::getMessage('ZR_PAIDACCESS_TAB_TEST'),
    ],
];

$isTestGateway = ($formValues['IS_TEST'] ?? 'N') === 'Y';
$testPassed = ($formValues['TEST_PASSED'] ?? 'N') === 'Y';
$testAmount = PaidAccessCore::getGatewayTestAmount($formValues['SITE_ID'] ?: null);

$tabControl = new CAdminTabControl('tabControl', $aTabs);

$formAction = $APPLICATION->GetCurPage() . ($id > 0 ? '?ID=' . $id . '&lang=' . $languageId : '?lang=' . $languageId);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>

<div class="zr-paidaccess-admin">

<?php
$aContext = [
    [
        'TEXT' => Loc::getMessage('ZR_PAIDACCESS_LIST_BTN'),
        'LINK' => 'zr_paidaccess_gateways.php?lang=' . $languageId,
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

<form method="post"
      action="<?= htmlspecialcharsbx($formAction) ?>"
      name="zr_paidaccess_gateway_form"
      id="zr_paidaccess_gateway_form">
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
            <div class="adm-detail-content-item-block">
                <table class="edit-table">
                    <tr>
                        <td width="40%" class="adm-detail-content-cell-l">
                            <?= Loc::getMessage('ZR_PAIDACCESS_FIELD_ACTIVE') ?>:
                        </td>
                        <td width="60%" class="adm-detail-content-cell-r">
                            <input type="checkbox" name="ACTIVE" value="Y"
                                <?= $formValues['ACTIVE'] === 'Y' ? ' checked' : '' ?>>
                        </td>
                    </tr>
                    <tr>
                        <td class="adm-detail-content-cell-l">
                            <?= Loc::getMessage('ZR_PAIDACCESS_FIELD_DEFAULT') ?>:
                        </td>
                        <td class="adm-detail-content-cell-r">
                            <input type="checkbox" name="IS_DEFAULT" value="Y" id="zr_gateway_is_default"
                                <?= $formValues['IS_DEFAULT'] === 'Y' ? ' checked' : '' ?>
                                <?= ($formValues['IS_TEST'] ?? 'N') === 'Y' ? ' disabled' : '' ?>>
                        </td>
                    </tr>
                    <tr>
                        <td class="adm-detail-content-cell-l">
                            <?= Loc::getMessage('ZR_PAIDACCESS_FIELD_IS_TEST') ?>:
                        </td>
                        <td class="adm-detail-content-cell-r">
                            <input type="checkbox" name="IS_TEST" value="Y" id="zr_gateway_is_test"
                                <?= ($formValues['IS_TEST'] ?? 'N') === 'Y' ? ' checked' : '' ?>>
                            <div class="adm-info-message-wrap" style="margin-top:8px;">
                                <div class="adm-info-message"><?= Loc::getMessage('ZR_PAIDACCESS_FIELD_IS_TEST_HINT') ?></div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="adm-detail-content-cell-l">
                            <?= Loc::getMessage('ZR_PAIDACCESS_FIELD_NAME') ?>:<span class="required">*</span>
                        </td>
                        <td class="adm-detail-content-cell-r">
                            <input type="text" name="NAME" id="zr_gateway_name_field"
                                   value="<?= htmlspecialcharsbx($formValues['NAME']) ?>"
                                   maxlength="255" required>
                        </td>
                    </tr>
                    <tr>
                        <td class="adm-detail-content-cell-l">
                            <?= Loc::getMessage('ZR_PAIDACCESS_FIELD_PROVIDER') ?>:<span class="required">*</span>
                        </td>
                        <td class="adm-detail-content-cell-r">
                            <select name="PROVIDER" id="zr-gateway-provider" class="select-field"
                                    data-prev="<?= htmlspecialcharsbx($provider) ?>">
                                <?php foreach ($providers as $code => $title): ?>
                                    <option value="<?= htmlspecialcharsbx($code) ?>"
                                        <?= $provider === $code ? ' selected' : '' ?>>
                                        <?= htmlspecialcharsbx($title) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="adm-detail-content-cell-l">
                            <?= Loc::getMessage('ZR_PAIDACCESS_FIELD_SITE') ?>:
                        </td>
                        <td class="adm-detail-content-cell-r">
                            <select name="SITE_ID" class="select-field">
                                <?php foreach ($sites as $lid => $siteTitle): ?>
                                    <option value="<?= htmlspecialcharsbx($lid) ?>"
                                        <?= (string)$formValues['SITE_ID'] === $lid ? ' selected' : '' ?>>
                                        <?= htmlspecialcharsbx($siteTitle) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="adm-detail-content-cell-l">
                            <?= Loc::getMessage('ZR_PAIDACCESS_FIELD_SORT') ?>:
                        </td>
                        <td class="adm-detail-content-cell-r">
                            <input type="number" name="SORT" class="select-field"
                                   value="<?= (int)$formValues['SORT'] ?>" min="0" step="1">
                        </td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>

    <?php $tabControl->BeginNextTab(); ?>

    <tr>
        <td colspan="2" align="center">
            <div class="adm-detail-content-item-block">
                <table class="edit-table" id="zr-gateway-options-table">
                    <?= GatewayFormBuilder::renderProviderFields($provider, $optionsValues, $id) ?>
                </table>
            </div>
        </td>
    </tr>

    <?php $tabControl->BeginNextTab(); ?>

    <tr>
        <td colspan="2" align="center">
            <div class="adm-detail-content-item-block">
                <?php if (!$isTestGateway): ?>
                    <div class="adm-info-message"><?= Loc::getMessage('ZR_PAIDACCESS_TEST_ONLY_FOR_TEST_GATEWAY') ?></div>
                <?php else: ?>
                    <table class="edit-table">
                        <tr>
                            <td width="40%" class="adm-detail-content-cell-l"><?= Loc::getMessage('ZR_PAIDACCESS_TEST_STATUS') ?>:</td>
                            <td width="60%" class="adm-detail-content-cell-r">
                                <?= $testPassed
                                ? StatusBadgeRenderer::render(
                                    Loc::getMessage('ZR_PAIDACCESS_TEST_PASSED'),
                                    StatusBadgeRenderer::STYLE_COMPLETED
                                )
                                : StatusBadgeRenderer::render(
                                    Loc::getMessage('ZR_PAIDACCESS_TEST_NOT_PASSED'),
                                    StatusBadgeRenderer::STYLE_WARNING
                                ) ?>
                            </td>
                        </tr>
                        <?php if ($testPassed && !empty($formValues['TEST_PASSED_AT'])): ?>
                            <tr>
                                <td class="adm-detail-content-cell-l"><?= Loc::getMessage('ZR_PAIDACCESS_TEST_PASSED_AT') ?>:</td>
                                <td class="adm-detail-content-cell-r">
                                    <?php
                                $passedAt = $formValues['TEST_PASSED_AT'];
                            echo htmlspecialcharsbx(
                                $passedAt instanceof \Bitrix\Main\Type\DateTime
                                    ? $passedAt->toString()
                                    : (string)$passedAt
                            );
                            ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php if ((int)($formValues['TEST_MODULE_PAYMENT_ID'] ?? 0) > 0): ?>
                            <tr>
                                <td class="adm-detail-content-cell-l"><?= Loc::getMessage('ZR_PAIDACCESS_TEST_PAYMENT_ID') ?>:</td>
                                <td class="adm-detail-content-cell-r">
                                    <a href="zr_paidaccess_payment_edit.php?ID=<?= (int)$formValues['TEST_MODULE_PAYMENT_ID'] ?>&lang=<?= LANGUAGE_ID ?>">
                                        #<?= (int)$formValues['TEST_MODULE_PAYMENT_ID'] ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="adm-detail-content-cell-l"><?= Loc::getMessage('ZR_PAIDACCESS_TEST_AMOUNT') ?>:</td>
                            <td class="adm-detail-content-cell-r">
                                <?= number_format($testAmount, 2, '.', ' ') ?> ₽
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <div class="adm-info-message-wrap">
                                    <div class="adm-info-message"><?= Loc::getMessage('ZR_PAIDACCESS_TEST_INSTRUCTION') ?></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" class="adm-detail-content-cell-r">
                                <?php if ($id > 0): ?>
                                    <button type="button" class="adm-btn" id="zr_gateway_test_create_btn"
                                        <?= $testPassed ? ' disabled' : '' ?>>
                                        <?= Loc::getMessage('ZR_PAIDACCESS_TEST_BTN_CREATE') ?>
                                    </button>
                                    <button type="button" class="adm-btn" id="zr_gateway_test_mark_btn"
                                        <?= $testPassed ? ' disabled' : '' ?>>
                                        <?= Loc::getMessage('ZR_PAIDACCESS_TEST_BTN_MARK') ?>
                                    </button>
                                <?php else: ?>
                                    <div class="adm-info-message"><?= Loc::getMessage('ZR_PAIDACCESS_TEST_SAVE_FIRST') ?></div>
                                <?php endif; ?>
                                <div id="zr_gateway_test_error" style="color:#c0392b;margin-top:12px;display:none;"></div>
                                <div id="zr_gateway_test_qr" style="margin-top:16px;"></div>
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>
            </div>
        </td>
    </tr>

    <?php
    $tabControl->Buttons([
        'back_url' => 'zr_paidaccess_gateways.php?lang=' . $languageId,
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

<script>
(function () {
    function applyShowIf() {
        var table = document.getElementById('zr-gateway-options-table');
        if (!table) {
            return;
        }

        table.querySelectorAll('.zr-gateway-field-row').forEach(function (row) {
            var show = true;
            var attrs = row.attributes;
            for (var i = 0; i < attrs.length; i++) {
                var name = attrs[i].name;
                if (name.indexOf('data-zr-show-if-') !== 0) {
                    continue;
                }
                var depCode = name.replace('data-zr-show-if-', '');
                var expected = attrs[i].value;
                var dep = table.querySelector('[name="OPTIONS[' + depCode + ']"]');
                if (!dep || dep.value !== expected) {
                    show = false;
                }
            }
            row.style.display = show ? '' : 'none';
        });
    }

    var table = document.getElementById('zr-gateway-options-table');
    if (table) {
        table.addEventListener('change', applyShowIf);
        applyShowIf();
    }

    var isTestCheckbox = document.getElementById('zr_gateway_is_test');
    var isDefaultCheckbox = document.getElementById('zr_gateway_is_default');
    if (isTestCheckbox && isDefaultCheckbox) {
        var syncTestDefault = function () {
            if (isTestCheckbox.checked) {
                isDefaultCheckbox.checked = false;
                isDefaultCheckbox.disabled = true;
            } else {
                isDefaultCheckbox.disabled = false;
            }
        };
        isTestCheckbox.addEventListener('change', syncTestDefault);
        syncTestDefault();
    }

    var testCreateBtn = document.getElementById('zr_gateway_test_create_btn');
    var testMarkBtn = document.getElementById('zr_gateway_test_mark_btn');
    var testQrBox = document.getElementById('zr_gateway_test_qr');
    var testErrorBox = document.getElementById('zr_gateway_test_error');

    function gatewayTestAjax(action, onSuccess) {
        if (testErrorBox) {
            testErrorBox.style.display = 'none';
            testErrorBox.textContent = '';
        }

        var formData = new FormData();
        formData.append('ajax_action', action);
        formData.append('sessid', '<?= bitrix_sessid() ?>');

        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success) {
                    throw new Error(data.error || '<?= CUtil::JSEscape(Loc::getMessage('ZR_PAIDACCESS_TEST_ERROR')) ?>');
                }
                onSuccess(data);
            })
            .catch(function (error) {
                if (testErrorBox) {
                    testErrorBox.style.display = 'block';
                    testErrorBox.textContent = error.message;
                }
            });
    }

    if (testCreateBtn) {
        testCreateBtn.addEventListener('click', function () {
            testCreateBtn.disabled = true;
            testCreateBtn.textContent = '<?= CUtil::JSEscape(Loc::getMessage('ZR_PAIDACCESS_TEST_CREATING')) ?>';
            gatewayTestAjax('create_test_payment', function (data) {
                if (testQrBox) {
                    testQrBox.innerHTML = data.html || '';
                }
                testCreateBtn.disabled = false;
                testCreateBtn.textContent = '<?= CUtil::JSEscape(Loc::getMessage('ZR_PAIDACCESS_TEST_BTN_CREATE')) ?>';
            });
        });
    }

    if (testMarkBtn) {
        testMarkBtn.addEventListener('click', function () {
            if (!confirm('Отметить тест пройденным без webhook?')) {
                return;
            }
            gatewayTestAjax('mark_test_passed', function () {
                window.location.reload();
            });
        });
    }

    var providerSelect = document.getElementById('zr-gateway-provider');
    if (providerSelect) {
        providerSelect.addEventListener('focus', function () {
            providerSelect.setAttribute('data-prev', providerSelect.value);
        });

        providerSelect.addEventListener('change', function () {
            var prev = providerSelect.getAttribute('data-prev') || '';
            if (providerSelect.value === prev) {
                return;
            }

            if (!confirm('<?= CUtil::JSEscape(Loc::getMessage('ZR_PAIDACCESS_PROVIDER_CHANGE_CONFIRM')) ?>')) {
                providerSelect.value = prev;
                return;
            }

            var form = document.getElementById('zr_paidaccess_gateway_form');
            var applyInput = document.createElement('input');
            applyInput.type = 'hidden';
            applyInput.name = 'apply';
            applyInput.value = 'Y';
            form.appendChild(applyInput);
            form.submit();
        });
    }
})();
</script>

<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
