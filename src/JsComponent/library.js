/** Библиотека файлов / ссылок / папок */

if (withDocumentEvents) {
    _(document).on('click', '.files_group', function () {
        const self = _(this);

        self.next('div.files_group_data')?.toggle();
    });

    _(document).on('click', 'div.project_tasks_library a.expand_all_branches', function () {
        _(this).parent().find('.files_group').each(function () {
            _(this).click();
        })
    });

    /** Вывод окошка добавления ссылки в библиотеку */
    _(document).on('click', '[id$=_library_link_wrapper]', function () {
        const btn = _(this);

        createPseudoPrompt(
            `<div><input type="text" name="add_link_input" placehold="${LOCALE.enterLinkAddress}"><input type="text" name="add_link_input2" placehold="${LOCALE.enterLinkName}"></div>`,
            LOCALE.linkAddHeader,
            [
                {
                    text: LOCALE.addCapitalized,
                    class: 'main',
                    click: function () {
                        const self = getNotyDialogDOM();
                        const input = self.find('input[name="add_link_input"]');
                        const input2 = self.find('input[name="add_link_input2"]');

                        if (input.val()) {
                            if (checkHttpUrl(input.val())) {
                                if (input2.val()) {
                                    actionRequest({
                                        action: 'file/add_link',
                                        link: input.val(),
                                        name: input2.val(),
                                        obj_type: btn.attr('obj_type'),
                                        obj_id: btn.attr('obj_id')
                                    });
                                } else {
                                    showMessage({
                                        text: LOCALE.linkNameNotEntered,
                                        type: 'error',
                                        timeout: 5000
                                    });

                                    input2.focus();
                                }
                            } else {
                                showMessage({
                                    text: LOCALE.wrongLinkFormat,
                                    type: 'error',
                                    timeout: 5000
                                });

                                input.focus();
                            }
                        } else {
                            showMessage({
                                text: LOCALE.linkAddressNotEntered,
                                type: 'error',
                                timeout: 5000
                            });

                            input.focus();
                        }
                    }
                }
            ],
            null,
            function () {
                const self = getNotyDialogDOM();
                const input = self.find('input[name="add_link_input"]');
                const input2 = self.find('input[name="add_link_input2"]');

                fraymPlaceholder(input);
                fraymPlaceholder(input2);
            }
        );
    });

    /** Вывод окошка добавления папки в библиотеку */
    _(document).on('click', '[id$=_library_create_folder_wrapper]', function () {
        const btn = _(this);

        createPseudoPrompt(
            `<div><input type="text" name="create_folder_name" placehold="${LOCALE.createFolderName}"></div>`,
            LOCALE.createFolderHeader,
            [
                {
                    text: LOCALE.createCapitalized,
                    class: 'main',
                    click: function () {
                        const self = getNotyDialogDOM();
                        const input = self.find('input[type="text"]');

                        if (input.val() != '') {
                            actionRequest({
                                action: 'file/create_folder',
                                name: input.val(),
                                obj_type: btn.attr('obj_type'),
                                obj_id: btn.attr('obj_id'),
                                additionalTarget: btn.parent().find('[class$="library"]')
                            }, self);
                        } else {
                            showMessage({
                                text: LOCALE.createFolderNameNotEntered,
                                type: 'error',
                                timeout: 5000
                            });

                            input.focus();
                        }
                    }
                }
            ],
            null,
            function () {
                const self = getNotyDialogDOM();
                const input = self.find('input[type="text"]');

                fraymPlaceholder(input);
            }
        );
    });

    /** Вывод окошка редактирования объекта в библиотеке */
    _(document).on('click', 'div.uploaded_file a.edit_file', function (e) {
        e.stopImmediatePropagation();

        const self = _(this);
        const name = self.next('a:not(.trash)').text();
        const description = self.next('span.uploaded_file_description')?.html().br2nl();
        const trashField = self.prev('a.trash');

        let type = '';
        let obj_id = '';

        if (trashField.is('[action_request="file/delete_folder"]')) {
            type = 'library_folder';
            obj_id = trashField.attr('obj_id');
        } else if (trashField.is('[post_action="file/delete_library_file"]')) {
            type = 'library_file';
            obj_id = trashField.attr('post_action_id');
        } else if (trashField.is('[action_request="file/delete_link"]')) {
            type = 'library_link';
            obj_id = trashField.attr('obj_id');
        } else if (trashField.is('[post_action="file/delete_conversation_file"]')) {
            type = 'conversation_file';
            obj_id = trashField.attr('post_action_id');
        }

        const btn = _(this);

        createPseudoPrompt(
            `<div><input type="text" name="edit_file_name" value="${name}" placehold="${LOCALE.editFileName}"><textarea name="edit_file_description" placehold="${LOCALE.editFileDescription}">${description ?? ''}</textarea></div>`,
            LOCALE.renameCapitalized,
            [
                {
                    text: LOCALE.renameCapitalized,
                    class: 'main',
                    click: function () {
                        const self = getNotyDialogDOM();
                        const input = self.find('input[name="edit_file_name"]');
                        const input2 = self.find('textarea[name="edit_file_description"]');

                        if (input.val()) {
                            actionRequest({
                                action: 'file/edit_file_or_folder_name',
                                name: input.val(),
                                description: input2.val(),
                                obj_type: type,
                                obj_id: obj_id,
                                additionalTarget: btn.parent()
                            });
                        } else {
                            showMessage({
                                text: LOCALE.editFileNameNotEntered,
                                type: 'error',
                                timeout: 5000
                            });

                            input.focus();
                        }
                    }
                }
            ],
            null,
            function () {
                const self = getNotyDialogDOM();
                const input = self.find('input[name="edit_file_name"]');
                const input2 = self.find('textarea[name="edit_file_description"]');

                fraymPlaceholder(input);
                fraymPlaceholder(input2);
            }
        );
    });

    /** Управление доступом к папке в библиотеке */
    _(document).on('click', '[id$="_library_show_rights_wrapper"]', function () {
        _('div#folder_usersList').toggle();
    });

    _(document).on('click', '[id$="_library_set_rights_wrapper"]', function () {
        const self = _(this);
        const usersList = [];

        _('input[name^="usersList"]:checked').each(function () {
            const num = _(this).attr('name').match(/\d+/);

            num = +num[0];
            usersList.push(num);
        });

        actionRequest({
            action: 'file/change_folder_rights',
            obj_type: '{folder}',
            obj_id: self.attr('obj_id'),
            usersList: usersList
        }, self.parent());
    });

    /** Динамические подгрузки */
    _(document).on('click', 'div.uploaded_file.folder:not([class^="files_group"])', function () {
        const self = _(this);
        self.html(`<a class="bold_link">${LOCALE.file.fileLoading}</a>`);

        actionRequest({
            action: 'file/load_library',
            obj_type: '{folder}',
            obj_id: self.attr('obj_id'),
            external: self.attr('external')
        }, self.parent());
    });

    _(document).on('click', 'a.folder_top', function () {
        const self = _(this);
        self.text(LOCALE.file.fileLoading);

        actionRequest({
            action: 'file/load_library',
            obj_type: self.attr('obj_type'),
            obj_id: self.attr('obj_id'),
            external: self.attr('external')
        }, self.parent().parent());
    });

    _(document).on('click', 'a.folder_path', function () {
        const self = _(this);

        self.text(LOCALE.file.fileLoading);

        actionRequest({
            action: 'file/load_library',
            obj_type: '{folder}',
            obj_id: self.attr('obj_id'),
            external: self.attr('external')
        }, self.parent().parent());
    });

    _arSuccess('move_file_to_folder', function (jsonData, params, target) {
        target.remove();
    })

    _arSuccess('load_library', function (jsonData, params, target) {
        target.parent().html(jsonData['response_text']);

        /** Перемещения файлов в папки */
        _(`div.uploaded_file:not(.folder)`).each(function () {
            if (el('a.edit_file', this)) {
                fraymDragDropApply(this, {
                    revert: true,
                    dropTargets: [
                        {
                            elementSelector: 'div.uploaded_file.folder',
                            onDrop: function (element) {
                                element = _(element);

                                const fileId = element.find('a.trash').attr('obj_id');
                                const folderId = _(this).attr('obj_id');

                                if (fileId > 0 && folderId > 0) {
                                    actionRequest({
                                        action: 'file/move_file_to_folder',
                                        file_id: fileId,
                                        folder_id: folderId,
                                        parent_obj: false
                                    }, element);
                                }
                            }
                        },
                        {
                            elementSelector: 'span.links',
                            onDrop: function (element) {
                                element = _(element);

                                const fileId = element.find('a.trash').attr('obj_id');
                                const folderId = _(this).find('a.folder_path').last().attr('obj_id');

                                if (fileId > 0 && folderId > 0) {
                                    actionRequest({
                                        action: 'file/move_file_to_folder',
                                        file_id: fileId,
                                        folder_id: folderId,
                                        parent_obj: true
                                    }, element);
                                }
                            }
                        }
                    ]
                })
            }
        });
    })

    _arSuccess('create_folder', function (jsonData, params, target) {
        showMessageFromJsonData(jsonData);

        const name = target.find('input[name="create_folder_name"]');
        const newDiv = elFromHTML(`<div class="uploaded_file folder" obj_id="${jsonData['id']}"><a class="edit_file" title="${LOCALE.edit}"></a><a class="trash careful action_request" title="${LOCALE.delete}" action_request="file/delete_folder" obj_id="${jsonData[`id`]}"></a> <a class="bold_link">${name.val()}</a></div>`);

        if (params.additionalTarget.find('span.links')) {
            params.additionalTarget.find('span.links').insert(newDiv, 'after');
        } else {
            params.additionalTarget.insert(newDiv, 'begin');
        }

        notyDialog?.close();
    })

    _arSuccess('edit_file_or_folder_name', function (jsonData, params, target) {
        showMessageFromJsonData(jsonData);

        if (params['obj_type'] == 'library_link' || params['obj_type'] == 'library_folder' || params['obj_type'] == 'library_file') {
            const description = params['description'].nl2br();

            params.additionalTarget.find('a.bold_link').text(params['name']);

            if (params.additionalTarget.find('span.uploaded_file_description')) {
                if (description.length == 0) {
                    params.additionalTarget.find('span.uploaded_file_description').prev('br')?.remove();
                    params.additionalTarget.find('span.uploaded_file_description').remove();
                } else {
                    params.additionalTarget.find('span.uploaded_file_description').html(description);
                }
            } else if (description.length > 0) {
                params.additionalTarget.insert(`<span class="uploaded_file_description">${description}</span>`, 'end');
            }
        } else if (params['obj_type'] == 'conversation_file') {
            params.additionalTarget.find('a:not(.trash, .edit_file)').text(params['name']);
        }

        notyDialog?.close();
    })

    _arSuccess('add_link', function (jsonData, params, target) {
        showMessageFromJsonData(jsonData);

        //обновляем окошко с файлами
        const loadLibraryBtn = _('button[id$="_library_link_wrapper"]');
        const loadLibraryLink = elFromHTML('<a></a>');

        loadLibraryBtn.parent().insert(loadLibraryLink, 'append');

        actionRequest({
            action: 'file/load_library',
            obj_type: loadLibraryBtn.attr('obj_type'),
            obj_id: loadLibraryBtn.attr('obj_id'),
            external: 'false'
        }, loadLibraryLink);

        notyDialog?.close();
    })

    _arSuccess('delete_folder', function (jsonData, params, target) {
        showMessageFromJsonData(jsonData);

        target.parent().remove();
    })

    _arSuccess('delete_link', function (jsonData, params, target) {
        showMessageFromJsonData(jsonData);

        target.parent().remove();
    })

    _arSuccess('change_folder_rights', function (jsonData, params, target) {
        showMessageFromJsonData(jsonData);

        if (jsonData['button_text'].length) {
            _('[id$="_library_show_rights_wrapper"] > span').text(jsonData['button_text']);
        }

        target.hide();
    })
}