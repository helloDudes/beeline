<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)
	die();
if($_GET["INWORKERLIST"]=="Y")
  $arResult["NUMBER_ERROR_MESS"]=GetMessage("INWORKERLIST_MESS");
if($_GET["INCORRECT"]=="Y")
  $arResult["NUMBER_ERROR_MESS"]=GetMessage("INCORRECT_MESS");

foreach ($arResult["ERRORS"] as $key => $value) {
	if($key=="LOGIN")
		$arResult["ERRORS"][$key] = str_replace("#FIELD_NAME#", "номера", $arResult["ERRORS"][$key]);
	if($key=="PASSWORD")
		$arResult["ERRORS"][$key] = str_replace("#FIELD_NAME#", "пароля", $arResult["ERRORS"][$key]);
	if($key=="CONFIRM_PASSWORD")
		$arResult["ERRORS"][$key] = str_replace("#FIELD_NAME#", "проверки пароля", $arResult["ERRORS"][$key]);
	$arResult["ERRORS"][$key] = str_replace("<br>", "", $arResult["ERRORS"][$key]);
}
?>
