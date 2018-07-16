<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)
	die();
header("Content-type: application/json");
if(!$arResult["ERRORS"])
	$arResult["ERRORS"]="N";
echo json_encode($arResult["ERRORS"]);
?>
