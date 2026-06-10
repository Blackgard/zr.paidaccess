<?php

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

use Bitrix\Main\Config\Option;

use Zr\PaidAccess\PaidAccessCore;
use Zr\PaidAccess\Access\AccessBlockHandler;
use Zr\PaidAccess\Access\RegistrationPaymentHandler;
use Zr\PaidAccess\Install\AgentInstaller;
use Zr\PaidAccess\Install\GatewayInstaller;
use Zr\PaidAccess\Install\LogInstaller;
use Zr\PaidAccess\Install\MailInstaller;
use Zr\PaidAccess\Install\GatewayTransactionInstaller;
use Zr\PaidAccess\Install\PaymentInstaller;
use Zr\PaidAccess\Tables\TableInstaller;

Loc::loadMessages(__FILE__);

class zr_paidaccess extends CModule
{
    private $moduleId = 'zr.paidaccess';
    private string $docRoot = '';

    /** @var array */
    protected array $files = [
        '/admin/zr_paidaccess_subscribers.php' => '/bitrix/admin/zr_paidaccess_subscribers.php',
        '/admin/zr_paidaccess_payments.php' => '/bitrix/admin/zr_paidaccess_payments.php',
        '/admin/zr_paidaccess_payment_edit.php' => '/bitrix/admin/zr_paidaccess_payment_edit.php',
        '/admin/zr_paidaccess_gateways.php' => '/bitrix/admin/zr_paidaccess_gateways.php',
        '/admin/zr_paidaccess_gateway_edit.php' => '/bitrix/admin/zr_paidaccess_gateway_edit.php',
        '/admin/zr_paidaccess_gateway_import.php' => '/bitrix/admin/zr_paidaccess_gateway_import.php',
        '/admin/zr_paidaccess_logs.php' => '/bitrix/admin/zr_paidaccess_logs.php',
        '/themes/.default/zr.paidaccess.css' => '/bitrix/themes/.default/zr.paidaccess.css',
        '/components/zr/personal.subscription' => '/local/components/zr/personal.subscription',
        '/components/zr/member.payment.list' => '/local/components/zr/member.payment.list',
        '/components/zr/fund.wallet' => '/local/components/zr/fund.wallet',
    ];

    public function __construct()
    {
        $arModuleVersion = array();
        
        include __DIR__ . '/version.php';

        if (is_array($arModuleVersion) && array_key_exists('VERSION', $arModuleVersion))
        {
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

        $context = \Bitrix\Main\Application::getInstance()->getContext();
		$server = $context->getServer();
		$this->docRoot = $server->getDocumentRoot();
    }

    public function doInstall()
    {
        ModuleManager::registerModule($this->MODULE_ID);
        
        $this->installDB();
        $this->installAccessTemplates();
        $this->installDefaultOptions();
        $this->copyFiles();
        Loc::loadMessages(__DIR__ . '/../lang/ru/install/index.php');
        MailInstaller::ensureEvents();
        GatewayInstaller::ensureSchema();
        PaymentInstaller::ensureSchema();
        GatewayTransactionInstaller::ensureSchema();
        LogInstaller::ensureTables();
        AgentInstaller::ensureAgents();
        $this->InstallEvents();
        $this->InstallUserRights();
    }

    public function doUninstall()
    {
        global $DOCUMENT_ROOT, $APPLICATION, $step;

		$step = intval($step);
		if($step<2)
		{   
			$APPLICATION->IncludeAdminFile(Loc::getMessage("ZR_PAIDACCESS_UNINSTALL_TITLE"), __DIR__ . "/unstep1.php");
		}
		elseif($step==2)
		{
            if (!$_REQUEST["savedata"])
            {
                $this->uninstallDB();
            }
			
            AgentInstaller::uninstallAgents();

            $this->removeFiles();
            if (empty($_REQUEST['savemail'])) {
                MailInstaller::uninstallEvents();
            }
            $this->UnInstallEvents();
            $this->UnInstallUserRights();

            ModuleManager::unRegisterModule($this->MODULE_ID);

            $APPLICATION->IncludeAdminFile(Loc::getMessage("ZR_PAIDACCESS_UNINSTALL_TITLE"), __DIR__ . "/unstep2.php");
		}
    }

    public function InstallEvents()
    {
        $eventManager = \Bitrix\Main\EventManager::getInstance();
        $eventManager->registerEventHandler(
            'main',
            'OnBeforeProlog',
            $this->MODULE_ID,
            AccessBlockHandler::class,
            'onBeforeProlog'
        );
        $eventManager->registerEventHandler(
            'main',
            'OnAfterUserRegister',
            $this->MODULE_ID,
            RegistrationPaymentHandler::class,
            'onAfterUserRegister'
        );
        $eventManager->registerEventHandler(
            'main',
            'OnAfterUserLogin',
            $this->MODULE_ID,
            RegistrationPaymentHandler::class,
            'onAfterUserLogin'
        );
    }

    public function UnInstallEvents()
    {
        $eventManager = \Bitrix\Main\EventManager::getInstance();
        $eventManager->unRegisterEventHandler(
            'main',
            'OnBeforeProlog',
            $this->MODULE_ID,
            AccessBlockHandler::class,
            'onBeforeProlog'
        );
        $eventManager->unRegisterEventHandler(
            'main',
            'OnAfterUserRegister',
            $this->MODULE_ID,
            RegistrationPaymentHandler::class,
            'onAfterUserRegister'
        );
        $eventManager->unRegisterEventHandler(
            'main',
            'OnAfterUserLogin',
            $this->MODULE_ID,
            RegistrationPaymentHandler::class,
            'onAfterUserLogin'
        );
    }

    /**
     * Каталог шаблонов страницы блокировки.
     */
    protected function installAccessTemplates(): void
    {
        $targetDir = $this->docRoot . PaidAccessCore::TEMPLATES_RELATIVE_PATH;

        if (!is_dir($targetDir)) {
            CheckDirPath($targetDir . '/');
        }

        $sourceFile = __DIR__ . '/templates/' . PaidAccessCore::DEFAULT_BLOCK_TEMPLATE;
        $targetFile = $targetDir . '/' . PaidAccessCore::DEFAULT_BLOCK_TEMPLATE;

        if (is_file($sourceFile) && !is_file($targetFile)) {
            copy($sourceFile, $targetFile);
        }
    }

    protected function installDefaultOptions(): void
    {
        $rsSites = \CSite::GetList($by = 'sort', $order = 'asc', ['ACTIVE' => 'Y']);

        while ($arSite = $rsSites->Fetch()) {
            $lid = $arSite['LID'];

            Option::set($this->MODULE_ID, PaidAccessCore::OPTION_ACCESS_BLOCK_TEMPLATE . '_' . $lid, PaidAccessCore::DEFAULT_BLOCK_TEMPLATE);
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

    /**
     * @return array
     */
    private function copyFiles(): array
    {
        $documentRoot = Application::getDocumentRoot();
        $errors       = [];

        foreach ($this->files as $from => $to)
        {
            $sourcePath = __DIR__ . $from;
            $destPath = $documentRoot . $to;
            if (!CopyDirFiles($sourcePath, $destPath, true, true))
            {
                $errors[] = $from . ':' . $to . '<br/>';
            }
        }

        return $errors;
    }

    private function removeFiles(): void
    {
        $documentRoot = Application::getDocumentRoot();
        foreach ($this->files as $from => $to)
        {
            DeleteDirFilesEx($documentRoot . $to);
        }
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
