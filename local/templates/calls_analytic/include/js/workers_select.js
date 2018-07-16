$(window).load(function(){
  $(".checkbox-cont").find("input").click(function() {
    var was_select = false;
    $(".checkbox-cont").find("input").each(function() {
      if($(this).prop("checked"))
        was_select = true;
    })
    if(was_select)
      $("#workers_all").attr("value", "N");
    else
      $("#workers_all").attr("value", "Y");
  });
  function toggle() {
    $(".checkbox-cont").fadeToggle();
    if($(".worker-searcher").css("width")=="97px") {
      $(".worker-searcher").animate({"width":"0px"}, 400);
      setTimeout(function() {$(".worker-searcher").css({"display":"none"});}, 400);
    }
    else {
      $(".worker-searcher").css({"display":"inline-block"});
      $(".worker-searcher").animate({"width":"97px"});
    };
    var span = $(this).find("span");
    span.toggleClass("glyphicon-chevron-down");
    span.toggleClass("glyphicon-chevron-up");
  };
  $(".worker-searcher").keyup(function() {
    var val = $(this).val();
    var reg = new RegExp(val, "i");
    $(".checkbox-cont").find("label").find("span").each(function() {
      var name = $(this).html();
      if(name.search(reg)) {
        $(this).closest("p").hide();
      }
      else {
        $(this).closest("p").show();
      }
    });
  });
  $(".workers-select").click(toggle);
  $(document).mouseup(function (e) {
		var popup = $(".worker-popup");
    var popupParent = popup.closest(".workers");
		if (!popupParent.is(e.target) && popupParent.has(e.target).length === 0 && popup.is(":visible")) {
			$(".workers-select").each(function() {
        if($(this).closest(".workers").find(".worker-popup").is(":visible"))
          $(this).click();
      });
		}
	});
});
