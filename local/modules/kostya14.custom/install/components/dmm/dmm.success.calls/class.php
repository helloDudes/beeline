<?
/**
 * Суммирует пропущенные/отвеченные звонки для каждого сотрудника
 *
 * @author Чижик Константин
 * @copyright 2018 ООО DMM
 */
\Bitrix\Main\Loader::includeModule('kostya14.custom');
use \Kostya14\Custom\DbInteraction;
use \Kostya14\Custom\Filter;
use \Bitrix\Main\Entity;

class classSuccessCallsB extends CBitrixComponent
{
  const MAX_WORKERS = 15;
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
    $arResult["WORKERS_SELECT"] = array();
    $arWorkerNumbers = array();
    while($el = $rsData->Fetch()){
      if(!empty($el["UF_PHONE_NUMBER"])) {
        if(!empty($el["UF_LAST_NAME"]))
          $el["UF_LAST_NAME"] .= " ";
        $arResult["WORKERS"][$el["UF_PHONE_NUMBER"]]["WORKER_NAME"] = $el["UF_LAST_NAME"].$el["UF_FIRST_NAME"];
        $arResult["WORKERS"][$el["UF_PHONE_NUMBER"]]["SUCCESS"] = 0;
        $arResult["WORKERS"][$el["UF_PHONE_NUMBER"]]["UNSUCCESS"] = 0;
        $arResult["WORKERS_SELECT"][$el["UF_PHONE_NUMBER"]]["WORKER_NAME"] = $el["UF_LAST_NAME"].$el["UF_FIRST_NAME"];
        $arWorkerNumbers[]=$el["UF_PHONE_NUMBER"];
      };
    };
    if(count($arWorkerNumbers)==0)
      $arWorkerNumbers = 0;
    return $arWorkerNumbers;
  }
  /**
  * Получает сумму отвеченных/неотвеченных звонков
  *
  * @param array $arResult
  * @param array $arWorkerNumbers массив телефонов сотрудников
  * @param array $arWorkerCounts массив сумарного числа звонков сотрудников
  */
  function GetCalls(&$arResult, $arWorkerNumbers, &$arWorkerCounts) {

    //Проверяем фильтры
    Filter::CheckWorkers($arWorkerNumbers, $_GET);
    $filter = array(
      array("LOGIC"=>"AND", array('UF_ABONENT_NUMBER'=>$arWorkerNumbers), array('!UF_ABONENT_NUMBER'=>false)),
      'UF_CALL_DIRECTION'=>true,
    );
    Filter::CheckData($filter, $_GET["date_from"], $_GET["date_to"]);

    //Формеруем параметры запроса
    $entity_data_class = DbInteraction::GetEntityDataClass(CALLS_HL_BLOCK_ID);
    $get_list_params = array(
       'select' => array('CNT', 'UF_ABONENT_NUMBER'),
       'filter' => $filter,
       'group' => array('UF_ABONENT_NUMBER'),
       'order' => array('CNT'=>'DESC'),
       'runtime' => array(new Entity\ExpressionField('CNT', 'COUNT(*)')),
    );

    //Получаем число отвеченых входящих
    $get_list_params["filter"]["UF_IS_ANSWERED"]=true;
    $rsData = $entity_data_class::getList($get_list_params);
    while($el = $rsData->Fetch()){
      $arResult["WORKERS"][$el["UF_ABONENT_NUMBER"]]["SUCCESS"]=$el["CNT"];
      $arResult["WORKERS"][$el["UF_ABONENT_NUMBER"]]["SELECTED"]=true;
      $arWorkerCounts[$el["UF_ABONENT_NUMBER"]]+=$el["CNT"];
    };

    //Получаем число неотвеченых входящих
    $get_list_params["filter"]["UF_IS_ANSWERED"]=false;
    $rsData = $entity_data_class::getList($get_list_params);
    while($el = $rsData->Fetch()){
      $arResult["WORKERS"][$el["UF_ABONENT_NUMBER"]]["UNSUCCESS"]=$el["CNT"];
      $arResult["WORKERS"][$el["UF_ABONENT_NUMBER"]]["SELECTED"]=true;
      $arWorkerCounts[$el["UF_ABONENT_NUMBER"]]+=$el["CNT"];
    };
  }
  /**
  * Оставляет MAX_WORKERS сотрудников с самым большим количеством звонков
  *
  * @param array $arResult
  * @param array $arWorkerCounts массив сумарного числа звонков сотрудников
  */
  function workersSynthesis(&$arResult, $arWorkerCounts) {
      arsort($arWorkerCounts);
      $arWorkerCounts = array_slice($arWorkerCounts, 0, self::MAX_WORKERS, true);
      foreach ($arResult["WORKERS"] as $key => $value) {
          if (!$arWorkerCounts[$key]) {
              unset($arResult["WORKERS"][$key]);
          }
      }
  }
  public function executeComponent() {
    global $USER;
    if(!$USER->IsAuthorized()) return;
    $user_login = $USER->GetLogin();
    if($this->startResultCache(false, array($user_login, $_GET)))
    {
      $arWorkerNumbers = $this->GetWorkers($this->arResult, $user_login);
      if($arWorkerNumbers) {
        $arWorkerCounts = array();
        $this->GetCalls($this->arResult, $arWorkerNumbers, $arWorkerCounts);
        if($_GET["all"]=="Y" || count($_GET)==0)
          $this->workersSynthesis($this->arResult, $arWorkerCounts);
      }
	  $this->includeComponentTemplate();
    };
  }
}
?>
