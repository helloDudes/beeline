<?
/**
 * Загружает список звонков АТС из БД.
 * Применяет к ним фильтры, значение которых передаётся в $_GET.
 *
 * @author Чижик Константин
 * @copyright 2018 ООО DMM
 */

use \Kostya14\Custom\DbInteraction;
use \Kostya14\Custom\ExternalApi;
use \Kostya14\Custom\Filter;
use \Bitrix\Main\Context;

class classCallsListB extends CBitrixComponent
{

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
    $entity_data_class = DbInteraction::GetEntityDataClass($this->arParams["WORKERS_HL_BLOCK_ID"]);
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
      $arWorkerNumbers = false;
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
    $entity_data_class = DbInteraction::GetEntityDataClass($this->arParams["CALLS_HL_BLOCK_ID"]);

    $context = Context::getCurrent();
    $request = $context->getRequest();

    //Проверка фильтров
    Filter::CheckWorkers($arWorkerNumbers, $request->getQueryList(), $this->arParams["NAV_NAME"]);
    $filter = array(
        array(
            "LOGIC"=>"AND",
            array('UF_ABONENT_NUMBER'=>$arWorkerNumbers),
            array('!UF_ABONENT_NUMBER'=>false)
        ),
        ">UF_CALL_CREATE_DATE"=>date("d.m.Y H:i:s", time()-(86400*370))
    );

    $this->CheckDirection($filter, $request->getQuery("direction"));
    $this->CheckAnswer($filter, $request->getQuery("is_answered"));
    Filter::CheckData($filter, $request->getQuery("date_from"), $request->getQuery("date_to"));
    $this->CheckClient($filter, $request->getQuery("client_number"));
    
    //Задаём постраничную навигацию
    $nav = new \Bitrix\Main\UI\PageNavigation($this->arParams["NAV_NAME"]);
    $nav->allowAllRecords(true)
        ->setPageSize($this->arParams["PAGE_SIZE"])
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
        $arResult["CALLS"][$el["ID"]]["DOWNLOAD_LINK"] = ExternalApi::GetRecordURL(
            $el["UF_EXT_TRACKING_ID"],
            $el["UF_ABONENT_NUMBER"]."@mpbx.sip.beeline.ru",
            $arResult["USER"]["UF_TOKEN"],
            0
        );
    }
  }
  /**
   * @throws Exception юзер не найден
   * @throws \Bitrix\Main\LoaderException Модуль kostya14.custom не установлен
   */
  public function executeComponent() {
    if (!\Bitrix\Main\Loader::includeModule('kostya14.custom')) {
        throw new \Bitrix\Main\LoaderException("Модуль kostya14.custom не установлен");
    }

    if(
        !$this->arParams["WORKERS_HL_BLOCK_ID"]
        || !$this->arParams["CALLS_HL_BLOCK_ID"]
        || !$this->arParams["NAV_NAME"]
        || !$this->arParams["PAGE_SIZE"]
    )
    {
        ShowError("Недостаточно параметров");
        return;
    }

    $this->arParams["WORKERS_HL_BLOCK_ID"] = intval($this->arParams["WORKERS_HL_BLOCK_ID"]);
    $this->arParams["CALLS_HL_BLOCK_ID"] = intval($this->arParams["CALLS_HL_BLOCK_ID"]);
    $this->arParams["NAV_NAME"] = strval($this->arParams["NAV_NAME"]);
    $this->arParams["PAGE_SIZE"] = intval($this->arParams["PAGE_SIZE"]);


    global $USER;
    if(!$USER->IsAuthorized()) return;
    $user_id=$USER->GetID();
    $this->arResult["USER"] = DbInteraction::getUser($user_id);

    if(!$this->arResult["USER"]) {
        throw New Exception("user not found");
    }

    if($this->startResultCache(false, array($this->arResult["USER"]["LOGIN"], $_GET)))
    {
      $arWorkerNumbers = $this->GetWorkers($this->arResult, $this->arResult["USER"]["LOGIN"]);
      if($arWorkerNumbers) {
          $this->GetCalls($this->arResult, $arWorkerNumbers);
      }
      $this->includeComponentTemplate();
    };
  }
}
?>
