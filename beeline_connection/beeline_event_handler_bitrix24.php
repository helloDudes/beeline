<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
use Bitrix\Main\Loader;
Loader::includeModule("highloadblock");
use Bitrix\Highloadblock\HighloadBlockTable as HLBT;
/**
 * Парсит события звонков (входящий/исходящий, звонок принят, звонок завершён и различные редиректы).
 * И направляет их в Bitrix24
 *
 * @author Чижик Константин
 * @copyright 2018 ООО DMM
 */

class BeelineEventHandlerBitrix24
{
/**
* Получает класс сущности highloadblock для дальнейшей работы с таблицей
*
* @param int $HlBlockId highloadblock id
* @return object $entity_data_class сущность
*/
  static function GetEntityDataClass($HlBlockId) {
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
* Записывает старт звонка в БД с его параметрами для дальнейшего анализа
* Открывает карточку звонка
* Инициализирует начало регистрации звонка в Bitrix24
*
* @param array $arEvent параметры звонка
* @param array $arATEData параметры подписки
*
*/
  static function AddEvent($arEvent, $arATEData) {

    $user = self::GetUserData($arATEData["ate_key"], $arEvent["targetId"]);

    if(!$user)
      return false;

    //Формируем массив опций для регистрации звонка
    $arOptions["USER_ID"] = $user["UF_CRM_USER_ID"];
    $arOptions["PHONE_NUMBER"] = $arEvent["clientFullNumber"];
    $arOptions["CRM_SOURCE"] = MULTICHANNEL_STATUS_ID_PREFIX.$arEvent["multicallNumber"];
    $arOptions["CALL_START_DATE"] = date(DateTime::ISO8601, $arEvent["startTimeUNIX"]);
    $arOptions["SHOW"] = true;
    if(
        ($user["UF_INCOMING_LID"] && $arEvent["event"]=="CallReceivedEvent")
        ||($user["UF_OUTGOING_LID"]
        && $arEvent["event"]=="CallOriginatedEvent")
    )
    {
        $arOptions["CRM_CREATE"] = true;
    }
    else
    {
        $arOptions["CRM_CREATE"] = false;
    };

    if($arEvent["event"]=="CallReceivedEvent") {
      $direction = true;
      $arOptions["TYPE"] = "2";
    }
    else {
      $direction = false;
      $arOptions["TYPE"] = "1";
    };

    $arCall = self::RestCommand($arATEData, "telephony.externalcall.register", $arOptions);

    //Добавляем параметры события в БД
    $entity_data_class = self::GetEntityDataClass(BITRIX24_EVENTS_HL_BLOCK_ID);
    $entity_data_class::add(array(
      "UF_BEELINE_CALL_ID"=>$arEvent["callId"],
      "UF_BITRIX24_CALL_ID"=>$arCall['result']["CALL_ID"],
      'UF_DIRECTION'=>$direction,
      'UF_MULTICALL_NUMBER'=>$arEvent["multicallNumber"],
      'UF_EXT_TRACKING_ID'=>$arEvent["extTrackingId"],
      'UF_IS_HUNT_GROUP'=>$arEvent["isHuntGroup"],
      'UF_EVENT_DATE'=>date("d.m.Y H:i:s"),
      'UF_LEAD_ID'=>$arCall['result']["CRM_CREATED_LEAD"],
    ));
  }
/**
* Обрабатывает событие завершения звонка
* Если звонок был завершён в процессе обзвона call-центра или группы обзвона
* то событие записывается в БД для дальнейшего анализа
* иначе оканчивается регистрация звонка в Bitrix24 и его данные появляются в CRM
*
* @param array $arEvent параметры звонка
* @param array $arATEData параметры подписки
*/
  static function CallCreate($arEvent, $arATEData) {
    $user = self::GetUserData($arATEData["ate_key"], $arEvent["targetId"]);
    if(!$user)
      return false;

    //Собираем данные из прошлых событий звонка для обращений к Bitrix24
    $entity_data_class = self::GetEntityDataClass(BITRIX24_EVENTS_HL_BLOCK_ID);
    $rsData = $entity_data_class::getList(array(
       'select' => array('ID', 'UF_BITRIX24_CALL_ID', 'UF_DIRECTION', 'UF_IS_HUNT_GROUP', 'UF_LEAD_ID'),
       'filter' => array(
         "UF_BEELINE_CALL_ID"=>$arEvent["callId"],
       ),
    ));
    $call = $rsData->Fetch();

    $arOptions["CALL_ID"] = $call["UF_BITRIX24_CALL_ID"];
    $arOptions["USER_ID"] = $user["UF_CRM_USER_ID"];

    //Скрываем карточку звонка
    self::RestCommand($arATEData, "telephony.externalcall.hide", $arOptions);

    if($user["UF_ADD_TO_CHAT"])
      $arOptions["ADD_TO_CHAT"] = 1;
    else
      $arOptions["ADD_TO_CHAT"] = 0;

    $duration = $arEvent["releaseTimeUNIX"] - $arEvent["startTimeUNIX"];
    $arOptions["DURATION"] = $duration;

    if(!$arEvent["answerTimeUNIX"]) {
      if($arEvent["releasingParty"]=="remoteRelease" || $arEvent["releasingParty"]=="localRelease")
        $arOptions["STATUS_CODE"]= "603-S";
      elseif($arEvent["internalReleaseCause"]=="Busy")
        $arOptions["STATUS_CODE"]= "486";
      else
        $arOptions["STATUS_CODE"] = "304";
    }

    //Если есть признаки звонка из группы обзвона или call-центра
    //то добавляем событие в БД и завершаем обработку
    //или запускаем регистрацию звонка из группы обзвона или call-центра
    if($arEvent["acdUserId"] || $call["UF_IS_HUNT_GROUP"]) {
      $arAdd = array(
        "UF_BEELINE_CALL_ID"=>$arEvent["callId"],
        "UF_BITRIX24_CALL_ID"=>$call["UF_BITRIX24_CALL_ID"],
        'UF_DIRECTION'=>true,
        'UF_EXT_TRACKING_ID'=>$arEvent["extTrackingId"],
        'UF_CRM_USER_ID'=>$user["UF_CRM_USER_ID"],
        'UF_TARGET_ID'=>$arEvent["targetId"],
        'UF_CREATE_TASK'=>$user["UF_CREATE_TASK"],
        'UF_ADD_TO_CHAT'=>$user["UF_ADD_TO_CHAT"],
        'UF_IS_ANSWERED'=>boolval($arEvent["answerTimeUNIX"]),
        'UF_STATUS_CODE'=>$arOptions["STATUS_CODE"],
        'UF_CLIENT_NUMBER'=>$arEvent["clientFullNumber"],
        'UF_EVENT_DATE'=>date("d.m.Y H:i:s"),
      );
      if($arEvent["answerTimeUNIX"]) {
        self::CallCenterCallRegister($arEvent, $arATEData, $arAdd);
      }
      else {
        $entity_data_class::add($arAdd);
      }
      return;
    }

    //Если звонок прямой то собираем опции и отображаем его в CRM
    if($arEvent["answerTimeUNIX"] && $record_url = self::GetRecordURL($arEvent, $arATEData["beeline_token"]))
      $arOptions["RECORD_URL"] = $record_url;

    $arQueries = array();
    $arQueries["externalcall_finish"]="telephony.externalcall.finish?".http_build_query($arOptions);

    if(!empty($arEvent["multicallNumber"]) && $call["UF_LEAD_ID"] && $arATEData["create_redirect"])
      self::AddRedirect(
          $arEvent["clientNumber"],
          $user["UF_EXTENSION"],
          $arATEData,
          $user["UF_CRM_USER_ID"],
          $call["UF_LEAD_ID"]
      );

    if(!$arEvent["answerTimeUNIX"] && $call["UF_DIRECTION"]) {
      if($user["UF_CREATE_TASK"]) {
        $arQueries["task_item_add"]="task.item.add?"
        .http_build_query(
          array(
            "TASKDATA"=>array(
              "TITLE"=>"Ответьте на пропущенный звонок по номеру ".$arEvent["clientFullNumber"].".",
              "DESCRIPTION" => "Ответьте на пропущенный звонок по номеру ".$arEvent["clientFullNumber"]
                  .", совершённый ".date("d.m")." в ".date("H:i").".",
              "RESPONSIBLE_ID" => $user["UF_CRM_USER_ID"],
            )
          )
        );
      }
    }
    self::RestCommand(
      $arATEData,
      "batch",
      array(
        "halt"=>0,
        "cmd"=> $arQueries,
      )
    );
  }
/**
* Ждёт формирования записи разговора RECORD_WAITING_TIME секунд и запрашивает её Билайна
*
* @param array $arEvent параметры звонка
* @param string $token токен Билайн
* @return string $Output["url"] ссылка на скачивание записи
*/
  static function GetRecordURL($arEvent, $token) {
    sleep(RECORD_WAITING_TIME);
    $url = "https://cloudpbx.beeline.ru/apis/portal/records/".urlencode($arEvent["extTrackingId"])
        ."/".urlencode($arEvent["targetId"])."/reference";

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    $headers = array();
    $headers[] = "X-Mpbx-Api-Auth-Token: ".$token;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $output = curl_exec($ch);
    curl_close ($ch);
    $arOutput = json_decode($output, true);
    CEventLog::Add(array(
       "AUDIT_TYPE_ID" => "BITRIX24_GET_RECORD",
       "MODULE_ID" => "main",
       "DESCRIPTION" => "url: ".$url." token: ".$token." output: ".$output,
    ));

    return $arOutput["url"];
  }
/**
 * Получает данные пользователя
 *
 * @param string $ate_key идентификатор АТС
 * @param string $targetID ID абонента
 * @return array $user данные пользователя
 */
  static function GetUserData($ate_key, $targetID) {
    $entity_data_class = self::GetEntityDataClass(BITRIX24_WORKERS_HL_BLOCK_ID);
    $rsData = $entity_data_class::getList(array(
       'select' => array(
           'ID',
           'UF_CRM_USER_ID',
           'UF_INCOMING_LID',
           'UF_OUTGOING_LID',
           'UF_CREATE_TASK',
           'UF_CRM_USER_ID',
           'UF_ADD_TO_CHAT',
           'UF_EXTENSION'
       ),
       'filter' => array(
         "UF_KEY"=>$ate_key,
         "UF_BEELINE_USER_ID"=>$targetID,
       ),
    ));
    $user = $rsData->Fetch();
    if(!$user["ID"])
      return false;
    return $user;
  }
  /**
  * При событии разрушения подписки, если оно происходит по текущему id подписки (subscribtionid), то
  * оформляем подписку заново
  *
  * @param string $subscriptionId id подписки
  */
  static function Repair($subscriptionId, $token, $id) {
    $url = "https://cloudpbx.beeline.ru/apis/portal/subscription";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url );
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt(
        $ch,
        CURLOPT_POSTFIELDS,
        "{ \"expires\" : "
        .SUBSCRIBE_LIFE_SPAN
        .", \"subscriptionType\" : \"BASIC_CALL\", \"url\" : \""
        .BEELINE_SERVER_NAME
        ."/calls_analytic/include/beeline_connection/beeline_event_handler_bitrix24.php\" }"
    );
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'X-MPBX-API-AUTH-TOKEN: '.$token,
      'Content-Type: application/json',
    ));
    $output = curl_exec($ch);
    curl_close($ch, CURLINFO_HTTP_CONNECTCODE);
    $arOutput = json_decode($output, true);

    $entity_data_class = self::GetEntityDataClass(BITRIX24_HL_BLOCK_ID);
    $entity_data_class::update($id, array(
      'UF_SUBSCRIPTION_ID'=>$arOutput["subscriptionId"],
    ));
    CAgent::RemoveAgent("SubscriptionRecovery_Bitrix24_Agent::AgentExecute('".$token."', ".$id.");", "main");
    CAgent::AddAgent(
      "SubscriptionRecovery_Bitrix24_Agent::AgentExecute('".$token."', ".$id.");",
      "main",
      "N",
      strval(RECOVERY_DURABILITY),
      date("d.m.Y H:i:s", time()+RECOVERY_DURABILITY),
      "Y",
      date("d.m.Y H:i:s", time()+RECOVERY_DURABILITY)
    );
  }
