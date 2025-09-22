/** Динамические действия */

if (withDocumentEvents) {
    /** Окошко добавления пользователя в объект */
    _(document).on('click', '.project_user_add, .community_user_add', function () {
        const btn = _(this);

        createPseudoPrompt(
            `<div><input type="text" name="user_name" placehold="${LOCALE.enterName}" obj_type="${btn.attr('obj_type')}" obj_id="${btn.attr('obj_id')}"><input type="hidden" name="user_id"></div>`,
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
                                action: 'conversation/send_invitation',
                                user_id: input2.val(),
                                obj_type: input.attr('obj_type'),
                                obj_id: input.attr('obj_id')
                            });
                        } else if (validateEmail(input.val().trim())) {
                            actionRequest({
                                action: 'conversation/send_invitation',
                                user_email: input.val().trim(),
                                obj_type: input.attr('obj_type'),
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
                        source: `/helper_users_list/?obj_type=${input.attr('obj_type')}&obj_id=${input.attr('obj_id')}`,
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

    _arSuccess('conversation_message_delete', function (jsonData, params, target) {
        showMessageFromJsonData(jsonData);

        target.closest('div.message.inner').remove();
    })

    _arSuccess('confirm_group_request', function (jsonData, params, target) {
        showMessageFromJsonData(jsonData);

        target.closest('div.commands').html(jsonData['response_data']);

        if (parseInt(jsonData['response_group']) > 0) {
            _(`input[name="project_group_ids[0][${jsonData['response_group']}]"]`).checked(true).change();
        }
    })

    _arSuccess('decline_group_request', function (jsonData, params, target) {
        showMessageFromJsonData(jsonData);

        target.closest('div.commands').html(jsonData['response_data']);
    })

    _arSuccess('grant_access', function (jsonData, params, target) {
        acceptDenySuccess(jsonData, params, target);
    })

    _arSuccess('deny_access', function (jsonData, params, target) {
        acceptDenySuccess(jsonData, params, target);
    })

    _arSuccess('accept_invitation', function (jsonData, params, target) {
        acceptDenySuccess(jsonData, params, target);
    })

    _arSuccess('decline_invitation', function (jsonData, params, target) {
        acceptDenySuccess(jsonData, params, target);
    })

    _arSuccess('accept_friend', function (jsonData, params, target) {
        acceptDenySuccess(jsonData, params, target);
    })

    _arSuccess('decline_friend', function (jsonData, params, target) {
        acceptDenySuccess(jsonData, params, target);
    })

    _arSuccess('leave_dialog', function (jsonData, params, target) {
        showMessageFromJsonData(jsonData);

        if (el(`div.actions_list_items[obj_id=${params['obj_id']}]`)) {
            updateState('/conversation/');
        } else {
            _(`div.conversations_widget_list_close[obj_id=${params['obj_id']}]`).click();
        }
    })

    _arSuccess('add_user_to_dialog', function (jsonData, params, target) {
        showMessageFromJsonData(jsonData);

        notyDialog?.close();

        if (params.additionalTarget.closest('div.actions_list_items')) {
            updateState(`/conversation/${jsonData['conversation_id']}/#bottom`);
        } else {
            if (params['obj_id'] == jsonData['conversation_id']) {
                const dialogWindow = _(`div.conversations_widget_message_container[obj_id=${params['obj_id']}]`);

                actionRequest({
                    action: 'conversation/get_dialog',
                    obj_id: dialogWindow.attr('obj_id'),
                    user_id: dialogWindow.attr('user_id'),
                    time: dialogWindow.attr('time')
                });
            } else {
                actionRequest({
                    action: 'conversation/get_dialog',
                    obj_id: jsonData['conversation_id'],
                    user_id: jsonData['user_id']
                });
            }
        }
    })

    _arSuccess('send_invitation', function (jsonData, params, target) {
        showMessageFromJsonData(jsonData);

        notyDialog?.close();
    })

    _arSuccess('mark_read', function () { })

    actionRequestSupressErrorForActions.push('mark_important');

    _arError('mark_important', function () { })

    _arSuccess('mark_important', function (jsonData, params, target) {
        const counter = target.find('span.important_button_counter');
        const text = target.find('span.important_button_text');

        if (jsonData['response_text'] == 'increase') {
            if (counter.text() != '') {
                counter.text(parseInt(counter.text()) + 1);
            } else {
                counter.text('1');
            }

            text.text(LOCALE.importantButtonMarked);
            target.addClass('marked');
        } else {
            if (counter.text() != '1') {
                counter.text(parseInt(counter.text()) - 1);
            } else {
                counter.text('');
            }

            text.text(LOCALE.importantButtonNotMarked);
            target.removeClass('marked');
        }

        actionRequest({
            action: 'popup_helper/get_important',
            obj_id: target.attr('obj_id'),
            obj_type: target.attr('obj_type')
        }, target);
    })

    _arSuccess('mark_need_response', function (jsonData, params, target) {
        showMessageFromJsonData(jsonData);
    })

    _arError('mark_need_response', function () { })

    _arSuccess('mark_has_response', function (jsonData, params, target) {
        showMessageFromJsonData(jsonData);
    })

    _arError('mark_has_response', function () { })
}

/** Стандартная обработка различных приглашений
 * 
 * @type {actionRequestCallbackSuccess}
*/
function acceptDenySuccess(jsonData, params, target) {
    showMessageFromJsonData(jsonData);

    if (target.parent().parent().hasClass('conversations_widget_message_content')) {
        const dialogWindow = target.closest('div.conversations_widget_message_container');

        actionRequest({
            action: 'conversation/get_dialog',
            obj_id: dialogWindow.attr('obj_id'),
            user_id: dialogWindow.attr('user_id'),
            time: dialogWindow.attr('time')
        });

        target.parent().text(LOCALE.commandDone);
    } else {
        //прежде всего проверяем не находимся ли мы в этот момент прямо на странице указанного диалога в разделе /conversation/
        if (el('input[type="hidden"][name="conversation_id"][value]')) {
            //находимся, значит, просто обновляем текущее окошко с текстом
            const cId = _('input[type="hidden"][name="conversation_id"]').attr('value');

            target.parent().html(`<div class="done">${LOCALE.commandDone}</div>`);

            actionRequest({
                action: 'conversation/load_conversation',
                obj_id: cId,
                obj_limit: 0,
                show_limit: 1,
                dynamic_load: true
            }, _('a#bottom'));
        } else {
            updateState(currentHref);
        }
    }
}
