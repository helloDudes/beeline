<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$arComponentParameters = array(
    "GROUPS" => array(),
    "PARAMETERS" => array(
        "WORKERS_HL_BLOCK_ID" => array(
            "NAME" => "ID highloadblock таблицы сотрудников",
            "TYPE" => "STRING",
        ),
        "UNANSWERED_CALLS_HL_BLOCK_ID" => array(
            "NAME" => "ID highloadblock таблицы неотвеченных звонков",
            "TYPE" => "STRING",
        ),
        "PAGE_SIZE" => array(
            "NAME" => "Элементов на странице",
            "TYPE" => "STRING",
            "DEFAULT" => "10",
        ),
        "CACHE_TIME"  =>  array("DEFAULT"=>0),
    )
);
?>