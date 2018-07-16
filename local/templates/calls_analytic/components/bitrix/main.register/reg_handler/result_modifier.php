<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)
	die();
foreach ($arResult["ERRORS"] as $key => $value) {
	if($key=="LOGIN")
		$arResult["ERRORS"][$key] = str_replace("#FIELD_NAME#", "номера", $arResult["ERRORS"][$key]);
	if($key=="PASSWORD")
		$arResult["ERRORS"][$key] = str_replace("#FIELD_NAME#", "пароля", $arResult["ERRORS"][$key]);
	if($key=="CONFIRM_PASSWORD")
		$arResult["ERRORS"][$key] = str_replace("#FIELD_NAME#", "проверки пароля", $arResult["ERRORS"][$key]);
}
?>
