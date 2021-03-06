<?
/**
 * Загружаем список неотвеченных звонков и необработанных заявок.
 *
 * @author Чижик Константин
 * @copyright 2018 ООО DMM
 */

use \Kostya14\Custom\DbInteraction;
use \Kostya14\Custom\Filter;
use \Bitrix\Main\Entity;
use \Bitrix\Main\Context;

class classCommonCallsAnalyticB extends CBitrixComponent
{
  /**
   * Фильтрует по типу (заявка/звонок)
   *
   * @param array $filter массив фильтров
   * @param string $type заявка/звонок
   * @param string $user_key идентификатор АТС
   * @param array $arWorkerNumbers телефоны сотрудников
   *
   */
  function CheckType(&$filter, $type, $user_key, $arWorkerNumbers) {
    if($type=="requests")
      $filter["UF_USER_KEY"]=$user_key;
    if($type=="calls")
      $filter[]=array("LOGIC"=>"AND", array('UF_ABONENT_NUMBER'=>$arWorkerNumbers), array('!UF_ABONENT_NUMBER'=>false));
    if(empty($type) || $type=="all")
      $filter[] = Array(
        "LOGIC"=>"OR",
        Array(
          array("LOGIC"=>"AND", array('UF_ABONENT_NUMBER'=>$arWorkerNumbers), array('!UF_ABONENT_NUMBER'=>false))
        ),
        Array(
          'UF_USER_KEY'=>$user_key
        )
      );
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
        $arResult["WORKERS"][$el["UF_PHONE_NUMBER"]]["UNANSW_COUNT"] = 0;
        $arResult["WORKERS"][$el["UF_PHONE_NUMBER"]]["ANSW_COUNT"] = 0;

        $arWorkerNumbers[]=$el["UF_PHONE_NUMBER"];
      };
    };
    if(count($arWorkerNumbers)==0)
      $arWorkerNumbers = false;
    return $arWorkerNumbers;
  }
  /**
  * Получает список звонков и заявок
  *
  * @param array $arResult
  * @param string $user_key идентификатор АТС
  * @param array $arWorkerNumbers массив номеров сотрудников
  */
  function GetCalls(&$arResult, $user_key, &$arWorkerNumbers) {
    $entity_data_class = DbInteraction::GetEntityDataClass($this->arParams["UNANSWERED_CALLS_HL_BLOCK_ID"]);
    $page_name = "nav-calls";

    $context = Context::getCurrent();
    $request = $context->getRequest();

    //Проверяем фильтры
    Filter::CheckWorkers($arWorkerNumbers, $request->getQueryList(), $page_name);

    $filter = array();

    Filter::CheckData($filter, $request->getQuery("date_from"), $request->getQuery("date_to"));
    $this->CheckType($filter, $request->getQuery("type"), $user_key, $arWorkerNumbers);

    //Постраничная навигация
    $nav = new \Bitrix\Main\UI\PageNavigation($page_name);
    $nav->allowAllRecords(true)
        ->setPageSize($this->arParams["PAGE_SIZE"])
        ->initFromUri();

    $rsData = $entity_data_class::getList(array(
      'select' => array(
        'ID',
        'UF_PHONE_NUMBER',
        'UF_ABONENT_NUMBER',
        'UF_CALL_CREATE_DATE',
        'UF_NAME',
        'UF_SERVER_NAME',
      ),
      'filter' => $filter,
      'order' => array('UF_CALL_CREATE_DATE' => 'DESC'),
      'limit' => $nav->getLimit(),
      'offset' => $nav->getOffset(),
      'count_total' => true,
    ));
    $nav->setRecordCount($rsData->getCount());
    $arResult["NAV"] = $nav;

    $arResult["CALLS"] = array();
    while($el = $rsData->Fetch()){
      $arResult["CALLS"][$el["ID"]]=$el;
    }
  }
  /**
   * Получает число неотвеченных звонков
   *
   * @param array $arResult
   * @param array $arWorkerNumbers массив номеров сотрудников
   */
  function GetWorkerUnansweredCount(&$arResult, $arWorkerNumbers) {
    $entity_data_class = DbInteraction::GetEntityDataClass($this->arParams["UNANSWERED_CALLS_HL_BLOCK_ID"]);

    $context = Context::getCurrent();
    $request = $context->getRequest();

    Filter::CheckWorkers($arWorkerNumbers, $request->getQueryList());
    $filter = array(
      array(
          "LOGIC"=>"AND",
          array('UF_ABONENT_NUMBER'=>$arWorkerNumbers),
          array('!UF_ABONENT_NUMBER'=>false)
      )
    );

    Filter::CheckData($filter, $request->getQuery("date_from"), $request->getQuery("date_to"));
    $get_list_params = array(
       'select' => array('CNT', 'UF_ABONENT_NUMBER'),
       'filter' => $filter,
       'group' => array('UF_ABONENT_NUMBER'),
       'runtime' => array(new Entity\ExpressionField('CNT', 'COUNT(*)')),
    );
    $rsData = $entity_data_class::getList($get_list_params);
    $arResult["CALLS_COUNT"] = 0;
    while($el = $rsData->Fetch()){
      $arResult["CALLS_COUNT"]+=$el["CNT"];
      $arResult["WORKERS"][$el["UF_ABONENT_NUMBER"]]["UNANSW_COUNT"]=$el["CNT"];
      $arResult["WORKERS"][$el["UF_ABONENT_NUMBER"]]["SELECTED"]=true;
    };
  }
  /**
   * Получает число неотвеченных звонков
   *
   * @param array $arResult
   * @param string $user_key идентификатор АТС
   */
  function GetRequestCount(&$arResult, $user_key) {
    $entity_data_class = DbInteraction::GetEntityDataClass($this->arParams["UNANSWERED_CALLS_HL_BLOCK_ID"]);
    $filter = array(
      'UF_USER_KEY'=>$user_key,
    );

    $context = Context::getCurrent();
    $request = $context->getRequest();

    Filter::CheckData($filter, $request->getQuery("date_from"), $request->getQuery("date_to"));
    $get_list_params = array(
       'select' => array('CNT'),
       'filter' => $filter,
       'runtime' => array(new Entity\ExpressionField('CNT', 'COUNT(*)')),
    );
    $rsData = $entity_data_class::getList($get_list_params);
    $el = $rsData->Fetch();
    $arResult["REQUEST_COUNT"]=$el["CNT"];
  }

