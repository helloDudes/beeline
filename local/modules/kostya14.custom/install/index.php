<?
/**
 * Created by PhpStorm
 * @author Чижик Константин
 * @copyright 2018 ООО DMM
 */

use \Bitrix\Main\ModuleManager;

class kostya14_custom extends CModule
{
    function __construct()
    {
        $arModuleVersion = array();
        include(__DIR__ . "/version.php");
        $this->MODULE_ID = 'kostya14.custom';
        $this->MODULE_VERSION = $arModuleVersion["VERSION"];
        $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        $this->MODULE_NAME = "Вспомогательный модуль разработчика";
        $this->MODULE_DESCRIPTION = "Вспомогательный модуль для часто используемого функционала проекта";

        $this->PARTNER_NAME = "Костя";
        $this->PARTNER_URI = "";

        $this->MODULE_SORT = 1;
        $this->SHOW_SUPER_ADMIN_GROUP_RIGHTS='Y';
        $this->MODULE_GROUP_RIGHTS = "Y";
    }
    function DoInstall() {
        global $APPLICATION;
        if($this->isVersionD7())
        {
            if(ModuleManager::isModuleInstalled('highloadblock')) {
                ModuleManager::registerModule($this->MODULE_ID);

                $this->InstallFiles();
            }
            else {
                $APPLICATION->ThrowException("Сначала установите модуль highloadblock");
            }
        }
        else
        {
            $APPLICATION->ThrowException("Ваша версия Bitrix слишком сильно устарела для данного модуля");
        }
        $APPLICATION->IncludeAdminFile(Loc::getMessage("ACADEMY_D7_INSTALL_TITLE"), $this->GetPath()."/install/step.php");
    }
    function DoUninstall() {
        ModuleManager::unRegisterModule($this->MODULE_ID);
        $this->UninstallFiles();
    }
}

?>