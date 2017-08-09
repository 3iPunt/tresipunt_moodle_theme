/**
 * @namespace
 */
/*
M.tresipunt_accordion.init = function(Y) {

    Y.delegate('click', function(e) {

        var node = this;
        
        var todos = Y.all('.sectioncontent');
        todos.hide();

        sectioncontent = node.one('.sectioncontent');
        Y.one("#section-1 .sectioncontent").toggleView();
        var isHidden = Y.one('#section-1#demo').getAttribute('hidden') === 'true'; 
        
    }, Y.config.doc, '.topics #section-1');
}*/

require(['jquery'], function($) {
    $(".topics li").click(function(e) {
        e.stopPropagation();
        $(".sectioncontent").hide();
        $(this).find(".sectioncontent").show();
        $('html,body').animate({
            scrollTop: $(this).offset().top-60
        }, 100);

    });
});