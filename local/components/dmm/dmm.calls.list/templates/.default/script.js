$(window).load(function(){
    function showRecordInterface() {
        $(this).closest(".recording-cont").find(".player-block-cont").fadeToggle();
    };
    $(".recording-cont").find("a").click(showRecordInterface);
    var settings = {
        progressbarWidth: '170px',
        progressbarHeight: '5px',
        progressbarColor: '#22ccff',
        progressbarBGColor: '#FFFFFF',
        defaultVolume: 0.8
    };
    $(".player").player(settings);
});
