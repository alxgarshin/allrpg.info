/** Управление заявками (и игрока, и мастера) */

window.lockedRoomsIds = defaultFor(window.lockedRoomsIds, []);
window.feesDone = defaultFor(window.feesDone, []);

if (el('form#form_application') || el('form#form_myapplication')) {
    /** Кнопка "К комментариям" */
    _('button#to_comments').on('click', function () {
        const remSize = Number(getComputedStyle(document.body, "").fontSize.match(/(\d*(\.\d*)?)px/)[1]) / 1.5;

        scrollWindow(_('div.conversation_form_data textarea[name="content"]').offset().top - (mobilecheck() ? (remSize * 8.3) : 0));
    });

    /** Изменение порядка вывода комментариев на календарный на основе их id */
    _('a#change_comments_order').on('click', function () {
        _(this).remove();

        _('div.application_conversations div.conversation_message_container div.conversation_form').remove();
        _('div.application_conversations a.show_hidden').click();
        _('div.application_conversations div.conversation_message.child').removeClass('child');
        _('div.application_conversations div.conversation_message').addClass('bottom_border');

        _('div.application_conversations').find('div.conversation_message_container')?.each(function () {
            const parent = _(this);
            const parentClassList = this.classList;

            parent.children()?.each(function () {
                const self = _(this, { noCache: true });

                parent.insert(this, 'before');

                parentClassList.forEach(function (className) {
                    if (className !== 'conversation_message_container') {
                        self.addClass(className);
                    }
                })

                self.destroy();
            });

            parent.remove();
        });

        const container = _('div.application_conversations');
        const items = Array.from(elAll('div.application_conversations div.conversation_message'));

        items.sort(sortMessages);
        items.forEach(item => {
            container.insert(item, 'begin');
        });
    });

    /** Отметка пунктов в списке комнат поселения, что они недоступны. Нужно для того, чтобы никакое переключение взносов не сделало их активными. */
    if (lockedRoomsIds.length > 0) {
        _('select[name^="rooms_selector"] option').each(function () {
            const self = _(this);

            if (lockedRoomsIds.includes(parseInt(self.attr('value')))) {
                self.attr('locked', true);
            }
        });
    }

    /** Обновление списка соседей по поселению */
    _('select[name^="rooms_selector"]').on('change', function () {
        const self = _(this);

        actionRequest({
            action: `${(el('form#form_myapplication') ? 'myapplication' : 'application')}/get_list_of_room_neighboors`,
            obj_id: self.val()
        }, self);
    });

    _('select[name^="rooms_selector"]').change();
}

/** История изменений заявки */
if (el('div.history_view')) {
    _('div.history_view div.field').hide();
    _('div.history_view h1:not(.form_header)').hide();
    _('div.history_view_old div.field').hide();
    _('div.history_view_old h1:not(.form_header)').hide();

    _('div.history_view div.field').each(function () {
        const self = _(this);
        const copyDiv = _(`div.history_view_old div.field[id="${self.attr('id')}"]`);

        if (copyDiv.asDomElement()) {
            const text1 = self.find('div.fieldvalue').html().replace(/\n/g, '').br2nl();
            const text2 = copyDiv.find('div.fieldvalue').html().replace(/\n/g, '').br2nl();

            if (text1.replace(/\n/g, '') != text2.replace(/\n/g, '')) {
                self.show();
                copyDiv.show();
                self.prev('h1').show();
                copyDiv.prev('h1').show();

                if (text2.length > 15 && !text2.match(/sbi sbi-check/) && !text2.match(/sbi sbi-times/)) {
                    diffApply(copyDiv, text1, text2);
                } else if (text2.match(/sbi sbi-check/) || text2.match(/sbi sbi-times/)) {
                    //просто показываем и ничего не делаем
                } else {
                    copyDiv.find('div.fieldvalue').html(`<span class="diff_deleted">${text2.nl2br()}</span>`);
                }

                //выравниваем высоту объектов в history_view_old
                if (copyDiv.outerHeight() < self.outerHeight()) {
                    if (self.find('.sbi')) {
                        copyDiv.outerHeight(self.outerHeight() - 3);
                    } else {
                        copyDiv.outerHeight(self.outerHeight());
                    }
                }

                if (copyDiv.outerHeight() > self.outerHeight()) {
                    if (self.find('.sbi')) {
                        self.outerHeight(copyDiv.outerHeight() - 3);
                    } else {
                        self.outerHeight(copyDiv.outerHeight());
                    }
                }
            }
        }
    });
}

if (withDocumentEvents) {
    getHelperData('div.to_player div.conversation_message', 'get_application_unread_people');
    getHelperData('div.from_player div.conversation_message', 'get_application_unread_people');

    _arSuccess('get_list_of_room_neighboors', function (jsonData, params, target) {
        _('div[id^="div_room_neighboors"]').html(jsonData['response_data']);
    })

    _arSuccess('get_list_of_groups', function (jsonData, params, target) {
        target = _('[id="choice_project_group_ids[0]"]');

        doDropfieldRefresh = false;

        _each(jsonData['response_data']['remove'], function (value) {
            target.find(convertName(`input#project_group_ids[0][${value}]`)).checked(false).change();
        });

        _each(jsonData['response_data']['add'], function (value) {
            target.find(convertName(`input#project_group_ids[0][${value}]`)).checked(true).change();
        });

        doDropfieldRefresh = true;

        _('[id="selected_project_group_ids[0]"]').trigger('refresh');
    })

    _arSuccess('get_application_unread_people', function (jsonData, params, target) {
        getHelpersSuccess(jsonData, params, target);
    })
}

/** Подгрузка библиотеки сравнения */
function diffApply(element, text1, text2) {
    const scriptName = 'diffApply';

    dataElementLoad(
        scriptName,
        element,
        () => {
            getScript('/vendor/diff/diff_match_patch.js').then(() => {
                dataLoaded['libraries'][scriptName] = true;
            });
        },
        function () {
            const diffClass = new Diff_match_patch();
            let text = diffClass.diff_main(text1, text2);
            diffClass.diff_cleanupSemantic(text);

            this.find('div.fieldvalue').html(diffClass.diff_prettyHtml(text));
        }
    );
}

/** Сортировка сообщений для календарного порядка */
function sortMessages(a, b) {
    return parseInt(_(b, { noCache: true }).attr('message_id')) > parseInt(_(a, { noCache: true }).attr('message_id')) ? 1 : -1;
}

/** Блокирование всех остальных возможностей выбора проживания, если у типа взноса установлен определенный тип проживания, кроме тех, у которых выставлен locked */
window.feeLockedRoomCheck = function () {
    if (feeLockedRoom.length) {
        //если у нас есть feeLockedRoom, значит, есть какие-то ограничения в соотношении взнос-тип поселения
        _('select[name^="rooms_selector"] option:not([value=""])').disable();

        _each(feeLockedRoom, function (element, index) {
            if (
                (
                    _(`input[type="checkbox"][name="project_fee_ids[0][${index}]"]`).asDomElements().length === 0 &&
                    feesDone.includes(index)
                ) ||
                _(`input[type="checkbox"][name="project_fee_ids[0][${index}]"]`).is(':checked')
            ) {
                _each(element, function (room_id) {
                    _(`select[name^="rooms_selector"] option[value="${room_id}"]:not([locked])`).enable();
                });
            }
        });
    }
}