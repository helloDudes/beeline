<?
/**
 * Created by PhpStorm
 * @author Чижик Константин
 * @copyright 2018 ООО DMM
 */

if (!check_bitrix_sessid())
    return;

if ($ex = $APPLICATION->GetException()) {
    echo CAdminMessage::ShowMessage(array(
        "TYPE" => "ERROR",
        "MESSAGE" => "Ошибка"),
        "DETAILS" => $ex->GetString(),
        "HTML" => true,
    ));
}
else {
    echo CAdminMessage::ShowNote("Установка завершена");
}

?>
<form action="<?echo $APPLICATION->GetCurPage(); ?>">
    <input type="hidden" name="lang" value="<?echo LANGUAGE_ID ?>">
    <input type="submit" name="" value="Вернуться в список модулей">
<form>