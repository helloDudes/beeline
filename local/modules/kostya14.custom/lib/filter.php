<?
/**
 * Хранит часто используемые в компонентах функции фильтра
 *
 * Created by PhpStorm
 * @author Чижик Константин
 * @copyright 2018 ООО DMM
 */

namespace Kostya14\Custom;

class Filter
{
    /**
     * Дополняет фильтр для GetList интервалом даты
     *
     * @param array $filter для GetList
     * @param string $date_from
     * @param string $date_to
     */
    public static function CheckData(&$filter, $date_from, $date_to) {
        if(!empty($date_from))
            $filter[">=UF_CALL_CREATE_DATE"]=$date_from." 00:00:00";
        if(!empty($date_to))
            $filter["<=UF_CALL_CREATE_DATE"]=$date_to." 23:59:59";
    }
    /**
     * Отбирает из всех сотрудников только выбранных в фильтре
     *
     * @param array $arWorkerNumbers массив телефонов сотрудников
     * @param array $arForm массив всех параметров фильтра
     * @param string $page_name имя параметра, который не следует учитывать
     */
    public static function CheckWorkers(&$arWorkerNumbers, $arForm, $page_name = "") {
        //Удаляем неучитываемый параметр
        unset($arForm[$page_name]);

        //Если не выбранны все сотрудники и задан список выбранных сотрудников перебираем их массив
        if($arForm["workers_all"]!="Y" && count($arForm)!=0) {
            $true_numbers = array();
            foreach ($arForm as $number => $value) {

                //Если выбранный сотрудник присутствует в списке сотрудников а не появился откуда то ещё,
                //то сохраняем его в массив выбранных сотрудников
                if(in_array($number, $arWorkerNumbers) && $value=="Y") {
                    $true_numbers[]=strval($number);
                }
            }

            //Обновляем массив сотрудников
            $arWorkerNumbers = $true_numbers;
        }
    }


}
?>