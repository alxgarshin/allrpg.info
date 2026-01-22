/** Сетка ролей */

if (postAllrpgRolesList === undefined) {
    function postAllrpgRolesList() {
        fraymDragDropApply('div#allrpgRolesListDiv div.allrpgRolesListString', {
            sortable: true,
            handler: 'span.character_move',
            dragEnd: function (e) {
                const self = _(this);

                const afterObjId = self.prev('div.allrpgRolesListString')?.find('div.allrpgRolesListCharacter')?.attr('data-obj-id');
                const groupId = self.closest('div.allrpgRolesListGroup').attr('data-obj-id');
                const objId = self.find('div.allrpgRolesListCharacter').attr('data-obj-id');

                actionRequest({
                    action: 'group/change_character_code',
                    obj_id: objId,
                    group_id: groupId,
                    after_obj_id: afterObjId
                });
            }
        })

        fraymDragDropApply('div#allrpgRolesListDiv div.allrpgRolesListGroup.editable', {
            sortable: true,
            handler: 'span.group_move:not(.disabled)',
            dragEnd: function (e) {
                const self = _(this);

                const prevItem = self.prev('div.allrpgRolesListGroup');

                const parentOffset = self.offset();
                const relX = e.pageX - parentOffset.left;
                const percentage = relX / (self.asDomElement().offsetWidth / 100);

                actionRequest({
                    action: 'group/change_group_code',
                    obj_id: self.attr('data-obj-id'),
                    level: (percentage < 20 ? 'sibling' : 'child'),
                    after_obj_id: prevItem.attr('data-obj-id')
                }, self);
            }
        })
    }
}

if (el('div#allrpgRolesListDiv')) {
    window.dynrequestPath = '/roles/';

    dataElementLoad(
        'roles',
        document,
        () => {
            getScript('/js/roles.min.js').then(() => {
                dataLoaded['libraries']['roles'] = true;
            });
        },
        function () {
            allrpgRolesList('create').then(() => {
                postAllrpgRolesList();
            })
        }
    )

    _('a.id_instead_of_description').on('click', function (event) {
        event.preventDefault();

        const e = _(`#${_(this).attr('href').substr(1)}`);

        if (e) {
            scrollWindow(e.offset().top);
        }
    });

    _('input#allrpgRolesListFilterInput').on('keyup change', function () {
        //ждем немного дальнейшего ввода
        window.clearTimeout(window['allrpgRolesListFilterInput_timeout']);
        window['allrpgRolesListFilterInput_timeout'] = setTimeout(function () {
            const self = _('input#allrpgRolesListFilterInput');
            const string = self.val();
            const roleslist = _('div#allrpgRolesListDiv');

            if (string == '') {
                roleslist.find('.allrpgRolesListGroup').show();
                roleslist.find('.allrpgRolesListString').show();
            } else {
                roleslist.find('.allrpgRolesListGroup').hide();
                roleslist.find('.allrpgRolesListString').hide();

                roleslist.find(`.allrpgRolesListCharacterName`, false, string).each(function () {
                    _(this).closest('.allrpgRolesListString').show();
                    _(this).closest('.allrpgRolesListGroup').show();
                });

                roleslist.find(`.allrpgRolesListGroupName`, false, string).each(function () {
                    _(this).closest('.allrpgRolesListGroup').show();
                    _(this).closest('.allrpgRolesListGroup').find('.allrpgRolesListString').show();
                });
            }
        }, 500);
    });

    _('a.expand_group').on('click', function () {
        const self = _(this);
        const groupId = self.attr('group_id');

        self.find('.sbi-plus').toggle();
        self.find('.sbi-minus').toggle();
        _(`div.group_filter[parent_id="${groupId}"]`).toggle();
    });

    /** Переключение видимости сетки ролей и режима ее вывода */
    if (el('div.roles_helper_functions')) {
        _('a#switch_show_roleslist').on('click', function () {
            const self = _(this);

            actionRequest({
                action: 'roles/switch_show_roleslist'
            }, self);
        });

        _('a#switch_view_roleslist_mode').on('click', function () {
            const self = _(this);

            actionRequest({
                action: 'roles/switch_view_roleslist_mode'
            }, self);
        });

        _('a#switch_show_roleslist_helper').on('click', function () {
            _('div#roles_helper_data').toggle();
        });
    }
}

if (withDocumentEvents) {
    getHelperData('div.allrpgRolesListApplicationsListApplication .sbi-info', 'show_user_info_from_rolelist');

    _arSuccess('switch_show_roleslist', function (jsonData, params, target) {
        target.parent().html(jsonData['response_data']);

        _('a#switch_show_roleslist').on('click', function () {
            const self = _(this);

            actionRequest({
                action: 'roles/switch_show_roleslist'
            }, self);
        });
    })

    _arSuccess('switch_view_roleslist_mode', function (jsonData, params, target) {
        target.parent().html(jsonData['response_data']);
        allrpgRolesList('create').then(() => {
            postAllrpgRolesList();
        })

        _('a#switch_view_roleslist_mode').on('click', function () {
            const self = _(this);

            actionRequest({
                action: 'roles/switch_view_roleslist_mode'
            }, self);
        });
    })

    _arSuccess('change_character_code', function () { })

    _arSuccess('change_group_code', function (jsonData, params, target) {
        const prevObj = _(`[data-obj-id="${params.after_obj_id}"]`);

        target.attr('data-obj-level', parseInt(prevObj.attr('data-obj-level')) + (params.level === 'child' ? 1 : 0));
    })

    _arSuccess('show_user_info_from_rolelist', function (jsonData, params, target) {
        getHelpersSuccess(jsonData, params, target);
    })
}