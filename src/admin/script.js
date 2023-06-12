jQuery(document).ready(function ($) {
    "use strict";

    let defaultColor = "#041E42";
    let linkColor = $(".color-picker");

    function pickColor(color) {
        linkColor.val(color);
    }

    function toggleText() {
        if ("" === linkColor.val().replace("#", "")) {
            linkColor.val(defaultColor);
            pickColor(defaultColor);
        } else {
            pickColor(linkColor.val());
        }
    }

    linkColor.wpColorPicker({
        change: function () {
            pickColor(linkColor.wpColorPicker("color"));
        },
        clear: function () {
            pickColor("");
        },
    });

    linkColor.click(toggleText);

    toggleText();

    linkColor.iris({
        palettes: [
            "#041E42", // FAU
            "#963B2F", // Phil
            "#662938", // RW
            "#003E61", // Med
            "#14462D", // Nat
            "#204251", // TF
        ],
    });
});
