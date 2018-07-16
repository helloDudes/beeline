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
  function getWorkers($token) {
      $url = "https://cloudpbx.beeline.ru/apis/portal/abonents";
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url );
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'X-MPBX-API-AUTH-TOKEN: '.$token
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
    curl_setopt($ch, CURLOPT_POSTFIELDS, "{ \"expires\" : ".SUBSCRIBE_LIFE_SPAN.", \"subscriptionType\" : \"BASIC_CALL\", \"url\" : \"".BEELINE_SERVER_NAME."/calls_analytic/include/beeline_connection/beeline_moysklad_event_handler2.php\" }");
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
  * Записывает массив сотрудников в БД
  *
  * @param $arWorkers
  * @param $user_login
  * @return $result от $DB->Query
  */
  function AddWorkers($arWorkers, $user_key) {
    $str = "INSERT INTO ".ATE_WORKERS_HL_BLOCK_NAME." (UF_KEY, UF_FIRST_NAME, UF_LAST_NAME, UF_PHONE_NUMBER, UF_BEELINE_USER_ID) VALUES ";
    foreach ($arWorkers as $worker) {
      if(empty($worker["phone"]))
        $worker["phone"] = str_replace("@mpbx.sip.beeline.ru", "", $worker["userId"]);
      $str.=" (";
      $str.="'".$user_key."',";
      $str.="'".$worker["firstName"]."',";
      $str.="'".$worker["lastName"]."',";
      $str.="'".$worker["phone"]."',";
      $str.="'".$worker["userId"]."'";
      $str.="),";
    };
    $str = substr($str, 0, -1);
    $str.=";";
    $connection = Bitrix\Main\Application::getConnection();
    $connection->query($str);
  }
  function AddMoySkladUsers($moysklad_token, $key) {
    $url = MOYSKLAD_API_URL."employee/";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url );
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $str);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      "Lognex-Phone-Auth-Token: ".$moysklad_token,
      'Content-Type: application/json',
    ));
    $output = curl_exec($ch);
    curl_close($ch, CURLINFO_HTTP_CONNECTCODE);
    $arOutput = json_decode($output, true);

    $del="DELETE FROM ".MOYSKLAD_WORKERS_HL_BLOCK_NAME." WHERE UF_KEY='".$key."'";
    $ins = "INSERT INTO ".MOYSKLAD_WORKERS_HL_BLOCK_NAME." (UF_KEY, UF_EXTENSION) VALUES ";
    foreach ($arOutput["employees"] as $employee) {
      if(!empty($employee["extention"])) {
        $ins.=" (";
        $ins.="'".$key."',";
        $ins.="'".$employee["extention"]."'";
        $ins.="),";
      }
    }
    $ins = substr($ins, 0, -1);
    $ins.=";";
    $connection = Bitrix\Main\Application::getConnection();
    $connection->query($del);
    $connection->query($ins);
  }
  function AddATE($arWorkers, $user_id, $user_key, $ate_id, $ate_exist, $moysklad_ate_id, $old_token) {
    $subscriptionId = $this->SubscribeActivate($_POST["token"]);
    $user = new CUser;
    $user->Update($user_id, array(
      "UF_USER_KEY"=>$user_key,
    ));
    if(!$subscriptionId)
      return false;
    $entity_data_class_ate = $this->GetEntityDataClass(ATS_HL_BLOCK_ID);
    $entity_data_class_ate_moysklad = $this->GetEntityDataClass(MOYSKLAD_HL_BLOCK_ID);

    if(!$ate_id) {
      $entity_data_class_ate::add(array(
        'UF_KEY'=>$user_key,
        'UF_MOYSKLAD'=>true,
      ));
      $this->AddWorkers($arWorkers, $user_key);
    };
    if(!$ate_exist || !$moysklad_ate_id) {
      $result = $entity_data_class_ate_moysklad::add(array(
        'UF_KEY'=>$user_key,
        'UF_ACCESS_TOKEN' => $_POST["moy_sklad_token"],
        'UF_BEELINE_TOKEN' => $_POST["token"],
        'UF_SUBSCRIPTION_ID' => $subscriptionId,
        'UF_SUBSCRIBE_EXPIRES' => date("d.m.Y", time()+(86400*30))." 00:00:00",
        'UF_LIMIT' => "all",
        'UF_DATE_OF_CREATION' => date("d.m.Y")." 00:00:00",
      ));
      $moysklad_ate_correct_id = $result->getId();
      if($ate_id) {
        $entity_data_class_ate::update($ate_id, array(
          'UF_MOYSKLAD'=>true,
        ));
      }
    }
    if($moysklad_ate_id) {

      $entity_data_class_ate_moysklad::update($moysklad_ate_id, array(
        'UF_ACCESS_TOKEN' => $_POST["moy_sklad_token"],
        'UF_BEELINE_TOKEN' => $_POST["token"],
        'UF_SUBSCRIPTION_ID' => $subscriptionId,
      ));
      $moysklad_ate_correct_id = $moysklad_ate_id;
    }
    $this->CreateSubscribe($moysklad_ate_correct_id, $old_token, $_POST["token"], $user_key);
    $this->arResult["MOYSKLAD_SUBSCRIBE_SUCCESS"] = true;
  }
  function CreateSubscribe($moysklad_ate_correct_id, $old_token, $new_token, $user_key) {
    CAgent::RemoveAgent("SubscriptionRecovery_MoySklad_Agent('".$old_token."', '".$user_key."');", "main");
    CAgent::RemoveAgent("SubscriptionRecovery_MoySklad_Agent::AgentExecute('".$old_token."', ".$moysklad_ate_correct_id.");", "main");
    CAgent::AddAgent(
      "SubscriptionRecovery_MoySklad_Agent::AgentExecute('".$new_token."', ".$moysklad_ate_correct_id.");",
      "main",
      "N",
      strval(RECOVERY_DURABILITY),
      date("d.m.Y H:i:s", time()+RECOVERY_DURABILITY),
      "Y",
      date("d.m.Y H:i:s", time()+RECOVERY_DURABILITY)
    );
  }
  function ATEAlreadySubscribed($key) {
    if(empty($key))
      return;
    $entity_data_class = self::GetEntityDataClass(MOYSKLAD_HL_BLOCK_ID);
    $rsData = $entity_data_class::getList(array(
       'select' => array('ID', 'UF_ACCESS_TOKEN', 'UF_BEELINE_TOKEN', 'UF_SUBSCRIBE_EXPIRES', 'UF_LIMIT'),
       'filter' => array("UF_KEY"=>$key),
    ));
    $el = $rsData->Fetch();

    $user_ate = array();
    $user_ate["ate_limit"] = $el["UF_LIMIT"];
    $user_ate["subscribe_expires"] = $el["UF_SUBSCRIBE_EXPIRES"];
    $user_ate["beeline_token"] = $el["UF_BEELINE_TOKEN"];
    $arReturn["moysklad_token"] = $arAnswer["UF_ACCESS_TOKEN"];
    $user_ate["ate_key"] = $key;
    return $user_ate;
  }
  function CheckNetwork($arUser, $arWorkers, &$key, &$ate_id) {
    $arWorkerNumbers = array();
    foreach ($arWorkers as $value) {
      if($value["phone"])
        $arWorkerNumbers[] = $value["phone"];
    };
    $entity_data_class = $this->GetEntityDataClass(WORKERS_HL_BLOCK_ID);
    $rsData = $entity_data_class::getList(array(
       'select' => array('ID', 'UF_USER_PHONE_NUMBER'),
       'filter' => array("UF_PHONE_NUMBER"=>$arWorkerNumbers, "!UF_USER_PHONE_NUMBER"=>$arUser["LOGIN"])
    ));
    while($el = $rsData->fetch()){
      $arThisNetUsers[$el["UF_USER_PHONE_NUMBER"]]=$el["UF_USER_PHONE_NUMBER"];
    };

    $rsData = Bitrix\Main\UserTable::getList(array(
       'select' => array('ID', 'UF_USER_KEY'),
       'filter' => array("LOGIN"=>$arThisNetUsers),
    ));
    $el = $rsData->fetch();
    $key = $el["UF_USER_KEY"];

    $entity_data_class = $this->GetEntityDataClass(ATE_WORKERS_HL_BLOCK_ID);
    $rsData = $entity_data_class::getList(array(
       'select' => array('ID', 'UF_KEY'),
       'filter' => array("UF_PHONE_NUMBER"=>$arWorkerNumbers)
    ));
    $el = $rsData->fetch();
    if(!empty($el["UF_KEY"])) {
      $key = $el["UF_KEY"];
      $entity_data_class = $this->GetEntityDataClass(ATS_HL_BLOCK_ID);
      $rsData = $entity_data_class::getList(array(
         'select' => array('ID'),
         'filter' => array("UF_KEY"=>$key)
      ));
      $el = $rsData->fetch();
      $ate_id = $el["ID"];
    };
  }
  function CheckMoySkladSubscription($user_key, &$moysklad_ate_id, &$old_token) {
    $entity_data_class = $this->GetEntityDataClass(MOYSKLAD_HL_BLOCK_ID);
    $rsData = $entity_data_class::getList(array(
       'select' => array('ID', 'UF_BEELINE_TOKEN'),
       'filter' => array("UF_KEY"=>$user_key)
    ));
    $el = $rsData->fetch();
    $moysklad_ate_id = $el["ID"];
    $old_token = $el["UF_BEELINE_TOKEN"];
  }
  function CreateUserSettingsTable(&$arResult, $arWorkers, $key, $token) {
    $arResult["BEELINE_WORKERS"] = $this->GetBeelineWorkers($arWorkers);
    $arResult["USERS_DATA"] = $this->GetMoySkladUsers($key);
    if($arResult["ATE_LIMIT"] && $arResult["ATE_LIMIT"]!="all") {
      $arResult["ROWS"] = $arResult["ATE_LIMIT"];
    }
    elseif(count($arResult["USERS_DATA"]["MOY_SKLAD_USERS"])>0) {
      $arResult["ROWS"] = count($arResult["USERS_DATA"]["MOY_SKLAD_USERS"]);
    }
    else {
      $arResult["ROWS"] = 15;
    }
  }
  function GetBeelineWorkers($arWorkers) {
    $arWorkersModif = array();
    foreach ($arWorkers as $worker) {
      $arWorkersModif[$worker["extension"]]=$worker;
    }
    return $arWorkersModif;
  }
  function GetMoySkladUsers($key) {
    $entity_data_class = self::GetEntityDataClass(MOYSKLAD_WORKERS_HL_BLOCK_ID);
    $rsData = $entity_data_class::getList(array(
     'select' => array('*'),
     'filter' => array(
       "UF_KEY"=>$key,
     ),
    ));
    $arBitrixUsers = array();
    $arBitrixUserRows = array();
    while($el = $rsData->Fetch()) {
      $arBitrixUsers["ROW_".$el["UF_ROW"]]["moysklad_user_login"]=$el["UF_MOYSKLAD_LOGIN"];
      $arBitrixUsers["ROW_".$el["UF_ROW"]]["user_extension"]=$el["UF_EXTENSION"];
      $arBitrixUsersID[$el["UF_MOYSKLAD_LOGIN"]][]=$el["UF_ROW"];
    };

    $arUsersData["MOY_SKLAD_USERS"]=$arBitrixUsers;
    return $arUsersData;
  }
  function CheckMoyskladToken() {
    $url = MOYSKLAD_API_URL."/employee";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url );
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $str);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      "Lognex-Phone-Auth-Token: ".$_POST["moy_sklad_token"],
      'Content-Type: application/json',
    ));
    $output = curl_exec($ch);
    $arOutput = json_decode($output, true);
    if($arOutput["errors"])
      return false;
    return true;
  }
  public function executeComponent() {
    global $USER;
    if(!$USER->IsAuthorized()) return;
    $user_id=$USER->GetID();
    $arUser = $this->getUser($user_id);

    if(!empty($_POST)) {
      if($arWorkers = $this->GetWorkers($_POST["token"])) {
        if($this->CheckMoyskladToken()) {
          $this->CheckNetwork($arUser, $arWorkers, $user_key, $ate_id);
          if(!$user_key) {
            $user_key = $arUser["UF_USER_KEY"];
            if(empty($user_key))
              $user_key = md5(uniqid($user_id, true));
            $this->AddATE($arWorkers, $user_id, $user_key, $ate_id, false, false, false);
          }
          else {
            $this->CheckMoySkladSubscription($user_key, $moysklad_ate_id, $old_token);
            if($moysklad_ate_id) {
              $this->AddATE($arWorkers, $user_id, $user_key, $ate_id, true, $moysklad_ate_id, $old_token);
            }
            else {
              $this->AddATE($arWorkers, $user_id, $user_key, $ate_id, true, false, false);
            }
          }
        }
        else {
          $this->arResult["MESS"]="Неверный ключ доступа.";
        }
      }
      else {
        $this->arResult["MESS"]="Неверный токен.";
      }
    }

    if($user_key)
      $current_user_key = $user_key;
    else
      $current_user_key = $arUser["UF_USER_KEY"];

    $user_ate = $this->ATEAlreadySubscribed($current_user_key);
    if($user_ate["beeline_token"]) {
      $this->arResult["ATE_ALREADY_SUBSCRIBED"] = true;
      $this->arResult["ATE_LIMIT"] = $user_ate["ate_limit"];
      $this->arResult["SUBSCRIBE_EXPIRES"] = $user_ate["subscribe_expires"];
      $date = DateTime::createFromFormat('d.m.Y H:i:s', $user_ate["subscribe_expires"]);
      $expires = $date->getTimestamp();
      $subscribe_duration = $expires-time();
      if($subscribe_duration>0)
        $this->arResult["SUBSCRIBE_DURATION"] = $subscribe_duration;
      else
        $this->arResult["SUBSCRIBE_DURATION"] =  0;
    }

    if($_POST["token"]) {
      $token = $_POST["token"];
      $key = $user_key;
    }
    else {
      $token = $user_ate["beeline_token"];
      $key = $user_ate["ate_key"];
    }

    if($this->arResult["MOYSKLAD_SUBSCRIBE_SUCCESS"] || $this->arResult["ATE_ALREADY_SUBSCRIBED"]) {
      $this->arResult["SUBSCRIBE_EXIST"] = true;
      if(!$this->arResult["MOYSKLAD_SUBSCRIBE_SUCCESS"])
        $arWorkers = $this->GetWorkers($user_ate["beeline_token"]);
      $this->CreateUserSettingsTable($this->arResult, $arWorkers, $key, $token);
    };
    $this->includeComponentTemplate();
  }
}
?>
