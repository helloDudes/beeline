<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
$this->addExternalJs(SITE_TEMPLATE_PATH."/include/js/jquery-ui.min.js");
$this->addExternalJs(SITE_TEMPLATE_PATH."/include/js/calendar.js");
$this->addExternalJs(SITE_TEMPLATE_PATH."/include/js/workers_select.js");
?>


<div class="panel panel-flat full-panel">
	<h3>
    <span>Последние упущенные клиенты</span>
    <div class="clarification-cont">
      <a href="" class="clarification">
        <span class="glyphicon glyphicon-question-sign"></span>
      </a>
      <div class="clarification-popup-cont">
        <div class="clarification-popup">
					<p>
	          Здесь вы можете посмотреть необработанные заявки и пропущенные звонки, по которым ваши сотрудники не перезвонили в течении установленного вами в настройках времени (время перезвона).
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
		<div>
      <p>Тип</p>
      <select name="type">
          <option value="all"<?if($_GET["type"]=="all"):?> selected<?endif;?>>Все</option>
          <option value="requests"<?if($_GET["type"]=="requests"):?> selected<?endif;?>>Заявки</option>
          <option value="calls"<?if($_GET["type"]=="calls"):?> selected<?endif;?>>Звонки</option>
      </select>
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
	<?if(count($arResult["WORKERS"])>0):?>
  <table class="table datatable-basic">
		<thead>
      <tr>
				<th>Тип</th>
        <th>Номер клиента</th>
				<?if($_GET["type"]!="requests"):?>
					<th>Сотрудник</th>
				<?endif;?>
        <th>Дата</th>
				<?if($_GET["type"]!="calls"):?>
					<th>Имя клиента</th>
					<th>Сервер отправки</th>
				<?endif;?>
      </tr>
    </thead>
    <tbody>
     <?foreach($arResult["CALLS"] as $arCall):?>
     <tr class="<?if($arCall["UF_IS_ANSWERED"]):?>success_calls<?else:?>unsuccess_calls<?endif;?>">
			 <td><?if($arCall["UF_ABONENT_NUMBER"]):?>Звонок<?else:?>Заявка<?endif;?></td>
       <td><?=$arCall["UF_PHONE_NUMBER"]?></td>
			 <?if($_GET["type"]!="requests"):?>
				 <td>
						<?if($arCall["UF_ABONENT_NUMBER"]):?>
							<?=$arResult["WORKERS"][$arCall["UF_ABONENT_NUMBER"]]["WORKER_NAME"]?>
						<?else:?>
							-
						<?endif;?>
				 </td>
			 <?endif;?>
			<td><?=$arCall["UF_CALL_CREATE_DATE"]?></td>
			<?if($_GET["type"]!="calls"):?>
				<th><?=$arCall["UF_NAME"]?></th>
				<th><?=$arCall["UF_SERVER_NAME"]?></th>
			<?endif;?>
		</tr>
     <?endforeach;?>
   </tbody>
  </table>
	<?$APPLICATION->IncludeComponent(
		 "bitrix:main.pagenavigation",
		 "call_analytic_pagenav",
		 array(
				"NAV_OBJECT" => $arResult["NAV"],
		 ),
		 false
	);?>
</div>
<div class="panel panel-flat full-panel">
	<h3>Неотвеченных заявок: <span class="unansw-count"><?=$arResult["REQUEST_COUNT"]?></span></h3>
	</br>
	</br>
	<h3>Неотвеченных звонков: <span class="unansw-count"><?=$arResult["CALLS_COUNT"]?></span></h3>
	</br>
	</br>
	<table class="table datatable-basic">
		<h3>Неотвеченных звонков по сотрудникам</h3>
		<thead>
			<tr>
				<th>Сотрудник</th>
				<th>Звонков без ответа</th>
			</tr>
		</thead>
		<tbody>
		<?foreach ($arResult["WORKERS"] as $number => $value):?>
			<?if($value["SELECTED"]):?>
				<tr>
					<th><?=$value["WORKER_NAME"]?></th>
					<th class="unansw-count"><?=$value["UNANSW_COUNT"]?></th>
				</tr>
			<?endif;?>
		<?endforeach;?>
		</tbody>
	</table>
	<?else:?>
	<table class="table datatable-basic">
    <h3>Последние неотвеченные звонки</h3>
	</table>
	<div class="no-data"><p>Нет данных</p></div>
	<?endif;?>
</div>
