$(window).load(function(){

  $(".come_in_button").click(function(event) {
    event.preventDefault();
    $(".select-form-auth").fadeToggle();
  });
  $(".select-form-auth").on('click', ':not(.child)', function(){
    event.preventDefault();
  });
  $(".select-form-auth").on('click', function(){
    $(this).fadeOut();
  });

  $(".reg-login").keyup(function() {
    var val = $(this).val().replace(/\D/g, "");
    val = val.replace(/(^7)|(^8)/, "");
    if(val.length<10)
      $(this).addClass("red-border");
    else
      $(this).removeClass("red-border");
  });
  $(".reg-pass").keyup(function() {
    if($(this).val().length<6)
      $(this).addClass("red-border");
    else
      $(this).removeClass("red-border");
  });
  $(".reg-confirm-pass").keyup(function() {
    if($(this).val()!=$(".reg-pass").val())
      $(this).addClass("red-border");
    else
      $(this).removeClass("red-border");
  });
  $(".form-control").blur(function() {
    $(this).keyup();
  });
  $("#reg-form").submit(function(event) {
    event.preventDefault();
    if(!$(".form-control").hasClass("red-border")) {
      $.ajax({
        url: "/calls_analytic/include/reg_check.php",
        type: "POST",
        data: {
          REGISTER: {
            LOGIN: $(".reg-login").val(),
            PASSWORD: $(".reg-pass").val(),
            CONFIRM_PASSWORD: $(".reg-confirm-pass").val(),
          },
          register_submit_button: "Регистрация",
        },
        dataType: "json",
        success: function(errors) {
          console.log(errors);
          if(errors=="N") {
            document.location.replace("/calls_analytic/integration/");
          }
          else {
            $("#reg-form").find(".warning-message").html(errors);
          };
        },
      });
    };
  });
  $("#auth-form").submit(function(event) {
    event.preventDefault();
    $.ajax({
      url: "/calls_analytic/include/auth_check.php?login=yes",
      type: "POST",
      data: {
        AUTH_FORM: "Y",
        TYPE: "AUTH",
        USER_LOGIN: $(".auth-login").val(),
        USER_PASSWORD: $(".auth-password").val(),
        Login: "",
      },
      dataType: "json",
      success: function(errors) {
        if(errors=="N") {
          document.location.reload();
        }
        else {
          $("#auth-form").find(".warning-message").html(errors);
        };
      },
    });
  });
});
