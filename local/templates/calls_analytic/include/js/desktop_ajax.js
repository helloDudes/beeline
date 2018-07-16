$(window).load(function(){
  var get_ajax_widget = function(name, file_path) {
    $.ajax({
      url: file_path,
      success: function(data){
        $(name).find(".desktop-panel").html(data);
      }
    });
  };
  var desktop_ajax_path = "/calls_analytic/desktop_ajax/";
  get_ajax_widget(".calls-list", desktop_ajax_path+"calls_list.php");
  get_ajax_widget(".calls-shedule", desktop_ajax_path+"calls_schedule.php");
  get_ajax_widget(".common-analytic", desktop_ajax_path+"common_analytic.php");
  get_ajax_widget(".calls-round", desktop_ajax_path+"calls_round.php");
  get_ajax_widget(".common-stat", desktop_ajax_path+"common_stat.php");
  get_ajax_widget(".success-calls", desktop_ajax_path+"success_calls.php");
  get_ajax_widget(".requests", desktop_ajax_path+"request_list.php");
});
