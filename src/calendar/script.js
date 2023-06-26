"use strict";

jQuery(document).ready(function($){
    var $loading = $('#loading').hide();
    $(document)
        .ajaxStart(function () {
            $loading.show();
        })
        .ajaxStop(function () {
            $loading.hide();
        });

    $('div.rrze-calendar').on('click', '.calendar-pager a', function(e) {
        e.preventDefault();
        var calendar = $('div.calendar-wrapper');
        var period = calendar.data('period');
        var layout = calendar.data('layout');
        var direction = $(this).data('direction');
        $.post(rrze_calendar_ajax.ajax_url, {         //POST request
            _ajax_nonce: rrze_calendar_ajax.nonce,     //nonce
            action: "rrze-calendar-update-calendar",            //action
            period: period,                  //data
            layout: layout,
            direction: direction,
        }, function(result) {
            calendar.remove();
            $('div.rrze-calendar').append(result);
        });
    });
});