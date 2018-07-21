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
        "NAV_NAME" => array(
            "NAME" => "Имя параметра постраничной навигации",
            "TYPE" => "STRING",
            "DEFAULT" => "nav-calls",
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
