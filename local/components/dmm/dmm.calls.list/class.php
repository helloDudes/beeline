<?
/**
 * Загружает список звонков АТС из БД.
 * Применяет к ним фильтры, значение которых передаётся в $_GET.
 *
 * @author Чижик Константин
 * @copyright 2018 ООО DMM
 */

\Bitrix\Main\Loader::includeModule('highloadblock');
use Bitrix\Highloadblock\HighloadBlockTable as HLBT;

class classCallsListB extends CBitrixComponent
{
  const NAV_NAME = 'nav-calls'; //Имя $_GET параметра постраничной навигации
  const PAGE_SIZE = 7;
  /**
  * Получает класс сущности highloadblock для дальнейшей работы с таблицей
  *
  * @param int $HlBlockId highloadblock id
  * @return object $entity_data_class сущность
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
  * Получает данные пользователя
  *
  * @param int $user_id
  * @return array $arUser массив со всеми параметрами пользователя
  */
  function getUser($user_id) {
    $rsUser = CUser::GetByID($user_id);
    $arUser = $rsUser->Fetch();
    return $arUser;
  }
  /**
  * Добавляет к фильтру звонков его направление, если задан $direct
  *
  * @param array $filter массив фильтров
  * @param string $direct содержит направление
  */
  function CheckDirection(&$filter, $direct) {
    if($direct=="incoming") {
      $filter["UF_CALL_DIRECTION"]=true;
    };
    if($direct=="outgoing") {
      $filter["UF_CALL_DIRECTION"]=false;
    };
  }
  /**
  * Отбирает из всех сотрудников только выбранных в фильтре
  *
  * @param array $arWorkerNumbers массив телефонов сотрудников
  * @param array $arForm массив всех параметров фильтра
  * @param string $page_name имя параметра, который не следует учитывать
  */
  function CheckWorkers(&$arWorkerNumbers, $arForm, $page_name) {
    unset($arForm[$page_name]);
    if($arForm["workers_all"]!="Y" && count($arForm)!=0) {
      $true_numbers = array();
      foreach ($arForm as $number => $value) {
        if(in_array($number, $arWorkerNumbers) && $value=="Y") {
          $true_numbers[]=strval($number);
        }
      }
      $arWorkerNumbers = $true_numbers;
    }
  }
  /**
  * Фильтрует только отвеченные или неотвеченные звонки
  *
  * @param array $filter массив фильтров
  * @param string $is_answered отвечен/неотвечен
  */
  function CheckAnswer(&$filter, $is_answered) {
    if($is_answered=="yes") {
      $filter["UF_IS_ANSWERED"]=true;
    };
    if($is_answered=="no") {
      $filter["UF_IS_ANSWERED"]=false;
    };
  }
  /**
  * Фильтрует по дате
  *
  * @param array $filter массив фильтров
  * @param string $date_from дата от, в формате dd.mm.YYYY
  * @param string $date_to дата до
  */
  function CheckData(&$filter, $date_from, $date_to) {
    if(!empty($date_from))
      $filter[">=UF_CALL_CREATE_DATE"]=$date_from." 00:00:00";
    if(!empty($date_to))
      $filter["<=UF_CALL_CREATE_DATE"]=$date_to." 23:59:59";
    if(empty($date_from) && empty($date_to))
      $filter[">=UF_CALL_CREATE_DATE"] = date("d.m.Y", time()-(86400*30))." 00:00:00";
  }
  /**
  * Фильтрует по номеру клиента
  *
  * @param array $filter массив фильтров
  * @param string $client_number номер клиента
  */
  function CheckClient(&$filter, $client_number) {
    if(!empty($client_number))
      $filter["?UF_PHONE_NUMBER"] = $client_number;
  }
  /**
  * Получает список сотрудников
  *
  * @param array $arResult
  * @param string $user_login
  * @return array $arWorkerNumbers массив номеров сотрудников
  */
  function GetWorkers(&$arResult, $user_login) {
    $entity_data_class = $this->GetEntityDataClass(WORKERS_HL_BLOCK_ID);
    $rsData = $entity_data_class::getList(array(
       'select' => array('ID', 'UF_PHONE_NUMBER', 'UF_FIRST_NAME', 'UF_LAST_NAME',),
       'filter' => array('UF_USER_PHONE_NUMBER'=>$user_login),
       'order' => array('UF_LAST_NAME' => 'ASC'),
    ));
    $arResult["WORKERS"] = array();
    $arWorkerNumbers = array();
    while($el = $rsData->Fetch()){
      if(!empty($el["UF_PHONE_NUMBER"])) {
        if(!empty($el["UF_LAST_NAME"]))
          $el["UF_LAST_NAME"] .= " ";
        $arResult["WORKERS"][$el["UF_PHONE_NUMBER"]]["WORKER_NAME"] = $el["UF_LAST_NAME"].$el["UF_FIRST_NAME"];
        $arWorkerNumbers[]=$el["UF_PHONE_NUMBER"];
      };
    };
    if(count($arWorkerNumbers)==0)
      $arWorkerNumbers = 0;
    return $arWorkerNumbers;
  }
  /**
  * Получает список звонков, связанных с сотрудниками
  * Применяет фильтры, использует постраничную навигацию
  *
  * @param array $arResult
  * @param array $arWorkerNumbers массив номеров сотрудников
  */
  function GetCalls(&$arResult, $arWorkerNumbers) {
    $entity_data_class = $this->GetEntityDataClass(CALLS_HL_BLOCK_ID);

    //Проверка фильтров
    $this->CheckWorkers($arWorkerNumbers, $_GET, self::NAV_NAME);
    $filter = array(
        array(
            "LOGIC"=>"AND",
            array('UF_ABONENT_NUMBER'=>$arWorkerNumbers),
            array('!UF_ABONENT_NUMBER'=>false)
        ),
        ">UF_CALL_CREATE_DATE"=>date("d.m.Y H:i:s", time()-(86400*370))
    );
    $this->CheckDirection($filter, $_GET["direction"]);
    $this->CheckAnswer($filter, $_GET["is_answered"]);
    $this->CheckData($filter, $_GET["date_from"], $_GET["date_to"]);
    $this->CheckClient($filter, $_GET["client_number"]);
    
    //Задаём постраничную навигацию
    $nav = new \Bitrix\Main\UI\PageNavigation(self::NAV_NAME);
    $nav->allowAllRecords(true)
        ->setPageSize(self::PAGE_SIZE)
        ->initFromUri();

    //Получаем список звонков с заданным фильтром
    $rsData = $entity_data_class::getList(array(
      'select' => array(
        'ID',
        'UF_PHONE_NUMBER',
        'UF_IS_ANSWERED',
        'UF_CALL_DIRECTION',
        'UF_ABONENT_NUMBER',
        'UF_CALL_CREATE_DATE',
        'UF_CALL_END_TIME',
        'UF_IS_RECORDING',
        'UF_EXT_TRACKING_ID',
        'UF_MULTICALL_NUMBER',
      ),
      'filter' => $filter,
      'order' => array('UF_CALL_CREATE_DATE' => 'DESC'),
      'offset' => $nav->getOffset(),
      'limit' => $nav->getLimit(),
      'count_total' => true,
    ));
    $nav->setRecordCount($rsData->getCount());
    $arResult["NAV"] = $nav; //Передаём объект $nav в шаблон для отображения постранички с его помощью

    $arResult["CALLS"] = array();
    while($el = $rsData->Fetch()){
        $arResult["CALLS"][$el["ID"]]["WORKER_PHONE_NUMBER"] = $el["UF_ABONENT_NUMBER"];
        $arResult["CALLS"][$el["ID"]]["DIRECTION"] = $el["UF_CALL_DIRECTION"];
        $arResult["CALLS"][$el["ID"]]["SUCCESS"] = $el["UF_IS_ANSWERED"];
        $arResult["CALLS"][$el["ID"]]["PHONE_NUMBER"] = $el["UF_PHONE_NUMBER"];
        $arResult["CALLS"][$el["ID"]]["CALL_CREATE_DATE"] = $el["UF_CALL_CREATE_DATE"];
        if($el["UF_CALL_CREATE_DATE"] && $el["UF_CALL_END_TIME"]) {
          $date = DateTime::createFromFormat('d.m.Y H:i:s', $el["UF_CALL_CREATE_DATE"]);
          $createDate = $date->getTimestamp();
          $date = DateTime::createFromFormat('d.m.Y H:i:s', $el["UF_CALL_END_TIME"]);
          $finishDate = $date->getTimestamp();
          $duration = $finishDate - $createDate;
          $hours = round($duration/3600);
          $arResult["CALLS"][$el["ID"]]["DURATION"] = $hours.":".date("i:s", $duration);
        };
        $arResult["CALLS"][$el["ID"]]["EXT_TRACKING_ID"] = $el["UF_EXT_TRACKING_ID"];
        $arResult["CALLS"][$el["ID"]]["MULTICALL_NUMBER"] = $el["UF_MULTICALL_NUMBER"];
    }
  }
  /**
  * Получает ссылку на запись каждого звонка
  *
  * @param array $arResult
  */
  function GetDownloadLink(&$arResult) {
    foreach ($arResult["CALLS"] as $key => $value) {
      if($arResult["CALLS"][$key]["EXT_TRACKING_ID"] && $arResult["CALLS"][$key]["SUCCESS"]) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://cloudpbx.beeline.ru/apis/portal/records/".urlencode($arResult["CALLS"][$key]["EXT_TRACKING_ID"])."/".urlencode($arResult["CALLS"][$key]["WORKER_PHONE_NUMBER"]."@mpbx.sip.beeline.ru")."/reference");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");

        $headers = array();
        $headers[] = "X-Mpbx-Api-Auth-Token: ".$arResult["USER"]["UF_TOKEN"];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = json_decode(curl_exec($ch), true);
        curl_close ($ch);
        $arResult["CALLS"][$key]["DOWNLOAD_LINK"]=$result["url"];
	     }
    }
  }
  public function executeComponent() {
    global $USER;
    if(!$USER->IsAuthorized()) return;
    $user_id=$USER->GetID();
    $this->arResult["USER"] = $this->getUser($user_id);
    if($this->startResultCache(false, array($this->arResult["USER"]["LOGIN"], $_GET)))
    {
      $arWorkerNumbers = $this->GetWorkers($this->arResult, $this->arResult["USER"]["LOGIN"]);
      $this->GetCalls($this->arResult, $arWorkerNumbers);
      $this->GetDownloadLink($this->arResult);
      $this->includeComponentTemplate();
    };
  }
}
?>
