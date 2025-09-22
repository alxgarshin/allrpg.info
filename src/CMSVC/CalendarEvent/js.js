/** События календаря */

/** Управление фото- и видео-материалами */
if (el('a.inner_add_something_button.add_photo')) {
    _('a.inner_add_something_button.add_photo').on('click', function (e) {
        e.stopPropagation();

        const btn = _(this);
        const calendarEventId = btn.attr('obj_id');

        createPseudoPrompt(
            `<div><input type="text" class="obligatory" name="add_photo_link" placehold="${LOCALE.addPhoto.link}" tabIndex="1"></div><input type="text" name="add_photo_name" placehold="${LOCALE.addPhoto.name}" tabIndex="2"><input type="text" name="add_photo_thumb" placehold="${LOCALE.addPhoto.thumb}" tabIndex="3"><input type="text" name="add_photo_author" placehold="${LOCALE.addPhoto.author}" tabIndex="4"></div>`,
            LOCALE.addCapitalized,
            [
                {
                    text: LOCALE.addCapitalized,
                    class: 'main',
                    click: function () {
                        const self = getNotyDialogDOM();
                        const input = self.find('input[name="add_photo_link"]');
                        const input2 = self.find('input[name="add_photo_name"]');
                        const input3 = self.find('input[name="add_photo_thumb"]');
                        const input4 = self.find('input[name="add_photo_author"]');

                        if (checkHttpUrl(input.val())) {
                            actionRequest({
                                action: 'calendar_event_gallery/add_calendar_event_gallery',
                                link: input.val(),
                                name: input2.val(),
                                thumb: input3.val(),
                                author: input4.val(),
                                obj_id: calendarEventId,
                            });
                        } else {
                            showMessage({
                                text: LOCALE.addPhoto.linkNotEntered,
                                type: 'error',
                                timeout: 5000
                            });

                            input.focus();
                        }
                    }
                }
            ],
            null,
            function () {
                const self = getNotyDialogDOM();
                const input = self.find('input[name="add_photo_link"]');
                const input2 = self.find('input[name="add_photo_name"]');
                const input3 = self.find('input[name="add_photo_thumb"]');
                const input4 = self.find('input[name="add_photo_author"]');

                fraymPlaceholder(input);
                fraymPlaceholder(input2);
                fraymPlaceholder(input3);
                fraymPlaceholder(input4);
            });
    });

    _('a.calendar_event_photo_video_edit').on('click', function (e) {
        e.stopPropagation();
        const self = _(this).closest('div.calendar_event_photo_video');
        const calendarEventId = self.attr('obj_id');

        createPseudoPrompt(
            `<div><input type="text" class="obligatory" name="add_photo_link" placehold="${LOCALE.addPhoto.link}" value="${self.find(`img`).closest(`a`).attr(`href`)}" tabIndex="1"><input type="text" name="add_photo_name" placehold="${LOCALE.addPhoto.name}" value="${self.find(`div.calendar_event_photo_video_description`).find(`span`)?.text() ?? ''}" tabIndex="2"><input type="text" name="add_photo_thumb" placehold="${LOCALE.addPhoto.thumb}" value="${self.find(`img`).attr(`src`)}" tabIndex="3"><input type="text" name="add_photo_author" placehold="${LOCALE.addPhoto.author}" value="${self.attr(`author`)}" tabIndex="4"></div>`,
            LOCALE.editCapitalized,
            [
                {
                    text: LOCALE.saveCapitalized,
                    class: 'main',
                    click: function () {
                        const self = getNotyDialogDOM();
                        const input = self.find('input[name="add_photo_link"]');
                        const input2 = self.find('input[name="add_photo_name"]');
                        const input3 = self.find('input[name="add_photo_thumb"]');
                        const input4 = self.find('input[name="add_photo_author"]');

                        if (checkHttpUrl(input.val())) {
                            actionRequest({
                                action: 'calendar_event_gallery/change_calendar_event_gallery',
                                link: input.val(),
                                name: input2.val(),
                                thumb: input3.val(),
                                author: input4.val(),
                                obj_id: calendarEventId,
                            });
                        } else {
                            showMessage({
                                text: LOCALE.addPhoto.linkNotEntered,
                                type: 'error',
                                timeout: 5000
                            });

                            input.focus();
                        }
                    }
                }
            ],
            null,
            function () {
                const self = getNotyDialogDOM();
                const input = self.find('input[name="add_photo_link"]');
                const input2 = self.find('input[name="add_photo_name"]');
                const input3 = self.find('input[name="add_photo_thumb"]');
                const input4 = self.find('input[name="add_photo_author"]');

                fraymPlaceholder(input);
                fraymPlaceholder(input2);
                fraymPlaceholder(input3);
                fraymPlaceholder(input4);
            });
    });
}

if (withDocumentEvents) {
    _arSuccess('add_calendar_event_gallery', function (jsonData, params, target) {
        notyDialog?.close();

        updateState(currentHref);
    })

    _arSuccess('change_calendar_event_gallery', function (jsonData, params, target) {
        notyDialog?.close();

        updateState(currentHref);
    })

    _arSuccess('delete_calendar_event_gallery', function (jsonData, params, target) {
        updateState(currentHref);
    })
}