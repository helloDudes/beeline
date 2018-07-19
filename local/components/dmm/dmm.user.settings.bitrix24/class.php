<?
/**
 * Загружает список сотрудников, привязанных к пользователю.
 * Загружает список звонков, привязанных к сотрудникам.
 *
 * Применяет к этим данным фильтры, значение которых передаётся из $_GET.
 *
 * @author Чижик Константин
 * @copyright 2018 ООО DMM
 */

\Bitrix\Main\Loader::includeModule('kostya14.custom');
use \Kostya14\Custom\DbInteraction;
use \Kostya14\Custom\ExternalApi;
use \Kostya14\Custom\Filter;
use \Bitrix\Main\Config\Option;

class classUserSettingsBitrix24 extends CBitrixComponent
{
  /**
  * Получает массив сотрудников, связанных с введёным токеном
  *
  * @param string $token идентификатор АТС
  * @return array $arWorkers массив сотрудников с их данными
  */
  function GetWorkers($token) {
    $arWorkers = ExternalApi::BeelineCommand("abonents/", $token, "GET", array());

    if(!isset($arWorkers["errorCode"]) && count($arWorkers)>0)
      return $arWorkers;
    return false;
  }
  /**
  * Записывает массив сотрудников в БД
  *
  * @param array $arWorkers массив сотрудников из запроса к Билайн
  * @param string $user_key идентификатор АТС
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
  /**
   * Перенаправляет на страницу авторизации, чтобы после её завершения получить данные для получения токена
   *
   * @param int $user_id
   */
  function GetCode($user_id) {
    //Задаём опции домена и токена для дальнейшего использования после авторизации
    Option::set("main", "token_for_".$user_id, $_POST["token"]);
    Option::set("main", "domain_for_".$user_id, $_POST["domain"]);

    $url = "https://".$_POST["domain"]."/oauth/authorize/"
    ."?client_id=".urlencode(BITRIX24_CLIENT_ID)
    ."&response_type=code"
    ."&redirect_uri=".urlencode(REDIRECT_URI);
    LocalRedirect($url);
  }
  /**
   * Перенаправляет на страницу авторизации, чтобы после её завершения получить данные для получения токена
   *
   * @param array $arForm полученые из GetCode данные авторизации
   * @return array $arOutput ответ Bitrix24
   */
  function GetAuth($arForm) {
    $url = "https://oauth.bitrix.info/oauth/token/"
    ."?client_id=".urlencode(BITRIX24_CLIENT_ID)
    ."&grant_type=authorization_code"
    ."&client_secret=".urlencode(BITRIX24_SECRET_CODE)
    ."&scope=".urlencode($arForm["scope"])
    ."&redirect_uri=".urlencode(REDIRECT_URI)
    ."&code=".urlencode($arForm["code"]);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($ch);
    curl_close($ch, CURLINFO_HTTP_CONNECTCODE);
    $arOutput = json_decode($output, true);

    CEventLog::Add(array(
       "AUDIT_TYPE_ID" => "BITRIX24_METHOD",
       "MODULE_ID" => "main",
       "DESCRIPTION" => "input: (url: ".$url.") output: ".$output,
    ));

    if(!$arOutput["access_token"]) {
      return false;
    };

    return $arOutput;
  }
  /**
   * Добавляет сущность АТС в БД, а именно в общую таблицу АТС, в таблицу АТС Bitrix24
   * и в другие таблицы связанные с Bitrix24
   *
   * @param string $user_key идентификатор АТС
   * @param int $user_id
   * @param int $ate_id АТС из общей таблицы АТС
   * @param array $arData данные АТС
   * @param array $arWorkers массив сотрудников
   * @param int $b24_ate_id АТС из таблицы АТС Bitrix24
   * @param string $old_token старый токен Билайн
   * @param string $reg_token новый токен Билайн
   * @param string $reg_domain домен Bitrix24
   */
  function AddATE($user_key, $user_id, $ate_id, $arData, $arWorkers, $b24_ate_id, $old_token, $reg_token, $reg_domain) {
    //Активируем подписку на события Билайн
    $res = ExternalApi::BeelineCommand(
        "subscription/",
        $reg_token,
        "PUT",
        array(
            "expires" => SUBSCRIBE_LIFE_SPAN,
            "subscriptionType" => "BASIC_CALL",
            "url" => BEELINE_SERVER_NAME."/calls_analytic/include/beeline_connection/beeline_event_handler_bitrix24.php",
        )
    );
    $subscriptionId = $res["subscriptionId"];

    //Обновляем идентификатор АТС у пользователя
    $user = new CUser;
    $user->Update($user_id, array(
      "UF_USER_KEY"=>$user_key,
    ));

    if(!$subscriptionId)
        return;

    $entity_data_class_ate = DbInteraction::GetEntityDataClass(ATS_HL_BLOCK_ID);
    $entity_data_class_ate_b24 = DbInteraction::GetEntityDataClass(BITRIX24_HL_BLOCK_ID);

    //Если в базе нет такой АТС, добавляем её и сотрудников
    if(!$ate_id) {
      $entity_data_class_ate::add(array(
        'UF_KEY'=>$user_key,
        'UF_BITRIX24'=>true,
      ));
      $this->AddWorkers($arWorkers, $user_key);
    };

    //Если у АТС нет интеграции с Bitrix24, то записываем её данные в БД
    //и отмечаем в общем списке АТС что подписка активна.
    //Иначе просто обновляем данные.
    if(!$b24_ate_id) {
      $result = $entity_data_class_ate_b24::add(array(
        'UF_KEY'=>$user_key,
        'UF_PORTAL'=> $reg_domain,
        'UF_ACCESS_TOKEN' => $arData["access_token"],
        'UF_EXPIRES_IN' => date("d.m.Y H:i:s", $arData["expires"]),
        'UF_REFRESH_TOKEN' => $arData["refresh_token"],
        'UF_MEMBER_ID' => $arData["member_id"],
        'UF_BEELINE_TOKEN' => $reg_token,
        'UF_SUBSCRIPTION_ID' => $subscriptionId,
        'UF_SUBSCRIBE_EXPIRES' => date("d.m.Y", time()+(86400*30))." 00:00:00",
        'UF_LIMIT' => "all",
        'UF_DATE_OF_CREATION' => date("d.m.Y")." 00:00:00",
      ));
      $b24_ate_correct_id = $result->getId();
      if($ate_id) {
        $entity_data_class_ate::update($ate_id, array(
          'UF_BITRIX24'=>true,
        ));
      }
      $this->AddDataToBitrix24($arData, $reg_token, $reg_domain, $user_key);
    }
    else {
      $entity_data_class_ate_b24::update($b24_ate_id, array(
        'UF_PORTAL'=> $reg_domain,
        'UF_ACCESS_TOKEN' => $arData["access_token"],
        'UF_EXPIRES_IN' => date("d.m.Y H:i:s", $arData["expires"]),
        'UF_REFRESH_TOKEN' => $arData["refresh_token"],
        'UF_MEMBER_ID' => $arData["member_id"],
        'UF_BEELINE_TOKEN' => $reg_token,
        'UF_SUBSCRIPTION_ID' => $subscriptionId,
      ));
      $b24_ate_correct_id = $b24_ate_id;
    }

    $this->CreateSubscribe($b24_ate_correct_id, $old_token, $reg_token);
    LocalRedirect($_SERVER["PHP_SELF"]."?SUCCESS=Y");
  }
  /**
   * Проверяет, существует ли уже АТС в нашей БД, просматривая,
   * есть ли уже какие либо номера её сотрудников в наших списках
   *
   * @param array $arUser массив данных пользователя
   * @param array $arWorkers массив даннх сотрудников АТС
   * @param string $key идентификатор АТС
   * @param int $ate_id из общей таблицы АТС
   */
  function CheckNetwork($arUser, $arWorkers, &$key, &$ate_id) {

    $arWorkerNumbers = array();
    foreach ($arWorkers as $value) {
      if($value["phone"])
        $arWorkerNumbers[] = $value["phone"];
    };
    $entity_data_class = DbInteraction::GetEntityDataClass(WORKERS_HL_BLOCK_ID);
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

    $entity_data_class = DbInteraction::GetEntityDataClass(ATE_WORKERS_HL_BLOCK_ID);
    $rsData = $entity_data_class::getList(array(
       'select' => array('ID', 'UF_KEY'),
       'filter' => array("UF_PHONE_NUMBER"=>$arWorkerNumbers)
    ));
    $el = $rsData->fetch();
    if(!empty($el["UF_KEY"])) {
      $key = $el["UF_KEY"];
      $entity_data_class = DbInteraction::GetEntityDataClass(ATS_HL_BLOCK_ID);
      $rsData = $entity_data_class::getList(array(
         'select' => array('ID'),
         'filter' => array("UF_KEY"=>$key)
      ));
      $el = $rsData->fetch();
      $ate_id = $el["ID"];
    };
  }
  /**
   * Проверяет, существует ли подписка в таблице Bitrix24
   *
   * @param string $user_key идентификатор АТС
   * @param int $b24_ate_id АТС из таблицы АТС Bitrix24
   * @param string $old_token страый идентификатор АТС
   */
  function CheckB24Subscription($user_key, &$b24_ate_id, &$old_token) {
    $entity_data_class = DbInteraction::GetEntityDataClass(BITRIX24_HL_BLOCK_ID);
    $rsData = $entity_data_class::getList(array(
       'select' => array('ID', 'UF_BEELINE_TOKEN'),
       'filter' => array("UF_KEY"=>$user_key)
    ));
    $el = $rsData->fetch();
    $b24_ate_id = $el["ID"];
    $old_token = $el["UF_BEELINE_TOKEN"];
  }
  /**
   * Удаляет старый агент подписки, и активирует новый.
   *
   * @param int $b24_ate_correct_id массив данных пользователя
   * @param string $new_token новый идентификатор АТС
   * @param string $old_token старый идентификатор АТС
   */
  function CreateSubscribe($b24_ate_correct_id, $old_token, $new_token) {
    CAgent::RemoveAgent(
      "SubscriptionRecovery_Bitrix24_Agent::AgentExecute('".$old_token."', ".$b24_ate_correct_id.");",
      "main"
    );
    CAgent::AddAgent(
      "SubscriptionRecovery_Bitrix24_Agent::AgentExecute('".$new_token."', ".$b24_ate_correct_id.");",
      "main",
      "N",
      strval(RECOVERY_DURABILITY),
      date("d.m.Y H:i:s", time()+RECOVERY_DURABILITY),
      "Y",
      date("d.m.Y H:i:s", time()+RECOVERY_DURABILITY)
    );
  }
  /**
   * Собирает данные для построения таблицы сопоставления пользователей Билайн и Bitrix24
   *
   * @param array $arResult
   * @param array $arWorkers массив сотрудников Билайн
   * @param string $key идентификатор АТС
   * @param string $access_token токен Bitrix24
   * @param string $domain домен Bitrix24
   * @param string $token токен Билайн
   */
  function CreateUserSettingsTable(&$arResult, $arWorkers, $key, $access_token, $domain, $token) {
    $arResult["BEELINE_WORKERS"] = $this->GetBeelineWorkersMod($arWorkers);
    $arResult["USERS_DATA"] = $this->GetBitrixUsers($key, $domain, $access_token);
    $arResult["MULTICALL_NUMBERS"] = $this->GetMulticallNumbers($token, $key);
    if($arResult["ATE_LIMIT"] && $arResult["ATE_LIMIT"]!="all") {
      $arResult["ROWS"] = $arResult["ATE_LIMIT"];
    }
    elseif(count($arResult["USERS_DATA"]["BITRIX24_USERS"])>0) {
      $arResult["ROWS"] = count($arResult["USERS_DATA"]["BITRIX24_USERS"]);
    }
    else {
      $arResult["ROWS"] = 15;
    }
  }
  /**
   * Получаем список многоканальных номеров
   *
   * @param string $token Билайн
   * @param string $key идентификатор АТС
   * @return array $arAnswerModif массив многоканальных номеров в виде Номер-Имя номера из БД
   */
  function GetMulticallNumbers($token, $key) {
    if(!$token)
      return;

    //Получаем список многоканальных номеров из Билайн
    $arAnswer =  ExternalApi::BeelineCommand("numbers/", $token, "GET", array());

    $arAnswerModif = array();
    foreach ($arAnswer as $value) {
      $arAnswerModif[$value["phone"]]="";
    }

    //Подставляем этим номерам имена из БД
    $entity_data_class = DbInteraction::GetEntityDataClass(BITRIX24_MULTICHANNEL_HL_BLOCK_ID);
    $rsData = $entity_data_class::getList(array(
     'select' => array('ID', 'UF_NUMBER', 'UF_NAME', 'UF_SOURCE_ID'),
     'filter' => array(
       "UF_KEY"=>$key,
     ),
    ));
    while($el = $rsData->Fetch()) {
      $arAnswerModif[$el["UF_NUMBER"]] = $el["UF_NAME"];
    }
    return $arAnswerModif;
  }
  /**
   * Записываем в Bitrix24 многоканальные номера как источники, создаём лидам и контактам пользовательское поле
   * "Без индивидуальной переадресации", создаём ВэбХуки на создание/удаление/обновление лида и контакта
   *
   * @param string $arATEData данные АТС Билайн
   * @param string $token токен Билайн
   * @param string $domain домен Bitrix24
   * @param string $key идентификатор АТС
   */
  function AddDataToBitrix24($arATEData, $token, $domain, $key) {
    $arMulticalls = $this->GetMulticallNumbers($token, $key);
    $ins = "INSERT INTO ".BITRIX24_MULTICHANNEL_HL_BLOCK_NAME." (UF_KEY, UF_NUMBER, UF_NAME, UF_SOURCE_ID) VALUES ";

    $arMultAdd = array();
    $indexAdd = 0;
    $indexCommon = 0;

    $arRestQuery = array();

    //Добавляем многоканальные номера в источники
    foreach($arMulticalls as $number => $val) {
      $name = "Билайн АТС ".$number;
      $indexAdd++;
      $indexCommon++;

      $arMultAdd["crm_status_add_".$indexAdd]="crm.status.add?"
      .http_build_query(
        array(
          "fields"=> array(
            "ENTITY_ID"=>"SOURCE",
            "STATUS_ID"=>MULTICHANNEL_STATUS_ID_PREFIX.$number,
            "NAME"=>$name,
          ),
        )
      );

      if($indexCommon % REST_API_LIMIT == 0) {
        $arRestQuery[] = array(
          "halt"=>0,
          "cmd"=> $arMultAdd,
        );
        $arMultAdd = array();
      }

      $ins.=" (";
      $ins.="'".$key."',";
      $ins.="'".$number."',";
      $ins.="'".$name."',";
      $ins.="'#SOURCE_ID_".$indexAdd."#'";
      $ins.="),";
    }

    //Создаём пользовательские поля "Без индивидуальной переадресации"
    $arOtherAdd["crm_lead_userfield_add"]="crm.lead.userfield.add?"
    .http_build_query(
      array(
        "fields"=> array(
          "FIELD_NAME" => "NO_REDIRECT",
          "EDIT_FORM_LABEL" => "Без индивидуальной переадресации",
          "USER_TYPE_ID" => "boolean",
          "XML_ID" => "NO_REDIRECT",
          "SETTINGS" => array("DEFAULT_VALUE" => false),
        )
      )
    );
    $arOtherAdd["crm_contact_userfield_add"]="crm.contact.userfield.add?"
    .http_build_query(
      array(
        "fields"=> array(
          "FIELD_NAME" => "NO_REDIRECT_C",
          "EDIT_FORM_LABEL" => "Без индивидуальной переадресации",
          "USER_TYPE_ID" => "boolean",
          "XML_ID" => "NO_REDIRECT_C",
          "SETTINGS" => array("DEFAULT_VALUE" => false),
        )
      )
    );

    //Привязываем события контактов и лидов
    $arOtherAdd["event_bind"]="event.bind?"
    .http_build_query(
      array(
        "event"=>"OnExternalCallStart",
        "handler"=>BEELINE_SSL_SERVER_NAME."/calls_analytic/include/beeline_connection/bitrix24_provider.php",
      )
    );
    $remainder = $indexCommon % REST_API_LIMIT;
    $free_space = REST_API_LIMIT - count($arOtherAdd);
    if($free_space < $remainder || $remainder == 0) {
      $arRestQuery[] = array(
        "halt"=>0,
        "cmd"=> $arMultAdd,
      );
      $arRestQuery[] = array(
        "halt"=>0,
        "cmd"=> $arOtherAdd,
      );
    }
    else {
      $arRestQuery[] = array(
        "halt"=>0,
        "cmd"=> array_merge($arMultAdd, $arOtherAdd),
      );
    }

    $arAnswer = array();

    //Отсылаем сформированные запросы
    foreach($arRestQuery as $query) {
      $batch = ExternalApi::RestCommand(
          array(
              "bitrix24_token" => $arATEData["access_token"],
              "domain" => $domain,
          ),
          "batch",
          $query
      );
      $arAnswer = array_merge($batch["result"]["result"], $arAnswer);
    }
    $ins = substr($ins, 0, -1);
    $ins.=";";

    //Подставляем id источников из ответа метку SOURCE_ID в
    //сформированном запросе добавления многоканальных источников в БД
    $ins = $this->StrReplaceSourceID($ins, $arAnswer, $indexAdd);

    $connection = Bitrix\Main\Application::getConnection();
    $connection->query($ins);
  }
  /**
   * Заменяем в запросе SQL метку SOURCE_ID_  на id источника, созданного в Bitrix24,
   * соответствующего многоканальному номеру, который в него записан
   *
   * @param string $ins SQL запрос записи в БД соответствия многоканального номера, имени и id источника из Bitrix24
   * @param array $arAnswer ответ Bitrix24 на команду записи источников в БД
   * @param int $add_count число добавленных источников
   * @return string $ins дополненый SQL запрос
   */
  function StrReplaceSourceID($ins, $arAnswer, $add_count) {
    $i = 1;

    while($i<=$add_count) {
      $ins = str_replace("#SOURCE_ID_".$i."#", $arAnswer["crm_status_add_".$i], $ins);
      $i++;
    }
    return $ins;
  }
  /**
   * Модифицирует массив абонентов Билайн, для большего удобства
   *
   * @param array $arWorkers массив абонентов Билайн
   * @return array $arWorkersModif модифицированный массив абонентов Билайн
   */
  function GetBeelineWorkersMod($arWorkers) {
    $arWorkersModif = array();
    foreach ($arWorkers as $worker) {
      $arWorkersModif[$worker["userId"]]=$worker;
    }
    return $arWorkersModif;
  }
  /**
   * Берёт список пользователей Bitrix24 из БД и самойBbitrix24 и сопоставляет их
   *
   * @param string $key идентификатор АТС
   * @param string $domain домен Bitrix24
   * @param string $access_token токен Bitrix24
   * @return array $arUsersData массив данных пользователей Bitrix24
   */
  function GetBitrixUsers($key, $domain, $access_token) {
    $entity_data_class = DbInteraction::GetEntityDataClass(BITRIX24_WORKERS_HL_BLOCK_ID);
    $rsData = $entity_data_class::getList(array(
     'select' => array('*'),
     'filter' => array(
       "UF_KEY"=>$key,
     ),
    ));
    $arBitrixUsers = array();
    while($el = $rsData->Fetch()) {
      $arBitrixUsers["ROW_".$el["UF_ROW"]]["incoming_lid"]=$el["UF_INCOMING_LID"];
      $arBitrixUsers["ROW_".$el["UF_ROW"]]["outgoing_lid"]=$el["UF_OUTGOING_LID"];
      $arBitrixUsers["ROW_".$el["UF_ROW"]]["create_task"]=$el["UF_CREATE_TASK"];
      $arBitrixUsers["ROW_".$el["UF_ROW"]]["crm_user_id"]=$el["UF_CRM_USER_ID"];
      $arBitrixUsers["ROW_".$el["UF_ROW"]]["add_to_chat"]=$el["UF_ADD_TO_CHAT"];
      $arBitrixUsers["ROW_".$el["UF_ROW"]]["responsible_manager"]=$el["UF_RESPONS_MANAGER"];
      $arBitrixUsers["ROW_".$el["UF_ROW"]]["beeline_user_id"]=$el["UF_BEELINE_USER_ID"];
    };

    $arAllBitrixUsers = ExternalApi::RestCommand(
          array(
              "bitrix24_token" => $access_token,
              "domain" => $domain,
          ),
          "user.get",
          array()
    );

    $arAllBitrixUsers = $arAllBitrixUsers["result"];

    foreach ($arAllBitrixUsers as $user) {
      $arBitrixList[$user["ID"]]=$user["NAME"]." ".$user["LAST_NAME"];
    }
    $arUsersData["BITRIX24_USERS"]=$arBitrixUsers;
    $arUsersData["BITRIX24_USER_LIST"]=$arBitrixList;
    return $arUsersData;
  }
  public function executeComponent() {
    global $USER;
    if(!$USER->IsAuthorized()) return;
    $user_id=$USER->GetID();
    $arUser = DbInteraction::getUser($user_id);
    $this->arResult["USER_DATA"]=$arUser;

    //Если началась процедура интеграции, получаем данные для доступа к Bitrix24
    if(!empty($_POST["domain"]) && !empty($_POST["token"])) {
      CEventLog::Add(array(
         "AUDIT_TYPE_ID" => "BITRIX24_REG",
         "MODULE_ID" => "main",
         "DESCRIPTION" => "user_id: ".$user_id." POST: ".json_encode($_POST),
      ));
      $this->GetCode($user_id);
    }

    //Если произошла авторизация Bitrix24, то начинаем процедуру создания интеграции
    if(!empty($_GET["code"])) {
      //Берём сохранённые на первом этапе опции домена и токена
      $reg_token = Option::get("main", "token_for_".$user_id);
      $reg_domain = Option::get("main", "domain_for_".$user_id);

      //Удаляем более ненужные опции
      Option::delete("main", array("name"=>"token_for_".$user_id));
      Option::delete("main", array("name"=>"domain_for_".$user_id));
      if(!empty($reg_token) && !empty($reg_domain)) {

        //Если найдена АТС с абонентами получаем данные для доступа к Bitrix24
        if($arWorkers = $this->GetWorkers($reg_token)) {
          $arData = $this->GetAuth();

          //Если данные получены, проверяем присутствие АТС в БД и создаём интеграцию на основании проверки
          if($arData["user_id"]) {
            $user_key = "";
            $ate_id = "";
            $this->CheckNetwork($arUser, $arWorkers, $user_key, $ate_id);

            //Если такой АТС нигде нет, то запускааем полный процесс добавления во все связанные таблицы
            if(!$user_key) {
              $user_key = $arUser["UF_USER_KEY"];
              if(empty($user_key))
                $user_key = md5(uniqid($user_id, true));
              $this->AddATE(
                  $user_key,
                  $user_id,
                  $ate_id,
                  $arData,
                  $arWorkers,
                  false,
                  false,
                  $reg_token,
                  $reg_domain
              );
            }
            else {
              //Если есть, то проверяем есть ли интеграция Bitrix24
              $b24_ate_id = false;
              $old_token = false;
              $this->CheckB24Subscription($user_key, $b24_ate_id, $old_token);

              //Если есть то обновляем, если нет, то создаём
              if($b24_ate_id) {
                $this->AddATE(
                    $user_key,
                    $user_id,
                    $ate_id,
                    $arData,
                    $arWorkers,
                    $b24_ate_id,
                    $old_token,
                    $reg_token,
                    $reg_domain
                );
              }
              else {
                $this->AddATE(
                    $user_key,
                    $user_id,
                    $ate_id,
                    $arData,
                    $arWorkers,
                    false,
                    false,
                    $reg_token,
                    $reg_domain
                );
              }
            }
          };
        }
        else {
          $this->arResult["WRONG_TOKEN"] = true;
        };
      };
    };

    //Получаем данные для формирования таблиц пользователей и т.д
    if($user_key)
      $current_user_key = $user_key;
    else
      $current_user_key = $arUser["UF_USER_KEY"];

    $user_ate = DbInteraction::GetB24AteData(array("UF_KEY"=>$current_user_key), BITRIX24_HL_BLOCK_ID);;

    if($user_ate["beeline_token"]) {
      $this->arResult["ATE_ALREADY_SUBSCRIBED"] = true;
      $this->arResult["ATE_LIMIT"] = $user_ate["ate_limit"];
      $this->arResult["ATE_OPTIONS"]["options"] = $user_ate["create_redirect"];
      $this->arResult["SUBSCRIBE_EXPIRES"] = $user_ate["subscribe_expires"];
      $date = DateTime::createFromFormat('d.m.Y H:i:s', $user_ate["subscribe_expires"]);
      $expires = $date->getTimestamp();
      $subscribe_duration = $expires-time();
      if($subscribe_duration>0)
        $this->arResult["SUBSCRIBE_DURATION"] = $subscribe_duration;
      else
        $this->arResult["SUBSCRIBE_DURATION"] =  0;
    }

    if($this->arResult["ATE_ALREADY_SUBSCRIBED"]) {
      $this->arResult["SUBSCRIBE_EXIST"] = true;
      $arWorkers = $this->GetWorkers($user_ate["beeline_token"]);
      $this->CreateUserSettingsTable(
          $this->arResult,
          $arWorkers, $user_ate["ate_key"],
          $user_ate["bitrix24_token"],
          $user_ate["domain"],
          $user_ate["beeline_token"]
      );
    };
    $this->includeComponentTemplate();
  }
}
?>
