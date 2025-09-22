/** Задачи и события */

if (el('form#form_task select[name^=obj_id]') || el('form#form_event select[name^=obj_id]')) {
    const type = el('input[type="hidden"][name^=obj_type]') ? _('input[type="hidden"][name^=obj_type]').val() : 'project';

    _('select[name^=obj_id]').on('change', function () {
        const self = _(this);

        actionRequest({
            action: 'project/get_community_or_project_members_list',
            obj_type: type,
            obj_id: self.val()
        }, self);

        if (el('form#form_task select[name^=obj_id]')) {
            const taskId = el('input[name="id[0]"]') ? _('input[name="id[0]"]').val() : 0;

            actionRequest({
                action: 'project/get_community_or_project_tasks_list',
                obj_type: type,
                obj_id: self.val(),
                task_id: taskId
            }, self);
        }
    });

    _('select[name^=responsible], input[name^=user_id]').on('change', function () {
        checkDatesAvailability();
    });

    _('input[name^=dateFrom]').on('change', function () {
        dateFromLogicTimepicker(this);
        checkDatesAvailability();
    });

    _('input[name^=dateTo]').on('change', function () {
        dateToLogicTimepicker(this);
        checkDatesAvailability();
    });

    checkDatesAvailability();
}

if (withDocumentEvents) {
    _arSuccess('get_community_or_project_members_list', function (jsonData, params, target) {
        target = _('select[name^=responsible]');
        const target2 = _('[id^=selected_user_id]');
        const target3 = _('[id^=choice_user_id]');

        if (target.asDomElement()) {
            target.empty();
            target.insert(`<option value="">${LOCALE.dropFieldChooseFromFound}</option>`, 'end');

            _each(jsonData['response_data'], function (value) {
                target.insert(`<option value="${value[0]}"${value[2] == 'me' ? ` selected` : ``}>${value[1]}</option>`, 'end');
            });
        }

        if (target2.asDomElement() && target3.asDomElement()) {
            target2.empty();
            target2.insert(`<div>${LOCALE.dropFieldChoose}</div>`, 'append');
            target3.find('.dropfield2_field')?.remove();

            _each(jsonData['response_data'], function (value) {
                target3.insert(`<div class="dropfield2_field"><input type="checkbox" name="user_id[0][${value[0]}]" id="user_id[0][${value[0]}]" class="inputcheckbox"${(value[2] == 'me' ? ` checked` : ``)}><label for="user_id[0][${value[0]}]">${(value[3] !== undefined ? `<img src="${value[3]}" width="30" title="${value[1]}">` : ``)}${value[1]}</label></div>`, 'append');
            });

            target3.closest('.dropfield2')?.prev('.dropfield')?.trigger('refresh');
        }

        if (target.asDomElement()) {
            checkDatesAvailability();
        }
    })

    _arSuccess('check_dates_availability', function (jsonData, params, target) {
        _('div#unavailable_users').remove();

        if (jsonData['response_data']['users']) {
            //получили данные пользователей, недоступных в данное время
            let dateFrom = false;
            let dateTo = false;

            if (jsonData['response_data']['closest_interval']['dateFrom']) {
                dateFrom = new Date(jsonData['response_data']['closest_interval']['dateFrom']);
            }

            if (jsonData['response_data']['closest_interval']['dateTo']) {
                dateTo = new Date(jsonData['response_data']['closest_interval']['dateTo']);
            }

            let html = `<div class="field" id="unavailable_users"><div class="fieldname red" id="name_unavailable_users">${LOCALE.unavailable_users}</div><div class="fieldvalue" id="div_unavailable_users">`;

            if (dateFrom || dateTo) {
                html += `<div id="closest_acceptable_time">${LOCALE.closest_acceptable_time}<br><a id="dates_closest" ${(dateFrom ? ` dateFrom="${dateFormat(dateFrom, true)}"` : ``)}${(dateTo ? ` dateTo="${dateFormat(dateTo, true)}"` : ``)}>${dateFormatText(dateFrom, true, getLocale())}${(dateTo ? (dateFrom ? '-' : '') + dateFormatText(dateTo, true, getLocale()) : '')}</a></div>`;
            }

            _each(jsonData['response_data']['users'], function (user_data) {
                html += user_data['html'];
            });

            html += '<div class="clear"></div></div></div>';
            _('div[id^=field_do_not_count_as_busy]').insert(html, 'after');

            if (dateFrom || dateTo) {
                _('a#dates_closest').on('click', function () {
                    const self = _(this);

                    if (self.attr('dateFrom').length) {
                        _('input[name^=dateFrom]').val(self.attr('dateFrom'));
                    }

                    if (self.attr('dateTo').length) {
                        _('input[name^=dateTo]').val(self.attr('dateTo'));
                    }

                    checkDatesAvailability();
                });
            }
        }
    })

    _arError('check_dates_availability', function (jsonData, params, target, error) {
        _('div#unavailable_users').remove();
    })

    _arSuccess('get_community_or_project_tasks_list', function (jsonData, params, target) {
        target = _('select[name^=parent_task]');
        const target2 = _('select[name^=following_task]');

        if (target.asDomElement() && target2.asDomElement()) {
            target.empty();
            target2.empty();
            target.insert(`<option value="">${LOCALE.dropFieldChooseFromFound}</option>`, 'end');
            target2.insert(`<option value="">${LOCALE.dropFieldChooseFromFound}</option>`, 'end');

            if (jsonData['response_data']['parent_task'] !== undefined) {
                _each(jsonData['response_data']['parent_task'], function (value) {
                    target.insert(`<option value="${value[0]}">${value[1]}</option>`, 'append');
                });
            }

            if (jsonData['response_data']['following_task'] !== undefined) {
                _each(jsonData['response_data']['following_task'], function (value) {
                    target2.insert(`<option value="${value[0]}">${value[1]}</option>`, 'append');
                });
            }
        }
    })

    _arSuccess('get_task_unread_people', function (jsonData, params, target) {
        getHelpersSuccess(jsonData, params, target);
    })
}

/** Проверка доступности дат пользователей при определении дат в задаче */
function checkDatesAvailability() {
    const objType = el('input[name^="obj_type"]') ? _('input[name^="obj_type"]').val() : 'task';
    let objId = el('input[name^="obj_id"]') ? _('input[name^="obj_id"]').val() : 0;
    const dateFrom = _('input[name^="date_from"]').val();
    const dateTo = _('input[name^="date_to"]').val();
    const responsibleId = _('select[name^="responsible"]').val();
    const userIds = [];

    _('input[name^="user_id"]:checked').each(function () {
        const $val = _(this).attr('name').match(/(\d+)]$/);

        userIds.push($val[1]);
    });

    actionRequest({
        action: 'task/check_dates_availability',
        obj_type: objType,
        obj_id: objId,
        date_from: dateFrom,
        date_to: dateTo,
        responsible_id: responsibleId,
        user_ids: userIds
    });
}