/**
 * Получает данные АТС, из которой приходит событие
 *
 * @param string $subscriptionId id подписки
 * @return array $arATEData данные АТС
 */
  static function GetATEData($subscriptionId) {

    $entity_data_class = self::GetEntityDataClass(BITRIX24_HL_BLOCK_ID);
    $rsData = $entity_data_class::getList(array(
       'select' => array(
           'ID',
           'UF_ACCESS_TOKEN',
           'UF_REFRESH_TOKEN',
           'UF_EXPIRES_IN',
           'UF_PORTAL',
           'UF_BEELINE_TOKEN',
           'UF_KEY',
           'UF_CREATE_REDIRECT'
       ),
       'filter' => array(
         "UF_SUBSCRIPTION_ID"=>$subscriptionId,
         ">UF_SUBSCRIBE_EXPIRES"=>date("d.m.Y H:i:s"),
       ),
    ));
    $el = $rsData->Fetch();

    if(!$el["ID"])
      return;
    $date = DateTime::createFromFormat('d.m.Y H:i:s', $el['UF_EXPIRES_IN']);
    $expires = $date->getTimestamp();

    //Если ключ доступа истёк, то берём новый и обновляем полученные в ответе данные
    if($expires <= time()) {
      $url = "https://oauth.bitrix.info/oauth/token/"
      ."?client_id=".urlencode(BITRIX24_CLIENT_ID)
      ."&grant_type=refresh_token"
      ."&client_secret=".urlencode(BITRIX24_SECRET_CODE)
      ."&refresh_token=".urlencode($el["UF_REFRESH_TOKEN"]);

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
      $entity_data_class::update($el["ID"], $arATE);
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
/**
 * Посылает какую либо команду в Bitrix24
 *
 * @param array $arATEData данные АТС
 * @param string $method метод REST API
 * @param array $arOptions набор опций
 * @return array $arOutput ответ Bitrix24
 */
  static function RestCommand($arATEData, $method, $arOptions) {
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
/**
 * Перебирает переадресации звонка и анализирует их
 *
 * @param array $redirections массив переадресаций
 * @param array $arEvent данные звонка
 * @param string $multicallNumber переменная в которую передаётся многоканальный номер
 * @param boolean $isHuntGroup переменная которой передаётся true если найдены признаки работы группы обзвона
 */
  static function CheckRedirects($redirections, $arEvent, &$multicallNumber, &$isHuntGroup = false) {
    $count = count($redirections);
    if($count>0) {
      $i = 0;
      while($i<$count) {
        $name = preg_split("/ /", $redirections[$i]->party->name);
        $userId = $redirections[$i]->party->userId;
        //Проверяем на признаки наличия данных о многоканальном источнике
        if($name[0]==$name[1]) {
           preg_match("/\d{10}/", $userId, $match);
           $userIdNumber = $match[0];
           if(!empty($userIdNumber))
            $multicallNumber = $userIdNumber;
        }
        //Проверяем на признаки присутствия в группе обзвона
        if(preg_match("/_hg_/", $userId)) {
          $isHuntGroup = true;
        }

        $i++;
      }
    }

    //Если не найдены даные о многоканальном источнике, то ищем их в предыдущем событии, иначе добавляем их сейчас
    if(!$multicallNumber && $arEvent["abonentNumber"] && $arEvent["clientNumber"]) {
      $entity_data_class = self::GetEntityDataClass(BITRIX24_EVENTS_HL_BLOCK_ID);
      $rsData = $entity_data_class::getList(array(
         'select' => array('ID', 'UF_MULTICALL_NUMBER'),
         'filter' => array(
           "UF_EXT_TRACKING_ID"=>$arEvent["extTrackingId"],
           "!UF_MULTICALL_NUMBER"=>false,
         ),
      ));
      $el = $rsData->Fetch();
      $multicallNumber = $el["UF_MULTICALL_NUMBER"];

    }
    elseif($multicallNumber && (!$arEvent["abonentNumber"] || !$arEvent["clientNumber"])) {
      $entity_data_class = self::GetEntityDataClass(BITRIX24_EVENTS_HL_BLOCK_ID);
      $rsData = $entity_data_class::add(array(
        "UF_EXT_TRACKING_ID"=>$arEvent["extTrackingId"],
        "UF_MULTICALL_NUMBER"=>$multicallNumber,
        'UF_EVENT_DATE'=>date("d.m.Y H:i:s"),
      ));
    }
  }
/**
 * Проверяет отдельное событие, взятое из БД и дополняет массивы различных параметров недостающими данными
 *
 * @param array $element массив данных события из БД
 * @param array $arOptions дополняемый массив опций для регистрации звонка
 * @param array $arFeatures дополняемый массив остальных опций
 * @param array $arRecord дополняемый массив данных, требуемых для получения записи
 */
  static function EventScan($element, &$arOptions, &$arFeatures, &$arRecord) {
    $arFeatures["elments_exist"] = true;
    if(!$arRecord["targetId"] && !empty($element["UF_TARGET_ID"]))
      $arRecord["targetId"]=$element["UF_TARGET_ID"];
    if(!$arOptions["USER_ID"] && !empty($element["UF_CRM_USER_ID"]))
      $arOptions["USER_ID"] = $element["UF_CRM_USER_ID"];
    if($element["UF_CREATE_TASK"])
      $arFeatures["create_task"]=true;
    if($element["UF_ADD_TO_CHAT"])
      $arOptions["ADD_TO_CHAT"]=1;
    if($element["UF_BITRIX24_CALL_ID"] && !$arOptions["CALL_ID"])
      $arOptions["CALL_ID"] = $element["UF_BITRIX24_CALL_ID"];
    if($element["UF_IS_ANSWERED"])
      $arFeatures["is_answered"]=true;
    if($element["UF_STATUS_CODE"] && !$arFeatures["status_code"])
      $arFeatures["status_code"] = $element["UF_STATUS_CODE"];
    if($element["UF_CLIENT_NUMBER"]) {
     preg_match("/\d{10}$/", $element["UF_CLIENT_NUMBER"], $match);
     $arFeatures["client_number"] = $match[0];
    }
    if(!$arFeatures["leadId"] && $element["UF_LEAD_ID"])
      $arFeatures["leadId"] = $element["UF_LEAD_ID"];

  }
/**
 * Регистрирует звонок, относящийся к call-центру или группе обзвона
 *
 * @param array $arEvent параметры звонка
 * @param array $arATEData параметры подписки
 * @param array $arAdd данные последнего события (если имеются)
 */
  static function CallCenterCallRegister($arEvent, $arATEData, $arAdd) {
    //Получаем информацию о событиях цепочки обзвона и настройках учавствующих пользователей и объединяем эти настройки
    //для формирования опций звонка
    $entity_data_class = self::GetEntityDataClass(BITRIX24_EVENTS_HL_BLOCK_ID);
    $rsData = $entity_data_class::getList(array(
       'select' => array('*'),
       'filter' => array(
         "UF_EXT_TRACKING_ID"=>$arEvent["extTrackingId"],
       ),
       'order' => array('ID'=>'DESC'),
    ));

    $arOptions["USER_ID"] = false;
    $arRecord=array();
    $arFeatures=array();
    if($arAdd) {
      self::EventScan($arAdd, $arOptions, $arFeatures, $arRecord);
    }
    while($el = $rsData->Fetch()) {
      self::EventScan($el, $arOptions, $arFeatures, $arRecord);
    }

    if(!$arFeatures["elments_exist"])
      return;

    //Если звонки никто не ответил, назначаем абонентом ответственного менеджера
    if($arFeatures["is_answered"]) {
      $arRecord["extTrackingId"]=$arEvent["extTrackingId"];
      $record_url = self::GetRecordURL($arRecord, $arATEData["beeline_token"]);
      if($record_url)
        $arOptions["RECORD_URL"] = $record_url;
    }
    else {
      $arOptions["STATUS_CODE"]="603-S";
      $manager = self::GetManager($arATEData["ate_key"]);
      if($manager)
        $arOptions["USER_ID"]=$manager["UF_CRM_USER_ID"];
    }

    $duration = $arEvent["releaseTimeUNIX"] - $arEvent["startTimeUNIX"];
    $arOptions["DURATION"] = $duration;

    $arQueries = array();
    $arQueries["externalcall_finish"]="telephony.externalcall.finish?".http_build_query($arOptions);

    if(!$extension = $manager["UF_EXTENSION"]) {
      $entity_data_class = self::GetEntityDataClass(BITRIX24_WORKERS_HL_BLOCK_ID);
      $rsData = $entity_data_class::getList(array(
         'select' => array('ID', 'UF_EXTENSION'),
         'filter' => array(
           "UF_CRM_USER_ID"=>$arOptions["USER_ID"],
         ),
      ));
      $el = $rsData->Fetch();
      $extension = $el["UF_EXTENSION"];
    }
    if($arFeatures["leadId"] && $arATEData["create_redirect"])
      self::AddRedirect(
          $arFeatures["client_number"],
          $extension,
          $arATEData,
          $arOptions["USER_ID"],
          $arFeatures["leadId"]
      );

    if($arFeatures["create_task"] && !$arFeatures["is_answered"]) {
      if(!$manager)
        return;

      $arQueries["task_item_add"]="task.item.add?".http_build_query(
        array(
          "TASKDATA"=>array(
            "TITLE"=>"Был пропущен звонок по номеру ".$arFeatures["client_number"].".",
            "DESCRIPTION" => "Есть пропущенный звонок по номеру "
                .$arFeatures["client_number"]
                .", совершённый "
                .date("d.m")
                ." в ".date("H:i").".",
            "RESPONSIBLE_ID" => $manager["UF_CRM_USER_ID"],
          )
        )
      );
    }
    self::RestCommand(
      $arATEData,
      "batch",
      array(
        "halt"=>0,
        "cmd"=> $arQueries,
      )
    );
  }
/**
 * Получет ответственного за групповой обзвон менеджера
 *
 * @param string $key идентификатор АТС
 * @return array $manager данные менеджера
 */
  static function GetManager($key) {
    $entity_data_class = self::GetEntityDataClass(BITRIX24_WORKERS_HL_BLOCK_ID);
    $rsData = $entity_data_class::getList(array(
       'select' => array('ID', 'UF_CRM_USER_ID', 'UF_EXTENSION'),
       'filter' => array(
         "UF_KEY"=>$key,
         "UF_RESPONS_MANAGER"=>true,
       ),
    ));
    $manager = $rsData->Fetch();
    return $manager;
  }
/**
 * Посылает какую либо команду в Билайн
 *
 * @param string $url адрес метода
 * @param string $token токен Билайн
 * @param string $type тип POST/GET...
 * @param array $arOptions массив опций
 * @return array $arAnswer данные ответа Билайн
 */
  static function BeelineCommand($url, $token, $type, $arOptions) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($arOptions));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);

    $headers = array();
    $headers[] = "X-Mpbx-Api-Auth-Token: ".$token;
    $headers[] = "Content-Type: application/json";
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    curl_close ($ch);

    $arAnswer = json_decode($result, true);
    return $arAnswer;
  }
