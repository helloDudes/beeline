$(window).load(function() {
  $("#moysklad_users_table").submit(function(event) {
    event.preventDefault();
    $("#result").animate({"opacity":"0"}, 200);
    setTimeout(function() {
      var msg = $("#moysklad_users_table").serialize();
      $.post(
        '/calls_analytic/include/moysklad/update_moysklad_users.php',
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
});
