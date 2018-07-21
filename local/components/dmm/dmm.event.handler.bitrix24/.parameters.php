<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$arComponentParameters = array(
	"GROUPS" => array(),
	"PARAMETERS" => array(
        "BITRIX24_HL_BLOCK_ID" => array(
            "NAME" => "ID highloadblock таблицы подписок Bitrix24",
            "TYPE" => "STRING",
        ),
        "ATE_WORKERS_HL_BLOCK_ID" => array(
            "NAME" => "ID highloadblock таблицы сотрудников АТС",
            "TYPE" => "STRING",
        ),
        "BITRIX24_WORKERS_HL_BLOCK_ID" => array(
            "NAME" => "ID highloadblock таблицы сотрудников Bitrix24",
            "TYPE" => "STRING",
        ),
        "BITRIX24_EVENTS_HL_BLOCK_ID" => array(
            "NAME" => "ID highloadblock таблицы событий Bitrix24",
            "TYPE" => "STRING",
        ),
        "BITRIX24_LEADS_HL_BLOCK_NAME" => array(
            "NAME" => "Имя highloadblock таблицы событий Bitrix24",
            "TYPE" => "STRING",
        ),
        "REDIRECT_URI" => array(
            "NAME" => "Страница обработчика авторизации Bitrix24",
            "TYPE" => "STRING",
            "DEFAULT" => "https://beelinestore.ru/calls_analytic/integration/bitrix24.php",
        ),
	)
);
?>
