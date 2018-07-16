<?
/**
 * Компонент настроек, где можно:
 * Ввести токен для активации подписки на получение статистики
 * Посмотреть список сотрудников
 *
 * @author Чижик Константин
 * @copyright 2018 ООО DMM
 */
\Bitrix\Main\Loader::includeModule('highloadblock');
use Bitrix\Highloadblock\HighloadBlockTable as HLBT;

class classSettings extends CBitrixComponent
{
  /**
  * Получает данные пользователя
  *
  * @param $user_id
  * @return $arUser массив с данными юзера
  */
  function getUser($user_id) {
    $rsUser = CUser::GetByID($user_id);
    $arUser = $rsUser->Fetch();
    return $arUser;
  }
  /**
  * Получает массив сотрудников, связанных с введёным токеном
  *
  * @param $user_login
  * @param $_POST["token"]
  * @return $arWorkers массив сотрудников с их данными
  */
  function getWorkers() {
      $url = "https://cloudpbx.beeline.ru/apis/portal/abonents";
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url );
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'X-MPBX-API-AUTH-TOKEN: '.$_POST["token"]
      ));
      $output = curl_exec($ch);
      curl_close($ch);
      $arWorkers = json_decode($output, true);
      CEventLog::Add(array(
         "AUDIT_TYPE_ID" => "CURL_QUERY",
         "MODULE_ID" => "main",
         "DESCRIPTION" => $output,
      ));
      if(!isset($arWorkers["errorCode"]) && count($arWorkers)>0)
        return $arWorkers;
    return false;
  }
  /**
  * Активирует подписку токеном
  *
  * @param $new_token
  */
  function SubscribeActivate($new_token) {
    $url = "https://cloudpbx.beeline.ru/apis/portal/subscription";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url );
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "{ \"expires\" : ".SUBSCRIBE_LIFE_SPAN.", \"subscriptionType\" : \"BASIC_CALL\", \"url\" : \"".BEELINE_SERVER_NAME."/calls_analytic/include/beeline_connection/beeline_event_handler.php\" }");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'X-MPBX-API-AUTH-TOKEN: '.$new_token,
      'Content-Type: application/json',
    ));
    $res = curl_exec($ch);
    curl_close($ch, CURLINFO_HTTP_CONNECTCODE);
    $res = json_decode($res, true);
    return $res["subscriptionId"];
  }
  /**
  * Записывает массив сотрудников в БД
  *
  * @param $arWorkers
  * @param $user_login
  * @return $result от $DB->Query
  */
  function AddWorkers($arWorkers, $user_login) {
    $str = "INSERT INTO ".WORKERS_HL_BLOCK_NAME." (UF_USER_PHONE_NUMBER, UF_FIRST_NAME, UF_LAST_NAME, UF_PHONE_NUMBER, UF_EXTENSION) VALUES ";
    foreach ($arWorkers as $worker) {
      if(empty($worker["phone"]))
        $worker["phone"] = str_replace("@mpbx.sip.beeline.ru", "", $worker["userId"]);
      $str.=" (";
      $str.="'".$user_login."',";
      $str.="'".$worker["firstName"]."',";
      $str.="'".$worker["lastName"]."',";
      $str.="'".$worker["phone"]."',";
      $str.="'".$worker["extension"]."'";
      $str.="),";
    };
    $str = substr($str, 0, -1);
    $str.=";";
    global $DB;
    $DB->Query($str);
  }
  /**
  * Очищает старый список сотрудников юзера
  *
  * @param $user_login
  */
  function ClearWorkerTable($user_login) {
    $str = "DELETE FROM ".WORKERS_HL_BLOCK_NAME." WHERE UF_USER_PHONE_NUMBER='".$user_login."';";
    global $DB;
    $DB->Query($str);
  }
  /**
  * Получает класс сущности highloadblock для дальнейшей работы с таблицей
  *
  * @param $HlBlockId highloadblock id
  * @return $entity_data_class сущность
  */
  function GetEntityDataClass($HlBlockId) {
      if (empty($HlBlockId) || $HlBlockId < 1)
      {
          return false;
      }
      $hlblock = HLBT::getById($HlBlockId)->fetch();
      $entity = HLBT::compileEntity($hlblock);
      $entity_data_class = $entity->getDataClass();
      return $entity_data_class;
  }
  /**
  * Получает список сотрудников
  *
  * @param $arResult
  * @param $user_login
  * @return $arResult["WORKERS"]
  */
  function GetWorkerList(&$arResult, $user_login) {
    $entity_data_class = $this->GetEntityDataClass(WORKERS_HL_BLOCK_ID);
    $rsData = $entity_data_class::getList(array(
       'select' => array('*'),
       'filter' => array("UF_USER_PHONE_NUMBER"=>$user_login)
    ));
    $arResult["WORKERS"]=array();
    while($el = $rsData->fetch()){
      $arResult["WORKERS"][]=$el;
    }
  }
  function CreateSubscribe($arWorkers, $new_id, $user_token, $key, $new_token, &$user_key) {
    $arWorkerNumbers = array();
    foreach ($arWorkers as $value) {
      if($value["phone"])
        $arWorkerNumbers[] = $value["phone"];
    };
    $entity_data_class = $this->GetEntityDataClass(WORKERS_HL_BLOCK_ID);
    $rsData = $entity_data_class::getList(array(
       'select' => array('ID', 'UF_USER_PHONE_NUMBER'),
       'filter' => array("UF_PHONE_NUMBER"=>$arWorkerNumbers)
    ));
    while($el = $rsData->fetch()){
      $arThisNetUsers[$el["UF_USER_PHONE_NUMBER"]]=$el["UF_USER_PHONE_NUMBER"];
    };
    $rsData = Bitrix\Main\UserTable::getList(array(
       'select' => array('ID', 'UF_SUBSCRIPTION_ID'),
       'filter' => array("LOGIN"=>$arThisNetUsers),
       'order' => array('UF_SUBSCRIPTION_ID'=>'DESC'),
    ));
    $el = $rsData->fetch();
    if($el["ID"]) {
      $rsUser = CUser::GetByID($el["ID"]);
      $arUser = $rsUser->Fetch();
    };
    if($el["ID"] && !empty($el["UF_SUBSCRIPTION_ID"])) {
      $user_key = $arUser["UF_USER_KEY"];
      $old_id = $el["ID"];
      $old_token = $arUser["UF_TOKEN"];
    }
    else {
      if(empty($key))
        $user_key = md5(rand());
      else
        $user_key = $key;
      $old_id = $new_id;
      $old_token = $user_token;
    };
    $user = new CUser;
    if(!empty($old_token))
      CAgent::RemoveAgent("SubscriptionRecovery_Agent('".$old_token."', ".$old_id.");", "main");
    if($new_id!=$old_id)
      $user->Update($old_id, Array("UF_SUBSCRIPTION_ID"=>""));
    $subscriptionId = $this->SubscribeActivate($new_token);
    $user->Update($new_id, Array(
      "UF_TOKEN"=>$new_token,
      "UF_SUBSCRIPTION_ID"=>$subscriptionId,
      "UF_INVALID_TOKEN"=>false,
      "UF_USER_KEY"=>$user_key,
    ));
    CAgent::AddAgent(
      "SubscriptionRecovery_Agent('".$new_token."', ".$new_id.");",
      "main",
      "N",
      strval(RECOVERY_DURABILITY),
      date("d.m.Y H:i:s", time()+RECOVERY_DURABILITY),
      "Y",
      date("d.m.Y H:i:s", time()+RECOVERY_DURABILITY)
    );
  }
  /**
  * Получаем список многоканальных номеров
  *
  * @param $arResult
  * @param $token
  * @return $arResult["MULTICALLS"]
  */
  function GetMulticallNumbers(&$arResult, $token) {
    if(!$token)
      return;
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://cloudpbx.beeline.ru/apis/portal/numbers");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");

    $headers = array();
    $headers[] = "X-Mpbx-Api-Auth-Token: ".$token;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    curl_close ($ch);

    $arResult["MULTICALLS"] = json_decode($result, true);
  }
  function UpdateChimeTime($user_id, $chime_time) {
    $user = new CUser;
    $user->Update($user_id, Array("UF_CHIME_TIME"=>$_POST["chime_time"]));
    CAgent::AddAgent(
      "AnalysisUnansweredCalls::AgentExecute(".$chime_time.");",
      "main",
      "N",
      strval(BUFFER_ANALYTIC_PERIOD),
      date("d.m.Y H:i:s", time()+BUFFER_ANALYTIC_PERIOD),
      "Y",
      date("d.m.Y H:i:s", time()+BUFFER_ANALYTIC_PERIOD)
    );
  }
  public function executeComponent() {
    global $USER;
    if(!$USER->IsAuthorized()) return;
    $user_id=$USER->GetID();
    $arUser = $this->getUser($user_id);
    if(!empty($arUser["UF_TOKEN"]))
      $token = $arUser["UF_TOKEN"];
    if($arUser["UF_INVALID_TOKEN"]) {
      $this->arResult["MESS"].= "<b>Ваш токен не работает, используйте новый</b><br>";
    };
    $this->arResult["USER_CHIME_TIME"] = $arUser["UF_CHIME_TIME"];
	  $this->arResult["USER_KEY"] = $arUser["UF_USER_KEY"];
    if(!empty($arUser["UF_TOKEN"]))
      $this->arResult["TOKEN_EXIST"]=true;
    if(!empty($_POST["token"])) {
      $_POST["token"]=trim($_POST["token"]);
      if($arWorkers = $this->getWorkers()) {
        $this->arResult["TOKEN_EXIST"]=true;
        $this->ClearWorkerTable($arUser["LOGIN"]);
        $this->CreateSubscribe($arWorkers, $user_id, $arUser["UF_TOKEN"], $arUser["UF_USER_KEY"], $_POST["token"], $this->arResult["USER_KEY"]);
        $token = $_POST["token"];
        $this->AddWorkers($arWorkers, $arUser["LOGIN"]);
        $this->arResult["MESS"].="Токен обновлён </br> Время перезвона обновлено";
      }
      else {
        $this->arResult["MESS"].="Токен не обновлён </br> Время перезвона обновлено";
      }
    }
    elseif(!empty($_POST)) {
      $this->arResult["MESS"].="Токен не обновлён </br> Время перезвона обновлено";
    }
    $this->GetWorkerList($this->arResult, $arUser["LOGIN"]);
    if(count($this->arResult["WORKERS"])>0)
      $this->GetMulticallNumbers($this->arResult, $token);
    if($_POST["chime_time"]) {
      $this->arResult["USER_CHIME_TIME"] = $_POST["chime_time"];
      $this->UpdateChimeTime($user_id, $_POST["chime_time"]);
    }
    $this->includeComponentTemplate();
  }
}
?>
