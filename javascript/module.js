require(['jquery'], function($) {
    $(".accordion-section").hide();
    $(".topics li").click(function(e) {
        e.stopPropagation();
        $(".accordion-section").hide();
        $(this).find(".accordion-section").show();
        $('html,body').animate({
            scrollTop: $(this).offset().top-60
        }, 100);

    });
});
