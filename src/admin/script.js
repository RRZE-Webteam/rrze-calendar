"use strict";

jQuery(document).ready(function ($) {
    /*
     * CPT CalendarFeed Edit Screen
     */

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

    if (linkColor.length > 0) {
        toggleText();
    }

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

    /*
     * CPT Event Edit Screen
     */

    let repeatCheck = $("input#repeat");
    let repeatIntervalSelect = $("select#repeat-interval");
    let repeatMonthlyTypeInput = $("input[name='repeat-monthly-type']");
    let newStartDateInput = $("body.post-new-php input#start_date");
    let newEndDateInput = $("body.post-new-php input#end_date");

    if (repeatCheck.length > 0) {
        triggerRepeatFields();
    }

    repeatCheck.on("change", function () {
        triggerRepeatFields();
    });

    repeatIntervalSelect.on("change", function () {
        triggerIntervalFields();
    });

    repeatMonthlyTypeInput.on("change", function () {
        triggerMonthlyTypeFields();
    });

    newStartDateInput.on("change", function () {
        if (!newEndDateInput.val() || newEndDateInput.val() < $(this).val()) {
            newEndDateInput.val($(this).val());
        }
    });

    function triggerRepeatFields() {
        if (repeatCheck.is(":checked")) {
            $("div.repeat").slideDown();
            triggerIntervalFields();
        } else {
            $("div.repeat").slideUp();
        }
    }

    function triggerIntervalFields() {
        var repeatInterval = $("option:selected", repeatIntervalSelect).val();
        if (repeatInterval === "week") {
            $("div.repeat-weekly").slideDown();
            $("div.repeat-monthly").slideUp();
        } else if (repeatInterval === "month") {
            $("div.repeat-monthly").slideDown();
            $("div.repeat-weekly").slideUp();
            triggerMonthlyTypeFields();
        }
    }

    function triggerMonthlyTypeFields() {
        var repeatMonthlyType = $(
            "input[name='repeat-monthly-type']:checked"
        ).val();
        if (typeof repeatMonthlyType == "undefined") {
            $("div.repeat-monthly-date").hide();
            $("div.repeat-monthly-dow").hide();
        } else if (repeatMonthlyType === "dow") {
            $("div.repeat-monthly-dow").slideDown();
            $("div.repeat-monthly-date").slideUp();
        } else if (repeatMonthlyType === "date") {
            $("div.repeat-monthly-date").slideDown();
            $("div.repeat-monthly-dow").slideUp();
        }
    }
});
