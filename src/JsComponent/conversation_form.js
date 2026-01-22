/** Форма ввода сообщений */

if (withDocumentEvents) {
    _(document).on('click', '.new_project_conversation, .new_community_conversation', function (e) {
        e.stopImmediatePropagation();

        let self = _(this);
        const form = self.parent().find('div.conversation_form').first();

        self.hide();
        self.next('div.tabs_horizontal_shadow')?.first()?.hide();

        if (!form.hasClass('shown')) {
            form.addClass('shown');
        }

        self = form.find('input[name="name"]').first();

        if (!self.asDomElement()) {
            self = form.find('textarea[name="content"]').first();
            self.focus();
            self.val('');
        } else {
            self.focus();
            self.val('');
        }
    });

    _(document).on('click', 'a.attach', function () {
        const target = _(this).closest('div.conversation_form_data').find('div.conversation_form_attachments');

        target.toggleClass('shown');

        if (target.hasClass('shown')) {
            filepondObjs.get(target.find('input[type=file]').attr('name')).browse();
        }
    });

    _(document).on('click', 'a.vote', function () {
        _(this).closest('div.conversation_form_data').find('div.conversation_form_vote').show();
    });

    _(document).on('focusin', 'div.conversation_form_data textarea, div.conversation_form_data input[type="text"]:not(.dpkr_time)', function () {
        showConversationForm(_(this));
    });

    _(document).on('keyup', 'div.conversation_form_data textarea, textarea#dialog_new_message', function () {
        const self = _(this);

        fraymAutocompleteApply(
            self,
            {
                source: '/helper_users_list/?no_id=1',
                conditionalSearch: true,
                select: function () {
                    const text = self.val();
                    const prependingText = text.substring(0, this.closestIndice + 1);
                    const followingText = text.substring(this.currentPosition, text.length);

                    self.val(prependingText + this.value + "[" + this.sid + "]" + followingText);
                }
            }
        );
    });

    _(document).on('click', 'div.conversation_form_data input[name="vote_answer_add"]', function () {
        const self = _(this);
        const prev = self.prev('input');
        const tabbi = +prev.attr('tabIndex') + 1;
        const name = +prev.attr('name').match(/\d+/) + 1;
        const newVoteAnswer = elFromHTML(`<input name="vote_answer[${name}]" type="text" placehold="${LOCALE.voteChoice}" tabIndex="${tabbi}">`);

        self
            .insert(newVoteAnswer, 'before')
            .insert('<br>', 'before');

        fraymPlaceholder(newVoteAnswer);
        _(newVoteAnswer).focus();
    });

    _(document).on('click', function (e) {
        if (!_(e.target).is('div.conversation_form_data, div.conversation_form_data *, a.wall_message_reply, button.new_project_conversation, button.new_project_conversation *, button.new_community_conversation, button.new_community_conversation *, a.conversation_message_reply, div.task_status, div.task_priority')) {
            _('div.conversation_form:not(.do_not_hide)').each(function () {
                const self = _(this);

                if (self.find('textarea').val() === '' || self.find('textarea').val() === self.find('textarea').attr('placehold')) {
                    self.find('div#help_conversation_form_data').hide();
                    self.removeClass('opened');
                }
            });
        }
    });

    _(document).on('focusin', 'div.conversation_form_data textarea[name="content"]', function () {
        const self = _(this);
        const helpDiv = self.closest('div.conversation_form').find('div#help_conversation_form_data').first().asDomElement();

        if (helpDiv) {
            showHelpAndName(helpDiv, this);
        }
    });

    _(document).on('focusout', 'div.conversation_form_data textarea[name="content"]', function () {
        const self = _(this);
        const helpDiv = self.closest('div.conversation_form').find('div#help_conversation_form_data').first().asDomElement();

        if (helpDiv) {
            _(helpDiv).hide();
        }
    });

    _(document).on('change', 'div.wall_message_vote_choice > input[type="radio"], div.conversation_message_vote_choice > input[type="radio"]', function () {
        const self = _(this);

        actionRequest({
            action: 'message/vote',
            m_id: self.attr('m_id'),
            value: self.attr('value'),
            type: self.closest('.wall_message_vote_choice') ? 'wall' : 'conversation'
        }, self);
    });

    _arSuccess('add_comment', function (jsonData, params, target) {
        const content = target.find('[name="content"]').val();

        target.find('[name="content"]').val('');
        target.find('[name="name"]')?.val('');
        target.find('[name="vote_name"]')?.val('');
        target.find('[name^="vote_answer\["]')?.val('');
        target.find('[name^="vote_answer\["]:not([name="vote_answer\[0\]"])')?.next('br')?.remove();
        target.find('[name^="vote_answer\["]:not([name = "vote_answer\[0\]"])')?.remove();
        target.find('div.uploaded_file')?.remove();
        target.find('div.conversation_form_attachments')?.hide();
        target.find('div.conversation_form_vote')?.hide();

        const btn = target.find('button.main');

        btn.each(function () {
            _(this).enable();
            removeLoader(this);
        });

        if (jsonData['html'] && jsonData['html'] != 'reload') {
            const comment_type = target.find('[name="obj_type"]').val();
            let appendToBlock = null;
            let result = elFromHTML(jsonData['html']);

            if (comment_type == '{task_comment}' || comment_type == '{event_comment}') {
                appendToBlock = _('[class$="_comment_form"]').closest('div.block');
                appendToBlock.insert(result, 'begin');
            } else if (comment_type == '{calendar_event_notion}') {
                const rating = target.find('[name="rating"]')?.val();
                const header = target.closest('div.block_data')?.prev('h2').find('sup');
                const curRating = header.text();

                appendToBlock = target.closest('div.block_data');
                appendToBlock.insert(result, 'begin');

                if (curRating) {
                    let newRating = parseInt(curRating) + parseInt(rating);

                    newRating = newRating.toString();

                    if (newRating > 0) {
                        newRating = `+${newRating}`;
                    }

                    header.text(newRating);
                }

                target.closest('div.block_data').find('.conversation_form').remove();
            } else if (comment_type == '{project_wall}' || comment_type == '{community_wall}' || comment_type == '{publication_wall}' || comment_type == '{ruling_item_wall}') {
                const parent = target.find('[name="parent"]').val();

                if (parent > 0) {
                    appendToBlock = target.closest('div.conversation_form');
                    appendToBlock.insert(result, 'before');
                } else {
                    appendToBlock = target.closest('div.block_header').next('div.block_data');
                    appendToBlock.insert(result, 'before');
                }
            } else if (comment_type == '{project_conversation}' || comment_type == '{community_conversation}' || comment_type == '{project_application_conversation}') {
                const parent = target.find('[name="parent"]')?.val();

                if (parent > 0) {
                    const parentDiv = target.closest('div.conversation_message_container')?.find(`div.conversation_message[message_id="${parent}"]`);
                    const level = parseInt(parentDiv?.attr('level'));

                    if (parentDiv?.next('div.conversation_message_children_container')) {
                        appendToBlock = parentDiv.next('div.conversation_message_children_container');
                    } else {
                        appendToBlock = elFromHTML('<div class="conversation_message_children_container"></div>');
                        parentDiv?.insert(appendToBlock, 'after');
                        appendToBlock = _(appendToBlock);
                    }

                    _(result).attr('level', level + (level < 5 ? 1 : 0));
                    appendToBlock.insert(result, 'end');
                } else {
                    appendToBlock = target.closest('div.conversation_form');
                    appendToBlock.insert(result, 'after');
                }

                if (comment_type == '{project_application_conversation}') {
                    //так как мы, сохраняя комментарий к заявке, сдвигаем updated_at заявки, нам нужно его выправить в полях: иначе она не будет сохраняться
                    _('input[name="updated_at[0]"]').val(parseInt(jsonData['response_updated_at']) + 20);
                }
            } else if (comment_type == '{conversation_message}') {
                const checkForQrpg = target.closest('div.qrpg_description');

                if (checkForQrpg) {
                    showMessageFromJsonData(jsonData);
                } else {
                    const conversationBlock = _(`div.message[c_id="${jsonData['c_id']}"]`);
                    const dt = new Date();

                    appendToBlock = _('a#bottom');
                    appendToBlock.insert(result, 'before');
                    appendToBlock.parent().scrollTop(appendToBlock.parent().asDomElement()?.scrollHeight);

                    _('div.conversation_message_switcher_scroller').insert(conversationBlock.asDomElement(), 'begin');

                    conversationBlock.find('div.content_preview')
                        ?.addClass('unread')
                        .removeClass('counter')
                        .html(`<span class="gray">${LOCALE.you}:</span> ${content.nl2space().substring(0, 40)}`);

                    conversationBlock.find('div.time').html(('0' + dt.getHours()).slice(-2) + ':' + ('0' + dt.getMinutes()).slice(-2));
                }
            }

            if (result) {
                result = _(result);

                result.find('input.inputfile[type=file]')?.each(function () {
                    fraymFileUploadApply(this, fileuploadOptions);
                });

                result.find('input[type="text"], input[type="password"], textarea')?.each(function () {
                    const field = _(this);

                    if (field.is('[placehold]')) {
                        fraymPlaceholder(field);
                    }
                });
            }

            _('body').click();
        }

        //обновляем библиотеку страницы автоматически, т.к. мог быть добавлен файл
        if (el('button[id$="_library_link_wrapper"]')) {
            const loadLibraryBtn = _('button[id$="_library_link_wrapper"]');
            const loadLibraryLink = elFromHTML('<a></a>');

            loadLibraryBtn.parent().insert(loadLibraryLink, 'append');

            actionRequest({
                action: 'file/load_library',
                obj_type: loadLibraryBtn.attr('obj_type'),
                obj_id: loadLibraryBtn.attr('obj_id'),
                external: 'false'
            }, _(loadLibraryLink));
        } else if (jsonData['html'] == 'reload') {
            updateState(currentHref);
        } else {
            showMessagesFromJson(jsonData);
        }
    })

    _arError('add_comment', function (jsonData, params, target, error) {
        const btn = target.find('button.main');

        btn.each(function () {
            _(this).enable();
            removeLoader(this);
        });
    })

    _arSuccess('vote', function (jsonData, params, target) {
        if (jsonData['response_text'].length) {
            target.closest(`div.${params.type}_message_content`)?.find(`div.${params.type}_message_vote_choice`)?.hide();
            target.closest(`div.${params.type}_message_content`)?.insert(jsonData['response_text'], 'append');
        }
    })
}