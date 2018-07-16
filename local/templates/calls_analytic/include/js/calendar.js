var calendar_toggle = function() {
  var calendar_title = $(this).closest(".calendar-title");
  var calendar_accept = calendar_title.find(".calendar-accept");
  calendar_title.find(".calendar-cont").fadeToggle();
  calendar_title.find(".calendar-toggle-icon").toggleClass("glyphicon-chevron-down");
  calendar_title.find(".calendar-toggle-icon").toggleClass("glyphicon-chevron-up");
  if(calendar_accept.css("width")!="0px")
    calendar_accept.animate({"width":"0px"});
  else
    calendar_accept.animate({"width":"82px"});
};
$('#calendar_from').datepicker({
inline: true,
firstDay: 1,
showOtherMonths: true,
monthNames: [ "Январь", "Февраль", "Март", "Апрель", "Май", "Июнь", "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь" ],
dayNamesMin: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
dateFormat: 'dd.mm.yy',
onSelect: function(date) {
  $("#date_from").attr("value", date);
  $("#date_from").closest(".calendar-title").find(".current-date").html(date);
},
});
$('#calendar_to').datepicker({
  inline: true,
  firstDay: 1,
  showOtherMonths: true,
  monthNames: [ "Январь", "Февраль", "Март", "Апрель", "Май", "Июнь", "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь" ],
  dayNamesMin: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
  dateFormat: 'dd.mm.yy',
  onSelect: function(date) {
    $("#date_to").attr("value", date);
    $("#date_to").closest(".calendar-title").find(".current-date").html(date);
  },
});

$(".calendar-button").click(calendar_toggle);
$(".calendar-accept").click(calendar_toggle);
$(".calendar-accept").click(function(){
  $(this).closest(".calendar-title").find("input").attr("value", "");
  $(this).closest(".calendar-title").find(".current-date").html("");
});
$(document).mouseup(function (e){
  var popup = $(".calendar-popup");
  var popupParent = popup.closest(".calendar-title");
  if (!popupParent.is(e.target) && popupParent.has(e.target).length === 0 && popup.is(":visible")) {
    $(".calendar-button").each(function() {
      if($(this).closest(".calendar-title").find(".calendar-popup").is(":visible"))
        $(this).click();
    });
  }
});
