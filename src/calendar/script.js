jQuery(document).ready(function ($) {
    function calendarIsMobile() {
        return window.innerWidth <= 782;
    }

    function calendarShowHideHeaders(elem) {
        if (typeof elem == "undefined" || elem == null) {
            elem = ".rrze-calendar";
        }
        // First we restore all of the headers we may be hiding
        $(
            elem +
                " .rrze-calendar-list-wrapper h4, " +
                elem +
                ":not(.monthnav-compact) .rrze-calendar-label, " +
                elem +
                " .rrze-calendar-month-grid .day"
        ).show();
        $(
            elem +
                " .rrze-calendar-list-wrapper h4, " +
                elem +
                ":not(.monthnav-compact) .rrze-calendar-label, " +
                elem +
                " .rrze-calendar-month-grid .day"
        )
            .removeClass("nomobile")
            .removeClass("hidden_in_list");
        // In list view, hide/show the day header
        if ($(".rrze-calendar.layout-list").length > 0) {
            $(elem + " .rrze-calendar-list-wrapper h4").each(function () {
                if (
                    $(this).next("dl").find('.event:not([style*="none"])')
                        .length == 0
                ) {
                    $(this).hide();
                } else {
                    $(this).show();
                }
            });
            // And also hide/show the month header
            $(elem + " .rrze-calendar-list-wrapper .rrze-calendar-label").each(
                function () {
                    if (
                        $(this)
                            .siblings(".rrze-calendar-date-wrapper")
                            .children('h4:not([style*="none"])').length == 0
                    ) {
                        $(this).hide();
                    } else {
                        $(this).show();
                    }
                }
            );
        }
        // In month view list (mobile breakpoint), hide the day header
        // Also applies to Pro in month view with table/list toggle set to list
        if (
            $("body.calendar_mobile .rrze-calendar.layout-month").length > 0 ||
            $(elem).data("month-table-list-toggle") == "list"
        ) {
            $(elem + " .rrze-calendar-month-grid .events").each(function () {
                if ($(this).find('.event:not([style*="none"])').length == 0) {
                    $(this)
                        .siblings(".day")
                        .addClass("nomobile")
                        .addClass("hidden_in_list");
                } else {
                    $(this)
                        .siblings(".day")
                        .removeClass("nomobile")
                        .removeClass("hidden_in_list");
                }
            });
            // And also hide/show the month header
            $(
                elem + " .rrze-calendar-month-wrapper .rrze-calendar-month-grid"
            ).each(function () {
                if ($(this).find('.event:not([style*="none"])').length == 0) {
                    $(this)
                        .siblings(".rrze-calendar-label")
                        .addClass("nomobile")
                        .addClass("hidden_in_list");
                } else {
                    $(this)
                        .siblings(".rrze-calendar-label")
                        .removeClass("nomobile")
                        .removeClass("hidden_in_list");
                }
            });
        }
    }

    $(function () {
        // VIEW: ALL

        // Add .calendar_mobile class to body if we're on a mobile screen size
        if (calendarIsMobile()) {
            $("body").addClass("calendar_mobile");
        }

        // Make offsite links open in new tab
        $(".rrze-calendar a").each(function () {
            if ($(this).attr("target") == "_blank") {
                $(this).addClass("offsite-link");
            } else if (
                typeof $(this).attr("href") != "undefined" &&
                $(this).attr("href").indexOf("http") == 0 &&
                $(this)
                    .attr("href")
                    .indexOf("//" + location.hostname) == -1
            ) {
                $(this).addClass("offsite-link").attr("target", "_blank");
            }
        });

        // VIEW: MONTH
        // Outer section wrapper has classes .rrze-calendar.layout-month

        if ($(".rrze-calendar.layout-month").length > 0) {
            // Month select interactivity
            $(".rrze-calendar.layout-month .rrze-calendar-select").on(
                "change",
                function () {
                    var calendar_cal = $(this).closest(".rrze-calendar");
                    calendar_cal.find(".rrze-calendar-month-wrapper").hide();
                    calendar_cal
                        .find(
                            '.rrze-calendar-month-wrapper[data-year-month="' +
                                $(this).val() +
                                '"]'
                        )
                        .show();
                    // Change arrow labels
                    var calendar_arrownav = calendar_cal.find(
                        ".rrze-calendar-arrow-nav"
                    );
                    if (calendar_arrownav.length > 0) {
                        var calendar_arrownav_prev = $(this)
                            .find("option:selected")
                            .prev();
                        if (calendar_arrownav_prev.length > 0) {
                            calendar_arrownav
                                .find(".prev")
                                .data(
                                    "goto",
                                    calendar_arrownav_prev.attr("value")
                                );
                            calendar_arrownav
                                .find(".prev-text")
                                .text(calendar_arrownav_prev.text())
                                .parent()
                                .removeClass("inactive");
                        } else {
                            calendar_arrownav.find(".prev").data("goto", "");
                            calendar_arrownav
                                .find(".prev-text")
                                .text("")
                                .parent()
                                .addClass("inactive");
                        }
                        var calendar_arrownav_next = $(this)
                            .find("option:selected")
                            .next();
                        if (calendar_arrownav_next.length > 0) {
                            calendar_arrownav
                                .find(".next")
                                .data(
                                    "goto",
                                    calendar_arrownav_next.attr("value")
                                );
                            calendar_arrownav
                                .find(".next-text")
                                .text(calendar_arrownav_next.text())
                                .parent()
                                .removeClass("inactive");
                        } else {
                            calendar_arrownav.find(".next").data("goto", "");
                            calendar_arrownav
                                .find(".next-text")
                                .text("")
                                .parent()
                                .addClass("inactive");
                        }
                    }
                }
            );
            // Month previous/next arrow interactivity
            $(".rrze-calendar.layout-month .rrze-calendar-arrow-nav > *").on(
                "click",
                function () {
                    if ($(this).data("goto") != "") {
                        var calendar_cal = $(this).closest(".rrze-calendar");
                        calendar_cal
                            .find(".rrze-calendar-select")
                            .val($(this).data("goto"))
                            .trigger("change");
                    }
                    return false;
                }
            );
            // Show/hide past events on mobile
            $('a[data-rrze-calendar-action="show-past-events"]').on(
                "click",
                function () {
                    var calendar_cal = $(this).closest(".rrze-calendar");
                    if (!calendar_cal.hasClass("show-past-events")) {
                        calendar_cal.addClass("show-past-events");
                        // Show toggle
                        $(this).text(rrze_calendar_i18n.hide_past_events);
                    } else {
                        calendar_cal.removeClass("show-past-events");
                        $(this).text(rrze_calendar_i18n.show_past_events);
                    }
                    // Don't jump!
                    return false;
                }
            );
            // Show/hide past events toggle depending on selected month
            $(".rrze-calendar-select").on("change", function () {
                var calendar_cal = $(this).closest(".rrze-calendar");
                // Always show if we're showing the full list (Pro only)
                if (calendar_cal.hasClass("month_list_all")) {
                    calendar_cal
                        .find('a[data-rrze-calendar-action="show-past-events"]')
                        .show();
                } else if ($(this).val() == $(this).attr("data-this-month")) {
                    calendar_cal
                        .find('a[data-rrze-calendar-action="show-past-events"]')
                        .show();
                } else {
                    calendar_cal
                        .find('a[data-rrze-calendar-action="show-past-events"]')
                        .hide();
                }
            });
            // Initial state
            $(
                ".rrze-calendar.layout-month .rrze-calendar-select:not(.hidden), .rrze-calendar.layout-month .rrze-calendar-arrow-nav"
            ).show();
            $(
                '.rrze-calendar.layout-month .rrze-calendar-month-wrapper[data-year-month="' +
                    $(".rrze-calendar-select").val() +
                    '"]'
            ).show();
            $(".rrze-calendar.layout-month .rrze-calendar-select").trigger(
                "change"
            );
            // Remove Show Past Events link if there *are* no past events
            $(".rrze-calendar.layout-month").each(function () {
                if (
                    $(this).find(
                        ".rrze-calendar-month-wrapper:visible .past:not(.empty)"
                    ).length == 0
                ) {
                    $(this).find(".rrze-calendar-past-events-toggle").remove();
                }
            });
        }
    });

    $(window).on("resize", function () {
        // Add/remove .calendar_mobile class on body
        if (calendarIsMobile()) {
            $("body").addClass("calendar_mobile");
        } else {
            $("body").removeClass("calendar_mobile");
        }

        // Show/hide headers
        calendarShowHideHeaders();
    });
});
