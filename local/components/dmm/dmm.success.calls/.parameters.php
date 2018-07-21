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
        "MAX_WORKERS" => array(
            "NAME" => "Максимум сотрудников",
            "TYPE" => "STRING",
            "DEFAULT" => "15",
        ),
        "CACHE_TIME"  =>  array("DEFAULT"=>0),
    )
);
?>