/**
 * @throws Exception юзер не найден
 * @throws \Bitrix\Main\LoaderException Модуль kostya14.custom не установлен
 */
  public function executeComponent() {
    if (!\Bitrix\Main\Loader::includeModule('kostya14.custom')) {
        throw new \Bitrix\Main\LoaderException("Модуль kostya14.custom не установлен");
    }

    if(!$this->arParams["WORKERS_HL_BLOCK_ID"] || !$this->arParams["UNANSWERED_CALLS_HL_BLOCK_ID"])
    {
        ShowError("Недостаточно параметров");
        return;
    }

    $this->arParams["WORKERS_HL_BLOCK_ID"] = intval($this->arParams["WORKERS_HL_BLOCK_ID"]);
    $this->arParams["UNANSWERED_CALLS_HL_BLOCK_ID"] = intval($this->arParams["UNANSWERED_CALLS_HL_BLOCK_ID"]);

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
        $this->GetCalls($this->arResult, $this->arResult["USER"]["UF_USER_KEY"], $arWorkerNumbers);
        $this->GetWorkerUnansweredCount($this->arResult, $arWorkerNumbers);
        $this->GetRequestCount($this->arResult, $this->arResult["USER"]["UF_USER_KEY"]);
      };
      $this->includeComponentTemplate();
    };
  }
}
?>
