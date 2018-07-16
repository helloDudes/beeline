<?
/**
 * Загружаем список неотвеченных звонков и необработанных заявок.
 *
 * @author Чижик Константин
 * @copyright 2018 ООО DMM
 */

\Bitrix\Main\Loader::includeModule('highloadblock');
use Bitrix\Highloadblock\HighloadBlockTable as HLBT;
use Bitrix\Main\Entity;

class classCommonCallsAnalyticB extends CBitrixComponent
{

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
  * Отбирает из всех сотрудников только выбранных в фильтре
  *
  * @param array $arWorkerNumbers массив телефонов сотрудников
  * @param array $arForm массив всех параметров фильтра
  * @param string $page_name игнорируемый параметр
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
        $arResult["WORKERS"][$el["UF_PHONE_NUMBER"]]["UNANSW_COUNT"] = 0;
        $arResult["WORKERS"][$el["UF_PHONE_NUMBER"]]["ANSW_COUNT"] = 0;

        $arWorkerNumbers[]=$el["UF_PHONE_NUMBER"];
      };
    };
    if(count($arWorkerNumbers)==0)
      $arWorkerNumbers = 0;
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
    $entity_data_class = $this->GetEntityDataClass(UNANSWERED_CALLS_HL_BLOCK_ID);
    $page_name = "nav-calls";
    $this->CheckWorkers($arWorkerNumbers, $_GET, $page_name);
    $filter = array();
    $this->CheckData($filter, $_GET["date_from"], $_GET["date_to"]);
    $this->CheckType($filter, $_GET["type"], $user_key, $arWorkerNumbers);
    $nav = new \Bitrix\Main\UI\PageNavigation($page_name);
    $nav->allowAllRecords(true)
        ->setPageSize(7)
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
    $entity_data_class = $this->GetEntityDataClass(UNANSWERED_CALLS_HL_BLOCK_ID);
    $this->CheckWorkers($arWorkerNumbers, $_GET);
    $filter = array(
      array(
        "LOGIC"=>"AND",
        array(
          array(
            "LOGIC"=>"AND",
            array('UF_ABONENT_NUMBER'=>$arWorkerNumbers),
            array('!UF_ABONENT_NUMBER'=>false)
          )
        ),
        array('!UF_ABONENT_NUMBER'=>false)
      ),
    );
    $this->CheckData($filter, $_GET["date_from"], $_GET["date_to"]);
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
    $entity_data_class = $this->GetEntityDataClass(UNANSWERED_CALLS_HL_BLOCK_ID);
    $filter = array(
      'UF_USER_KEY'=>$user_key,
    );
    $this->CheckData($filter, $_GET["date_from"], $_GET["date_to"]);
    $get_list_params = array(
       'select' => array('CNT'),
       'filter' => $filter,
       'runtime' => array(new Entity\ExpressionField('CNT', 'COUNT(*)')),
    );
    $rsData = $entity_data_class::getList($get_list_params);
    $el = $rsData->Fetch();
    $arResult["REQUEST_COUNT"]=$el["CNT"];
  }
  public function executeComponent() {
    global $USER;
    if(!$USER->IsAuthorized()) return;
    $user_id=$USER->GetID();
    $this->arResult["USER"] = $this->getUser($user_id);
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
