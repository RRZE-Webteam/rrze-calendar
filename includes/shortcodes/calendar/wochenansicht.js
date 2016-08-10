jQuery(document).ready(function ($) {
    
    $(document).mouseup(function (e) {

        var container = $(".intervall .buttons");

        if (container.has(e.target).length === 0) {
            container.hide();
        }
    });

    $(".aktion").click(function (e) {
        e.preventDefault();
        // console.log($(this).siblings().find(".buttons"));
        $(".buttons").show();
    });

});
