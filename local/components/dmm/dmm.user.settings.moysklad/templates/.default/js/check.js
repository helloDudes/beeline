$(window).load(function() {
  $(".limit-field").keyup(function() {
    var limit = $(this).val();
    $(".user-settings-table tbody tr").each(function() {
      if($(this).data("row")>limit)
        $(this).slideUp();
      else
        $(this).slideDown();
    });
  });
  $(".limit-field").change(function() {
    $(this).keyup();
  });

  $(".responsible-manager").click(function() {
    $(".responsible-manager").removeAttr("checked");
    $(this).prop("checked", true);
  });
});
