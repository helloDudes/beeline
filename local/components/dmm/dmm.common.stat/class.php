<?
/**
 * Считает суммарное количество звонков в виде:
 *            Все   Входящие  Исходящие
 * Всего        *         *          *
 * Отвеченно    *         *          *
 * Пропущенно   *         *          *
 *
 * @author Чижик Константин
 * @copyright 2018 ООО DMM
 */

use \Kostya14\Custom\DbInteraction;
use \Kostya14\Custom\Filter;
use \Bitrix\Main\Entity;
use \Bitrix\Main\Context;

class classCommonStatisticB extends CBitrixComponent
{
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
    $entity_data_class = DbInteraction::GetEntityDataClass($this->arParams["CALLS_HL_BLOCK_ID"]);

    $context = Context::getCurrent();
    $request = $context->getRequest();

    //Используем фильтры
    Filter::CheckWorkers($arWorkerNumbers, $request->getQueryList());
    $filter = array(
        array(
            "LOGIC"=>"AND",
            array('UF_ABONENT_NUMBER'=>$arWorkerNumbers),
            array('!UF_ABONENT_NUMBER'=>false)
        )
    );

    Filter::CheckData($filter, $request->getQuery("date_from"), $request->getQuery("date_to"));

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
    $entity_data_class = DbInteraction::GetEntityDataClass($this->arParams["CALLS_HL_BLOCK_ID"]);

    $context = Context::getCurrent();
    $request = $context->getRequest();

    //Проверяем фильтры
    Filter::CheckWorkers($arWorkerNumbers, $request->getQueryList());
    $filter = array(
        array(
            "LOGIC"=>"AND",
            array('UF_ABONENT_NUMBER'=>$arWorkerNumbers),
            array('!UF_ABONENT_NUMBER'=>false)
        )
    );
    Filter::CheckData($filter, $request->getQuery("date_from"), $request->getQuery("date_to"));

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

    $arResult["MULTICALLS"]["NO_MULTICALL"]["ANSWERED"]=0;
    while($el = $rsData->Fetch()){
      if(empty($el["UF_MULTICALL_NUMBER"]))
        $arResult["MULTICALLS"]["NO_MULTICALL"]["ANSWERED"]=$el["CNT"];
      else
        $arResult["MULTICALLS"][$el["UF_MULTICALL_NUMBER"]]["ANSWERED"]=$el["CNT"];
    };

    //Считаем число неотвеченных
    $get_list_params["filter"]["UF_IS_ANSWERED"]=false;
    $rsData = $entity_data_class::getList($get_list_params);

    $arResult["MULTICALLS"]["NO_MULTICALL"]["UNANSWERED"]=0;
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
* @throws Exception юзер не найден
* @throws \Bitrix\Main\LoaderException Модуль kostya14.custom не установлен
*/
  public function executeComponent() {
    if (!\Bitrix\Main\Loader::includeModule('kostya14.custom')) {
      throw new \Bitrix\Main\LoaderException("Модуль kostya14.custom не установлен");
    }

    if(!$this->arParams["WORKERS_HL_BLOCK_ID"] || !$this->arParams["CALLS_HL_BLOCK_ID"])
    {
        ShowError("Недостаточно параметров");
        return;
    }

    $this->arParams["WORKERS_HL_BLOCK_ID"] = intval($this->arParams["WORKERS_HL_BLOCK_ID"]);
    $this->arParams["CALLS_HL_BLOCK_ID"] = intval($this->arParams["CALLS_HL_BLOCK_ID"]);

    global $USER;
    if(!$USER->IsAuthorized()) return;
    $user_login = $USER->GetLogin();

    if(!$user_login) {
      throw New Exception("user not found");
    }

    if($this->startResultCache(false,  array($user_login, $_GET)))
    {
      $arWorkerNumbers = $this->GetWorkers($this->arResult, $user_login);
      if($arWorkerNumbers) {
        $this->GetCalls($this->arResult, $arWorkerNumbers);
        $this->GetMulticallNumbers($this->arResult, $arWorkerNumbers);
        $this->DataAnalysis($this->arResult);
      };
      $this->includeComponentTemplate();
    };
  }
}
?>
