/** Виджет задач */
let loadTasksTimeoutTimer = 120000;

if (withDocumentEvents) {
    if (el('div.tasks_widget')) {
        actionRequestSupressErrorForActions.push('load_tasks');

        _('div.tasks_widget').on('click', function () {
            if (_('div.tasks_widget_list').is(':visible')) {
                _('div.tasks_widget_list').hide();
            } else {
                actionRequest({
                    action: 'task/load_tasks',
                    obj_type: 'mine',
                    obj_id: 'all',
                    widget_style: true,
                    show_list: true
                });
            }
        });

        _(document).on('click', 'div.tasks_widget_list_close', function () {
            const self = _(this);

            self.closest('div.tasks_widget_list').hide();
        });

        _(document).on('keyup', '#tasks_widget_list_search_input', function () {
            const self = _(this);
            const scrolldiv = _('div.tasks_widget_list_scroll');
            const string = self.val();

            if (string != '') {
                const string2 = autoLayoutKeyboard(string);

                scrolldiv.children('.tasks_widget_list_item_separator').hide();

                scrolldiv.find('.tasks_widget_list_item_name', false, false, string)?.each(function () {
                    _(this).parent().hide();
                });

                scrolldiv.find(`.tasks_widget_list_item_name`, false, string)?.each(function () {
                    _(this).parent().show();
                });

                scrolldiv.find(`.tasks_widget_list_item_name`, false, string2)?.each(function () {
                    _(this).parent().show();
                });
            } else {
                scrolldiv.children().show();
            }
        });

        _(document).on('click', 'div.tasks_widget_list_functions', function () {
            const self = _(this);
            const dialogWindow = self.closest('div.tasks_widget_list_container');

            dialogWindow.find('div.tasks_widget_list_functions_list').toggle();
        });

        _(document).on('click', 'a.tasks_widget_list_functions_switch', function () {
            const self = _(this);

            self.parent().hide();

            actionRequest({
                action: 'task/load_tasks',
                obj_type: self.attr('obj_type'),
                obj_id: 'all',
                widget_style: true,
                show_list: true
            });
        });

        _(document).on('click', 'a.tasks_widget_list_functions_addtask', function () {
            const self = _(this);
            self.parent().hide();

            createPseudoPrompt(
                `<div><input type="text" name="addtask_name" id="addtask_name" placehold="${LOCALE.tasksWidgetNewTaskName}"></div></div>`,
                LOCALE.tasksWidgetNewTaskHeader,
                [
                    {
                        text: LOCALE.approveCapitalized,
                        class: 'main',
                        click: function () {
                            const self = getNotyDialogDOM();
                            const input = self.find('input[type=text]');

                            if (input.val()) {
                                actionRequest({
                                    action: 'task/add_task',
                                    name: input.val()
                                });

                                notyDialog?.close();
                            } else {
                                showMessage({
                                    text: LOCALE.tasksWidgetNewTaskNameNotEntered,
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
                });
        });

        _(document).on('click', 'div.tasks_widget_list_item', function () {
            const self = _(this);
            const previousItem = self.prev('div.tasks_widget_list_item');
            const functionsList = _('div.tasks_widget_list_scroll div.tasks_widget_list_item_settings_list');
            let waitTime = 0;

            if (functionsList.is(':visible')) {
                functionsList.removeClass('slideDown');
                waitTime = 300;
            }

            delay(waitTime).then(function () {
                if (self.hasClass('editable')) {
                    functionsList.find('a.tasks_widget_list_item_settings_list_edit')?.show();

                    functionsList.find('a.tasks_widget_list_item_settings_list_indent')?.toggle(previousItem?.asDomElement() && !previousItem?.is('div.tasks_widget_list_item_separator') && parseInt(previousItem.attr('level')) >= parseInt(self.attr('level')));

                    functionsList.find('a.tasks_widget_list_item_settings_list_outdent')?.toggle(parseInt(self.attr('level')) - 1 >= 0);
                } else {
                    functionsList.find('a.tasks_widget_list_item_settings_list_edit')?.hide();
                    functionsList.find('a.tasks_widget_list_item_settings_list_indent')?.hide();
                    functionsList.find('a.tasks_widget_list_item_settings_list_outdent')?.hide();
                }

                if (!self.next()?.is('div.tasks_widget_list_item_settings_list')) {
                    self.insert(functionsList.asDomElement(), 'after');

                    delay(100).then(function () {
                        functionsList.addClass('slideDown');
                    })
                } else {
                    functionsList.toggleClass('slideDown', waitTime === 0);
                }
            })
        });

        _(document).on('click', 'a.tasks_widget_list_item_settings_list_goto', function () {
            updateState(`/task/${_(this).parent().prev('div.tasks_widget_list_item').attr('obj_id')}/`);
            _('div.tasks_widget_list_close').trigger('click');
        });

        _(document).on('click', 'a.tasks_widget_list_item_settings_list_edit', function () {
            updateState(`/task/${_(this).parent().prev('div.tasks_widget_list_item').attr('obj_id')}/act=edit`);
            _('div.tasks_widget_list_close').trigger('click');
        });

        _(document).on('click', 'a.tasks_widget_list_item_settings_list_outdent', function () {
            const item = _(this).parent().prev('div.tasks_widget_list_item');
            const previousItem = item.prev(`div.tasks_widget_list_item[level="${(parseInt(item.attr('level')) - 2)}"]`);
            let level;

            if (!previousItem?.asDomElement()) {
                level = 0;
            } else {
                level = parseInt(previousItem.attr('level')) + 1;
            }

            if (level == 0) {
                actionRequest({
                    action: 'task/outdent_task',
                    obj_id: item.attr('obj_id')
                });
            } else {
                actionRequest({
                    action: 'task/outdent_task',
                    obj_id: item.attr('obj_id'),
                    parent_task_id: previousItem.attr('obj_id')
                });
            }
        });

        _(document).on('click', 'a.tasks_widget_list_item_settings_list_indent', function () {
            const item = _(this).parent().prev('div.tasks_widget_list_item');
            const previousItem = item.prev('div.tasks_widget_list_item').first();

            if (previousItem?.asDomElement() && !previousItem?.is('div.tasks_widget_list_item_separator') && parseInt(previousItem.attr('level')) >= parseInt(item.attr('level'))) {
                actionRequest({
                    action: 'task/indent_task',
                    obj_id: item.attr('obj_id'),
                    parent_task_id: previousItem.attr('obj_id')
                });
            }
        });

        _arSuccess('load_tasks', function (jsonData, params) {
            _('div.tasks_widget').find('span.value').text(jsonData['response_text']);

            if (params['show_list'] == true) {
                let html = `<div class="tasks_widget_list_header"><div class="tasks_widget_list_close sbi"></div><div class="tasks_widget_list_functions"></div>${LOCALE.tasksWidgetHeaders[params['obj_type']]}</div><div class="tasks_widget_list_functions_list">`;

                _each(LOCALE.tasksWidgetHeaders, function (taskGroupName, key) {
                    html += `<a class="tasks_widget_list_functions_switch" obj_type="${key}">${taskGroupName}</a>`;
                });

                html += `<a class="tasks_widget_list_functions_addtask">${LOCALE.tasksWidgetFunctionsAddTask}</a></div><div class="tasks_widget_list_scroll ${params['obj_type']}">`;

                if (params['obj_type'] == 'closed') {
                    _each(jsonData['response_data']['closed'], function (value) {
                        html += `<div class="tasks_widget_list_item closed${(value[`editable`] === "true" ? ` editable` : ``)}" obj_id="${value[`id`]}" level="${value[`level`]}"><div class="tasks_widget_list_item_settings"></div>${(value[`unread_count`] > 0 ? `<div class="tasks_widget_list_item_count">${value[`unread_count`]}</div>` : ``)}<div class="tasks_widget_list_item_name${(value[`bold`] === `true` ? ' bold' : ``)}" style="padding-left: ${value['level']}em;">${value[`name`]}</div><div class="clear"></div></div>`;
                    });
                } else {
                    _each(['overdue', 'today', 'tomorrow', 'later', 'no_date'], function (taskGroupName) {
                        if (jsonData['response_data'][taskGroupName]) {
                            html += `<div class="tasks_widget_list_item_separator">${LOCALE.tasksWidgetSeparators[taskGroupName]}</div>`;

                            _each(jsonData['response_data'][taskGroupName], function (value) {
                                html += `<div class="tasks_widget_list_item ${taskGroupName + (value[`editable`] === "true" ? ` editable` : ``)}" obj_id="${value[`id`]}"  level="${value[`level`]}"><div class="tasks_widget_list_item_settings"></div>${(value[`unread_count`] > 0 ? `<div class="tasks_widget_list_item_count">${value[`unread_count`]}</div>` : ``)}<div class="tasks_widget_list_item_name${(value[`bold`] === `true` ? ' bold' : ``)}" style="padding-left: ${value['level']}em;">${value[`name`]}</div><div class="clear"></div></div>`;
                            });
                        }
                    });
                }

                html += '<div class="tasks_widget_list_item_settings_list slide">';

                _each(LOCALE.tasksWidgetItemSettings, function (function_name, key) {
                    html += `<a class="tasks_widget_list_item_settings_list_${key}">${function_name}</a>`;
                });

                html += `</div></div><div class="tasks_widget_list_search"><input type="text" id="tasks_widget_list_search_input" placehold="${LOCALE.tasksWidgetSearch}"></div>`;

                _('div.tasks_widget_list > div.tasks_widget_list_container').html(html);

                fraymPlaceholder('#tasks_widget_list_search_input');

                fraymDragDropApply('.tasks_widget_list_scroll .tasks_widget_list_item', {
                    sortable: true,
                    dragStart: function () {
                        _('div.tasks_widget_list_scroll div.tasks_widget_list_item_settings_list').hide();
                    },
                    dragEnd: function () {
                        const self = _(this);

                        if (self.hasClass('editable')) {
                            const prevElement = self.prev();

                            if (prevElement?.is('div.tasks_widget_list_item_separator')) {
                                actionRequest({
                                    action: 'task/outdent_task',
                                    obj_id: self.attr('obj_id')
                                });
                            } else if (prevElement?.is('div.tasks_widget_list_item')) {
                                actionRequest({
                                    action: 'task/indent_task',
                                    obj_id: self.attr('obj_id'),
                                    parent_task_id: self.prev('div.tasks_widget_list_item').attr('obj_id')
                                });
                            }
                        }
                    }
                })

                _('div.tasks_widget_list').show();
            }
        })

        _arSuccess('outdent_task', function (jsonData, params, target) {
            let objType = 'mine';

            _each(LOCALE.tasksWidgetHeaders, function (taskGroupName) {
                if (_('div.tasks_widget_list_scroll').hasClass(taskGroupName)) {
                    objType = taskGroupName;
                }
            });

            actionRequest({
                action: 'task/load_tasks',
                obj_type: objType,
                obj_id: 'all',
                widget_style: true,
                show_list: true
            });
        })

        _arError('outdent_task', function (jsonData, params, target, error) {
            let objType = 'mine';

            _each(LOCALE.tasksWidgetHeaders, function (taskGroupName) {
                if (_('div.tasks_widget_list_scroll').hasClass(taskGroupName)) {
                    objType = taskGroupName;
                }
            });

            actionRequest({
                action: 'task/load_tasks',
                obj_type: objType,
                obj_id: 'all',
                widget_style: true,
                show_list: true
            });
        })

        _arSuccess('indent_task', function (jsonData, params, target) {
            let objType = 'mine';

            _each(LOCALE.tasksWidgetHeaders, function (taskGroupName) {
                if (_('div.tasks_widget_list_scroll').hasClass(taskGroupName)) {
                    objType = taskGroupName;
                }
            });

            actionRequest({
                action: 'task/load_tasks',
                obj_type: objType,
                obj_id: 'all',
                widget_style: true,
                show_list: true
            });
        })

        _arError('indent_task', function (jsonData, params, target, error) {
            let objType = 'mine';

            _each(LOCALE.tasksWidgetHeaders, function (taskGroupName) {
                if (_('div.tasks_widget_list_scroll').hasClass(taskGroupName)) {
                    objType = taskGroupName;
                }
            });

            actionRequest({
                action: 'task/load_tasks',
                obj_type: objType,
                obj_id: 'all',
                widget_style: true,
                show_list: true
            });
        })

        _arSuccess('add_task', function (jsonData, params, target) {
            showMessageFromJsonData(jsonData);

            actionRequest({
                action: 'task/load_tasks',
                obj_type: 'mine',
                obj_id: 'all',
                widget_style: true,
                show_list: true
            });
        })

        loadTasksTimeout();
    }
}

function loadTasksTimeout() {
    if (document.addEventListener === undefined || visibilityChangeHidden === undefined || !document[visibilityChangeHidden] || isInStandalone) {
        actionRequest({
            action: 'task/load_tasks',
            obj_type: 'mine',
            obj_id: 'all',
            widget_style: true,
            show_list: false
        });
    }

    debounce('loadTasks', loadTasksTimeout, loadTasksTimeoutTimer);
}