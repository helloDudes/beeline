$(window).load(function() {

  //Отправка таблицы пользователй
  $("#bitrix24_users_table").submit(function(event) {
    event.preventDefault();
    $("#result").animate({"opacity":"0"}, 200);
    setTimeout(function() {
      var msg = $("#bitrix24_users_table").serialize();
      $.post(
        '/calls_analytic/include/bitrix24/update_bitrix24_users.php',
        msg,
        function(res) {
          $("#result").html(res);
          $("#result").animate({"opacity":"1"}, 200);
          if(res=="<span class='users-update-success'>Сохранено:)</span>") {
            setTimeout(function() {
              $("#result").animate({"opacity":"0"}, 1000);
            }, 3000);
          }
        }
      );
    }, 200);
  });

  //Отправка имён многоканальных номеров
  $("#multicall_table").submit(function(event) {
    event.preventDefault();
    $("#multicall_result").animate({"opacity":"0"}, 200);
    setTimeout(function() {
      var msg = $("#multicall_table").serialize();
      $.post(
        '/calls_analytic/include/bitrix24/update_bitrix24_multicalls.php',
        msg,
        function(res) {
          $("#multicall_result").html(res);
          $("#multicall_result").animate({"opacity":"1"}, 200);
          if(res=="<span class='users-update-success'>Сохранено:)</span>") {
            setTimeout(function() {
              $("#multicall_result").animate({"opacity":"0"}, 1000);
            }, 3000);
          }
        }
      );
    }, 200);
  });

  //Переключатель автоматического перенаправления
  $(".redirect-switch").find(".toggle").click(function() {
    var url = '/calls_analytic/include/bitrix24/ate_options_update.php';
    var toggle = $(this);
    if($(this).hasClass("redirect-off")) {
      $.post(
        url,
        {
          redirect: "on",
          number: $("#number").val(),
        },
        function(res) {
          if(res=="Y") {
            toggle.animate({"margin-left":"47px"}, 200);
            toggle.html("Включено");
            toggle.addClass("redirect-on");
            toggle.removeClass("redirect-off");
          }
          else {
            alert("Ошибка");
          }
        }
      );
    }
    else {
      $.post(
        url,
        {
          redirect: "off",
          number: $("#number").val(),
        },
        function(res) {
          if(res=="Y") {
            toggle.animate({"margin-left":"0px"}, 200);
            toggle.html("Выключено");
            toggle.removeClass("redirect-on");
            toggle.addClass("redirect-off");
          }
          else {
            alert("Ошибка");
          }
        }
      );
    }
  });
});
