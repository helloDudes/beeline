<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
$this->addExternalJs($this->GetFolder()."/js/player.js"); //Отвечает за отображение плеера
$this->addExternalJs(SITE_TEMPLATE_PATH."/include/js/jquery-ui.min.js");
$this->addExternalJs(SITE_TEMPLATE_PATH."/include/js/calendar.js");
$this->addExternalJs(SITE_TEMPLATE_PATH."/include/js/workers_select.js");
?>
<div class="panel panel-flat full-panel">
  <a class="excel-download" href="/calls_analytic/include/excel_creator.php"><span class="glyphicon glyphicon-file"></span> Скачать в xls за сегодня</a>
  <h3>
    <span>Последние звонки</span>
    <div class="clarification-cont">
      <a href="" class="clarification">
        <span class="glyphicon glyphicon-question-sign"></span>
      </a>
      <div class="clarification-popup-cont">
        <div class="clarification-popup">
          <p>
            Здесь вы можете посмотреть весь список звонков вашей АТС
          </p>
        </div>
      </div>
    </div>
  </h3>
  <form class="table-filter">
    <div>
      <p>Направление</p>
      <select name="direction">
          <option value="all"<?if($_GET["direction"]=="all"):?> selected<?endif;?>>Все</option>
          <option value="incoming"<?if($_GET["direction"]=="incoming"):?> selected<?endif;?>>Входящие</option>
          <option value="outgoing"<?if($_GET["direction"]=="outgoing"):?> selected<?endif;?>>Исходящие</option>
      </select>
    </div>
    <div>
      <p>Отвечен/неотвечен</p>
      <select name="is_answered">
          <option value="all"<?if($_GET["is_answered"]=="all"):?> selected<?endif;?>>Все</option>
          <option value="yes"<?if($_GET["is_answered"]=="yes"):?> selected<?endif;?>>Отвеченные</option>
          <option value="no"<?if($_GET["is_answered"]=="no"):?> selected<?endif;?>>Неотвеченные</option>
      </select>
    </div>
    <div>
      <p>Номер клиента</p>
      <input class="client-number" type="text" name="client_number" value="<?=$_GET["client_number"]?>">
    </div>
    <div class="workers">
      <p>Сотрудники</p>
      <a class="workers-select">Выбрать <span class="glyphicon glyphicon-chevron-down"></span></a>
      <div class="checkbox-cont worker-popup">
        <?foreach ($arResult["WORKERS"] as $number => $arWorker):?>
          <p><label><input type="checkbox" name="<?=$number?>" value="Y"<?if($_GET[$number]=="Y"):?> checked<?endif;?>><span><?=$arWorker["WORKER_NAME"]?></span></label><p>
        <?endforeach;?>
      </div>
      <input type="hidden" name="workers_all" id="workers_all" value="<?if($_GET["workers_all"]=="Y" || empty($_GET)):?>Y<?else:?>N<?endif;?>">
      <input class="worker-searcher" type="text">
    </div>
    <div class="calendar-title">
      <p>
        <a class="calendar-button">
          <span>Дата от <span class="current-date"><?if($_GET["date_from"]):?><?=$_GET["date_from"]?><?else:?><?=date("d.m.Y", time()-(86400*30))?><?endif;?></span></span>
          <span class="glyphicon glyphicon-calendar"></span>
          <span class="calendar-toggle-icon glyphicon glyphicon-chevron-down"></span>
        </a>
        <a class="calendar-accept"><span> ОТМЕНА</span></a>
      </p>
      <div class="calendar-cont calendar-popup"><div id="calendar_from"></div></div>
      <input type="hidden" value="<?=$_GET["date_from"]?>" name="date_from" id="date_from">
    </div>
    <div class="calendar-title">
      <p>
        <a class="calendar-button">
          <span>Дата до <span class="current-date"><?if($_GET["date_to"]):?><?=$_GET["date_to"]?><?else:?><?=date("d.m.Y")?><?endif;?></span></span>
          <span class="glyphicon glyphicon-calendar"></span>
          <span class="calendar-toggle-icon glyphicon glyphicon-chevron-down"></span>
        </a>
        <a class="calendar-accept"><span> ОТМЕНА</span></a>
      </p>
      <div class="calendar-cont calendar-popup"><div id="calendar_to"></div></div>
      <input type="hidden" value="<?=$_GET["date_to"]?>" name="date_to" id="date_to">
    </div>
    </br>
    <input type="submit" value="Применить" class="btn btn-info">
  </form>
  <?if(count($arResult["CALLS"])>0):?>
  <table class="table datatable-basic">
    <?$APPLICATION->IncludeComponent(
       "bitrix:main.pagenavigation",
       "call_analytic_pagenav",
       array(
          "NAV_OBJECT" => $arResult["NAV"],
       ),
       false
    );?>
    <thead>
      <tr>
        <th>Входящий/ Исходящий</th>
        <th>Номер клиента</th>
        <th>Сотрудник</th>
        <th>Номер сотрудника</th>
        <th>Многоканальный номер</th>
				<th>Ответ</th>
				<th>Дата звонка</th>
				<th>Запись</th>
        <th>Длительность</th>
      </tr>
    </thead>
    <tbody>
     <?foreach($arResult["CALLS"] as $arCall):?>
     <tr>
       <td><span class="label <?if($arCall["DIRECTION"]>0):?>label-info">Входящий</span><?else:?>label-default">Исходящий</span><?endif?></td>
       <td><?=$arCall["PHONE_NUMBER"]?></td>
       <td><?=$arResult["WORKERS"][$arCall["WORKER_PHONE_NUMBER"]]["WORKER_NAME"]?></td>
			 <td><?=$arCall["WORKER_PHONE_NUMBER"]?></td>
       <td><?=$arCall["MULTICALL_NUMBER"]?></td>
       <td><span class="label <?if($arCall["SUCCESS"]>0):?>label-success">Принят</span><?else:?>label-danger">Не принят</span><?endif?></td>
			 <td><?=$arCall["CALL_CREATE_DATE"]?></td>
			 <td class="success_icon">
         <?if($arCall["SUCCESS"] && !empty($arCall["DOWNLOAD_LINK"])):?>
           <div class="recording-cont">
             <a><span class="glyphicon glyphicon-phone-alt"></span></a>
             <div class="player-block-cont">
               <div class="player-block">
                 <audio class="player" src="<?=$arCall["DOWNLOAD_LINK"]?>"></audio>
               </div>
             </div>
           </div>
         <?else:?>
            <span class="glyphicon glyphicon-remove" style="color: red;"></span>
         <?endif;?>
       </td>
       <td class="duration"><?=$arCall["DURATION"]?></td>
     </tr>
     <?endforeach;?>
   </tbody>
  </table>
  <?$APPLICATION->IncludeComponent(
     "bitrix:main.pagenavigation",
     "call_analytic_pagenav",
     array(
        "NAV_OBJECT" => $arResult["NAV"],
        "SHOW_ALL" => "N"
     ),
     false
  );?>
  <?else:?>
  <div class="no-data"><p>Нет данных</p></div>
  <?endif;?>
</div>