/**
 * Создаёт правило индивидуальной переадрессации
 *
 * @param string $clientNumber номер клиента
 * @param string $extension короткий номер клиента
 * @param array $arATEData параметры АТС
 * @param int $crmUserId ID пользователя Bitrix24
 * @param int $leadId ID созданного лида в Bitrix24
 */
  static function AddRedirect($clientNumber, $extension, $arATEData, $crmUserId, $leadId) {
    $add = "INSERT INTO ".BITRIX24_LEADS_HL_BLOCK_NAME;
    $add .= " (UF_KEY, UF_CLIENT_NUMBER, UF_LEAD_ID, UF_CRM_USER_ID, UF_TYPE) VALUES ";
    $add .= "('".$arATEData["ate_key"]."', '".$clientNumber."', '".$leadId."', '".$crmUserId."', 'LEAD')";
    $connection = Bitrix\Main\Application::getConnection();
    $connection->query($add);

    $arAdd = array(array(
      "inboundNumber"=>$clientNumber,
      "extension"=>$extension,
    ));
    $addAnswer = self::BeelineCommand(
      BEELINE_API_SERVER_NAME."/icr/route",
      $arATEData["beeline_token"],
      "PUT",
      $arAdd
    );
  }
/**
 * Выполняет обработчик
 *
 * @param string $xml входящий xml в виде строки
 */
  static function executeHandler($xml) {
    //Начинаем парсить параметры xml в массив $arEvent
    $xml = str_replace('<?xml version="1.0" encoding="UTF-8"?>', "", $xml);

    $xmlData = new SimpleXMLElement($xml);
    $xmlDataChilds = $xmlData->children("xsi", TRUE);
    $event = trim(strval($xmlDataChilds->eventData->attributes("xsi1", true)[0]));
    $arEvent["event"] = str_replace("xsi:", "", $event);
    if($arEvent["event"]=="HookStatusEvent")
      return;

    $arEvent["targetId"] = trim(strval($xmlDataChilds->targetId));
    $arEvent["extTrackingId"] = trim(strval($xmlDataChilds->eventData->call->extTrackingId));
    preg_match("/\d{10}/", $arEvent["targetId"], $match);
    preg_match("/_vxml/", $arEvent["targetId"], $matchVXML);

    //Если в extTrackingId присутствует номер в формате 10 цифр без "_vxml", то записываем abonentNumber в этом формате
    if(!$matchVXML[0])
      $arEvent["abonentNumber"] = $match[0];
    if(!$arEvent["abonentNumber"] && preg_match("/SIP/", $arEvent["targetId"]))
      $arEvent["abonentNumber"] = str_replace("@mpbx.sip.beeline.ru", "", $arEvent["targetId"]);

    //Если в address присутствует номер в формате 10 цифр, то записываем clientNumber в этом формате
    $arEvent["address"] = trim(strval($xmlDataChilds->eventData->call->remoteParty->address));
    preg_match("/\d{10}$/", $arEvent["address"], $match);
    $arEvent["clientNumber"] = $match[0];

    $arEvent["clientFullNumber"] = str_replace(array("tel:", "+7"), array("", "8"), $arEvent["address"]);

    $arEvent["subscriptionId"] = trim(strval($xmlDataChilds->subscriptionId));
    $arEvent["startTimeUNIX"] = trim(strval($xmlDataChilds->eventData->call->startTime));
    $arEvent["answerTimeUNIX"] = trim(strval($xmlDataChilds->eventData->call->answerTime));
    $arEvent["releaseTimeUNIX"] = trim(strval($xmlDataChilds->eventData->call->releaseTime));
    $arEvent["startTimeUNIX"] = substr($arEvent["startTimeUNIX"], 0, -3);
    $arEvent["answerTimeUNIX"] = substr($arEvent["answerTimeUNIX"], 0, -3);
    $arEvent["releaseTimeUNIX"] = substr($arEvent["releaseTimeUNIX"], 0, -3);
    $arEvent["callId"] = trim(strval($xmlDataChilds->eventData->call->callId));
    $arEvent["releasingParty"] = trim(strval($xmlDataChilds->eventData->call->releasingParty));
    $arEvent["internalReleaseCause"] = trim(strval($xmlDataChilds->eventData->call->releaseCause->internalReleaseCause));
    $arEvent["acdUserId"] = trim(strval($xmlDataChilds->eventData->call->acdCallInfo->acdUserId));
    $arEvent["callType"] = trim(strval($xmlDataChilds->eventData->call->remoteParty->callType));

    //Анализируем все перенаправления $redirections
    if($arEvent["event"]!="SubscriptionTerminatedEvent") {
      $redirections = $xmlDataChilds->eventData->call->redirections->redirection;
      self::CheckRedirects(
          $redirections,
          $arEvent,
          $arEvent["multicallNumber"],
          $arEvent["isHuntGroup"]
      );
    }
    //Конец парсинга

    //CallReceivedEvent входящий звонок
    //CallOriginatedEvent исходящий звонок
    //CallReleasedEvent завершённый звонок
    //SubscriptionTerminatedEvent остановка подписки на события

    //Берёт данные об АТС по ID подписки, если не находит, то обработка останавливается
    if
    (
      $arEvent["event"]=="CallReceivedEvent"
      || $arEvent["event"]=="CallOriginatedEvent"
      || $arEvent["event"]=="CallReleasedEvent"
      || $arEvent["event"]=="SubscriptionTerminatedEvent"
    )
    {
      $arATEData = self::GetATEData($arEvent["subscriptionId"]);
      if(!$arATEData["ate_id"])
        return;
    }

    if(($arEvent["abonentNumber"] && $arEvent["clientNumber"])) {
      if($arEvent["event"]=="CallReceivedEvent" || $arEvent["event"]=="CallOriginatedEvent")
        self::AddEvent($arEvent, $arATEData);
      if($arEvent["event"]=="CallReleasedEvent")
        self::CallCreate($arEvent, $arATEData);
    };
    //Проверка по признакам события завершения обзвона call-центром
    if(
      $arEvent["event"]=="CallReleasedEvent"
      && $arEvent["clientNumber"]
      && preg_match("/MPBX/", $arEvent["targetId"])
      && $arEvent["callType"] == "Network"
    )
    {
      if($arEvent["releasingParty"]=="remoteRelease")
        self::CallCenterCallRegister($arEvent, $arATEData);
    }
    //Если сломалась действующая подписка, чиним
    if($arEvent["event"]=="SubscriptionTerminatedEvent") {
      self::Repair($arEvent["subscriptionId"], $arATEData["beeline_token"], $arATEData["ate_id"]);
    };
  }
}

$input = file_get_contents('php://input');
/*CEventLog::Add(array(
   "AUDIT_TYPE_ID" => "BITRIX24_INPUT",
   "MODULE_ID" => "main",
   "DESCRIPTION" => $input,
));*/
if(!empty($input))
  BeelineEventHandlerBitrix24::executeHandler($input);

?>
