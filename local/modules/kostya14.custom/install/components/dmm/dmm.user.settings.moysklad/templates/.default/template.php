<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
?>
<?if($arResult["SUBSCRIBE_EXIST"]):?>
<script>
  function digitalWatch(unix_date) {
    if(unix_date<0)
      return;
    var date = new Date((unix_date-(3600*3))*1000);
    var month = date.getMonth();
    var day = date.getDate();
    if(day!=0)
      day = day-1;
    var hour = date.getHours();
    var minute = date.getMinutes();
    var second = date.getSeconds();
    $("#month").html(month);
    $("#day").html(day);
    $("#hour").html(hour);
    $("#minute").html(minute);
    $("#second").html(second);
    var second_date = unix_date-1;
    if(unix_date>0) {
      setTimeout(function() {
        digitalWatch(second_date);
      }, 1000);
    }
  };
  digitalWatch(<?=$arResult["SUBSCRIBE_DURATION"]?>);
</script>
<div class="panel panel-flat full-panel token-panel subscribe-time">
  <h3>Ваша подписка активна до <?=$arResult["SUBSCRIBE_EXPIRES"]?>.</h3>
  </br>
  <h3 style="margin-bottom: 0px" class="timer">
    Осталось ещё
    <span id="month_cont">
      <span id="month">0</span> мес
    </span>
    <span id="day_cont">
      <span id="day">0</span> д
    </span>
    <span id="hour_cont">
      <span id="hour">0</span> ч
    </span>
    <span id="minute_cont">
      <span id="minute">0</span> мин
    </span>
     и
    <span id="second_cont">
      <span id="second">0</span> с
    </span>
  </h3>
</div>
<?endif;?>
<div class="panel panel-flat full-panel token-panel">
  <h3>МойСклад</h3>
  <h5><?=$arResult["MESS"]?></h5>
  <form method="post">
    <div>
      <p class="label label-info">Токен</p>
      <input autocomplete="off" spellcheck="false" type="text" name="token" placeholder="Токен вашей АТС">
      <div class="clarification-cont">
        <a href="" class="clarification">
          <span class="glyphicon glyphicon-question-sign"></span>
        </a>
        <div class="clarification-popup-cont">
          <div class="clarification-popup">
            <p>
              Найти или сгенерировать свой токен вы сможете на сайте Облачной ATC Билайн в разделе Настройки>API.
            </p>
          </div>
        </div>
      </div>
    </div>
    </br>
    <div>
      <p class="label label-info black-label">Ключ доступа</p>
      <input autocomplete="off" type="text" name="moy_sklad_token" placeholder='Ваш код доступа в "МоёмСкладе"'>
      <div class="clarification-cont">
        <a href="" class="clarification">
          <span class="glyphicon glyphicon-question-sign"></span>
        </a>
        <div class="clarification-popup-cont">
          <div class="clarification-popup">
            <p>
              Найти или сгенерировать токен аккаунта в сервисе "МойСклад" вы сможете на странице "Приложения", ссылка на которую появляется при клике на имя вашего аккаунта в верхней правой части вашего кабинета.
              </br>
              В разделе звонков нажмите вы увидите блок "МойСклад". Нажмите на кнопку "Настроить". Там вы увидите как сгенерировать данный токен.
            </p>
          </div>
        </div>
      </div>
    </div>
    </br>
    <input type="submit" class="btn btn-danger" value="Запустить интеграцию">
    </br>
    </br>
    <div>
      <p class="label label-default user-key">Провайдер телефонии: <span>http://beelinestore.ru/calls_analytic/include/beeline_connection/moysklad_provider.php</span></p>
      <div class="clarification-cont">
        <a href="" class="clarification">
          <span class="glyphicon glyphicon-question-sign"></span>
        </a>
        <div class="clarification-popup-cont">
          <div class="clarification-popup">
            <p>
              Вам необходимо указать этот адрес в сервисе "МойСклад", на странице "Приложения", ссылка на которую появляется при клике на имя вашего аккаунта в верхней правой части вашего кабинета.
              </br>
              В разделе звонков вы увидите блок "МойСклад". Нажмите на кнопку "Настроить". Там вы увидите поле "Адрес провайдера телефонии", вставьте туда этот адрес и установите галочку в поле "Подключить".
            </p>
          </div>
        </div>
      </div>
    </div>
    </br>
    <!--<div>
      <p class="api-key-instruction label label-default user-key" style="background: none">
        <a href="https://beelinestore.ru/calls_analytic/api_instructions.php">Инструкция по интеграции с "Моим Складом"</a>
      </p>
  	</div>-->
    </br>
  </form>
