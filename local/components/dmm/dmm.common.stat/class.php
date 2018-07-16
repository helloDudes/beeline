<?
/**
 * Считает суммарное количество звонков в общем, и для каждого сотрудника отдельно, если выбранны сотрудники по фильтру:
 *            Все   Входящие  Исходящие
 * Всего        *         *          *
 * Отвеченно    *         *          *
 * Пропущенно   *         *          *
 *
 * @author Чижик Константин
 * @copyright 2018 ООО DMM
 */
\Bitrix\Main\Loader::includeModule('highloadblock');
use Bitrix\Highloadblock\HighloadBlockTable as HLBT;
use Bitrix\Main\Entity;

class classCommonStatisticB extends CBitrixComponent
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
  * Получает список сотрудников
  *
  * @param array $arResult
  * @param string $user_login
  * @return array $arWorkerNumbers массив номеров сотрудников
  */
  function GetWorkers(&$arResult, $user_login) {
    $entity_data_class = $this->GetEntityDataClass(WORKERS_HL_BLOCK_ID);
    $rsData = $entity_data_class::getList(array(
       'select' => array('ID', 'UF_PHONE_NUMBER', 'UF_FIRST_NAME', 'UF_LAST_NAME'),
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
  * Отбирает из всех сотрудников только выбранных в фильтре
  *
  * @param array $arWorkerNumbers массив телефонов сотрудников
  * @param array $arForm массив всех параметров фильтра
  */
  function CheckWorkers(&$arWorkerNumbers, $arForm) {
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
  * Получает таблицу для общей статистики:
  *            Все            Входящие          Исходящие
  * Всего      ["total"]      ["total_in"]      ["total_out"]
  * Отвеченно  ["answered"]   ["answered_in"]   ["answered_out"]
  * Пропущенно ["unanswered"] ["unanswered_in"] ["unanswered_out"]
  *
  * @param array $arResult
  * @param array $arWorkerNumbers массив телефонов сотрудников
  */
  function GetCalls(&$arResult, $arWorkerNumbers) {
    $entity_data_class = $this->GetEntityDataClass(CALLS_HL_BLOCK_ID);

    //Используем фильтры
    $this->CheckWorkers($arWorkerNumbers, $_GET);
    $filter = array(
        array(
            "LOGIC"=>"AND",
            array('UF_ABONENT_NUMBER'=>$arWorkerNumbers),
            array('!UF_ABONENT_NUMBER'=>false)
        )
    );
    $this->CheckData($filter, $_GET["date_from"], $_GET["date_to"]);

    //Создаём массив параметров для запроса
    $get_list_params = array(
       'select' => array('CNT', 'UF_IS_ANSWERED'),
       'filter' => $filter,
       'group' => array('UF_IS_ANSWERED'),
       'runtime' => array(new Entity\ExpressionField('CNT', 'COUNT(*)')),
    );
    $get_list_params["filter"]["UF_CALL_DIRECTION"] = true;

    //Формируем пустую таблицу для общей статистики
    $arResult['COMMON_STATISTIC']["total"] = 0;
    $arResult['COMMON_STATISTIC']["total_in"] = 0;
    $arResult['COMMON_STATISTIC']["total_out"] = 0;
    $arResult['COMMON_STATISTIC']["answered"] = 0;
    $arResult['COMMON_STATISTIC']["answered_in"] = 0;
    $arResult['COMMON_STATISTIC']["answered_out"] = 0;
    $arResult['COMMON_STATISTIC']["unanswered"] = 0;
    $arResult['COMMON_STATISTIC']["unanswered_in"] = 0;
    $arResult['COMMON_STATISTIC']["unanswered_out"] = 0;

    //Считаем число входящих отвеченных/неотвеченных
    $rsData = $entity_data_class::getList($get_list_params);
    while($el = $rsData->Fetch()) {
      if($el["UF_IS_ANSWERED"])
        $arResult['COMMON_STATISTIC']["answered_in"]=$el["CNT"];
      else
        $arResult['COMMON_STATISTIC']["unanswered_in"]=$el["CNT"];
    }

    //Считаем число исходящих отвеченных/неотвеченных
    $get_list_params["filter"]["UF_CALL_DIRECTION"] = false;
    $rsData = $entity_data_class::getList($get_list_params);
    while($el = $rsData->Fetch()) {
      if($el["UF_IS_ANSWERED"])
        $arResult['COMMON_STATISTIC']["answered_out"]=$el["CNT"];
      else
        $arResult['COMMON_STATISTIC']["unanswered_out"]=$el["CNT"];
    }

    //Считаем суммарное число по числу отвеченных и пропущеных
    $arResult['COMMON_STATISTIC']["total_in"]=$arResult['COMMON_STATISTIC']["answered_in"]+$arResult['COMMON_STATISTIC']["unanswered_in"];
    $arResult['COMMON_STATISTIC']["total_out"]=$arResult['COMMON_STATISTIC']["answered_out"]+$arResult['COMMON_STATISTIC']["unanswered_out"];
    $arResult['COMMON_STATISTIC']["answered"]=$arResult['COMMON_STATISTIC']["answered_in"]+$arResult['COMMON_STATISTIC']["answered_out"];
    $arResult['COMMON_STATISTIC']["unanswered"]=$arResult['COMMON_STATISTIC']["unanswered_in"]+$arResult['COMMON_STATISTIC']["unanswered_out"];
    $arResult['COMMON_STATISTIC']["total"]=$arResult['COMMON_STATISTIC']["total_in"]+$arResult['COMMON_STATISTIC']["total_out"];
  }
  /**
  * Получает суммму звонков по каждому многоканальному номеру
  *
  * @param array $arResult
  * @param array $arWorkerNumbers массив телефонов сотрудников
  */
  function GetMulticallNumbers(&$arResult, $arWorkerNumbers) {
    $entity_data_class = $this->GetEntityDataClass(CALLS_HL_BLOCK_ID);

    //Проверяем фильтры
    $this->CheckWorkers($arWorkerNumbers, $_GET);
    $filter = array(
        array(
            "LOGIC"=>"AND",
            array('UF_ABONENT_NUMBER'=>$arWorkerNumbers),
            array('!UF_ABONENT_NUMBER'=>false)
        )
    );
    $this->CheckData($filter, $_GET["date_from"], $_GET["date_to"]);

    //Формируем параметры для запроса
    $get_list_params = array(
       'select' => array('CNT', 'UF_MULTICALL_NUMBER'),
       'filter' => $filter,
       'group' => array('UF_MULTICALL_NUMBER'),
       'runtime' => array(new Entity\ExpressionField('CNT', 'COUNT(*)')),
    );
    $get_list_params["filter"]["UF_IS_ANSWERED"]=true;

    //Считаем число отвеченных
    $rsData = $entity_data_class::getList($get_list_params);
    while($el = $rsData->Fetch()){
      if(empty($el["UF_MULTICALL_NUMBER"]))
        $arResult["MULTICALLS"]["NO_MULTICALL"]["ANSWERED"]=$el["CNT"];
      else
        $arResult["MULTICALLS"][$el["UF_MULTICALL_NUMBER"]]["ANSWERED"]=$el["CNT"];
    };

    //Считаем число неотвеченных
    $get_list_params["filter"]["UF_IS_ANSWERED"]=false;
    $rsData = $entity_data_class::getList($get_list_params);
    while($el = $rsData->Fetch()){
      if(empty($el["UF_MULTICALL_NUMBER"]))
        $arResult["MULTICALLS"]["NO_MULTICALL"]["UNANSWERED"]=$el["CNT"];
      else
        $arResult["MULTICALLS"][$el["UF_MULTICALL_NUMBER"]]["UNANSWERED"]=$el["CNT"];
    };
  }
  /**
   * Считает число всех звонков, складывая число отвеченных и неотвеченных, избегая лишнего запроса к БД
   *
   * @param array $arResult
   */
  function DataAnalysis(&$arResult) {
    foreach ($arResult["MULTICALLS"] as $number => $arMulticall) {
      $answ = $arMulticall["ANSWERED"];
      $unansw = $arMulticall["UNANSWERED"];
      $arResult["MULTICALLS"][$number]["TOTAL"] = $answ + $unansw;
    }
  }
  /**
   * Получает статистику по каждому работнику отдельно
   *
   * @param array $arResult
   * @param array $arWorkerNumbers массив телефонов сотрудников
   */
  function GetWorkerStat(&$arResult, $arWorkerNumbers) {
    $arResult["WORKER_STAT"]=array();
    $entity_data_class = $this->GetEntityDataClass(CALLS_HL_BLOCK_ID);

    //Проверяем фильтры
    $this->CheckWorkers($arWorkerNumbers, $_GET);
    $filter = array();
    $this->CheckData($filter, $_GET["date_from"], $_GET["date_to"]);

    //Выполняем для каждго сотрудика
    foreach ($arResult["WORKERS"] as $number => $worker) {
      //Проверяем, есть ли номер из фильтра в списке доступных номеров и формируем данные для запроса
      if(in_array($number, $arWorkerNumbers)) {
        $filter['UF_ABONENT_NUMBER']=$number;
        $get_list_params = array(
           'select' => array('CNT', 'UF_IS_ANSWERED'),
           'filter' => $filter,
           'group' => array('UF_IS_ANSWERED'),
           'runtime' => array(new Entity\ExpressionField('CNT', 'COUNT(*)')),
        );

        //Создаём пустую таблицу сотрудника
        $get_list_params["filter"]["UF_CALL_DIRECTION"] = true;
        $arResult["WORKER_STAT"][$number]["total"] = 0;
        $arResult["WORKER_STAT"][$number]["total_in"] = 0;
        $arResult["WORKER_STAT"][$number]["total_out"] = 0;
        $arResult["WORKER_STAT"][$number]["answered"] = 0;
        $arResult["WORKER_STAT"][$number]["answered_in"] = 0;
        $arResult["WORKER_STAT"][$number]["answered_out"] = 0;
        $arResult["WORKER_STAT"][$number]["unanswered"] = 0;
        $arResult["WORKER_STAT"][$number]["unanswered_in"] = 0;
        $arResult["WORKER_STAT"][$number]["unanswered_out"] = 0;

        //Считаем число входящих отвеченных/неотвеченных
        $rsData = $entity_data_class::getList($get_list_params);
        while($el = $rsData->Fetch()) {
          if($el["UF_IS_ANSWERED"])
            $arResult["WORKER_STAT"][$number]["answered_in"]=$el["CNT"];
          else
            $arResult["WORKER_STAT"][$number]["unanswered_in"]=$el["CNT"];
        }

        //Считаем число исходящих отвеченных/неотвеченных
        $get_list_params["filter"]["UF_CALL_DIRECTION"] = false;
        $rsData = $entity_data_class::getList($get_list_params);
        while($el = $rsData->Fetch()) {
          if($el["UF_IS_ANSWERED"])
            $arResult["WORKER_STAT"][$number]["answered_out"]=$el["CNT"];
          else
            $arResult["WORKER_STAT"][$number]["unanswered_out"]=$el["CNT"];
        }

        //Считаем число всех звонков
        $arResult["WORKER_STAT"][$number]["total_in"]=$arResult["WORKER_STAT"][$number]["answered_in"]+$arResult["WORKER_STAT"][$number]["unanswered_in"];
        $arResult["WORKER_STAT"][$number]["total_out"]=$arResult["WORKER_STAT"][$number]["answered_out"]+$arResult["WORKER_STAT"][$number]["unanswered_out"];
        $arResult["WORKER_STAT"][$number]["answered"]=$arResult["WORKER_STAT"][$number]["answered_in"]+$arResult["WORKER_STAT"][$number]["answered_out"];
        $arResult["WORKER_STAT"][$number]["unanswered"]=$arResult["WORKER_STAT"][$number]["unanswered_in"]+$arResult["WORKER_STAT"][$number]["unanswered_out"];
        $arResult["WORKER_STAT"][$number]["total"]=$arResult["WORKER_STAT"][$number]["total_in"]+$arResult["WORKER_STAT"][$number]["total_out"];
      }
    }
  }
  public function executeComponent() {
    global $USER;
    if(!$USER->IsAuthorized()) return;
    $user_login = $USER->GetLogin();
    if($this->startResultCache(false,  array($user_login, $_GET)))
    {
      $entity_data_class = $this->GetEntityDataClass(CALLS_HL_BLOCK_ID);
      $arWorkerNumbers = $this->GetWorkers($this->arResult, $user_login);
      if($arWorkerNumbers) {
        $this->GetCalls($this->arResult, $arWorkerNumbers);
        if($_GET["workers_all"]!="Y" && count($_GET)!=0)
          $this->GetWorkerStat($this->arResult, $arWorkerNumbers);
        $this->GetMulticallNumbers($this->arResult, $arWorkerNumbers);
        $this->DataAnalysis($this->arResult);
      };
      $this->includeComponentTemplate();
    };
  }
}
?>
