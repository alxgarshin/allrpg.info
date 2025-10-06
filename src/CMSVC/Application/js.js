loadJsComponent('application').then(function () {
    /** Мастерское управление заявками */

    /** Выборка заявок */
    if (el('div.kind_application')) {
        _('input#application_search').on('keyup', function () {
            //ждем немного дальнейшего ввода
            const self = _(this);

            window.clearTimeout(window['application_search_timeout']);
            window['application_search_timeout'] = setTimeout(function () {
                if (self.val() != '') {
                    actionRequest({
                        action: 'application/get_applications_table',
                        obj_name: self.val()
                    });
                } else {
                    _('table.menutable.application').show();
                    _('div.pagecounter').show();
                    _('table.menutable.application').prev('table.menutable.applications_search_table')?.remove();
                }
            }, 500);
        });

        _('input#application_comments_search').on('keyup', function () {
            //ждем немного дальнейшего ввода
            const self = _(this);

            window.clearTimeout(window['application_comments_search_timeout']);
            window['application_comments_search_timeout'] = setTimeout(function () {
                if (self.val() != '') {
                    actionRequest({
                        action: 'application/get_applications_comments_table',
                        obj_name: self.val()
                    });
                } else {
                    _('table.menutable.application').show();
                    _('div.pagecounter').show();
                    _('table.menutable.application').prev('table.menutable.applications_search_table')?.remove();
                }
            }, 500);
        });
    }

    /** Выставление спец.группы всем отфильтрованным заявкам */
    if (el('a#set_special_group')) {
        _('a#set_special_group').on('click', function () {
            const select = _(el('select[name="master_group_selector"]').cloneNode(true)).attr('name', 'master_group_selector_visible');

            createPseudoPrompt(`<div>${select.asDomElement().outerHTML}</div>`,
                LOCALE.setSpecialGroup,
                [
                    {
                        text: LOCALE.approveCapitalized,
                        class: 'main',
                        click: function () {
                            actionRequest({
                                action: 'application/set_special_group',
                                obj_id: _('select[name="master_group_selector_visible"]').val(),
                                filter: _('a#set_special_group').attr('filter')
                            });
                        }
                    }
                ]);
        });
    }

    /** Переход на генератор документов из заявок */
    if (el('a#applications_generate_documents')) {
        _('a#applications_generate_documents').on('click', function () {
            const select = _(el('select[name="documents_generator_selector"]').cloneNode(true)).attr('name', 'documents_generator_selector_visible');

            createPseudoPrompt(`<div>${select.asDomElement().outerHTML}</div>`,
                LOCALE.generateDocuments, [
                {
                    text: LOCALE.approveCapitalized,
                    class: 'main',
                    click: function () {
                        window.open(`/document/template_id=${_('select[name="documents_generator_selector_visible"]').val()}&action=generate_documents&application_id[0]=filter`, "_blank");
                    }
                }
            ]);
        });
    }

    if (el('form#form_application')) {
        /** Ссылка, исправляющая имя персонажа в сетке */
        _('a#fix_character_name_by_sorter').on('click', function () {
            const self = _(this);

            actionRequest({
                action: 'application/fix_character_name_by_sorter',
                obj_id: self.attr('obj_id'),
                name: self.closest('div.field')?.find('input')?.val()
            }, self);
        });

        /** Изменение состояния "взнос сдан" */
        _('input[name="money[0]"], input[name="money_provided[0]"]').on('keyup change', function () {
            const self = _('div[id="div_money_paid[0]"]');

            if (parseInt(_('input[name="money_provided[0]"]').val()) >= parseInt(_('input[name="money[0]"]').val())) {
                self.html('<span class="sbi sbi-check"></span>');
            } else {
                self.html('<span class="sbi sbi-times"></span>');
            }

            loadSbiBackground();
        });

        /** Выставление групп в соответствии с выбранным персонажем и переход к персонажу */
        let projectCharacterId = _('input[name="project_character_id[0]"]:checked').val();

        _('input[name="project_character_id[0]"]').on('click', function () {
            const self = _(this);

            if (self.val() != projectCharacterId) {
                actionRequest({
                    action: 'myapplication/get_list_of_groups',
                    obj_id: self.val(),
                    prev_obj_id: projectCharacterId
                });

                projectCharacterId = self.val();
            }

            if (projectCharacterId > 0) {
                _('div[id="help_project_character_id[0]"]').show().find('a')?.first()?.attr('href', `/character/${projectCharacterId}/`);
            } else {
                _('div[id="help_project_character_id[0]"]').hide();
            }
        });
        _('input[name="project_character_id[0]"]:checked').click();

        /** Окошко передачи заявки */
        _('a#transfer_application').on('click', function () {
            const btn = _(this);

            createPseudoPrompt(
                `<div><input type="text" name="user_name" placehold="${LOCALE.enterName}" obj_id="${btn.attr('obj_id')}"><input type="hidden" name="user_id"></div>`,
                LOCALE.searchCapitalized,
                [
                    {
                        text: LOCALE.inviteCapitalized,
                        class: 'main',
                        click: function () {
                            const self = getNotyDialogDOM();
                            const input = self.find('input[type=text]');
                            const input2 = self.find('input[type=hidden]');

                            if (input2.val() > 0) {
                                actionRequest({
                                    action: 'application/transfer_application',
                                    user_id: input2.val(),
                                    obj_id: input.attr('obj_id')
                                });
                            } else {
                                showMessage({
                                    text: LOCALE.wrongUser,
                                    type: 'error',
                                    timeout: 5000
                                });
                            }
                        }
                    }
                ],
                null,
                function () {
                    const self = getNotyDialogDOM();
                    const input = self.find('input[type=text]');
                    const input2 = self.find('input[type=hidden]');

                    fraymPlaceholder(input);

                    fraymAutocompleteApply(
                        input,
                        {
                            source: '/helper_users_list/?full_search=1',
                            makeEmptySearches: true,
                            select: function () {
                                const self = getNotyDialogDOM();

                                input2.val(this.id);

                                self.find('button.main').click();
                            },
                            change: function (value) {
                                this.options.minLength = isInt(value) ? 1 : 3;

                                if (!value) {
                                    input2.val(null);
                                }
                            }
                        }
                    );

                    input.trigger('activate');
                });
        });

        _('a#transfer_application_cancel').on('click', function () {
            actionRequest({
                action: 'application/transfer_application_cancel',
                obj_id: _(this).attr('obj_id')
            });
        });

        /** Проверка на наличие неотправленного коммента при сохранении заявки */
        let commentsChecked = false;

        _('div.conversation_form_data textarea[name="content"]').on('change', function () {
            const self = _(this);

            if (self.val()) {
                blockDefaultSubmit = true;
                commentsChecked = false;
            }
        });

        _('div.conversation_form_data button.main').on('click', function () {
            const self = _(this);

            blockDefaultSubmit = false;
            commentsChecked = true;

            self.closest('form').trigger('submit');
        });

        _('form#form_application').on('submit', function (e) {
            if (!commentsChecked && blockDefaultSubmit) {
                if (_('div.conversation_form_data textarea[name="content"]')?.val() && _('input[id="go_back_after_save[0]"]').is(':checked')) {
                    fraymNotyPrompt(
                        null,
                        LOCALE.leavingApplicationWithComment,
                        function (dialog) {
                            blockDefaultSubmit = false;
                            commentsChecked = true;

                            _('form#form_application').trigger('submit');

                            dialog.close();
                        },
                        function (dialog) {
                            scrollWindow(_('div.conversation_form_data textarea[name="content"]').offset().top);

                            dialog.close();
                        }
                    );
                } else {
                    blockDefaultSubmit = false;
                    commentsChecked = true;

                    _('form#form_application').trigger('submit');
                }
            }
        });

        if (el('form#form_application input[type="checkbox"][name^="project_fee_ids[0]"]')) {
            //есть мультивыбор опций взноса
            _('form#form_application input[type="checkbox"][name^="project_fee_ids[0]"]').on('change', function () {
                //меняем данные, только когда сданный взнос меньше требуемого
                if (parseInt(_('input[name="money_provided[0]"]').val()) < parseInt(_('input[name="money[0]"]').val())) {
                    let money = 0;

                    _('form#form_application input[type="checkbox"][name^="project_fee_ids[0]"]').each(function () {
                        const self = _(this);

                        if (self.is(':checked')) {
                            const name = self.attr('name');
                            const label = _(`label[for="${name}"]`).text();

                            if (label.match(/-\d+$/)) {
                                money -= parseInt(label.match(/\d+$/));
                            } else {
                                money += parseInt(label.match(/\d+$/));
                            }
                        }
                    });

                    _('input[name="money[0]"]').val(money).trigger('change');
                }

                window.feeLockedRoomCheck();
            });

            _('form#form_application input[type="checkbox"][name^="project_fee_ids[0]"]').change();
        }
    }

    if (withDocumentEvents) {
        _arSuccess('fix_character_name_by_sorter', function (jsonData, params, target) {
            const character_id = params['obj_id'];
            const optionsDiv = _(`a[rel="project_character_id[0][${character_id}]"`).closest('div.options');
            const label = _(`label[for="project_character_id[0][${character_id}]"`);

            optionsDiv.html(optionsDiv.html().replace(/^[^<]+/, `${params['name']} `));
            label.html(label.html().replace(/^[^<]+/, `${params['name']} `));
            target.remove();
        })

        _arSuccess('get_applications_table', function (jsonData, params, target) {
            _('table.menutable.application').hide();
            _('div.pagecounter').hide();
            _('table.menutable.applications_search_table').remove();
            _('table.menutable.application').insert(jsonData['response_data'], 'before');
        })

        _arSuccess('get_applications_comments_table', function (jsonData, params, target) {
            _('table.menutable.application').hide();
            _('div.pagecounter').hide();
            _('table.menutable.applications_search_table').remove();
            _('table.menutable.application').insert(jsonData['response_data'], 'before');
        })

        _arSuccess('set_special_group', function (jsonData, params, target) {
            showMessageFromJsonData(jsonData);

            notyDialog?.close();
        })

        _arSuccess('confirm_payment', function (jsonData, params, target) {
            showMessageFromJsonData(jsonData);

            target.closest('div.commands').html(jsonData['response_data']);

            if (parseInt(jsonData['response_amount']) > 0) {
                _('input[name="money_provided[0]"]').val((parseInt(_('input[name="money_provided[0]"]').val()) || 0) + parseInt(jsonData['response_amount']));
            }
        })

        _arSuccess('decline_payment', function (jsonData, params, target) {
            showMessageFromJsonData(jsonData);

            target.closest('div.commands').html(jsonData['response_data']);
        })

        _arSuccess('transfer_application', function (jsonData, params, target) {
            showMessageFromJsonData(jsonData);

            _('a#transfer_application').hide();
            _('a#transfer_application_cancel').show();

            notyDialog?.close();
        })

        _arSuccess('transfer_application_cancel', function (jsonData, params, target) {
            showMessageFromJsonData(jsonData);

            _('a#transfer_application').show();
            _('a#transfer_application_cancel').hide();
        })
    }
})
