loadJsComponent('task_event');

/** Задачи */

if (withDocumentEvents) {
    getHelperData('div.task_message_data', 'get_task_unread_people');

    /** Изменения полей статуса и приоритета в задачах */
    _(document).on('click', 'div.task_status', function () {
        showConversationForm(_('div.conversation_form_data textarea'));
        _('div.conversation_form_controls select[name="status"]').focus();
    });

    _(document).on('click', 'div.task_priority', function () {
        showConversationForm(_('div.conversation_form_data textarea'));
        _('div.conversation_form_controls select[name="priority"]').focus();
    });

    _(document).on('change', 'div.conversation_form_controls select[name="status"]', function () {
        const self = _(this);

        if (self.val() == '{delayed}') {
            self.next('input').show();
        } else {
            self.next('input').hide();
        }
    });
}