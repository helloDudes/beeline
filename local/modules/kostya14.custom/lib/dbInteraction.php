<?
/**
* Класс для работы с БД и сущностями телефонии
*
* Created by PhpStorm
* @author Чижик Константин
* @copyright 2018 ООО DMM
*/

namespace Kostya14\Custom;
\Bitrix\Main\Loader::includeModule("highloadblock");
use \Bitrix\Highloadblock\HighloadBlockTable as HLBT;

class DbInteraction
{
    /**
     * Получает класс сущности highloadblock для дальнейшей работы с таблицей
     *
     * @param int $HlBlockId highloadblock id
     * @return object $entity_data_class сущность
     */
    public static function GetEntityDataClass($HlBlockId) {
        if (!$HlBlockId)
        {
            return false;
        }
        $hlblock = HLBT::getById($HlBlockId)->fetch();
        $entity = HLBT::compileEntity($hlblock);
        $entity_data_class = $entity->getDataClass();
        return $entity_data_class;
    }
    /**
     * Получает данные пользователя
     *
     * @param int $user_id
     * @param array $arFields
     * @return array $el массив со всеми параметрами пользователя
     */
    public static function getUser($user_id, $arFields = array("*")) {
        if (!$user_id)
        {
            return false;
        }
        $rsData = \Bitrix\Main\UserTable::getList(array(
            'select' => $arFields,
            'filter' => array("ID"=>$user_id),
        ));
        $el = $rsData->fetch();
        return $el;
    }
    /**
     * Получает данные АТС, из которой приходит событие
     *
     * @param array $arFilter массив фильтра
     * @param int $hl_block_id
     * @param boolean $subscribe_expires выбирать только тех, у кого не истекла подписка
     * @return array $arATEData данные АТС
     */
    public static function GetB24AteData($arFilter, $hl_block_id, $subscribe_expires = true) {
        if(empty($arFilter) || !$hl_block_id) {
            return false;
        }

        $filter = $arFilter;

        if($subscribe_expires) {
            $filter[">UF_SUBSCRIBE_EXPIRES"]=date("d.m.Y H:i:s");
        };

        $entity_data_class = self::GetEntityDataClass($hl_block_id);
        $rsData = $entity_data_class::getList(array(
            'select' => array('*'),
            'filter' => $filter,
        ));
        $el = $rsData->Fetch();

        if(!$el["ID"]) {
            return false;
        }

        $date = \DateTime::createFromFormat('d.m.Y H:i:s', $el['UF_EXPIRES_IN']);
        $expires = $date->getTimestamp();

        //Если ключ доступа истёк, то берём новый и обновляем полученные в ответе данные
        if($expires <= time()) {
            try {
                $arATE = externalApi::GetNewB24Access($el["ID"], $el["UF_REFRESH_TOKEN"]);
            }
            catch (\Exception $e) {
                \CEventLog::Add(array(
                    "AUDIT_TYPE_ID" => "BITRIX24_ERROR",
                    "MODULE_ID" => "main",
                    "DESCRIPTION" => $e->getMessage(),
                ));
            }
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