/** Стена / обсуждения / отзывы */

if (withDocumentEvents) {
    getHelperData('div.conversation_message_vote_choice_made', 'get_vote');
    getHelperData('div.wall_message_vote_choice_made', 'get_vote');

    _(document).on('click', 'div.wall_message_more, div.conversation_message_more', function () {
        _('div[class*=_message_more_list]').hide();
        _(this).next('div[class*=_message_more_list]').show();
    });

    _(document).on('mouseleave', 'div.wall_message_container, div.conversation_message_container', function () {
        _(this).find('div[class*=_message_more_list]')?.hide();
    });

    _(document).on('click', 'a.wall_message_more_function.wall_message_more_edit, a.conversation_message_more_function.conversation_message_more_edit', function () {
        const self = _(this);

        self.parent()?.show();
        self.closest('div[class$=_message], div[class$="_message child"]')?.find('a[class$=_message_edit]')?.first()?.click();
    });

    _(document).on('click', 'a.wall_message_more_function.wall_message_more_reply, a.conversation_message_more_function.conversation_message_more_reply', function () {
        const self = _(this);

        self.parent()?.hide();
        self.closest('div[class$=_message], div[class$="_message child"]')?.find('a[class$=_message_reply]')?.first()?.click();
    });

    _(document).on('click', 'a.wall_message_more_function.wall_message_more_delete, a.conversation_message_more_function.conversation_message_more_delete', function () {
        const self = _(this);
        const message = _(this).closest('div[class$=_message], div[class$="_message child"]');

        self.parent()?.hide();

        actionRequest({
            action: 'conversation/wall_message_delete',
            obj_id: message.attr('message_id')
        }, message);
    });

    _(document).on('click', 'a.wall_message_more_function.wall_message_more_mark_read, a.conversation_message_more_function.conversation_message_more_mark_read', function () {
        const self = _(this);
        const message = self.closest('[message_id]');

        self.parent()?.hide();
        self.remove();

        actionRequest({
            action: 'mark/mark_read',
            obj_id: message.attr('message_id')
        }, message);
    });

    _(document).on('click', 'a.conversation_message_more_function.conversation_message_more_need_response', function () {
        const self = _(this);
        const message = self.closest('[message_id]');

        self.parent()?.hide();
        self.hide();
        self.parent()?.find('a[class*="_message_more_has_response"]')?.show();
        self.closest('.conversation_message')?.addClass('need_response');

        actionRequest({
            action: 'mark/mark_need_response',
            obj_id: message.attr('message_id')
        }, message);
    });

    _(document).on('click', 'a.conversation_message_more_function.conversation_message_more_has_response', function () {
        const self = _(this);
        const message = self.closest('[message_id]');

        self.parent()?.hide();
        self.hide();
        self.parent()?.find('a[class*="_message_more_need_response"]')?.show();
        self.closest('.conversation_message')?.removeClass('need_response');

        actionRequest({
            action: 'mark/mark_has_response',
            obj_id: message.attr('message_id')
        }, message);
    });

    _(document).on('click', 'a.wall_message_edit', function (e) {
        e.stopImmediatePropagation();

        const self = _(this);
        const id = self.attr('message_id');
        const content = self.closest('div.wall_message')?.find('div.wall_message_content')?.first();
        const ratingContent = self.closest('div.wall_message')?.find('div.wall_message_rating') ? '<select name="rating" id="rating_edit"><option value="1">+1</option><option value="0">0</option><option value="-1">-1</option></select>' : '';
        const isNotion = self.closest('div.wall_message').hasClass('notion');

        self.text(LOCALE.cancelCapitalized).removeClass('wall_message_edit').addClass('wall_message_edit_cancel');

        content.hide().insert(`<div class="wall_message_edit${(isNotion ? ' notion' : '')}"><textarea name="content" message_id="${id}">${content.html().br2nl()}</textarea><div class="conversation_form_controls">${ratingContent}<button type="button" class="wall_message_save_button main">${LOCALE.saveCapitalized}</button><button type="button" class="wall_message_delete_button nonimportant">${LOCALE.deleteCapitalized}</button></div></div>`, 'after');

        if (self.closest('div.wall_message')?.find('div.wall_message_rating')?.first()) {
            _('select#rating_edit').val(self.closest('div.wall_message').find('div.wall_message_rating').first().text());
        }
    });

    _(document).on('click', 'a.wall_message_edit_cancel', function (e) {
        e.stopImmediatePropagation();

        const self = _(this);
        const content = self.closest('div.wall_message')?.find('div.wall_message_content')?.first();

        self.text(LOCALE.editCapitalized).removeClass('wall_message_edit_cancel').addClass('wall_message_edit');

        content.next('div.wall_message_edit')?.remove();
        content.show();
    });

    _(document).on('click', 'a.conversation_message_edit', function (e) {
        e.stopImmediatePropagation();

        const self = _(this);
        const id = self.attr('message_id');
        const content = self.closest('div.conversation_message')?.find('div.conversation_message_content')?.first();
        const content2 = content.find('div.conversation_message_content_text');

        self.text(LOCALE.cancelCapitalized).removeClass('conversation_message_edit').addClass('conversation_message_edit_cancel');

        content.hide().insert(`<div class="conversation_message_edit"><textarea name="content" message_id="${id}">${content2.html().br2nl()}</textarea><div class="conversation_form_controls"><button type="button" class="conversation_message_save_button main">${LOCALE.saveCapitalized}</button><button type="button" class="conversation_message_delete_button nonimportant">${LOCALE.deleteCapitalized}</button></div></div>`, 'after');
    });

    _(document).on('click', 'a.conversation_message_edit_cancel', function (e) {
        e.stopImmediatePropagation();

        const self = _(this);
        const content = self.closest('div.conversation_message')?.find('div.conversation_message_content')?.first();

        self.text(LOCALE.editCapitalized).removeClass('conversation_message_edit_cancel').addClass('conversation_message_edit');

        content.next('div.conversation_message_edit')?.remove();
        content.show();
    });

    _(document).on('click', 'button.wall_message_save_button, button.conversation_message_save_button', function () {
        const self = _(this);
        const parent = self.closest('div[class*="_message_edit"]');
        const isNotion = parent?.hasClass('notion');
        const content = parent?.find('textarea[name="content"]');
        const rating = parent?.find('select[name="rating"]')?.val() || null;

        actionRequest({
            action: (isNotion ? 'notion/notion_message_save' : 'conversation/message_save'),
            obj_id: content.attr('message_id'),
            text: content.val(),
            rating: rating
        }, parent);
    });

    _(document).on('click', 'button.wall_message_delete_button, button.conversation_message_delete_button', function () {
        const self = _(this);
        const parent = self.closest('div[class*="_message_edit"]');
        const isNotion = parent?.hasClass('notion');
        const content = parent?.find('textarea[name="content"]');

        const action = isNotion ? 'notion/notion_message_delete' : 'conversation/wall_message_delete';

        actionRequest({
            action: action,
            obj_id: content.attr('message_id'),
        }, parent);
    });

    _(document).on('click', 'a.wall_message_reply', function () {
        const self = _(this);
        const parent = self.attr('message_id');
        const respondTo = self.attr('respond_to');
        const form = self.closest('div.wall_message').next('div.conversation_form');

        if (!form.hasClass('shown')) {
            form.show();
        }

        form.find('input[name="parent"]').val(parent);

        form.find('textarea[name="content"]')?.first()
            ?.focus()
            .val(`@${respondTo}[${self.attr('respond_to_id')}], `);

        showConversationForm(form);
    });

    _(document).on('click', 'a.conversation_message_reply', function () {
        const self = _(this);
        const parent = self.attr('message_id');
        const respondTo = self.attr('respond_to');
        let form;

        //если это форма комментариев в заявках, развернутая в календарный вид, то открываем форму на самом верху страницы
        if (el('form#form_application') || el('form#form_myapplication')) {
            if (el('a#change_comments_order')) {
                form = self.closest('div.conversation_message_container')?.find('div.conversation_form')?.first();
            } else {
                form = self.closest('div.application_conversations')?.find('div.conversation_form')?.first();
            }
        }

        if (!form || !form.asDomElement()) {
            form = self.closest('div.conversation_message_container')?.find('div.conversation_form')?.first();
        }

        if (!form?.hasClass('shown')) {
            form?.show();
        }

        form.find('input[name="parent"]').val(parent);

        form.find('textarea[name="content"]')?.first()
            ?.focus()
            .val(`@${respondTo}[${self.attr('respond_to_id')}], `);

        showConversationForm(form);

        if (el('.fraymmodal-content')) {
            const height = el('.fraymmodal-content').scrollHeight;
            _('.fraymmodal-content', { noCache: true }).scrollTop(height).destroy();
        }
    });

    _arSuccess('load_wall', function (jsonData, params, target) {
        if (jsonData['response_text'] == '') {
            jsonData['response_text'] = '<span></span>';
        }

        const newContent = _(elFromHTML(jsonData['response_text']));

        newContent.find('input[type="text"][placehold], input[type="password"][placehold], textarea[placehold]')?.each(function () {
            const field = _(this);

            fraymPlaceholder(field);
        });

        newContent.find('input.inputfile[type=file]')?.each(function () {
            fraymFileUploadApply(this, fileuploadOptions);
        });

        newContent.each(function () {
            target.insert(this, 'before');
        });

        target.remove();

        if (params['search_string'] !== undefined) {
            _('input#conversation_search_input').removeClass('loading');
        }

        if (params['obj_type'] === '{main_conversation}' && el('div.actions_list_items')) {
            const objId = _('div.actions_list_items').attr('obj_id');

            _('div[c_id].selected').removeClass('selected');

            if (el(`div[c_id="${objId}"]`)) {
                const selectedDialog = _(`div[c_id="${objId}"]`);

                selectedDialog.addClass('selected');

                if (selectedDialog.find('div.content_preview')?.hasClass('counter')) {
                    selectedDialog.find('div.content_preview').removeClass('unread');
                }
            }
        }
    })

    _arSuccess('show_hide_notion', function (jsonData, params, target) {
        target.text(jsonData['response_data']['text']);

        if (jsonData['response_data']['active'] == '1') {
            target.closest('div.wall_message_container')?.removeClass('inactive');
        } else {
            target.closest('div.wall_message_container')?.addClass('inactive');
        }

        const prevRating = target.closest('div.wall_message')?.find('div.wall_message_rating')?.text();
        const header = target.closest('div.soc_part.section')?.find('h2')?.first();
        const curRating = header?.text().match(/\((.*?)\)/);

        if (curRating && prevRating != '') {
            let newRating;

            if (jsonData['response_data']['active'] == '1') {
                newRating = parseInt(curRating[1]) + parseInt(prevRating);
            } else {
                newRating = parseInt(curRating[1]) - parseInt(prevRating);
            }

            newRating = newRating.toString();

            if (newRating > 0) {
                newRating = `+${newRating}`;
            }

            header.text(header.text().replace(/\((.*?)\)/, `(${newRating})`));
        }
    })

    _arSuccess('notion_message_delete', function (jsonData, params, target) {
        showMessageFromJsonData(jsonData);

        target = target.closest('div[class$=_message]');
        const prevRating = target.find('div.wall_message_rating')?.text();
        const header = target.closest('div.block_data')?.prev('h2').find('sup');
        const curRating = header.text();

        if (curRating && prevRating) {
            let newRating = parseInt(curRating) - parseInt(prevRating);

            newRating = newRating.toString();

            if (newRating > 0) {
                newRating = `+${newRating}`;
            }

            header.text(newRating);
        }

        target.closest('div.wall_message_container').remove();
    })

    _arSuccess('notion_message_save', function (jsonData, params, target) {
        showMessageFromJsonData(jsonData);

        const type = 'wall';
        const content = target.find('textarea[name="content"]')?.first();
        const rating = target.find('select[name="rating"]')?.first()?.val();
        const prevRating = target.closest(`div.${type}_message_data`)?.find(`div.${type}_message_rating`)?.text();
        const header = target.closest('div.block_data')?.prev('h2').find('sup');
        const curRating = header.text();

        target.prev(`div.${type}_message_content`)?.html(content.val().nl2br()).show();
        target.closest(`div.${type}_message_data`)?.find(`a.${type}_message_edit_cancel`)?.text(LOCALE.editCapitalized).removeClass(`${type}_message_edit_cancel`).addClass(`${type}_message_edit`);

        target.closest(`div.${type}_message_data`)?.find(`div.${type}_message_rating`)?.text(rating);

        if (curRating && prevRating) {
            let newRating = parseInt(curRating) + parseInt(rating) - parseInt(prevRating);

            newRating = newRating.toString();

            if (newRating > 0) {
                newRating = `+${newRating}`;
            }

            header.text(newRating);
        }

        target.remove();
    })

    _arSuccess('message_save', function (jsonData, params, target) {
        showMessageFromJsonData(jsonData);

        const type = target.hasClass('conversation_message_edit') ? 'conversation' : 'wall';
        const content = target.find('textarea[name="content"]');

        if (type == 'conversation') {
            target.prev(`div.${type}_message_content`)?.find(`div.${type}_message_content_text`)?.html(content.val().nl2br());
            target.prev(`div.${type}_message_content`)?.show();
        } else {
            target.prev(`div.${type}_message_content`)?.html(content.val().nl2br()).show();
        }

        target.closest(`div.${type}_message_data`)?.find(`a.${type}_message_edit_cancel`)?.text(LOCALE.editCapitalized).removeClass(`${type}_message_edit_cancel`).addClass(`${type}_message_edit`);

        target.remove();
    })

    _arSuccess('wall_message_delete', function (jsonData, params, target) {
        showMessageFromJsonData(jsonData);

        if (target.asDomElement() && !target.hasClass('wall_message') && !target.hasClass('conversation_message')) {
            target = target.closest('div[class$=_message], div[class$="_message child"]');
        }

        const type = target.hasClass('conversation_message') ? 'conversation' : 'wall';

        if (jsonData['delete_type'] == 'leave message') {
            target.find(`div.${type}_message_content`)?.html(LOCALE.messageDeleted).show();
            target.find(`a.${type}_message_edit_cancel`)?.text(LOCALE.editCapitalized).removeClass(`${type}_message_edit_cancel`).addClass(`${type}_message_edit`);
            target.find(`div.${type}_message_edit`)?.remove();
        } else if (jsonData['delete_type'] == 'delete message') {
            target.remove();
        } else if (jsonData['delete_type'] == 'delete all') {
            target.closest('div[class*="_message_container"]')?.remove();
        }
    })
}