<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
\Bitrix\Main\Loader::includeModule('kostya14.custom');
use \Kostya14\Custom\DbInteraction;
use \Kostya14\Custom\ExternalApi;
/**
 * Парсит события звонков (входящий/исходящий, звонок принят, звонок завершён а так же различные редиректы).
 * И направляет их в Bitrix24
 *
 * @author Чижик Константин
 * @copyright 2018 ООО DMM
 */

class BeelineEventHandlerBitrix24
{
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
      return;

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

    $arCall = ExternalApi::RestCommand($arATEData, "telephony.externalcall.register", $arOptions);

    //Добавляем параметры события в БД
    $entity_data_class = DbInteraction::GetEntityDataClass(BITRIX24_EVENTS_HL_BLOCK_ID);
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
      return;

    //Собираем данные из прошлых событий звонка для обращений к Bitrix24
    $entity_data_class = DbInteraction::GetEntityDataClass(BITRIX24_EVENTS_HL_BLOCK_ID);
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
    ExternalApi::RestCommand($arATEData, "telephony.externalcall.hide", $arOptions);

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
    $record_url = ExternalApi::GetRecordURL(
        $arEvent["extTrackingId"],
        $arEvent["targetId"],
        $arATEData["beeline_token"],
        RECORD_WAITING_TIME
    );
    if($arEvent["answerTimeUNIX"] && $record_url)
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
    ExternalApi::RestCommand(
      $arATEData,
      "batch",
      array(
        "halt"=>0,
        "cmd"=> $arQueries,
      )
    );
  }
/**
 * Получает данные пользователя
 *
 * @param string $ate_key идентификатор АТС
 * @param string $targetID ID абонента
 * @return array $user данные пользователя
 */
  static function GetUserData($ate_key, $targetID) {
    $entity_data_class = DbInteraction::GetEntityDataClass(BITRIX24_WORKERS_HL_BLOCK_ID);
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
      $entity_data_class = DbInteraction::GetEntityDataClass(BITRIX24_EVENTS_HL_BLOCK_ID);
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
      $entity_data_class = DbInteraction::GetEntityDataClass(BITRIX24_EVENTS_HL_BLOCK_ID);
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
    $entity_data_class = DbInteraction::GetEntityDataClass(BITRIX24_EVENTS_HL_BLOCK_ID);
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
      $record_url = ExternalApi::GetRecordURL(
            $arRecord["extTrackingId"],
            $arRecord["targetId"],
            $arATEData["beeline_token"],
            RECORD_WAITING_TIME
      );
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
      $entity_data_class = DbInteraction::GetEntityDataClass(BITRIX24_WORKERS_HL_BLOCK_ID);
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
    ExternalApi::RestCommand(
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
    $entity_data_class = DbInteraction::GetEntityDataClass(BITRIX24_WORKERS_HL_BLOCK_ID);
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
    $addAnswer = ExternalApi::BeelineCommand(
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
      $arATEData = DbInteraction::GetB24AteData(
          array("UF_SUBSCRIPTION_ID"=>$arEvent["subscriptionId"],),
          BITRIX24_HL_BLOCK_ID
      );
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
      ExternalApi::RepairSubscribe(array(
          "token" => $arATEData["beeline_token"],
          "life_span" => SUBSCRIBE_LIFE_SPAN,
          "handler_url" => BEELINE_SERVER_NAME ."/calls_analytic/include/beeline_connection/beeline_event_handler_bitrix24.php",
          "id" => $arATEData["ate_id"],
          "agent_name" => "SubscriptionRecovery_Bitrix24_Agent::AgentExecute('".$arATEData["beeline_token"]."', ".$arATEData["ate_id"].");",
          "recovery_durability" => RECOVERY_DURABILITY
      ));
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
