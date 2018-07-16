<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
$this->addExternalJs(SITE_TEMPLATE_PATH."/include/js/jquery-ui.min.js");
$this->addExternalJs(SITE_TEMPLATE_PATH."/include/js/calendar.js");
$this->addExternalJs(SITE_TEMPLATE_PATH."/include/js/workers_select.js");
?>
<div class="panel panel-flat full-panel">
  <h3>
    <span>Общая статистика</span>
    <div class="clarification-cont">
      <a href="" class="clarification">
        <span class="glyphicon glyphicon-question-sign"></span>
      </a>
      <div class="clarification-popup-cont">
        <div class="clarification-popup">
					<p>
	          Здесь вы можете посмотреть всю статистику по звонкам, многоканальным номерам и каждому сотруднику.
						</br>
						Запись в эту таблицу происходит по истечению времени, которое вы установили для перезвона в настройках профиля.
						</br>
						Запись удаляется если сотрудник всё же перезвонил данному клиенту.
	        </p>
        </div>
      </div>
    </div>
  </h3>
  <form class="table-filter">
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
  <?if(count($arResult["COMMON_STATISTIC"]["total"])>0):?>
  <table class="table datatable-basic">
    <thead>
      <tr>
        <th>Общая статистика</th>
        <th>Все</th>
        <th>Входящие</th>
        <th>Исходящие</th>
      </tr>
    </thead>
    <tbody class="common_analytic">
      <tr>
        <th>Всего</th>
        <th class="text-common"><?=$arResult["COMMON_STATISTIC"]["total"]?></th>
        <th class="text-common"><?=$arResult['COMMON_STATISTIC']["total_in"]?></th>
        <th class="text-common"><?=$arResult['COMMON_STATISTIC']["total_out"]?></th>
      </tr>
      <tr>
        <th>Отвечено</th>
        <th class="text-success"><?=$arResult["COMMON_STATISTIC"]["answered"]?></th>
        <th class="text-success"><?=$arResult["COMMON_STATISTIC"]["answered_in"]?></th>
        <th class="text-success"><?=$arResult["COMMON_STATISTIC"]["answered_out"]?></th>
      </tr>
      <tr>
        <th>Пропущено</th>
        <th class="text-warning"><?=$arResult["COMMON_STATISTIC"]["unanswered"]?></th>
        <th class="text-warning"><?=$arResult["COMMON_STATISTIC"]["unanswered_in"]?></th>
        <th class="text-warning"><?=$arResult["COMMON_STATISTIC"]["unanswered_out"]?></th>
      </tr>
    </tbody>
  </table>
</div>
<div class="clearfix">
<?foreach ($arResult["WORKER_STAT"] as $number => $arWorkerCounts):?>
  <div class="panel panel-flat full-panel worker-table col-lg-4">
    <table class="table datatable-basic">
      <thead>
        <tr>
          <th><?=$arResult["WORKERS"][$number]["WORKER_NAME"]?></th>
          <th>Все</th>
          <th>Входящие</th>
          <th>Исходящие</th>
        </tr>
      </thead>
      <tbody class="common_analytic">
        <tr>
          <th>Всего</th>
          <th class="text-common min"><?=$arWorkerCounts["total"]?></th>
          <th class="text-common min"><?=$arWorkerCounts["total_in"]?></th>
          <th class="text-common min"><?=$arWorkerCounts["total_out"]?></th>
        </tr>
        <tr>
          <th>Отвечено</th>
          <th class="text-success min"><?=$arWorkerCounts["answered"]?></th>
          <th class="text-success min"><?=$arWorkerCounts["answered_in"]?></th>
          <th class="text-success min"><?=$arWorkerCounts["answered_out"]?></th>
        </tr>
        <tr>
          <th>Пропущено</th>
          <th class="text-warning min"><?=$arWorkerCounts["unanswered"]?></th>
          <th class="text-warning min"><?=$arWorkerCounts["unanswered_in"]?></th>
          <th class="text-warning min"><?=$arWorkerCounts["unanswered_out"]?></th>
        </tr>
      </tbody>
    </table>
  </div>
<?endforeach;?>
</div>
<div style="clear: both;"></div>
<div class="panel panel-flat full-panel multicall-panel">
  <h3>Мультиканальные звонки</h3>
  <table class="table datatable-basic">
    <thead>
      <tr>
        <th>Мультиканальный номер</th>
        <th>Все</th>
        <th>Отвеченные</th>
        <th>Неотвеченные</th>
      </tr>
    </thead>
    <tbody class="common_analytic">
      <?foreach($arResult["MULTICALLS"] as $number => $arMultiall):?>
        <?if($number=="NO_MULTICALL") $number="Не мультиканальный";?>
        <tr>
          <th><?=$number?></th>
          <th class="text-common"><?=$arMultiall["TOTAL"]?></th>
          <th class="text-success"><?if($arMultiall["ANSWERED"]>0):?><?=$arMultiall["ANSWERED"]?><?else:?><span class="glyphicon glyphicon-remove" style="color: red; text-align: center;"></span><?endif?></th>
          <th class="text-warning"><?if($arMultiall["UNANSWERED"]>0):?><?=$arMultiall["UNANSWERED"]?><?else:?><span class="glyphicon glyphicon-remove" style="color: red; text-align: center;"></span><?endif?></th>
        </tr>
      <?endforeach;?>
    </tbody>
  </table>
</div>
<?else:?>
  <div class="no-data"><p>Нет данных</p></div>
<?endif;?>