</div>
<?if($arResult["SUBSCRIBE_EXIST"]):?>
<?$this->addExternalJs($this->GetFolder()."/js/ajax.js");?>
<?$this->addExternalJs($this->GetFolder()."/js/check.js");?>
<?$this->addExternalJs($this->GetFolder()."/js/rows_manage.js");?>
  <div class="panel panel-flat full-panel">
    <h3>Пользователи</h3>
    <?if($arResult["ATE_LIMIT"]=="all"):?>
      <p>Ваш лимит не ограничен! Добавляйте столько пользователей, сколько нужно!</p>
    <?else:?>
      <p>Ваш лимит - <?=$arResult["ATE_LIMIT"]?> пользователей.</p>
    <?endif;?>
    <form method="post" id="moysklad_users_table">
      <input type="hidden" id="rows" name="rows" value="<?=$arResult["ROWS"]?>">
      <?foreach ($arResult["BEELINE_WORKERS"] as $worker):?>
        <input type="hidden" name="<?=$worker["extension"]?>" value="<?=$worker["userId"]?>">
      <?endforeach;?>
      <table class="table datatable-basic user-settings-table">
        <thead>
          <tr>
            <th>№</th>
            <th>Аккаунт "МоегоСклада"</th>
            <th>Аккаунт АТС Beeline</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?
          $i = 1;
          while($i<=$arResult["ROWS"]):
          ?>
            <tr data-row="<?=$i?>" class="row-user">
              <td><?=$i?></td>
              <td>
                <input name="moysklad_login_<?=$i?>" type="text" placeholder="example@user" value="<?if($arResult["USERS_DATA"]["MOY_SKLAD_USERS"]["ROW_".$i]["moysklad_user_login"]!="N"):?><?=$arResult["USERS_DATA"]["MOY_SKLAD_USERS"]["ROW_".$i]["moysklad_user_login"]?><?endif;?>">
              </td>
              <td>
                <select name="beeline_worker_<?=$i?>" class="beeline-worker">
                  <option value="N">Не выбрано</option>
                  <?foreach ($arResult["BEELINE_WORKERS"] as $worker):?>
                    <option<?if($arResult["USERS_DATA"]["MOY_SKLAD_USERS"]["ROW_".$i]["user_extension"]===$worker["extension"]):?> selected<?endif;?> value="<?=$worker["extension"]?>"><?=$worker["extension"]?></option>
                  <?endforeach;?>
                </select>
              </td>
              <td></td>
            </tr>
          <?
          $i++;
          endwhile;
          ?>
          <tr>
            <td>
              <?if($arResult["ATE_LIMIT"]=="all"):?>
                <a href="" class="glyphicon glyphicon-plus" id="plus"></a>
              <?endif;?>
            </td>
            <td>
              <?if($arResult["ATE_LIMIT"]=="all"):?>
                <a href="" class="glyphicon glyphicon-minus" id="minus"></a>
              <?endif;?>
            </td>
            <td id="result"></td>
            <td>
              <input type="submit" value="Отправить" class="btn btn-info" style="float: right;">
            </td>
          </tr>
        </tbody>
      </table>
    </form>
  </div>
<?endif;?>
