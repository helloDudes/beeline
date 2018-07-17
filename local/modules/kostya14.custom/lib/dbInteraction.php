<?
/**
* Created by PhpStorm
* @author Чижик Константин
* @copyright 2018 ООО DMM
*/

namespace Kostya14\Custom;
use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable as HLBT;

class DbInteraction
{
    public function __construct()
    {
        if(!Loader::includeModule("highloadblock")) {
            throw New Exception("Highloadblock module is not installed");
        }
    }
    public static function GetEntityDataClass($HlBlockId) {
        if (empty($HlBlockId) || $HlBlockId < 1)
        {
            return false;
        }
        $hlblock = HLBT::getById($HlBlockId)->fetch();
        $entity = HLBT::compileEntity($hlblock);
        $entity_data_class = $entity->getDataClass();
        return $entity_data_class;
    }
    public static function getUser($user_id) {
        $rsData = Bitrix\Main\UserTable::getList(array(
            'select' => array('*'),
            'filter' => array("ID"=>$user_id),
        ));
        $el = $rsData->fetch();
        return $el;
    }

    public static function GetB24AteData($field, $value, $hl_block_id, $subscribe_expires = true) {
        $filter = array();
        $filter[$field] = $value;

        if($subscribe_expires) {
            $filter[">UF_SUBSCRIBE_EXPIRES"]=date("d.m.Y H:i:s");
        };

        $entity_data_class = self::GetEntityDataClass($hl_block_id);
        $rsData = $entity_data_class::getList(array(
            'select' => array('*'),
            'filter' => $filter,
        ));
        $el = $rsData->Fetch();

        if(!$el["ID"])
            return;
        $date = DateTime::createFromFormat('d.m.Y H:i:s', $el['UF_EXPIRES_IN']);
        $expires = $date->getTimestamp();

        //Если ключ доступа истёк, то берём новый и обновляем полученные в ответе данные
        if($expires <= time()) {
            $arATE = Kostya14\Custom\externalApi::GetNewB24Access($el["ID"], $el["UF_REFRESH_TOKEN"]);
            $arAnswer = $arATE;
        }
        else {
            $arAnswer = $el;
        }
        $arReturn = array();
        $arReturn["ate_id"] = $el["ID"];
        $arReturn["ate_key"] = $el["UF_KEY"];
        $arReturn["beeline_token"] = $el["UF_BEELINE_TOKEN"];
        $arReturn["bitrix24_token"] = $arAnswer["UF_ACCESS_TOKEN"];
        $arReturn["domain"] = $el["UF_PORTAL"];
        $arReturn["create_redirect"] = $el["UF_CREATE_REDIRECT"];
        return $arReturn;
    }

}
?>