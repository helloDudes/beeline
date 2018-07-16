$(window).load(function(){
  var clarification_toggle = function(event) {
    event.preventDefault();
    $(this).css({"color": "#000000"});
    $(this).closest(".clarification-cont").find(".clarification-popup-cont").fadeToggle('medium', function() {});
  };
  $(".desktop-panel").on('click', '.clarification', clarification_toggle);
  $(".full-panel").on('click', '.clarification', clarification_toggle);
  $(document).mouseup(function (e) {
		var popup = $(".clarification-popup-cont");
    var popupParent = popup.closest(".clarification-cont");
		if (!popupParent.is(e.target) && popupParent.has(e.target).length === 0 && popup.is(":visible")) {
			$(".clarification-popup-cont").fadeOut();
		}
	});
});
