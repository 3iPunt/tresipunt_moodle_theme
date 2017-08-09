/**
 * Created by Roser on 10/07/2017.
 */
require(['jquery'], function($) {
    $(document).ready(function () {
        $('nav a').on('click', function (e) {
            update_hash($(this).attr('href'));
        });
        $('nav a[href="' + document.URL + '"]').click();
    });

    function update_hash(href) {
        var hash = href.substr(href.indexOf('#'));
        if (hash) {
            $('li' + hash).children('span').trigger('click');
        }
    }
});