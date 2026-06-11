<?php

use Bitrix\Main\HttpApplication;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Zr\PaidAccess\Options\ModuleOptionsStructure;

$module_id = 'zr.paidaccess';
$prefix = 'ZR_PAIDACCESS_';

global $APPLICATION;
if ($APPLICATION->GetGroupRight($module_id) == 'D') {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}

Loc::loadMessages(__FILE__);

Loader::includeModule($module_id);

$siteLid = SITE_ID == 'ru' ? 's1' : SITE_ID;
$allOptions = \Bitrix\Main\Config\Option::getForModule($module_id);

$request = HttpApplication::getInstance()->getContext()->getRequest();

$arStructureOptions = ModuleOptionsStructure::getGroups();

$aTabs = [];
$rsSites = CSite::GetList($by = 'sort', $order = 'asc', ['ACTIVE' => 'Y']);
while ($arSite = $rsSites->Fetch()) {
    foreach ($arStructureOptions as $topCodeSetting => $arTopSettings) {
        $arOptions = [];
        if (!empty($arTopSettings['OPTIONS']) && is_array($arTopSettings['OPTIONS']) && count($arTopSettings['OPTIONS']) > 0) {
            foreach ($arTopSettings['OPTIONS'] as $codeOption => $arOption) {
                $option = [
                    !empty($codeOption) ? $codeOption .'_'. $arSite['LID'] : '',
                    $arOption['TITLE'],
                    $arOption['DEFAULT'] ?: ''
                ];

                $haveError = false;
                switch ($arOption['TYPE']) {
                    case "multiselectbox":
                        $option[] = [
                            'multiselectbox',
                            $arOption['VALUES'],
                        ];
                        break;
                    case "textarea":
                        $option[] = [
                            'textarea',
                            $arOption['ROWS'],
                            $arOption['COLS'],
                        ];
                        break;
                    case "statictext":
                        break;
                    case "statichtml":
                        $htmlValue = $arOption['VALUE'];
                        if (!empty($arOption['GATEWAY'])) {
                            $htmlValue = '<div class="zr-paidaccess-gateway-field" data-zr-gateway="'
                                . htmlspecialcharsbx($arOption['GATEWAY']) . '" data-zr-field="'
                                . htmlspecialcharsbx($codeOption) . '">' . $htmlValue . '</div>';
                        }
                        $option[2] = $htmlValue;
                        $option[3] = ['statichtml'];
                        break;
                    case "checkbox":
                        $option[] = [
                            'checkbox'
                        ];
                        break;
                    case "text":
                        $option[] = ['text', $arOption['WIDTH'] ?: 30];
                        break;
                    case "password":
                        break;
                    case "selectbox":
                        $option[] = ["selectbox", $arOption['VALUES']];
                        break;
                    case "file":
                        $option[] = ["file"];
                        break;
                    case "note":
                        $option = ["note" => $arOption['TEXT']];
                        break;
                    case "title":
                        if (!empty($arOption['GATEWAY'])) {
                            $option = '<span class="zr-paidaccess-gateway-field" data-zr-gateway="'
                                . htmlspecialcharsbx($arOption['GATEWAY']) . '" data-zr-field="'
                                . htmlspecialcharsbx($codeOption) . '">' . $arOption['TEXT'] . '</span>';
                        } else {
                            $option = $arOption['TEXT'];
                        }
                        break;
                    default:
                        $haveError = true;
                        break;
                }

                if ($haveError) {
                    continue;
                }
                $arOptions[] = $option;
            }
        }

        $aTabs[] =
        [
            'DIV' => $arTopSettings['ID'] . "_" . $arSite['LID'],
            'TAB' => $arTopSettings['TITLE'].' ('.$arSite['LID'].')',
            'OPTIONS' => $arOptions
        ];
    }
}

// save settings
if ($request->isPost() && $request['Update'] && check_bitrix_sessid()) {
    foreach ($aTabs as $aTab) {
        foreach ($aTab['OPTIONS'] as $arOption) {
            if (!is_array($arOption)) {
                continue;
            }
            if ($arOption['note']) {
                continue;
            }
            __AdmSettingsSaveOption($module_id, $arOption);
        }
    }
}

// Show form
$tabControl = new CAdminTabControl('tabControl', $aTabs);
?>

<?$tabControl->Begin();?>
<form method="POST" action="<?=$APPLICATION->GetCurPage()?>?mid=<?=htmlspecialcharsbx($request['mid'])?>&lang=<?=$request['lang']?>" name="zr_reviewhl_settings">
    <?=bitrix_sessid_post()?>
    <?foreach ($aTabs as $aTab) {
        if ($aTab['OPTIONS']) {
            $tabControl->BeginNextTab();
            __AdmSettingsDrawList($module_id, $aTab['OPTIONS']);
        }
    }?>
    <?php $tabControl->Buttons(); ?>
    <input type="submit" name="Update" value="<?=Loc::getMessage('MAIN_SAVE')?>">
    <input type="reset" name="reset" value="<?=Loc::getMessage('MAIN_RESET')?>">
</form>
<?$tabControl->End();?>
