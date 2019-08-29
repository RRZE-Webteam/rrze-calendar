jQuery(document).ready(function($) {
    "use strict";

    var default_color = '';
    var link_color = $('.color-picker');

    function pickColor(color) {
        link_color.val(color);
    }

    function toggle_text() {
        if ('' === link_color.val().replace('#', '')) {
            link_color.val(default_color);
            pickColor(default_color);
        } else {
            pickColor(link_color.val());
        }
    }

    link_color.wpColorPicker({
        change: function(event, ui) {
            pickColor(link_color.wpColorPicker('color'));
        },
        clear: function() {
            pickColor('');
        }
    });

    link_color.click(toggle_text);

    toggle_text();

    link_color.iris({
        //          PhilFak     RwFak     MedFak     NatFak    TechFak       ZUV
        palettes: ['#A36B0D', '#8d1429', '#0381A2', '#048767', '#6E7881', '#003366']
    });

});