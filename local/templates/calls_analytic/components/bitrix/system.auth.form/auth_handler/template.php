<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)
	die();
header("Content-type: application/json");
if(!$arResult["ERROR_MESSAGE"]["MESSAGE"])
  $arResult["ERROR_MESSAGE"]["MESSAGE"]="N";
echo json_encode($arResult["ERROR_MESSAGE"]["MESSAGE"]);
?>
