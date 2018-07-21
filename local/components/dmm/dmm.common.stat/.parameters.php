<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$arComponentParameters = array(
    "GROUPS" => array(),
    "PARAMETERS" => array(
        "WORKERS_HL_BLOCK_ID" => array(
            "NAME" => "ID highloadblock таблицы сотрудников",
            "TYPE" => "STRING",
        ),
        "CALLS_HL_BLOCK_ID" => array(
            "NAME" => "ID highloadblock таблицы звонков",
            "TYPE" => "STRING",
        ),
        "CACHE_TIME"  =>  array("DEFAULT"=>0),
    )
);
?>