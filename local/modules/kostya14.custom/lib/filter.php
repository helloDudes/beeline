<?
/**
 * Created by PhpStorm
 * @author Чижик Константин
 * @copyright 2018 ООО DMM
 */

namespace Kostya14\Custom;

class Filter
{
    public static function CheckData(&$filter, $date_from, $date_to) {
        if(!empty($date_from))
            $filter[">=UF_CALL_CREATE_DATE"]=$date_from." 00:00:00";
        if(!empty($date_to))
            $filter["<=UF_CALL_CREATE_DATE"]=$date_to." 23:59:59";
    }
    public static function CheckWorkers(&$arWorkerNumbers, $arForm, $page_name = "") {
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


}
?>