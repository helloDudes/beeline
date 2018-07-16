$(window).load(function() {
  $("#plus").click(function(event) {
    event.preventDefault();
    var older_bro = $(".row-user").last();
    var num_row = older_bro.data("row")+1;
    $("#rows").attr("value", num_row);
    var new_row = '<tr data-row="'+num_row+'" class="row-user">';
      new_row += '<td>'+num_row+'</td>';
      new_row += '<td>';
        new_row += '<input name="moysklad_login_'+num_row+'" type="text" placeholder="example@user">';
      new_row += '</td>';
      new_row += '<td>';
        new_row+='<select name="beeline_worker_'+num_row+'" class="beeline-worker">';
        new_row+='</select>';
      new_row += '</td>';
    new_row += '</tr>';

    older_bro.after(new_row);

    var beeline_list = older_bro.find(".beeline-worker").html();

    var new_bro = $(".row-user").last();
    var beeline_worker = new_bro.find(".beeline-worker");
    beeline_worker.html(beeline_list);
    beeline_worker.find("option").first().prop("selected", true);
  });
  $("#minus").click(function(event) {
    event.preventDefault();
    var older_bro = $(".row-user").last();
    var num_row = older_bro.data("row")-1;
    if(num_row!=0) {
      $("#rows").attr("value", num_row);
      older_bro.remove();
    };
  });
});
