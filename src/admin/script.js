"use strict";

jQuery(document).ready(function ($) {
    const feedUpdateConfig = window.rrzeCalendarFeedUpdate;

    if (feedUpdateConfig) {
        const updateDialog = $(`
            <dialog class="rrze-calendar-feed-update-dialog" aria-labelledby="rrze-calendar-feed-update-title" aria-describedby="rrze-calendar-feed-update-message">
                <form method="dialog">
                    <h2 id="rrze-calendar-feed-update-title">${escapeHtml(feedUpdateConfig.strings.title)}</h2>
                    <p id="rrze-calendar-feed-update-message" class="rrze-calendar-feed-update-message"></p>
                    <div class="rrze-calendar-feed-update-actions">
                        <button type="button" class="button rrze-calendar-feed-update-cancel">
                            ${escapeHtml(feedUpdateConfig.strings.cancel)}
                        </button>
                        <button type="button" class="button button-primary rrze-calendar-feed-update-confirm">
                            ${escapeHtml(feedUpdateConfig.strings.confirm)}
                        </button>
                    </div>
                </form>
            </dialog>
        `).appendTo(document.body);
        const dialogElement = updateDialog.get(0);
        let confirmedUrl = "";

        function escapeHtml(value) {
            return $("<div>").text(value).html();
        }

        function addToken(url, token) {
            const updateUrl = new URL(url, window.location.href);
            updateUrl.searchParams.set(feedUpdateConfig.tokenQueryArg, token);
            return updateUrl.toString();
        }

        function showError(message) {
            $(".rrze-calendar-feed-update-notice").remove();
            const notice = $("<div>", {
                class: "notice notice-error rrze-calendar-feed-update-notice",
            }).append($("<p>").text(message || feedUpdateConfig.strings.error));

            $(".wrap h1").first().after(notice);
        }

        $(document).on("click", ".rrze-calendar-update-feed", function (event) {
            event.preventDefault();

            const link = $(this);

            if (link.attr("aria-busy") === "true") {
                return;
            }

            const originalText = link.text();
            const targetUrl = link.attr("href");

            link.attr("aria-busy", "true").text(feedUpdateConfig.strings.checking);
            $(".rrze-calendar-feed-update-notice").remove();

            $.post(feedUpdateConfig.ajaxUrl, {
                action: feedUpdateConfig.action,
                nonce: feedUpdateConfig.nonce,
                post_id: link.data("feed-id"),
            })
                .done(function (response) {
                    if (!response.success || !response.data?.token) {
                        showError(response.data?.message);
                        return;
                    }

                    const updateUrl = addToken(targetUrl, response.data.token);

                    if (!response.data.requires_confirmation) {
                        window.location.assign(updateUrl);
                        return;
                    }

                    confirmedUrl = updateUrl;
                    const messageTemplate =
                        response.data.confirmation_type === "cancellation"
                            ? feedUpdateConfig.strings.cancellationMessage
                            : feedUpdateConfig.strings.emptySnapshotMessage;
                    const message = messageTemplate.replace(
                        "%d",
                        response.data.imported_event_count
                    );
                    updateDialog
                        .find(".rrze-calendar-feed-update-message")
                        .text(message);
                    dialogElement.showModal();
                })
                .fail(function (xhr) {
                    showError(xhr.responseJSON?.data?.message);
                })
                .always(function () {
                    link.removeAttr("aria-busy").text(originalText);
                });
        });

        updateDialog
            .find(".rrze-calendar-feed-update-cancel")
            .on("click", function () {
                confirmedUrl = "";
                dialogElement.close();
            });

        updateDialog
            .find(".rrze-calendar-feed-update-confirm")
            .on("click", function () {
                if (confirmedUrl) {
                    window.location.assign(confirmedUrl);
                }
            });

        updateDialog.on("close", function () {
            confirmedUrl = "";
        });
    }

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
