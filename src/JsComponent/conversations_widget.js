/** Виджет диалогов */

let protectionAgainstDoubles = [];

if (withDocumentEvents) {
    if (el('div.conversations_widget')) {
        actionRequestSupressErrorForActions.push('get_dialog');

        _(document).on('click', 'div.conversations_widget_list_sound', function () {
            const self = _(this);
            self.toggleClass('mute');

            const dialogWindow = self.closest('div.conversations_widget_message_container');

            actionRequest({
                action: 'conversation/set_dialog_position',
                obj_id: dialogWindow.attr('obj_id'),
                left: dialogWindow.asDomElement().style.left,
                top: dialogWindow.asDomElement().style.top,
                visible: true,
                user_id: dialogWindow.attr('user_id'),
                sound: self.hasClass('mute') ? 'mute' : 'on'
            });
        });

        _(document).on('click', 'div.conversations_widget_list_functions', function () {
            const self = _(this);
            const dialogWindow = self.closest('div.conversations_widget_message_container');

            dialogWindow.find('div.conversations_widget_message_functions')?.toggle();
        });

        _(document).on('click', 'a.conversations_widget_message_functions_add_people', function () {
            let btn = _(this);
            const dialogWindow = btn.closest('div.conversations_widget_message_container') || btn.closest('div.actions_list_items');

            createPseudoPrompt(
                `<div><input type="text" name="user_name" placehold="${LOCALE.dialogUsersSearch}" obj_id="${dialogWindow.attr('obj_id')}"><input type="hidden" name="user_id"></div>`,
                LOCALE.searchCapitalized,
                [
                    {
                        text: LOCALE.inviteCapitalized,
                        class: 'main',
                        click: function () {
                            const self = getNotyDialogDOM();
                            const input = self.find('input[type=text]');
                            const input2 = self.find('input[type=hidden]');

                            if (input2.val()) {
                                actionRequest({
                                    action: 'conversation/add_user_to_dialog',
                                    user_id: input2.val(),
                                    obj_id: input.attr('obj_id'),
                                    additionalTarget: btn
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
                            source: '/helper_users_list/',
                            makeEmptySearches: true,
                            select: function () {
                                const self = getNotyDialogDOM();
                                const input2 = self.find('input[type=hidden]');

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

        _(document).on('click', 'a.conversations_widget_message_functions_switch_use_names_type', function () {
            const self = _(this);
            const dialogWindow = self.closest('div.conversations_widget_message_container') || self.closest('div.actions_list_items');

            actionRequest({
                action: 'conversation/switch_use_names_type',
                obj_id: dialogWindow.attr('obj_id'),
                user_id: dialogWindow.attr('user_id')
            }, dialogWindow);
        });

        _(document).on('click', 'a.conversations_widget_message_functions_leave', function () { });

        _(document).on('click', 'a.conversations_widget_message_functions_rename', function () {
            let btn = _(this);
            const dialogWindow = btn.closest('div.conversations_widget_message_container') || btn.closest('div.actions_list_items');

            createPseudoPrompt(
                `<div><input type="text" name="change_name" id="change_name" placehold="${LOCALE.conversationWidgetNewName}" value="${_('div.conversation_message_maincontent').find('h2').text()}"></div>`,
                LOCALE.conversationWidgetNewNameHeader,
                [
                    {
                        text: LOCALE.approveCapitalized,
                        class: 'main',
                        click: function () {
                            const self = getNotyDialogDOM();
                            const input = self.find('input[type=text]');

                            if (input.val()) {
                                actionRequest({
                                    action: 'conversation/conversation_rename',
                                    obj_id: dialogWindow.attr('obj_id'),
                                    user_id: dialogWindow.attr('user_id'),
                                    value: input.val()
                                }, dialogWindow);

                                notyDialog?.close();
                            } else {
                                showMessage({
                                    text: LOCALE.conversationWidgetNewNameNotEntered,
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

                    fraymPlaceholder(input);
                }
            );
        });

        _(document).on('click', 'div.conversations_widget_list_close', function (e) {
            e.stopImmediatePropagation();

            const self = _(this);

            if (self.parent().hasClass('conversations_widget_container_avatar')) {
                actionRequest({
                    action: 'conversation/delete_dialog_position',
                    obj_id: self.attr('obj_id'),
                    user_id: self.attr('user_id')
                }, self);

                _(`div.conversations_widget_message_container[user_id="${self.attr('user_id')}"]`).destroy().remove();

                self.parent().remove();
            } else {
                const dialogWindow = self.parent().parent();

                if (dialogWindow.hasClass('conversations_widget_message_container')) {
                    dialogWindow.hide();

                    actionRequest({
                        action: 'conversation/set_dialog_position',
                        obj_id: dialogWindow.attr('obj_id'),
                        left: dialogWindow.asDomElement().style.left,
                        top: dialogWindow.asDomElement().style.top,
                        visible: false,
                        user_id: dialogWindow.attr('user_id'),
                        sound: dialogWindow.find('.conversations_widget_list_sound').hasClass('mute') ? 'mute' : 'on'
                    });
                } else if (dialogWindow.parent().hasClass('conversations_widget_list')) {
                    dialogWindow.parent().hide();
                }
            }
        });

        _(document).on('click', 'div.conversations_widget_list_item', function (e) {
            e.stopPropagation();
            e.preventDefault();

            const self = _(this);
            const selector = self.attr('obj_id') > 0 && self.attr('obj_id') != 'new' ?
                `[obj_id="${self.attr('obj_id')}"]` :
                `[user_id="${self.attr('user_id')}"]`;

            if (el(`div.conversations_widget_message_container${selector}`)) {
                _(`div.conversations_widget_message_container${selector}`)
                    .show()
                    .find('textarea')
                    ?.first()
                    .focus();

                if (el(`div.conversations_widget_container_avatar${selector}`)) {
                    _(`div.conversations_widget_container_avatar${selector}`).show();
                } else {
                    actionRequest({
                        action: 'conversation/get_dialog_avatar',
                        obj_id: self.attr('obj_id'),
                        user_id: self.attr('user_id')
                    });
                }
            } else {
                actionRequest({
                    action: 'conversation/get_dialog',
                    obj_id: self.attr('obj_id'),
                    user_id: self.attr('user_id')
                });
            }

            self.closest('div.conversations_widget_list_container')?.find('div.conversations_widget_list_close')?.click();
        });

        _(document).on('click', 'div.conversations_widget', function () {
            if (_('div.conversations_widget_list').is(':visible')) {
                _('div.conversations_widget_list').hide();
            } else {
                actionRequest({
                    action: 'user/get_new_events',
                    show_list: true
                });
            }
        });

        _(document).on('keydown', '#dialog_new_message', function (e) {
            if (e.ctrlKey && e.keyCode == 13) {
                const self = this;
                const val = self.value;

                if (typeof self.selectionStart == "number" && typeof self.selectionEnd == "number") {
                    const start = self.selectionStart;

                    self.value = val.slice(0, start) + "\n" + val.slice(self.selectionEnd);
                    self.selectionStart = self.selectionEnd = start + 1;
                } else if (document.selection && document.selection.createRange) {
                    const range = document.selection.createRange();

                    this.focus();

                    range.text = "\r\n";
                    range.collapse(false);
                    range.select();
                }

                return false;
            } else if (e.keyCode == 13 && _(this).val().trim() != '') {
                e.preventDefault();

                _(this).next('button').click();
            } else if (e.keyCode == 13 && _(this).val().trim() == '') {
                return false;
            }

            return true;
        });

        _(document).on('click', '#dialog_new_message + button', function () {
            const self = _('#dialog_new_message');

            if (self.val().trim() != '') {
                if (protectionAgainstDoubles[self.attr('obj_id')] == self.val()) {
                    //игнорировать дубль
                } else {
                    protectionAgainstDoubles[self.attr('obj_id')] = self.val();

                    actionRequest({
                        action: 'conversation/dialog_new_message',
                        obj_id: self.attr('obj_id'),
                        user_id: self.attr('user_id'),
                        value: self.val()
                    }, self);
                }
            }
        });

        _(document).on('click', 'div.conversations_widget_message_container', function (e) {
            _('div.conversations_widget_message_container').css('zIndex', 10499);
            _(this).css('zIndex', 10500);

            if (!_(e.target).is('a, div.conversations_widget_list_functions')) {
                _(this).find('div.conversations_widget_message_functions').hide();
            }
        });

        _(document).on('keyup', '#conversations_widget_list_search_input', function () {
            const self = _(this);
            const scrolldiv = _('div.conversations_widget_list_scroll');
            const string = self.val();

            if (string != '') {
                const string2 = autoLayoutKeyboard(string);

                scrolldiv.children('.conversations_widget_list_item_separator').hide();

                scrolldiv.find('.conversations_widget_list_item_name', false, false, string)?.each(function () {
                    _(this).parent().hide();
                });

                scrolldiv.find(`.conversations_widget_list_item_name`, false, string)?.each(function () {
                    _(this).parent().show();
                });

                scrolldiv.find(`.conversations_widget_list_item_name`, false, string2)?.each(function () {
                    _(this).parent().show();
                });
            } else {
                scrolldiv.children().show();
            }
        });

        _arSuccess('dialog_new_message', function (jsonData, params, target) {
            const dialogWindow = _(`div.conversations_widget_message_container[user_id="${params['user_id']}"]`);

            target.val('');

            protectionAgainstDoubles[target.attr('obj_id')] = '';

            dialogWindow.find('div.no_message_yet').remove();

            actionRequest({
                action: 'conversation/get_dialog',
                obj_id: params['obj_id'],
                user_id: params['user_id'],
                time: dialogWindow.attr('time')
            });
        })

        _arError('dialog_new_message', function (jsonData, params, target, error) {
            target.val(params['value']);
        })

        _arSuccess('switch_use_names_type', function (jsonData, params, target) {
            //перезагрузка диалога
            if (target.find('div.conversations_widget_message_header > a')) {
                actionRequest({
                    action: 'conversation/get_dialog',
                    obj_id: params['obj_id'],
                    user_id: params['user_id'],
                    time: target.attr('time')
                });
            } else {
                _('div.conversation_message_maincontent_scroller_wrap')
                    .html(`<a class="load_conversation" obj_limit="0" obj_id="${params['obj_id']}"></a><a id="bottom"></a>`)
                    .find('a.load_conversation')
                    .click();
            }
        })

        _arSuccess('get_dialog_avatar', function (jsonData, params, target) {
            if (jsonData['response_data']) {
                if (
                    (params[`obj_id`] > 0 && el(`div.conversations_widget_container_avatar[obj_id="${params['obj_id']}"]`)) ||
                    (params[`obj_id`] === undefined && el(`div.conversations_widget_container_avatar[user_id="${params['user_id']}"]`))
                ) {
                    showHideByValue(`div.conversations_widget_container_avatar_unread_messages[user_id="${params['user_id']}"]`, params['unread_messages']);
                } else {
                    let avatarDiv = elFromHTML(`<div class="conversations_widget_container_avatar" obj_id="${params[`obj_id`]}" user_id="${params[`user_id`]}">${jsonData['response_data']}<div class="conversations_widget_list_close sbi" obj_id="${params[`obj_id`]}" user_id="${params[`user_id`]}"></div><div class="conversations_widget_container_avatar_unread_messages" obj_id="${params[`obj_id`]}" user_id="${params[`user_id`]}"></div><div class="clear"></div></div>`);

                    _('div.conversations_widget_container').insert(avatarDiv, 'begin');

                    avatarDiv = _(avatarDiv);

                    showHideByValue(avatarDiv.find('div.conversations_widget_container_avatar_unread_messages')?.asDomElement(), params['unread_messages']);

                    avatarDiv.show();

                    avatarDiv.on('click', function (e) {
                        e.stopImmediatePropagation();

                        const dialogWindow = params['obj_id'] == 'new' ?
                            _(`div.conversations_widget_message_container[user_id="${params['user_id']}"]`) :
                            _(`div.conversations_widget_message_container[obj_id="${params['obj_id']}"]`);

                        if (dialogWindow.is(':visible')) {
                            if (dialogWindow.css('zIndex') == 10499 && elAll('div.conversations_widget_message_container.shown').length > 1) {
                                _('div.conversations_widget_message_container').css('zIndex', 10499);
                                dialogWindow.css('zIndex', 10500);
                            } else {
                                actionRequest({
                                    action: 'conversation/set_dialog_position',
                                    obj_id: params['obj_id'],
                                    user_id: params['user_id'],
                                    left: dialogWindow.asDomElement().style.left,
                                    top: dialogWindow.asDomElement().style.top,
                                    visible: false,
                                    sound: dialogWindow.find('.conversations_widget_list_sound').hasClass('mute') ? 'mute' : 'on'
                                });

                                dialogWindow.toggle();
                            }
                        } else {
                            actionRequest({
                                action: 'conversation/set_dialog_position',
                                obj_id: params['obj_id'],
                                user_id: params['user_id'],
                                left: dialogWindow.asDomElement().style.left,
                                top: dialogWindow.asDomElement().style.top,
                                visible: true,
                                sound: dialogWindow.find('.conversations_widget_list_sound').hasClass('mute') ? 'mute' : 'on'
                            });

                            delay(100).then(() => {
                                actionRequest({
                                    action: 'conversation/get_dialog',
                                    obj_id: params['obj_id'],
                                    user_id: params['user_id'],
                                    set_position: true
                                });
                            })
                        }
                    })

                    //обновляем счетчики сообщений
                    actionRequest({
                        action: 'user/get_new_events'
                    });
                }

                loadSbiBackground();
            }
        })

        _arSuccess('get_dialog', function (jsonData, params, target) {
            const selector = `[user_id="${params['user_id']}"]${((params['obj_id'] > 0 && params['obj_id'] != 'new') ? `[obj_id="${params['obj_id']}"]` : ``)}`;

            if (el(`div.conversations_widget_message_container${selector}`)) {
                target = _(`div.conversations_widget_message_container${selector}`);

                if (target.attr('obj_id') == 'new' && params['obj_id'] != 'new') {
                    target.attr('obj_id', params['obj_id']);
                    _(`div.conversations_widget_container_avatar[user_id="${params['user_id']}"]`).attr('obj_id', params['obj_id']);

                    actionRequest({
                        action: 'conversation/set_dialog_position',
                        obj_id: params['obj_id'],
                        left: target.asDomElement().style.left,
                        top: target.asDomElement().style.top,
                        visible: true,
                        user_id: params['user_id'],
                        sound: target.find('.conversations_widget_list_sound').hasClass('mute') ? 'mute' : 'on'
                    });
                }
            } else {
                target = _(elFromHTML(`<div class="conversations_widget_message_container on_start" obj_id="${params[`obj_id`]}" user_id="${params[`user_id`]}"><div class="conversations_widget_message_header"><div class="conversations_widget_list_close sbi"></div><div class="conversations_widget_list_sound"></div><div class="conversations_widget_list_functions"></div><a href="/conversation/${params[`obj_id`]}/#bottom">${jsonData[`name`]}</a></div><div class="conversations_widget_message_functions"><a class="conversations_widget_message_functions_add_people">${LOCALE.conversationWidgetFunctionsAddPeople}</a>${(jsonData[`is_group`] === `true` ? `<a class="conversations_widget_message_functions_rename">${LOCALE.rename}</a><a class="conversations_widget_message_functions_leave careful" action_request="conversation/leave_dialog" obj_id="${params['obj_id']}">${LOCALE.conversationWidgetFunctionsLeave}</a>` : ``)}</div><div class="conversations_widget_message_scroll" limit="10"></div><div class="conversations_widget_message_conversation_form"><textarea id="dialog_new_message" placeholder="${LOCALE.conversationWidgetInputText}" obj_id="${params[`obj_id`]}" user_id="${params[`user_id`]}"></textarea><button></button></div></div>`));

                fraymDragDropApply(target, {
                    handler: target.find('.conversations_widget_message_header').asDomElement(),
                    dragStart: function () {
                        _('div.conversations_widget_message_container').css('zIndex', 10499);
                    },
                    dragEnd: function () {
                        const self = _(this);

                        self.removeClass('on_start');

                        actionRequest({
                            action: 'conversation/set_dialog_position',
                            obj_id: params['obj_id'],
                            left: self.asDomElement().style.left,
                            top: self.asDomElement().style.top,
                            visible: true,
                            user_id: params['user_id'],
                            sound: self.find('.conversations_widget_list_sound').hasClass('mute') ? 'mute' : 'on'
                        });
                    }
                });

                _(`div.conversations_widget_list`).insert(target, 'before');

                target.find('div.conversations_widget_message_scroll').on('resize scroll', function () {
                    const self = _(this);

                    if (self.scrollTop() == 0 && self.attr('limit') != 'done') {
                        actionRequest({
                            action: 'conversation/get_dialog',
                            obj_id: params['obj_id'],
                            user_id: params['user_id'],
                            limit: self.attr('limit')
                        });
                    }
                });

                loadSbiBackground();
            }

            if (params['set_position'] == true) {
                _('div.conversations_widget_message_container').css('zIndex', 10499);

                target.css('zIndex', 10500);
                target.css('left', jsonData['left']).css('top', jsonData['top']);

                if (jsonData['left'] !== '' || jsonData['top'] !== '') {
                    target.removeClass('on_start');
                }

                target.find('.conversations_widget_list_sound').toggleClass('mute', jsonData['sound'] == 'mute');
            }

            const scroll = target.find('div.conversations_widget_message_scroll');

            if (params['limit']) {
                scroll.attr('limit', jsonData['limit']);

                if (jsonData['dialog']) {
                    const scHeight = scroll.asDomElement().scrollHeight;

                    scroll.html(jsonData['dialog'] + scroll.html());
                    scroll.scrollTop(scroll.asDomElement().scrollHeight - scHeight);
                } else {
                    scroll.attr('limit', 'done');
                }
            } else if (params['time']) {
                if (jsonData['dialog']) {
                    scroll.html(scroll.html() + jsonData['dialog']);
                    scroll.scrollTop(scroll.asDomElement().scrollHeight);
                }

                if (jsonData['time'] != 'keep') {
                    target.attr('time', jsonData['time']);
                }

                if (jsonData['messages_marked_read'] > 0) {
                    if (target.find('div.conversations_widget_list_sound').hasClass('mute')) {
                        //
                    } else {
                        el('#new_message_alert').play();
                    }
                }
            } else {
                target.attr('time', jsonData['time']);
                scroll.html(jsonData['dialog']);

                if (jsonData['visible'] == 'notset' || jsonData['visible'] === true) {
                    actionRequest({
                        action: 'conversation/get_dialog',
                        obj_id: params['obj_id'],
                        user_id: params['user_id'],
                        time: target.attr('time')
                    });

                    target.show();
                    scroll.scrollTop(scroll.asDomElement().scrollHeight);
                    target.find('textarea').focus();
                }

                if (el(`div.conversations_widget_container_avatar${selector}`)) {
                    //
                } else {
                    actionRequest({
                        action: 'conversation/get_dialog_avatar',
                        obj_id: params['obj_id'],
                        user_id: params['user_id']
                    });
                }
            }
        })

        _arSuccess('set_dialog_position', function () { })

        _arSuccess('delete_dialog_position', function () { })

        _arSuccess('conversation_rename', function (jsonData, params, target) {
            showMessageFromJsonData(jsonData);

            if (target.find('div.conversations_widget_message_header > a')) {
                target.find('div.conversations_widget_message_header > a').text(params['value']);

                actionRequest({
                    action: 'conversation/get_dialog',
                    obj_id: params['obj_id'],
                    user_id: params['user_id'],
                    time: target.attr('time')
                });
            } else {
                _('div.conversation_message_maincontent').find('h2').text(params['value']);

                actionRequest({
                    action: 'conversation/load_conversation',
                    obj_id: params['obj_id'],
                    obj_limit: 0,
                    show_limit: 1,
                    dynamic_load: true
                }, _('a#bottom'));
            }
        })
    }
}