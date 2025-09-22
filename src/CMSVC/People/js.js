/** Профиль */

/** Изменение статуса в профиле */
if (el('span.user_status_switcher')) {
    _('span.user_status_switcher.active').on('click', function () {
        const self = _(this);

        createPseudoPrompt('<div><input name="change_status" id="change_status"></div>', LOCALE.newStatusHeader, [
            {
                text: LOCALE.approveCapitalized,
                class: 'main',
                click: function () {
                    actionRequest({
                        action: 'user/change_status',
                        obj_id: 'none',
                        value: _('#change_status').val()
                    }, self);

                    notyDialog?.close();
                }
            }
        ]);
    });
}

if (withDocumentEvents) {
    _arSuccess('change_status', function (jsonData, params, target) {
        if (jsonData['response_text']) {
            target.text(jsonData['response_text']);
        } else {
            target.html(`<i>${LOCALE.statusClickToChange}</i>`);
        }
    })
}