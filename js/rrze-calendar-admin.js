jQuery(document).ready(function ($) {
    $('ul.rrze-calendar-select-list').listFilterizer({
        filters: [{
            label: rrze_calendar_vars.filters_label_1,
            selector: '*'
        }, {
            label: rrze_calendar_vars.filters_label_2,
            selector: ':has(input:checked)'
        }],
        inputPlaceholder: rrze_calendar_vars.placeholder
    });

    $('.delete-category a').click(function () {
        return showNotice.warn();
    })
});