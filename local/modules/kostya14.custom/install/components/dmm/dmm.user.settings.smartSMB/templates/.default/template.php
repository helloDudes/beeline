<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
?>
<div class="panel panel-flat full-panel token-panel">
  <h3>SmartSMB</h3>
  <?if($arResult["COUNT_NEW_WORKERS"]>0):?>
    <p>Успешно добавлено <?=$arResult["COUNT_NEW_WORKERS"]?> сотрудников</p>
  <?endif;?>
  <?if($arResult["MESS"]):?>
    <br>
    <?=$arResult["MESS"]?>
    <br>
  <?endif;?>
  <form method="post">
    <div>
      <p class="label label-info">Новый токен</p>
      <input autocomplete="off" spellcheck="false" type="text" name="token" placeholder="<?if(!$arResult["TOKEN_EXIST"]):?>Для начала работы отправьте токен<?else:?>Введите новый или оставьте пустым<?endif;?>">
      <div class="clarification-cont">
        <a href="" class="clarification">
          <span class="glyphicon glyphicon-question-sign"></span>
        </a>
        <div class="clarification-popup-cont">
          <div class="clarification-popup">
            <p>
              Если не хотите менять токен, оставьте поле пустым. Найти или сгенерировать свой токен вы сможете на сайте Облачной ATC Билайн в разделе Настройки>API.
            </p>
          </div>
        </div>
      </div>
    </div>
    <div class="chime-time">
      <p class="label label-info">Время перезвона</p>
      <input type="number" name="chime_time" value="<?if($arResult["USER_CHIME_TIME"]):?><?=$arResult["USER_CHIME_TIME"]?><?else:?>20<?endif;?>" min="1">
      <span class="minutes">мин</span>
      <div class="clarification-cont">
        <a href="" class="clarification">
          <span class="glyphicon glyphicon-question-sign"></span>
        </a>
        <div class="clarification-popup-cont">
          <div class="clarification-popup">
            <p>
              В случае, если ваш сотрудник пропустит звонок или оставит неотвеченной заявку и не перезвонит в течении заданного времени перезвона,
то этот звонок или заявка будут занесены в таблицу упущенных клиентов. И как только кто либо перезвонит этому клиенту, он от туда удалится.
            </p>
          </div>
        </div>
      </div>
    </div>
	</br>
	<div>
    <?if(!empty($arResult["USER_KEY"])):?>
      <p class="label label-default user-key">Ключ АТС: <span><?=$arResult["USER_KEY"]?></span></p>
      </br>
      </br>
      <p class="api-key-instruction label label-default user-key" style="background: none">
        <a href="https://beelinestore.ru/calls_analytic/instructions/request_api.php">Инструкция по интеграции заявок</a>
      </p>
    <?endif;?>
	</div>
    </br>
    <input type="submit" class="btn btn-danger" value="Сохранить">
  </form>
</div>
<?if($arResult["TOKEN_EXIST"]!==false):?>
  <?if(!empty($arResult["MULTICALLS"])):?>
    <div class="panel panel-flat full-panel multicall-numbers-panel">
        <h3>Мультиканальные номера</h3>
        </br>
        <?foreach ($arResult["MULTICALLS"] as $arNumber): ?>
          <span class="label label-warning"><?=$arNumber["phone"]?> </span>
        <?endforeach;?>
    </div>
  <?endif;?>
  <div class="panel panel-flat full-panel">
    <?if(!empty($arResult["WORKERS"])):?>
    <table class="table datatable-basic">
      <h5>Сотрудники</h5>
      <thead>
        <tr>
          <th>Сотрудник</th>
          <th>Номер телефона</th>
          <th>Короткий номер</th>
        </tr>
      </thead>
      <tbody>
       <?foreach($arResult["WORKERS"] as $arCall):?>
       <tr>
         <td><?=$arCall["UF_FIRST_NAME"]?> <?=$arCall["UF_LAST_NAME"]?></td>
         <td><?=$arCall["UF_PHONE_NUMBER"]?></td>
         <td><?=$arCall["UF_EXTENSION"]?></td>
       </tr>
       <?endforeach;?>
     </tbody>
    </table>
    <?else:?>
    <h5>У вас пока нет зарегестрированных сотрудников</h5>
    <?endif;?>
  </div>
<?endif;?>
