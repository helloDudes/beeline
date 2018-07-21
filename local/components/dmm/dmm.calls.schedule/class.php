<?
/**
 * Стоит график суммарного количества звонков по временным промежуткам, применяет фильтры
 *
 * @author Чижик Константин
 * @copyright 2018 ООО DMM
 */

use \Kostya14\Custom\DbInteraction;
use \Kostya14\Custom\Filter;
use \Bitrix\Main\Entity;
use \Bitrix\Main\Context;

class classCallsScheduleB extends CBitrixComponent
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
    $arWorkerNumbers = array();
    $arResult["WORKERS"] = array();
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
  * Выбирает интервал: день, неделя, месяц
  *
  * @param string $interval ("week", "month", "year")
  * @return array $arDate массив данных, нужных для формирования интервала в запросе
  * ["COUNT"] число дней, ["DURABILITY"] длинна интервала в timestamp
  */
  function CheckInterval($interval) {
    $arDate = array();
    if($interval=="day") {
      $arDate["INTERVAL_STR"]="За 24 часа";
      $arDate["SQL_FORMAT"]='%d.%m %H';
      $arDate["DISPLAY_FORMAT"]="H";
      $arDate["START_TIME"]=time()-86400;
      $arDate["COUNT"]=24;
      $arDate["DURABILITY"]=3600;
      return $arDate;
    }
    if($interval=="week") {
      $arDate["INTERVAL_STR"]="За 7 дней";
      $arDate["SQL_FORMAT"]='%d.%m';
      $arDate["DISPLAY_FORMAT"]="d.m";
      $arDate["COUNT"]=7;
      $arDate["START_TIME"]=time()-(86400*$arDate["COUNT"]);
      $arDate["DURABILITY"]=86400;
      return $arDate;
    }
    if($interval=="month") {
      $arDate["INTERVAL_STR"]="За 30 дней";
      $arDate["SQL_FORMAT"]='%d.%m';
      $arDate["DISPLAY_FORMAT"]="d.m";
      $arDate["COUNT"]=31;
      $arDate["START_TIME"]=time()-(86400*$arDate["COUNT"]);
      $arDate["DURABILITY"]=86400;
      return $arDate;
    }
    $arDate["INTERVAL_STR"]="За 24 часа";
    $arDate["SQL_FORMAT"]='%d.%m %H';
    $arDate["DISPLAY_FORMAT"]="H";
    $arDate["START_TIME"]=time()-86400;
    $arDate["COUNT"]=24;
    $arDate["DURABILITY"]=3600;
    return $arDate;
  }
  /**
  * Добавляет к фильтру звонков его направление, если задан $direct
  *
  * @param string $direct содержит направление
  * @return string $str добавление к фильтру
  */
  function CheckDirection($direct) {
    $str = "";
    if($direct=="incoming") {
      $str=" AND UF_CALL_DIRECTION=true";
    };
    if($direct=="outgoing") {
      $str=" AND UF_CALL_DIRECTION=false";
    };
    return $str;
  }
  /**
  * Фильтрует только отвеченные или неотвеченные звонки
  *
  * @param string $is_answered отвечен/неотвечен
  * @return string $str добавление к фильтру
  */
  function CheckAnswer($is_answered) {
    $str = "";
    if($is_answered=="yes") {
      $str=" AND UF_IS_ANSWERED=true";
    };
    if($is_answered=="no") {
      $str=" AND UF_IS_ANSWERED=true";
    };
    return $str;
  }
  /**
  * Формирует массив в формате дата->количество звонков
  *
  * @param array $arResult
  * @param array $arWorkerNumbers массив телефонов сотрудников
  */
  function GetDate(&$arResult, $arWorkerNumbers) {
    $context = Context::getCurrent();
    $request = $context->getRequest();

    //Проверем интервал
    $arResult["PARAMS"] = $this->CheckInterval($request->getQuery("interval"));
    $arResult["PARAMS"]["FORMAT"]=str_replace("%", "", $arResult["PARAMS"]["SQL_FORMAT"]);
    $i=0;
    $time = $arResult["PARAMS"]["START_TIME"];

    //Создаём массив по датам с пустым COUNT параметром
    $arResult['DATE'] = array();
    while($i<=$arResult["PARAMS"]["COUNT"]) {
      $date = date($arResult["PARAMS"]["FORMAT"], $time);
      $arResult['DATE'][$date]["DISPLAY_DATE"]=date($arResult["PARAMS"]["DISPLAY_FORMAT"], $time);
      if($arResult["PARAMS"]["DISPLAY_FORMAT"]=="H")
        $arResult['DATE'][$date]["DISPLAY_DATE"].=":00";
      $arResult['DATE'][$date]["COUNT"]=0;
      $time = $time+$arResult["PARAMS"]["DURABILITY"];
      $i++;
    };

    //Проверяем остальные фильтры
    Filter::CheckWorkers($arWorkerNumbers, $request->getQueryList());
    $workers = "('".implode("', '", $arWorkerNumbers)."')";
    $direction = $this->CheckDirection($request->getQuery("direction"));
    $answered = $this->CheckAnswer($request->getQuery("is_answered"));

    //Запрашиваем
    $str = "SELECT COUNT(*) AS CNT, DATE_FORMAT(`UF_CALL_CREATE_DATE`, '"
        .$arResult["PARAMS"]["SQL_FORMAT"]
        ."') AS d FROM calls_list WHERE UF_ABONENT_NUMBER IN "
        .$workers.$direction.$answered." AND UF_ABONENT_NUMBER!=false AND UF_CALL_CREATE_DATE>'"
        .date("Y-m-d H:i:s", $arResult["PARAMS"]["START_TIME"])
        ."' GROUP BY d";
    $connection = Bitrix\Main\Application::getConnection();
    $res = $connection->query($str);
    while($el = $res->Fetch()) {
      $arResult['DATE'][$el["d"]]["COUNT"]=$el['CNT'];
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

    if(!$this->arParams["WORKERS_HL_BLOCK_ID"])
    {
        ShowError("Недостаточно параметров");
        return;
    }

    $this->arParams["WORKERS_HL_BLOCK_ID"] = intval($this->arParams["WORKERS_HL_BLOCK_ID"]);

    global $USER;
    if(!$USER->IsAuthorized()) return;
    $user_login = $USER->GetLogin();

    if(!$user_login) {
        throw New Exception("user not found");
    }

    if($this->startResultCache(false, array($user_login, $_GET)))
    {
      $arWorkerNumbers = $this->GetWorkers($this->arResult, $user_login);
      if($arWorkerNumbers) {
        $this->GetDate($this->arResult, $arWorkerNumbers);
      };
      $this->includeComponentTemplate();
    };
  }
}
?>
