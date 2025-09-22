loadJsComponent('application').then(function () {
    /** Мои заявки */

    blockDefaultSubmit = true;

    if (el('form#form_myapplication')) {
        /** Форма оплаты */
        _('a#provide_payment').on('click', function () {
            const self = _('div.provide_payment_form');

            if (self.hasClass('shown')) {
                self.hide();
            } else {
                _('input[name="amount[0]"]').val(parseInt(_('div[id="div_money[0]"]').text()) - parseInt(_('div[id="div_money_provided[0]"]').text()));
                self.show();

                scrollWindow(self.offset().top);
            }
        });

        let transactionSent = false;
        _('form#form_transaction').on('submit', function () {
            if (!transactionSent) {
                blockDefaultSubmit = false;
                transactionSent = true;

                _('form#form_transaction').trigger('submit');

                transactionSent = false;
                blockDefaultSubmit = true;
            }
        });

        _('select[name="project_payment_type_id[0]"]').on('change', function () {
            const self = _(this);

            if (_(`option[value="${self.val()}"]`).attr(`pay_type`) == `paymaster` || _(`option[value="${self.val()}"]`).attr(`pay_type`) == `paykeeper`) {
                _('[id="field_pay_by_card[0]"]').show();
                _('[id="field_test_payment[0]"]').show();
            } else {
                _('[id="field_pay_by_card[0]"]').hide();
                _('[id="field_test_payment[0]"]').hide();
            }
        });
        _('select[name="project_payment_type_id[0]"]').change();

        /** Выставление групп в соответствии с выбранным персонажем */
        _('input[name="project_character_id[0]"]').on('change', function () {
            const self = _(this);

            actionRequest({
                action: 'myapplication/get_list_of_groups',
                obj_id: self.val()
            });
        });
        _('input[name="project_character_id[0]"]:checked').change();

        /** Проверка на наличие неотправленного коммента при сохранении заявки */
        let commentsChecked = false;

        _('form#form_myapplication').on('submit', function () {
            if (!commentsChecked) {
                if (_('div.conversation_form_data textarea[name="content"]')?.val() && _('input[id="go_back_after_save[0]"]').is(':checked') && blockDefaultSubmit) {
                    fraymNotyPrompt(
                        null,
                        LOCALE.leavingApplicationWithComment,
                        function (dialog) {
                            blockDefaultSubmit = false;
                            commentsChecked = true;

                            _('form#form_myapplication').trigger('submit');

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

                    _('form#form_myapplication').trigger('submit');
                }
            }
        });

        if (el('form#form_myapplication input[type="checkbox"][name^="project_fee_ids[0]"]')) {
            //есть мультивыбор опций взноса
            _('form#form_myapplication input[type="checkbox"][name^="project_fee_ids[0]"]').on('change', function () {
                const self = _('div[id="div_money_paid[0]"]');
                let money = 0;

                _('form#form_myapplication input[type="checkbox"][name^="project_fee_ids[0]"]').each(function () {
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

                _('div[id="div_money[0]"]').text(money);

                if (parseInt(_('div[id="div_money_provided[0]"]').text()) >= parseInt(_('div[id="div_money[0]"]').text()) && parseInt(_('div[id="div_money[0]"]').text()) > 0) {
                    self.html('<span class="sbi sbi-check"></span>');
                } else {
                    self.html('<span class="sbi sbi-times"></span>');
                }

                loadSbiBackground();

                window.feeLockedRoomCheck();
            });

            _('form#form_myapplication input[type="checkbox"][name^="project_fee_ids[0]"]').change();
        } else {
            window.feeLockedRoomCheck();
        }

        /** Приглашение другого игрока в комнату */
        _('a#add_neighboor_myapplication').on('click', function () {
            createPseudoPrompt(
                `<div><input type="text" name="application_name" placehold="${LOCALE.enterName}" obj_id="${_(this).attr('obj_id')}"><input type="hidden" name="application_id"></div>`,
                LOCALE.searchCapitalizedApplications,
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
                                    action: 'myapplication/add_neighboor_request',
                                    application_id: input.attr('obj_id'),
                                    user_id: input2.val(),
                                    room_id: _('select[name^="rooms_selector"]').val()
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
                            source: `/helper_application/?nochar=1&obj_id=${input.attr('obj_id')}`,
                            makeEmptySearches: true,
                            select: function () {
                                const self = getNotyDialogDOM();

                                input2.val(this.obj_id);

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
    }

    if (withDocumentEvents) {
        _arSuccess('add_neighboor_request', function (jsonData, params, target) {
            showMessageFromJsonData(jsonData);

            notyDialog?.close();
        })
    }
})
