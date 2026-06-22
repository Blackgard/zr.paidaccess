<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Zr\PaidAccess\Install\AgentInstaller;
use Zr\PaidAccess\Install\DocumentInstaller;
use Zr\PaidAccess\Install\EventInstaller;
use Zr\PaidAccess\Install\FileInstaller;
use Zr\PaidAccess\Install\FundInstaller;
use Zr\PaidAccess\Install\GatewayInstaller;
use Zr\PaidAccess\Install\GatewayTransactionInstaller;
use Zr\PaidAccess\Install\LogInstaller;
use Zr\PaidAccess\Install\MailInstaller;
use Zr\PaidAccess\Install\PaymentInstaller;
use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Tables\TableInstaller;

Loc::loadMessages(__FILE__);

class zr_paidaccess extends CModule
{
    private $moduleId = 'zr.paidaccess';

    public function __construct()
    {
        $arModuleVersion = [];

        include __DIR__ . '/version.php';

        if (is_array($arModuleVersion) && array_key_exists('VERSION', $arModuleVersion)) {
            $this->MODULE_VERSION = $arModuleVersion['VERSION'];
            $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        }

        $this->MODULE_ID = $this->moduleId;
        $this->MODULE_NAME = Loc::getMessage('ZR_PAIDACCESS_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('ZR_PAIDACCESS_MODULE_DESCRIPTION');
        $this->PARTNER_NAME = Loc::getMessage('ZR_PAIDACCESS_PARTNER_NAME');
        $this->PARTNER_URI = Loc::getMessage('ZR_PAIDACCESS_PARTNER_URI');
        $this->SHOW_SUPER_ADMIN_GROUP_RIGHTS = 'Y';
        $this->MODULE_GROUP_RIGHTS = 'Y';
    }

    public function doInstall()
    {
        ModuleManager::registerModule($this->MODULE_ID);

        $this->installDB();
        $this->installDefaultOptions();
        FileInstaller::ensureFiles();
        Loc::loadMessages(__DIR__ . '/../lang/ru/install/index.php');
        MailInstaller::ensureEvents();
        GatewayInstaller::ensureSchema();
        PaymentInstaller::ensureSchema();
        GatewayTransactionInstaller::ensureSchema();
        FundInstaller::ensureSchema();
        DocumentInstaller::ensureSchema();
        LogInstaller::ensureTables();
        AgentInstaller::ensureAgents();
        EventInstaller::ensureEvents();
        $this->InstallUserRights();
    }

    public function DoUpdate()
    {
        Loader::includeModule($this->MODULE_ID);
        FileInstaller::ensureFiles();
        GatewayInstaller::ensureSchema();
        PaymentInstaller::ensureSchema();
        GatewayTransactionInstaller::ensureSchema();
        FundInstaller::ensureSchema();
        DocumentInstaller::ensureSchema();
        LogInstaller::ensureTables();
        AgentInstaller::ensureAgents();
        EventInstaller::ensureEvents();

        return true;
    }

    public function doUninstall()
    {
        global $DOCUMENT_ROOT, $APPLICATION, $step;

        $step = intval($step);
        if ($step < 2) {
            $APPLICATION->IncludeAdminFile(Loc::getMessage("ZR_PAIDACCESS_UNINSTALL_TITLE"), __DIR__ . "/unstep1.php");
        } elseif ($step == 2) {
            if (!$_REQUEST["savedata"]) {
                $this->uninstallDB();
            }

            AgentInstaller::uninstallAgents();

            FileInstaller::uninstallFiles();
            if (empty($_REQUEST['savemail'])) {
                MailInstaller::uninstallEvents();
            }
            EventInstaller::uninstallEvents();
            $this->UnInstallUserRights();

            ModuleManager::unRegisterModule($this->MODULE_ID);

            $APPLICATION->IncludeAdminFile(Loc::getMessage("ZR_PAIDACCESS_UNINSTALL_TITLE"), __DIR__ . "/unstep2.php");
        }
    }

    public function InstallEvents()
    {
        EventInstaller::ensureEvents();
    }

    public function UnInstallEvents()
    {
        EventInstaller::uninstallEvents();
    }

    protected function installDefaultOptions(): void
    {
        $rsSites = \CSite::GetList($by = 'sort', $order = 'asc', ['ACTIVE' => 'Y']);

        while ($arSite = $rsSites->Fetch()) {
            $lid = $arSite['LID'];

            Option::set($this->MODULE_ID, PaidAccessCore::OPTION_ACCESS_BLOCK_TEMPLATE . '_' . $lid, PaidAccessCore::DEFAULT_BLOCK_TEMPLATE);
            Option::set(
                $this->MODULE_ID,
                PaidAccessCore::OPTION_DOCUMENT_CONSENT_ENABLED . '_' . $lid,
                PaidAccessCore::DEFAULT_DOCUMENT_CONSENT_ENABLED
            );
            Option::set(
                $this->MODULE_ID,
                PaidAccessCore::OPTION_DOCUMENT_CONSENT_BLOCK_TEMPLATE . '_' . $lid,
                PaidAccessCore::DEFAULT_DOCUMENT_CONSENT_BLOCK_TEMPLATE
            );
            Option::set(
                $this->MODULE_ID,
                PaidAccessCore::OPTION_DOCUMENT_CONSENT_REQUIRE_OPEN . '_' . $lid,
                PaidAccessCore::DEFAULT_DOCUMENT_CONSENT_REQUIRE_OPEN
            );
            Option::set(
                $this->MODULE_ID,
                PaidAccessCore::OPTION_ACCESS_RESTRICTED_GROUPS . '_' . $lid,
                PaidAccessCore::DEFAULT_ACCESS_RESTRICTED_GROUPS
            );
        }
    }

    public function installDB()
    {
        Loader::includeModule($this->MODULE_ID);
        TableInstaller::install();
    }

    public function uninstallDB()
    {
        Loader::includeModule($this->MODULE_ID);
        LogInstaller::dropTables();
        TableInstaller::uninstall();
    }

    public function InstallUserRights(): void
    {
        global $APPLICATION;
        $APPLICATION->SetGroupRight($this->MODULE_ID, "D", "R"); // Доступ запрещен
        $APPLICATION->SetGroupRight($this->MODULE_ID, "E", "R"); // Только чтение
        $APPLICATION->SetGroupRight($this->MODULE_ID, "F", "W"); // Полный доступ к модулю
        $APPLICATION->SetGroupRight($this->MODULE_ID, "G", "X"); // Полный доступ, включая настройки
    }

    public function UnInstallUserRights(): void
    {
        global $APPLICATION;
        $APPLICATION->DelGroupRight($this->MODULE_ID);
    }

    /**
     * Получение описания уровней доступа
     *
     * @return array
     */
    public function GetModuleRightList(): array
    {
        return [
            "reference_id" => ["D", "E", "F", "G"],
            "reference" => [
                "[D] ".Loc::getMessage("ZR_PAIDACCESS_DENIED"),
                "[E] ".Loc::getMessage("ZR_PAIDACCESS_READ_ONLY"),
                "[F] ".Loc::getMessage("ZR_PAIDACCESS_FULL_ACCESS"),
                "[G] ".Loc::getMessage("ZR_PAIDACCESS_FULL_ACCESS_SETTINGS")
            ]
        ];
    }
}
