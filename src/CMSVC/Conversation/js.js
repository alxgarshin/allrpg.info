/** Раздел диалогов */

if (el('div.kind_conversation')) {
    /** Поиск диалогов в списке диалогов */
    if (el('div.conversation_message_switcher_search_container a.search_image')) {
        let previousValue = '';

        _('div.conversation_message_switcher_search_container input.search_input').on('keyup', function () {
            if (previousValue !== this.value) {
                const self = _(this);
                let append = null;

                if (self.val().length > 2 || self.val().length == 0) {
                    _('div.conversation_message_switcher_scroller').empty().insert('<a></a>', 'append');
                    append = _('div.conversation_message_switcher_scroller').find('a');

                    self.addClass('loading');
                }

                if (self.val().length > 2) {
                    actionRequest({
                        action: 'message/load_wall',
                        obj_type: '{main_conversation}',
                        search_string: self.val()
                    }, append);
                } else if (self.val().length == 0) {
                    actionRequest({
                        action: 'message/load_wall',
                        obj_type: '{main_conversation}',
                        search_string: ''
                    }, append);
                }
            }

            previousValue = this.value;
        });
    }
}

/** Автооткрытие списка диалогов при его прокрутке */
if (el('div.conversation_message_switcher_scroller')) {
    _('div.conversation_message_maincontent_scroller').on('wheel scroll', function () {
        autoClickLoads();
    });

    _('div.conversation_message_maincontent_scroller_wrap').on('wheel scroll', function () {
        autoClickLoads();
    });
}

if (withDocumentEvents) {
    getHelperData('div.message.inner', 'get_unread_people');

    _(document).on('click', 'div.conversation_message_switcher_scroller div[c_id]', function () {
        const self = _(this);

        updateState(`/conversation/${self.attr('c_id')}/`, 'div.conversation_message_maincontent');
        _('div[c_id].selected').removeClass('selected');

        self.addClass('selected');

        if (self.find('div.content_preview')?.hasClass('counter')) {
            self.find('div.content_preview').removeClass('unread');
        }

        if (_('div.conversation_message_switcher').css('float') == 'none') {
            //мобильная версия
            _('div.conversation_message_switcher').addClass('inside_conversation');
        }
    });

    _(document).on('click', 'div.conversation_message_switcher_scroller div.photoName a', function () {
        _(this).closest('div.conversation_message_switcher_scroller div[c_id]').click();

        return false;
    });

    _arSuccess('load_conversation', function (jsonData, params, target) {
        //если мы загрузились по истории браузера и отсутствует список диалогов сбоку, обновляем страницу
        if (!el('div.conversation_message_switcher')) {
            window.location.reload();
        }

        target.insert(jsonData['response_text'], 'before');

        if (params['dynamic_load']) {
            //динамическая подгрузка, нужно убрать кнопку подгрузки предыдущих сообщений
            target.parent().find('div.message_content.unread')?.removeClass('unread');

            //динамически подгрузили новые сообщения, нужно промотать вниз принудительно
            _('div.conversation_message_maincontent_scroller_wrap').scrollTop(_('div.conversation_message_maincontent_scroller_wrap').asDomElement().scrollHeight);
        } else {
            //если ссылка была на кнопку "предыдущие столько-то", убираем. В ином случае ссылка почти наверняка на a#bottom, который убирать не нужно.
            if (target.hasClass('load_conversation')) {
                target.remove();
            }

            if (params['obj_limit'] == 0) {
                //первая подгрузка диалога, нужно промотать вниз принудительно
                _('div.conversation_message_maincontent_scroller_wrap').scrollTop(_('div.conversation_message_maincontent_scroller_wrap').asDomElement().scrollHeight);
            }
        }

        //если есть непрочитанные сообщения, уменьшаем общий каунтер непрочитанности тут же и ставим их бэкграунд на исчезновение
        if (el('div.conversation_message_maincontent_scroller_wrap div.message_content.unread')) {
            const count = _('div.conversation_message_maincontent_scroller_wrap').find('div.message_content.unread').length;
            const unreadCount = parseInt(_('span#new_messages_counter').first().text());
            const unreadCount2 = parseInt(_('span#new_personal_counter').first().text());

            showHideByValue('span#new_messages_counter', unreadCount - count);
            showHideByValue('span#new_personal_counter', unreadCount2 - count);

            _('div.conversation_message_maincontent_scroller_wrap div.message_content.unread').addClass('viewed');
        }

        //в конце загрузки, если есть кнопочка диалога, делаем ее выбранной
        _('div[c_id].selected').removeClass('selected');
        _(`div[c_id="${params['obj_id']}"]`).addClass('selected');

        if (_(`div[c_id="${params['obj_id']}"]`).find('div.content_preview')?.hasClass('counter')) {
            _(`div[c_id="${params['obj_id']}"]`).find('div.content_preview').removeClass('unread');
        }
    })
}