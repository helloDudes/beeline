<?
/**
 * Считает количество звонков у каждого сотрудника.
 *
 * @author Чижик Константин
 * @copyright 2018 ООО DMM
 */
\Bitrix\Main\Loader::includeModule('kostya14.custom');
use \Kostya14\Custom\DbInteraction;
use \Kostya14\Custom\Filter;
use \Bitrix\Main\Entity;

class classRoundScheduleB extends CBitrixComponent
{
  /**
  * Получает список сотрудников
  *
  * @param array $arResult
  * @param string $user_login
  * @return array $arWorkerNumbers массив номеров сотрудников
  */
  function GetWorkers(&$arResult, $user_login) {
    $entity_data_class = DbInteraction::GetEntityDataClass(WORKERS_HL_BLOCK_ID);
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
        $arResult["WORKERS"][$el["UF_PHONE_NUMBER"]]["CALLS_COUNT"] = 0;
        $arWorkerNumbers[]=$el["UF_PHONE_NUMBER"];
      };
    };
    if(count($arWorkerNumbers)==0)
      $arWorkerNumbers = 0;
    return $arWorkerNumbers;
  }
  /**
  * Считает все звонки для каждого сотрудника, применяет фильтры
  *
  * @param $arResult
  * @param $arWorkerNumbers массив номеров сотрудников
  */
  function GetCallsCount(&$arResult, $arWorkerNumbers) {
    $entity_data_class = DbInteraction::GetEntityDataClass(CALLS_HL_BLOCK_ID);

    //Проверка фильтров
    Filter::CheckWorkers($arWorkerNumbers, $_GET);
    $filter = array(
        array("LOGIC"=>"AND",
            array('UF_ABONENT_NUMBER'=>$arWorkerNumbers),
            array('!UF_ABONENT_NUMBER'=>false)
        )
    );
    Filter::CheckData($filter, $_GET["date_from"], $_GET["date_to"]);

    $rsData = $entity_data_class::getList(array(
       'select' => array('CNT', 'UF_ABONENT_NUMBER'),
       'filter' => $filter,
       'group' => array('UF_ABONENT_NUMBER'),
       'order' => array('CNT'=>'DESC'),
       'runtime' => array(new Entity\ExpressionField('CNT', 'COUNT(*)')),
    ));
    $i = 1;
    while($el = $rsData->Fetch()){
      if($i<=15) {
        $arResult["WORKERS"][$el["UF_ABONENT_NUMBER"]]["CALLS_COUNT"]=$el["CNT"];
      }
      elseif($i==16) {
        $arResult["WORKERS"]["MINOR"]["CALLS_COUNT"]=$el["CNT"];
        $arResult["WORKERS"]["MINOR"]["WORKER_NAME"]="Остальные";
      }
      else {
        $arResult["WORKERS"]["MINOR"]["CALLS_COUNT"]+=$el["CNT"];
      }
      $i++;
    };
  }
  public function executeComponent() {
    global $USER;
    if(!$USER->IsAuthorized()) return;
    $user_login = $USER->GetLogin();
    if($this->startResultCache(false, array($user_login, $_GET)))
    {
      $arWorkerNumbers = $this->GetWorkers($this->arResult, $user_login);
      if($arWorkerNumbers) {
        $this->GetCallsCount($this->arResult, $arWorkerNumbers);
      };
      $this->includeComponentTemplate();
    };
  }
}
?>
