<?
/**
 * Отвечает за curl вызовы к внешним API
 *
 * Created by PhpStorm
 * @author Чижик Константин
 * @copyright 2018 ООО DMM
 */

namespace Kostya14\Custom;

class externalApi
{
    const BEELINE_URL = "https://cloudpbx.beeline.ru/apis/portal/";

    /**
     * Посылает какую либо команду в Билайн
     *
     * @param string $url адрес метода
     * @param string $token токен Билайн
     * @param string $type тип POST/GET...
     * @param array $arOptions массив опций метода
     * @param boolean $is_json устанавливать ли заголовок Content-Type: application/json
     * @return array $arAnswer данные ответа Билайн
     */
    public static function BeelineCommand($url, $token, $type = "GET", $arOptions = array(), $is_json = false) {
        if(empty($url) || empty($token)){
            return false;
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, self::BEELINE_URL.$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);

        $headers = array();
        $headers[] = "X-Mpbx-Api-Auth-Token: ".$token;

        if($is_json) {
            $headers[] = "Content-Type: application/json";
        }

        if(!empty($arOptions)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($arOptions));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $output = curl_exec($ch);
        curl_close ($ch);

        $arOutput = json_decode($output, true);
        return $arOutput;
    }
    /**
     * Посылает какую либо команду в Bitrix24
     *
     * @param array $arATEData данные АТС bitrix24_token, domain
     * @param string $method метод REST API
     * @param array $arOptions набор опций метода
     * @return array $arOutput ответ Bitrix24
     */
    public static function RestCommand($arATEData, $method, $arOptions = array()) {
        if(empty($arATEData["domain"]) || empty($arATEData["bitrix24_token"]) || empty($method)){
            return false;
        }

        $queryUrl  = 'https://' . $arATEData["domain"] . '/rest/' . $method;
        $queryData = http_build_query(array_merge($arOptions, array('auth' => $arATEData["bitrix24_token"])));

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_POST           => 1,
            CURLOPT_HEADER         => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL            => $queryUrl,
            CURLOPT_POSTFIELDS     => $queryData,
            CURLOPT_VERBOSE         => 1
        ));
        $output = curl_exec($curl);
        curl_close($curl);
        $arOutput = json_decode($output, true);
        \CEventLog::Add(array(
            "AUDIT_TYPE_ID" => "BITRIX24_METHOD",
            "MODULE_ID" => "main",
            "DESCRIPTION" => "input: (domain: "
                .$arATEData["domain"]
                ." url: "
                .$queryUrl
                ." method: "
                .$method
                ." options: "
                .$queryData
                .") output: "
                .$output,
        ));
        //Если в ответе остались не попавшие в первые 50 элементов(максимальный лимит числа отдаваемых элементов),
        //то запрвшиваем их с отступом start
        if($arOutput["next"]) {
            if(!$arOptions["start"])
                $arOptions["start"] = $arOutput["next"];
            else
                $arOptions["start"] += $arOutput["next"];

            $nextOutput = self::RestCommand(
                $arATEData,
                $method,
                $arOptions
            );
            $arOutput["result"] = array_merge($arOutput["result"], $nextOutput["result"]);
        }

        return $arOutput;
    }
    /**
     * Ждёт формирования записи разговора $record_waiting_time секунд и запрашивает её Билайна
     *
     * @param string $extTrackingId звонка
     * @param string $targetId звонка
     * @param string $token токен Билайн
     * @param int $record_waiting_time время ожидания формирования записи в секундах
     * @return string $Output["url"] ссылка на скачивание записи
     */
    public static function GetRecordURL($extTrackingId, $targetId, $token, $record_waiting_time = 0) {
        if(empty($extTrackingId) && empty($targetId) && empty($token)) {
            return false;
        };
        sleep($record_waiting_time);

        $url = "/records/" . urlencode($extTrackingId) . "/" . urlencode($targetId) . "/reference";

        try {
            $arOutput = self::BeelineCommand($url, $token, "GET");
        }
        catch(\Exception $e) {
            \CEventLog::Add(array(
                "AUDIT_TYPE_ID" => "MY_ERROR",
                "MODULE_ID" => "main",
                "DESCRIPTION" => $e->getMessage(),
            ));
        }

        return $arOutput["url"];
    }
    /**
     * Получаем новый access token Bitrix24 и заменяем им старый в указаной таблице
     *
     * @param int $id подписки АТС
     * @param array $arData BITRIX24_CLIENT_ID, BITRIX24_SECRET_CODE, refresh_token
     * @param int $hl_block_id id таблицы подписки
     * @throws \Exception если не удалось получить новый access token в ответе
     * @return array $arATE новые данные АТС
     */
    public static function GetNewB24Access($id, $arData, $hl_block_id) {
        if(
            !$id
            || empty($arData["BITRIX24_CLIENT_ID"])
            || empty($arData["BITRIX24_SECRET_CODE"])
            || empty($arData["refresh_token"])
            || !$hl_block_id
        )
        {
            return false;
        }

        $url = "https://oauth.bitrix.info/oauth/token/"
            ."?client_id=".urlencode($arData["BITRIX24_CLIENT_ID"])
            ."&grant_type=refresh_token"
            ."&client_secret=".urlencode($arData["BITRIX24_SECRET_CODE"])
            ."&refresh_token=".urlencode($arData["refresh_token"]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch, CURLINFO_HTTP_CONNECTCODE);
        $arOutput=json_decode($output, true);

        if(!$arOutput["access_token"]) {
            throw new \Exception(__FUNCTION__.': query fail:('.$output.')');
        }

        \CEventLog::Add(array(
            "AUDIT_TYPE_ID" => "BITRIX24_METHOD",
            "MODULE_ID" => "main",
            "DESCRIPTION" => "input: (url: ".$url.") output: ".$output,
        ));

        $arATE = array(
            'UF_ACCESS_TOKEN' => $arOutput["access_token"],
            'UF_EXPIRES_IN' => date("d.m.Y H:i:s", $arOutput["expires"]),
            'UF_REFRESH_TOKEN' => $arOutput["refresh_token"],
            'UF_MEMBER_ID' => $arOutput["member_id"],
        );
        $entity_data_class = DbInteraction::GetEntityDataClass($hl_block_id);
        $entity_data_class::update($id, $arATE);
        return $arATE;
    }
    /**
     * Восстанавливаем подписку на события звонка, если она разрушилась преждевременно
     *
     * @param array $arData token токен Билайн, life_span срок жизни подписки,
     * handler_url по какому адресу получать подписку, agent_name имя агента переподписки,
     * recovery_durability частота срабатывания агента переподписки
     */
    public static function RepairSubscribe($arData) {
        if(
            empty($arData["token"])
            || !$arData["life_span"]
            || empty($arData["handler_url"])
            || !$arData["id"]
            || empty($arData["agent_name"])
            || !$arData["recovery_durability"]
        )
        {
            return;
        }

        $url = "subscription/";

        $arOutput = self::BeelineCommand(
            $url,
            $arData["token"],
            "PUT",
            array(
                "expires" => $arData["life_span"],
                "subscriptionType" => "BASIC_CALL",
                "url" => $arData["handler_url"],
            ),
            true
        );

        $entity_data_class = DbInteraction::GetEntityDataClass($arData["hl_block_id"]);
        $entity_data_class::update($arData["id"], array(
            'UF_SUBSCRIPTION_ID'=>$arOutput["subscriptionId"],
        ));
        \CAgent::RemoveAgent($arData["agent_name"], "main");
        \CAgent::AddAgent(
            $arData["agent_name"],
            "main",
            "N",
            strval($arData["recovery_durability"]),
            date("d.m.Y H:i:s", time()+$arData["recovery_durability"]),
            "Y",
            date("d.m.Y H:i:s", time()+$arData["recovery_durability"])
        );
    }
}
?>