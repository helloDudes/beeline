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
    public function isVersionD7()
    {
        return CheckVersion(\Bitrix\Main\ModuleManager::getVersion('main'), '14.00.00');
    }
    public function GetPath($notDocumentRoot=false)
    {
        if($notDocumentRoot)
            return str_ireplace(Application::getDocumentRoot(),'',dirname(__DIR__));
        else
            return dirname(__DIR__);
    }
    function DoInstall() {
        global $APPLICATION;
        if($this->isVersionD7())
        {
            if(ModuleManager::isModuleInstalled('highloadblock')) {
                $path=$this->GetPath()."/install/components";

                if(\Bitrix\Main\IO\Directory::isDirectoryExists($path)) {
                    CopyDirFiles($path, $_SERVER["DOCUMENT_ROOT"] . "/bitrix/components", true, true);
                }
                else {
                    throw new \Bitrix\Main\IO\InvalidPathException($path);
                }

                ModuleManager::registerModule($this->MODULE_ID);
            }
            else {
                $APPLICATION->ThrowException("Сначала установите модуль highloadblock");
            }
        }
        else
        {
            $APPLICATION->ThrowException("Ваша версия Bitrix слишком сильно устарела для данного модуля");
        }
        $APPLICATION->IncludeAdminFile("Установка вспомогательного модуля", $this->GetPath()."/install/step.php");
    }
    function DoUninstall() {
        $path=$this->GetPath()."/install/components";

        if(\Bitrix\Main\IO\Directory::isDirectoryExists($path)) {
            DeleteDirFiles($_SERVER["DOCUMENT_ROOT"] . $path, $_SERVER["DOCUMENT_ROOT"] . "/bitrix/components", true, true);
        }


        ModuleManager::unRegisterModule($this->MODULE_ID);
    }
}

?>