<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$arComponentParameters = array(
	"GROUPS" => array(),
	"PARAMETERS" => array(
        "ATS_HL_BLOCK_ID" => array(
            "NAME" => "ID highloadblock общей таблицы АТС",
            "TYPE" => "STRING",
        ),
        "BITRIX24_HL_BLOCK_ID" => array(
            "NAME" => "ID highloadblock таблицы подписок Bitrix24",
            "TYPE" => "STRING",
        ),
        "WORKERS_HL_BLOCK_ID" => array(
            "NAME" => "ID highloadblock таблицы сотрудников",
            "TYPE" => "STRING",
        ),
        "ATE_WORKERS_HL_BLOCK_ID" => array(
            "NAME" => "ID highloadblock таблицы сотрудников АТС",
            "TYPE" => "STRING",
        ),
        "ATE_WORKERS_HL_BLOCK_NAME" => array(
            "NAME" => "Имя таблицы сотрудников",
            "TYPE" => "STRING",
            "DEFAULT" => "workers",
        ),
        "BITRIX24_MULTICHANNEL_HL_BLOCK_ID" => array(
            "NAME" => "ID highloadblock таблицы многоканальных номеров",
            "TYPE" => "STRING",
        ),
        "BITRIX24_MULTICHANNEL_HL_BLOCK_NAME" => array(
            "NAME" => "имя таблицы многоканальных номеров",
            "TYPE" => "STRING",
            "DEFAULT" => "multichannels",
        ),
        "BITRIX24_WORKERS_HL_BLOCK_ID" => array(
            "NAME" => "ID highloadblock таблицы сотрудников Bitrix24",
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
