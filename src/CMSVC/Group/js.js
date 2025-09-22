/** Группы персонажей */

if (el('form#form_group')) {
    /** Отключение кнопки "Удалить" в группе, которая запрещена к изменению */
    _('input[name="disable_changes[0]"]').on('change', function () {
        const self = _(this);

        if (self.is(':checked')) {
            _('form#form_group').find('button.careful').disable();
        } else {
            _('form#form_group').find('button.careful').enable();
        }
    });
    _('input[name="disable_changes[0]"]').change();

    /** Выставление мастера и локаций после в соответствие с родительской группой */
    window.grgft = true;

    _('select[name="parent[0]"]').on('change', function () {
        const self = _(this);

        if (_('input[name="id[0]"]').val() > 0 && window.grgft) {
            window.grgft = false;
        } else {
            actionRequest({
                action: 'group/get_responsible_gamemaster',
                obj_id: self.val()
            });
        }

        actionRequest({
            action: 'group/get_child_groups',
            obj_id: self.val(),
            group_id: _("input[name='id[0]']").val()
        });
    });
    _('select[name="parent[0]"]').change();

    fraymDragDropApply(`div[id="div_link_to_characters[0]"] a`, {
        sortable: true,
        dragEnd: function () {
            const self = _(this);

            const link = self.attr('href');
            const linkRegexp = /\/(\d+)\//g;
            const values = linkRegexp.exec(link);
            const link2 = self.prev()?.attr('href');
            let afterObjId = null;

            if (link2) {
                const link2Regexp = /\/(\d+)\//g;
                const values2 = link2Regexp.exec(link2);

                afterObjId = values2[1];
            }

            let trClassNumber = 1;
            _(`div[id='div_link_to_characters[0]'] a`).each(function () {
                _(this).removeClass('string1').removeClass('string2').addClass(`string${trClassNumber}`);
                trClassNumber = 3 - trClassNumber;
            });

            const groupId = _('input[name="id[0]"]').val();

            actionRequest({
                action: 'group/change_character_code',
                obj_id: values[1],
                group_id: groupId,
                after_obj_id: afterObjId
            });
        }
    })
}

if (withDocumentEvents) {
    _arSuccess('get_child_groups', function (jsonData, params, target) {
        target = _('select[name="code[0]"]');

        target.empty();

        _each(jsonData['response_data'], function (value, key) {
            target.insert(`<option value="${key}">${value}</option>`, 'append');
        });

        target.val(parseInt(jsonData['response_data_selected']));
    })

    _arSuccess('get_responsible_gamemaster', function (jsonData, params, target) {
        if (parseInt(jsonData['response_data']) > 0) {
            _('select[name="responsible_gamemaster_id[0]"]').val(parseInt(jsonData['response_data']));
        }
    })

    _arSuccess('change_character_code', function () { })
}