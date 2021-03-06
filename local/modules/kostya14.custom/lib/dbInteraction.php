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
    const BITRIX24_CLIENT_ID = 1111111111;
    const BITRIX24_SECRET_CODE = "somesecretcode";

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
            $arATE = ExternalApi::GetNewB24Access($el["ID"], $el["UF_REFRESH_TOKEN"], $hl_block_id);

            if(!$arATE) {
                return false;
            }

            $arAnswer = $arATE;
        }
        else {
            $arAnswer = $el;
        }
        $arReturn = array();
        $arReturn["ate_id"] = $el["ID"];
        $arReturn["ate_key"] = $el["UF_KEY"];
        $arReturn["ate_limit"] = $el["UF_LIMIT"];
        $arReturn["beeline_token"] = $el["UF_BEELINE_TOKEN"];
        $arReturn["subscribe_expires"] = $el["UF_SUBSCRIBE_EXPIRES"];
        $arReturn["bitrix24_token"] = $arAnswer["UF_ACCESS_TOKEN"];
        $arReturn["domain"] = $el["UF_PORTAL"];
        $arReturn["create_redirect"] = $el["UF_CREATE_REDIRECT"];
        return $arReturn;
    }
}
?>