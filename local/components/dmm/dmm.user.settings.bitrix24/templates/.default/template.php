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
  <h3>Bitrix24</h3>
  <?if($_GET["SUCCESS"]=="Y"):?>
    <h5>Интеграция активирована!</h5>
  <?else:?>
    <?if($arResult["WRONG_TOKEN"]):?>
      <h5>Неверный токен вашей АТС</h5>
    <?endif;?>
    <form method="POST" action="/calls_analytic/integration/bitrix24.php">
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
                Найти или сгенерировать свой токен вы сможете на сайте Облачной ATC Билайн в разделе <a href="https://cloudpbx.beeline.ru/#%D0%9D%D0%B0%D1%81%D1%82%D1%80%D0%BE%D0%B9%D0%BA%D0%B8">Настройки>API</a>.
              </p>
            </div>
          </div>
        </div>
      </div>
      <div>
        <p class="label label-info black-label">Домен Bitrix24</p>
        <input type="text" name="domain" placeholder="Домен вашего bitrix24 (example.bitrix24.ru)">
      </div>
      </br>
      <input type="submit" class="btn btn-danger" value="Отправить">
    </form>
  <?endif;?>
  <?if($arResult["SUBSCRIBE_EXIST"]):?>
      <input type="hidden" id="number" name="number" value="<?=$arResult["USER_DATA"]["LOGIN"]?>">
      <div class="redirect-option">
          <p class="redirect-title">Индивидуальная<br>переадресация</p>
          <div class="redirect-switch">
              <a <?if($arResult["ATE_OPTIONS"]["redirect"]):?>style="margin-left: 48px;"<?endif;?> class="<?if($arResult["ATE_OPTIONS"]["redirect"]):?>redirect-on<?else:?>redirect-off<?endif;?> toggle"><?if($arResult["ATE_OPTIONS"]["redirect"]):?>Включено<?else:?>Выключено<?endif;?></a>
          </div>
      </div>
  <?endif;?>
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
    <form method="post" id="bitrix24_users_table">
      <input type="hidden" id="rows" name="rows" value="<?=$arResult["ROWS"]?>">
      <input type="hidden" name="number" value="<?=$arResult["USER_DATA"]["LOGIN"]?>">
      <?foreach ($arResult["BEELINE_WORKERS"] as $worker):?>
        <input type="hidden" name="<?=$worker["userId"]?>" value="<?=$worker["extension"]?>">
      <?endforeach;?>
      <table class="table datatable-basic user-settings-table">
        <thead>
          <tr>
            <th>№</th>
            <th>Пользователь CRM</th>
            <th>Абонент Beeline</th>
            <th>Вх. лид</th>
            <th>Исх. лид</th>
            <th>Доб. в чат</th>
            <th>Созд. задачу</th>
    				<th>Глав. менеджер</th>
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
                <select name="crm_account_<?=$i?>" class="crm-account">
                  <option value="N">Не выбрано</option>
                  <?foreach ($arResult["USERS_DATA"]["BITRIX24_USER_LIST"] as $id => $name):?>
                    <option<?if($arResult["USERS_DATA"]["BITRIX24_USERS"]["ROW_".$i]["crm_user_id"]==$id):?> selected<?endif;?> value="<?=$id?>"><?=$name?></option>
                  <?endforeach;?>
                </select>
              </td>
              <td>
                <select name="beeline_worker_<?=$i?>" class="beeline-worker">
                  <option value="N">Не выбрано</option>
                  <?foreach ($arResult["BEELINE_WORKERS"] as $key_worker => $worker):?>
                    <option<?if($arResult["USERS_DATA"]["BITRIX24_USERS"]["ROW_".$i]["beeline_user_id"]===$worker["userId"]):?> selected<?endif;?> value="<?=$worker["userId"]?>"><?=$worker["firstName"]?> <?=$worker["lastName"]?></option>
                  <?endforeach;?>
                </select>
              </td>
              <td>
                <input type="checkbox" name="incoming_lid_<?=$i?>"<?if($arResult["USERS_DATA"]["BITRIX24_USERS"]["ROW_".$i]["incoming_lid"]):?> checked<?endif;?>>
              </td>
              <td>
                <input type="checkbox" name="outgoing_lid_<?=$i?>"<?if($arResult["USERS_DATA"]["BITRIX24_USERS"]["ROW_".$i]["outgoing_lid"]):?> checked<?endif;?>>
              </td>
              <td>
                <input type="checkbox" name="chat_<?=$i?>"<?if($arResult["USERS_DATA"]["BITRIX24_USERS"]["ROW_".$i]["add_to_chat"]):?> checked<?endif;?>>
              </td>
              <td>
                <input type="checkbox" name="create_task_<?=$i?>"<?if($arResult["USERS_DATA"]["BITRIX24_USERS"]["ROW_".$i]["create_task"]):?> checked<?endif;?>>
              </td>
              <td>
                <input class="responsible-manager" type="checkbox" name="responsible_manager_<?=$i?>"<?if($arResult["USERS_DATA"]["BITRIX24_USERS"]["ROW_".$i]["responsible_manager"]):?> checked<?endif;?>>
              </td>
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
            <td colspan="5" id="result"></td>
            <td>
              <input type="submit" value="Отправить" class="btn btn-info" style="float: right;">
            </td>
          </tr>
        </tbody>
      </table>
    </form>
  </div>

  <?if(count($arResult["MULTICALL_NUMBERS"])>0):?>
    <div class="panel panel-flat full-panel">
      <form method="post" id="multicall_table">
        <input type="hidden" name="number" value="<?=$arResult["USER_DATA"]["LOGIN"]?>">
        <h3>Многоканальные номера</h3>
        <table class="table datatable-basic user-settings-table">
          <thead>
            <tr>
              <th>Номер</th>
              <th>Имя в CRM</th>
            </tr>
          </thead>
          <tbody>
            <?foreach($arResult["MULTICALL_NUMBERS"] as $number => $name):?>
              <tr>
                <td><?=$number?></td>
                <td>
                  <input type="text" name="<?=$number?>" placeholder="Билайн АТС <?=$number?>" value="<?=$name?>">
                </td>
              </tr>
            <?endforeach;?>
            <tr>
              <td id="multicall_result"></td>
              <td>
                <input type="submit" value="Отправить" class="btn btn-info" style="float: right;">
              </td>
            </tr>
          </tbody>
        </table>
      </form>
    </div>
  <?endif;?>
<?endif;?>
