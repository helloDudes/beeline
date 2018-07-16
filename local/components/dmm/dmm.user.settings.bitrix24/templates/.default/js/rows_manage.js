$(window).load(function() {

  //Добавляет и удаляет строки в таблице пользователей, если тариф безлимитный
  $("#plus").click(function(event) {
    event.preventDefault();
    var older_bro = $(".row-user").last();
    var num_row = older_bro.data("row")+1;
    $("#rows").attr("value", num_row);
    var new_row = '<tr data-row="'+num_row+'" class="row-user">';
      new_row+='<td>'+num_row+'</td>';
      new_row+='<td>';
        new_row+='<select name="crm_account_'+num_row+'" class="crm-account">';
        new_row+='</select>';
      new_row += '</td>';
      new_row += '<td>';
        new_row+='<select name="beeline_worker_'+num_row+'" class="beeline-worker">';
        new_row+='</select>';
      new_row += '</td>';
      new_row += '<td>';
        new_row += '<input type="checkbox" name="incoming_lid_'+num_row+'">';
      new_row += '</td>';
      new_row += '<td>';
        new_row += '<input type="checkbox" name="outgoing_lid_'+num_row+'">';
      new_row += '</td>';
      new_row += '<td>';
        new_row += '<input type="checkbox" name="chat_'+num_row+'">';
      new_row += '</td>';
      new_row += '<td>';
        new_row += '<input type="checkbox" name="create_task_'+num_row+'">';
      new_row += '</td>';
      new_row += '<td>';
        new_row += '<input class="responsible-manager" type="checkbox" name="responsible_manager_'+num_row+'">';
      new_row += '</td>';
    new_row += '</tr>';

    older_bro.after(new_row);

    var crm_list = older_bro.find(".crm-account").html();
    var beeline_list = older_bro.find(".beeline-worker").html();

    var new_bro = $(".row-user").last();
    var crm_account = new_bro.find(".crm-account");
    var beeline_worker = new_bro.find(".beeline-worker");
    crm_account.html(crm_list);
    beeline_worker.html(beeline_list);
    crm_account.find("option").first().prop("selected", true);
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
