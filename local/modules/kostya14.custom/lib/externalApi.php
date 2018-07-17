<?
/**
 * Created by PhpStorm
 * @author Чижик Константин
 * @copyright 2018 ООО DMM
 */

namespace Kostya14\Custom;

class externalApi
{
    const BEELINE_URL = "https://cloudpbx.beeline.ru/apis/portal";

    public static function BeelineCommand($url, $token, $type, $arOptions = array(), $is_json = false) {
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

        $result = curl_exec($ch);
        curl_close ($ch);

        $arAnswer = json_decode($result, true);
        return $arAnswer;
    }

    public static function RestCommand($arATEData, $method, $arOptions) {
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
        CEventLog::Add(array(
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
        if($arOutput["error"]) {
            return false;
        };

        return $arOutput;
    }

    public static function GetWorkers($token) {
        $url = "/abonents";

        $arWorkers = self::BeelineCommand($url, $token, "GET");

        if(!isset($arWorkers["errorCode"]) && count($arWorkers)>0)
            return $arWorkers;
        return false;
    }

    public static function GetRecordURL($extTrackingId, $targetId, $token, $record_waiting_time) {
        sleep($record_waiting_time);

        $url = "/records/".urlencode($extTrackingId)."/".urlencode($targetId)."/reference";

        $arOutput = self::BeelineCommand($url, $token, "GET");

        return $arOutput["url"];
    }

    public static function GetNewB24Access($id, $arData, $hl_block_id) {
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
        CEventLog::Add(array(
            "AUDIT_TYPE_ID" => "BITRIX24_METHOD",
            "MODULE_ID" => "main",
            "DESCRIPTION" => "input: (url: ".$url.") output: ".$output,
        ));
        if(!$arOutput["access_token"]) {
            return false;
        }

        $arATE = array(
            'UF_ACCESS_TOKEN' => $arOutput["access_token"],
            'UF_EXPIRES_IN' => date("d.m.Y H:i:s", $arOutput["expires"]),
            'UF_REFRESH_TOKEN' => $arOutput["refresh_token"],
            'UF_MEMBER_ID' => $arOutput["member_id"],
        );
        $entity_data_class = Kostya14\Custom\DbInteraction::GetEntityDataClass($hl_block_id);
        $entity_data_class::update($id, $arATE);
        return $arATE;
    }
    public static function RepairSubscribe($arData) {
        $url = "/subscription";

        $arOutput = self::BeelineCommand(
            $url,
            $arData["token"],
            "PUT",
            array(
                "expires" => $arData["live_span"],
                "subscriptionType" => "BASIC_CALL",
                "url" => $arData["handler_url"],
            ),
            true
        );

        $entity_data_class = self::GetEntityDataClass($arData["hl_block_id"]);
        $entity_data_class::update($arData["id"], array(
            'UF_SUBSCRIPTION_ID'=>$arOutput["subscriptionId"],
        ));
        CAgent::RemoveAgent($arData["agent_name"], "main");
        CAgent::AddAgent(
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