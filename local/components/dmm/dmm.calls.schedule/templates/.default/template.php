<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
$this->addExternalJs("https://www.gstatic.com/charts/loader.js");
$this->addExternalJs(SITE_TEMPLATE_PATH."/include/js/workers_select.js");
?>
<script type="text/javascript">
    //Построение графика, передаём $arResult["DATE"] в массив data.addRows
    google.charts.load('current', {'packages':['line']});
    google.charts.setOnLoadCallback(drawChart);

  function drawChart() {

    var data = new google.visualization.DataTable();
    data.addColumn('string');
    data.addColumn('number');

    data.addRows([
      <?foreach ($arResult["DATE"] as $callsCount):?>['<?=$callsCount["DISPLAY_DATE"]?>',  <?=$callsCount["COUNT"]?>],<?endforeach;?>
    ]);

    var options = {
      chart: {
        title: '<?=$arResult["PARAMS"]["INTERVAL_STR"]?>',
        subtitle: ''
      },
      width: '',
      height: 400
    };

    var chart = new google.charts.Line(document.getElementById('curve_chart'));

    chart.draw(data, google.charts.Line.convertOptions(options));
  }
</script>
<div class="panel panel-flat full-panel">
  <?
  $empty=true;
  foreach ($arResult["DATE"] as $value) {
    if($value>0)
      $empty=false;
  };
  ?>
  <h3>
    <span>Количество звонков по датам</span>
    <div class="clarification-cont">
      <a href="" class="clarification">
        <span class="glyphicon glyphicon-question-sign"></span>
      </a>
      <div class="clarification-popup-cont">
        <div class="clarification-popup">
          <p>
              Здесь вы можете посмотреть, как менялась нагрузка по звонкам в вашей АТС по графику в формате (количество звонков/время).
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
        <input type="hidden" name="workers_all" id="workers_all" value="<?if($_GET["workers_all"]=="Y" || empty($_GET)):?>Y<?else:?>N<?endif;?>">
      </div>
      <input class="worker-searcher" type="text">
    </div>
    <div>
      <p>Интервал</p>
      <select name="interval">
          <option value="day"<?if($_GET["interval"]=="day" || !$_GET["interval"]):?> selected<?endif;?>>24 часа</option>
          <option value="week"<?if($_GET["interval"]=="week"):?> selected<?endif;?>>7 дней</option>
          <option value="month"<?if($_GET["interval"]=="month"):?> selected<?endif;?>>30 дней</option>
      </select>
    </div>
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
    </br>
    <input type="submit" value="Применить" class="btn btn-info">
  </form>
  <?if($arResult['DATE']):?>
  <div id="curve_chart"></div>
  <?else:?>
  <div class="no-data"><p>Нет данных</p></div>
  <?endif;?>
</div>
