/** Сюжеты и завязки */

/** Автоподгрузка всех возможных значений поля "Для" и "Про" в завязку */
if (el('form#form_plot')) {
    /** Выставление групп в соответствии с вводом имени игрока / заявки / персонажа */
    if (el('input[name="search_groups_by_name[0]"]')) {
        fraymAutocompleteApply(el('input[name="search_groups_by_name[0]"]'), {
            source: '/helper_application/',
            select: function () {
                actionRequest({
                    action: 'group/get_list_of_groups_by_character_or_application',
                    obj_id: this.obj_id,
                    obj_type: this.obj_type
                });
            }
        });

        if (el('input[name="search_groups_by_name_default_application[0]"]')) {
            actionRequest({
                action: 'group/get_list_of_groups_by_character_or_application',
                obj_id: _('input[name="search_groups_by_name_default_application[0]"]').val(),
                obj_type: 'application'
            });
        } else if (el('input[name="search_groups_by_name_default_character[0]"]')) {
            actionRequest({
                action: 'group/get_list_of_groups_by_character_or_application',
                obj_id: _('input[name="search_groups_by_name_default_character[0]"]').val(),
                obj_type: 'character'
            });
        }
    }
}

if (el('form#form_plot_plot')) {
    window.glops = true;

    _('form#form_plot_plot select[name="parent[0]"]').on('change', function () {
        const self = _(this);

        if (_('input[name="id[0]"]').val() > 0 && window.glops) {
            window.glops = false;
        } else {
            const fieldFor = _('[id="selected_applications_1_side_ids[0]"]');
            const fieldFor2 = _('[id="choice_applications_1_side_ids[0]"]');
            const fieldAbout = _('[id="selected_applications_2_side_ids[0]"]');
            const fieldAbout2 = _('[id="choice_applications_2_side_ids[0]"]');

            fieldFor.empty().insert(`<div>${LOCALE.searchOngoing}</div>`, 'append');
            fieldAbout.empty().insert(`<div>${LOCALE.searchOngoing}</div>`, 'append');

            fieldFor2.find('.dropfield2_field')?.remove();
            fieldAbout2.find('.dropfield2_field')?.remove();

            actionRequest({
                action: 'plot/get_list_of_plot_sides',
                obj_id: self.val()
            });
        }

        if (self.val() > 0) {
            _('a#go_to_plot').attr('href', `/plot/plot/${self.val()}/act=edit`).show();
        } else {
            _('a#go_to_plot').attr('href', '').hide();
        }
    });
    _('select[name="parent[0]"]').change();

    contentSearchForWysiwygApply(el('[id="content[0]"]'));
}

if (withDocumentEvents) {
    _arSuccess('get_list_of_groups_by_character_or_application', function (jsonData, params, target) {
        target = _('[id^="choice_project_character_ids"]');
        doDropfieldRefresh = false;

        target.find('input:checked:not(:disabled)')?.each(function () {
            this.checked = false;
        });

        _each(jsonData['response_data']['add'], value => {
            target.find(convertName(`input#project_character_ids[0][group${value}]`)).asDomElement().checked = true;
        });

        doDropfieldRefresh = true;

        _('[id="selected_project_character_ids[0]"]').trigger('refresh');
    })

    _arSuccess('get_list_of_plot_sides', function (jsonData, params, target) {
        const fieldFor = _('[id="selected_applications_1_side_ids[0]"]');
        const fieldFor2 = _('[id="choice_applications_1_side_ids[0]"]');
        const fieldAbout = _('[id="selected_applications_2_side_ids[0]"]');
        const fieldAbout2 = _('[id="choice_applications_2_side_ids[0]"]');

        fieldFor.empty().insert(`<div>${LOCALE.dropFieldChoose}</div>`, 'append');
        fieldAbout.empty().insert(`<div>${LOCALE.dropFieldChoose}</div>`, 'append');

        fieldFor2.find('.dropfield2_field')?.remove();

        _each(jsonData['response_data'], function (value) {
            fieldFor2.insert(`<div class="dropfield2_field"${(value[2] !== undefined ? ` style="padding-left: ${parseInt(value[2]) * 2}em"` : ``)}><input type="checkbox" name="applications_1_side_ids[0][${value[0]}]" id="applications_1_side_ids[0][${value[0]}]" class="inputcheckbox"><label for="applications_1_side_ids[0][${value[0]}]"> ${value[1]}</label></div>`, 'append');
        });
        fieldFor2.closest('.dropfield2').prev('.dropfield').trigger('refresh');

        fieldAbout2.find('.dropfield2_field')?.remove();

        _each(jsonData['response_data'], function (value) {
            fieldAbout2.insert(`<div class="dropfield2_field"${(value[2] !== undefined ? ` style="padding-left: ${parseInt(value[2]) * 2}em"` : ``)}><input type="checkbox" name="applications_2_side_ids[0][${value[0]}]" id="applications_2_side_ids[0][${value[0]}]" class="inputcheckbox"><label for="applications_2_side_ids[0][${value[0]}]"> ${value[1]}</label></div>`, 'append');
        });
        fieldAbout2.closest('.dropfield2').prev('.dropfield').trigger('refresh');
    })
}

/** Автозаполнение при вводе @ в поле текста завязки */
function contentSearchForWysiwygApply(element) {
    ifDataLoaded(
        'fraymWysiwygApply',
        'contentSearchForWysiwygApply',
        element,
        function () {
            setTimeout(function (element) {

                const wysiwyg = wysiwygObjs.get(_(element).attr('id'));

                wysiwyg.on('text-change', function () {
                    let html = wysiwyg.getText();

                    if (html.indexOf('@') >= 0) {
                        let curpos = 0;

                        const selection = wysiwyg.getSelection();

                        if (selection) {
                            curpos = selection.index;
                        }

                        if (curpos <= html.indexOf('@')) {
                            curpos = html.indexOf('@') + 1;
                        }

                        let indices = [];
                        for (let pos = html.indexOf('@'); pos !== -1; pos = html.indexOf('@', pos + 1)) {
                            indices.push(pos);
                        }

                        let closestIndice = 0;
                        for (let i = 0; i < indices.length; i++) {
                            if (indices[i] >= closestIndice && indices[i] < curpos) {
                                closestIndice = indices[i];
                            }
                        }

                        const t = html.substring(closestIndice + 1, curpos - 1);
                        const l = html.substring(curpos - 1, curpos);

                        if (t !== '' && l === ' ') {
                            _(`div[id="selected_applications_2_side_ids[0]"] div.options`).each(function () {
                                const element = this;

                                if (element.textContent.includes(t)) {
                                    const name = element.textContent;
                                    let rel = _(element).find('a[rel]').attr('rel');
                                    rel = rel.replace('applications_2_side_ids[0][', '');
                                    rel = rel.replace(']', '');

                                    const replaceText = `${name}[${rel}]`;

                                    wysiwyg.deleteText(closestIndice + 1, t.length, 'silent');
                                    wysiwyg.insertText(closestIndice + 1, replaceText, 'silent');

                                    const newIndex = closestIndice + 2 + replaceText.length;

                                    setTimeout(function (wysiwyg, newIndex) {
                                        wysiwyg.setSelection(newIndex);
                                    }, 30, wysiwyg, newIndex);
                                }
                            })
                        }
                    }
                });
            }, 1000, this);
        }
    );
}