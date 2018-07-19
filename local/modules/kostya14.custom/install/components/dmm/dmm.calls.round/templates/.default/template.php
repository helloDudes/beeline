<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
$this->addExternalJs("https://www.gstatic.com/charts/loader.js");
$this->addExternalJs(SITE_TEMPLATE_PATH."/include/js/jquery-ui.min.js");
$this->addExternalJs(SITE_TEMPLATE_PATH."/include/js/calendar.js");
$this->addExternalJs(SITE_TEMPLATE_PATH."/include/js/workers_select.js");
?>
<script type="text/javascript">
  //Построение круговой диаграммы, суть в передаче $arResult["WORKERS"] в массив google.visualization.arrayToDataTable
  google.charts.load("current", {packages:["corechart"]});
  google.charts.setOnLoadCallback(drawChart);
  function drawChart() {
    var data = google.visualization.arrayToDataTable([
      ['Task', 'Hours per Day'],
      <?foreach ($arResult["WORKERS"] as $arWorker):?><?if($arWorker["CALLS_COUNT"]>0):?>['<?=$arWorker["WORKER_NAME"]?>', <?=$arWorker["CALLS_COUNT"]?>],<?endif;?><?endforeach;?>
    ]);

    var options = {
      title: '',
      pieHole: 0.4,
    };

    var chart = new google.visualization.PieChart(document.getElementById('donutchart'));
    chart.draw(data, options);
  }
</script>
	<div class="panel panel-flat full-panel">
    <h3>
      <span>Распределение звонков</span>
      <div class="clarification-cont">
        <a href="" class="clarification">
          <span class="glyphicon glyphicon-question-sign"></span>
        </a>
        <div class="clarification-popup-cont">
          <div class="clarification-popup">
            <p>
              Здесь вы можете посмотреть как распределяется нагрузка по звонкам на ваших сотрудников.
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
    <?if(count($arResult["WORKERS"])>0):?>
    <div id="donutchart" style="height: 500px"></div>
    <?else:?>
		<div class="no-data"><p>Нет данных</p></div>
		<?endif;?>
  </div>
