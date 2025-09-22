let scrollPageTop = true;

let deferredPrompt;
let PWAsuccess = false;

let getNewEventsTimeoutTimer = 30000;

let newTasksCounterObjType = null;
let newTasksCounterObjId = 'all';
window['newTasksCounterObjTypeCache'] = defaultFor(window['newTasksCounterObjTypeCache'], null);
window['newTasksCounterObjIdCache'] = defaultFor(window['newTasksCounterObjIdCache'], null);

window['projectControlId'] = defaultFor(window['projectControlId'], 0);
window['projectControlItems'] = defaultFor(window['projectControlItems'], 'hide');
window['projectControlItemsName'] = defaultFor(window['projectControlItemsName'], null);
window['projectControlItemsRights'] = defaultFor(window['projectControlItemsRights'], null);

let newApplicationCommentsIds = [];
let newIngameApplicationCommentsIds = [];
window.feeLockedRoom = defaultFor(window.feeLockedRoom, []);

let geolocationId = 0;
let flashlight = false;

/** Инициализация различных кастомных элементов, которые могут быть, а могут и отсутствовать на конкретной странице */
async function projectInit(withDocumentEvents, updateHash) {
    /** Фиксируем время начала отработки */
    startTime = new Date().getTime();

    updateHash = defaultFor(updateHash, false);

    blockDefaultSubmit = false;

    await loadJsCssForCMSVC();

    loadSbiBackground();

    /** Остановить камеру */
    stopVideo();

    /** Нужно ли прокрутить страницу на верх по окончанию действия? Можно менять в процессе инициализации, чтобы избежать ненужной прокрутки */
    scrollPageTop = true;

    /** Отключаем геолокацию, если мы не в модуле игрока */
    if (!el('div.kind_ingame') && geolocationId > 0) {
        navigator.geolocation.clearWatch(geolocationId);
        geolocationId = 0;
    }

    /** Выставление параметров для getNewEventsTimeout() */
    if (window['newTasksCounterObjIdCache']) {
        newTasksCounterObjType = window['newTasksCounterObjTypeCache'];
        newTasksCounterObjId = window['newTasksCounterObjIdCache'];
        window['newTasksCounterObjTypeCache'] = null;
        window['newTasksCounterObjIdCache'] = null;
    } else {
        newTasksCounterObjType = null;
        newTasksCounterObjId = 'all';
    }

    /** Кнопка вывода QR-code */
    if (el('.show_qr_code')) {
        preload([
            `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${currentHref}`
        ]);
    }

    /** Маркеры непрочтенных сообщений */
    _('[unread_count]').each(function () {
        const self = _(this);

        if (!self.hasClass('unread_count_applied')) {
            if (self.closest('[id^="tabs"]')) {
                const tab = self.closest('[id^="tabs"]');
                const link = _(`a[href="#${tab.attr('id')}"]`);
                const count = parseInt(self.attr('unread_count'));

                if (count > 0) {
                    if (link.find('.red')) {
                        count += parseInt(link.find('.red').text());
                        link.find('.red').text(` ${count}`);
                    } else {
                        link.html(`${link.html()}<sup class="red">${count}</sup>`);
                    }
                }
            }

            self.addClass('unread_count_applied');
        }
    });

    /** Поиск людей в управлении правами пользователей различных объектов */
    if (el('input[name=user_rights_lookup]')) {
        _('input[name=user_rights_lookup]').on('keyup', function () {
            const pureString = _(this).val();
            const string = autoLayoutKeyboard(pureString);
            const parentObj = _(this).parent();

            if (string === '') {
                parentObj.find('div.photoName').show();
            } else {
                parentObj.find('div.photoName div.photoName_name a', false, null, pureString)?.each(function () {
                    _(this).closest('div.photoName').hide();
                });

                parentObj.find('div.photoName div.photoName_name a', false, null, string)?.each(function () {
                    _(this).closest('div.photoName').hide();
                });

                parentObj.find(`div.photoName div.photoName_name a`, false, pureString)?.each(function () {
                    _(this).closest('div.photoName').show();
                });

                parentObj.find(`div.photoName div.photoName_name a`, false, string)?.each(function () {
                    _(this).closest('div.photoName').show();
                });
            }
        });
    }

    /** Меню действий */
    submenuToggle('div.actions_list_text', 'div.actions_list_items');

    /** Управление основным меню */
    if (el('div.mobile_menu')) {
        /** Проверяем, есть ли ссылка в меню с таким url'ом. Если да, то ставим ей класс selected */
        const currentHrefParsed = parseUri(currentHref);
        const shortHref = currentHref.match('https://([^/]+)/([^/]+)/');

        let menuObj = el(`div.mobile_menu a.submenu[href="${currentHref}"]`) ? _(`div.mobile_menu a.submenu[href="${currentHref}"]`) : (shortHref !== null && el(`div.mobile_menu a.submenu[href="${shortHref[0]}"]`) ? _(`div.mobile_menu a.submenu[href="${shortHref[0]}"]`) : null);

        if (!menuObj) {
            menuObj = el(`div.mobile_menu a.menu[href="${currentHref}"]`) ? _(`div.mobile_menu a.menu[href="${currentHref}"]`) : (shortHref !== null && el(`div.mobile_menu a.menu[href="${shortHref[0]}"]`) ? _(`div.mobile_menu a.menu[href="${shortHref[0]}"]`) : null);
        }

        if (menuObj) {
            _('div.mobile_menu').removeClass('shown');
            _('.mobile_menu .selected').removeClass('selected');
            menuObj.addClass('selected');
        } else if (el(`a.submenu[href="${currentHref}"]`)) {
            _('.mobile_menu .selected').removeClass('selected');
            _(`a.submenu[href="${currentHref}"]`).addClass('selected');
        } else if (el(`a.menu[href="${currentHref}"]`)) {
            _('.mobile_menu .selected').removeClass('selected');
            _(`a.menu[href="${currentHref}"]`).addClass('selected');
        } else if (currentHrefParsed.path === '/') {
            _('.mobile_menu .selected').removeClass('selected');
        }

        /** Переключение управляющих элементов подпунктов проекта */
        const controlItemsDiv = _('div#project_control_items');
        const projectHref = `/project/${projectControlId}/`;

        if (projectControlItems === 'show') {
            if (el(`div.item6 a.submenu[obj_id="${projectControlId}"]`)) {
                _(`div.item6 a.submenu[obj_id="${projectControlId}"]`).insert(controlItemsDiv.show(), 'after');
            } else if (projectControlItemsName) {
                _(`div.mobile_menu a.submenu_4_item_1`).insert(`<a class="edit" href="${projectHref}act=edit"></a><a class="submenu submenu_4_item_100" href="${projectHref}" obj_id="${projectControlId}">${projectControlItemsName}</a>`, `after`);

                _(`div.mobile_menu a.submenu[obj_id="${projectControlId}"]`).insert(controlItemsDiv.show(), 'after');
            } else {
                _(`div.mobile_menu a.submenu_4_item_1`).insert(controlItemsDiv.show(), 'after');
            }

            if (projectControlItemsRights) {
                controlItemsDiv.attr('rights', projectControlItemsRights);
            }

            /** Замена id в ссылке, ведущей на список ролей проекта */
            _('a.submenu.roles_list').attr('href', `${absolutePath()}/roles/${projectControlId}/`);
        } else {
            if (projectControlId > 0 && projectControlItemsName && !el(`div.item6 a.submenu[obj_id="${projectControlId}"]`)) {
                _(`div.mobile_menu div.submenu_6`).insert(`<a class="submenu submenu_4_item_2 selected" href="${projectHref}" obj_id="${projectControlId}">${projectControlItemsName}</a>`, `end`);
            }

            controlItemsDiv
                .hide()
                .attr('rights', '');
        }
    }

    /** Предложение установки приложения */
    if (el('div.PWAinfo:not(.pwaInfoApplied)')) {
        const self = _('div.PWAinfo:not(.pwaInfoApplied)');
        const userAgent = window.navigator.userAgent.toLowerCase();

        if (/iphone|ipod|ipad/i.test(navigator.platform) || /iphone|ipad|ipod/i.test(userAgent)) {
            if (!isInStandalone && /safari/.test(userAgent)) {
                self.find('div.undefined').hide();
                self.find('div.ios').show();
            }
        } else if ("onbeforeinstallprompt" in window && !/Opera|OPR\//.test(userAgent)) {
            self.find('div.undefined').hide();
            self.find('div.android').show();

            if (PWAsuccess) {
                self.find('div.android').addClass('showSuccess');
            }
        }

        self.addClass('pwaInfoApplied');
    }

    /** Динамические поля */
    initDynamicFields();

    if (withDocumentEvents) {
        /** БЛОКИРОВКА СКРОЛЛА */

        _(document).on('mouseover touchstart', '.mobile_menu.shown, .conversations_widget_list, .conversations_widget_message_container, .tasks_widget_list, div.fullpage_cover', function () {
            if (document.documentElement.scrollHeight > window.innerHeight) {
                _('body').addClass('noscroll');
            }
        });

        _(document).on('mouseout touchend', '.mobile_menu.shown, .conversations_widget_list, .conversations_widget_message_container, .tasks_widget_list, div.fullpage_cover', function () {
            _('body').removeClass('noscroll');
        });

        _(document).on('click', '.mobile_menu.shown a, a.logo, div.conversations_widget_list_close', function () {
            _('body').removeClass('noscroll');
        });

        /** ПРЕДУПРЕЖДЕНИЕ ОБ ОТСУТСТВИИ СВЯЗИ */

        window.addEventListener('online', function () {
            const self = _('div.fullpage_cover');

            self.removeClass('offline_shown');

            if (self.hasClass('was_shown')) {
                self.removeClass('was_shown');
            } else {
                self.hide();
            }
        });

        window.addEventListener('offline', function () {
            const self = _('div.fullpage_cover');

            if (self.is(':visible')) {
                self.addClass('was_shown');
            }

            self.addClass('offline_shown');
            self.show();
        });

        if (navigator.onLine) {
            const self = _('div.fullpage_cover');

            self.removeClass('offline_shown');

            if (self.hasClass('was_shown')) {
                self.removeClass('was_shown');
            } else {
                self.hide();
            }
        } else {
            const self = _('div.fullpage_cover');

            if (self.is(':visible')) {
                self.addClass('was_shown');
            }

            self.addClass('offline_shown');
            self.show();
        }

        /** УСТАНОВКА ПРИЛОЖЕНИЯ */

        _(document).on('click', 'div.PWAinfo .PWAsuccess', function () {
            window.open(window.location.href, '_blank');
        });

        _(document).on('click', 'div.android div.installPWA', function () {
            _('div.PWAinfo div.installPWA').addClass('transparentText');
            appendLoader('div.PWAinfo div.installPWA');

            if (deferredPrompt !== undefined) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function (choiceResult) {
                    if (choiceResult.outcome === 'accepted') {
                        _('div.PWAinfo div.android').addClass('showSuccess');
                        PWAsuccess = true;
                    } else {
                        _('div.PWAinfo div.installPWA').removeClass('transparentText');
                        removeLoader('div.PWAinfo div.installPWA');
                    }
                });
            }
        });

        if ("onbeforeinstallprompt" in window && !window.navigator.userAgent.match(/Opera|OPR\//)) {
            window.addEventListener('beforeinstallprompt', function (e) {
                e.preventDefault();
                PWAsuccess = false;
                deferredPrompt = e;
            });
        }

        if ('getInstalledRelatedApps' in navigator) {
            getInstalledApps();
        }

        /** Регистрация service worker'а необходимого для PWA */
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/js/pwa_sw.min.js').catch(function (err) {
                console.warn('Error whilst registering application service worker', err);
            });
        }

        /** ВЫПАДАЮЩИЕ МЕНЮ */

        /** Мобильное меню */
        submenuToggle('div.mobile_menu_button', 'div.mobile_menu');

        /** Меню пользователя */
        submenuToggle('div.login_user_data div.photoName', 'div.user_menu');

        /** Переключатель локали */
        submenuToggle('div.locale_switcher', 'div.locale_switcher_list');

        /** ПОИСКИ */

        /** Обработка enter'а в строке поиска */
        _(document).on('keydown', 'input.search_input', function (e) {
            if (e.keyCode == 13) {
                _(this).closest('form').submit();
            }
        });

        /** При входе в поле поиска обязательно сохраняем открытым меню в мобильном виде */
        _(document).on('focus click', 'input.search_input', function (e) {
            e.stopImmediatePropagation();
        });

        /** Любые поиски */
        _(document).on('click', 'a.search_image', function () {
            _(this).closest('form').trigger('submit');
        })

        /** Поиск на верху страницы */
        if (el('[name="qwerty"]')) {
            const self = _('[name="qwerty"]');
            const parent = self.closest('div.qwerty_space');

            fraymAutocompleteApply(
                self,
                {
                    source: '/helper_search/',
                    select: function () {
                        parent.find('form').submit();
                    },
                    change: function (value) {
                        this.options.minLength = isInt(value) ? 1 : 3;
                    }
                }
            );
        }

        /** ОСНОВНОЕ МЕНЮ */

        _(document).on('click', 'div.mobile_menu a, a.logo', function () {
            _('div.mobile_menu').removeClass('shown');
        });

        _(document).on('click', 'a.logo', function (e) {
            e.preventDefault();

            _('a.menu.item3').click();
        });

        /** Нужно, чтобы не проходил переход по клику на верхний уровень мобильного меню */
        _('div.mobile_menu a.menu').each(function () {
            if (_(this).next('div.submenu')) {
                _(this).addClass('no_dynamic_content');
            }
        });

        /** Любую ссылку из текста страницы с адресом, совпадающим со ссылкой в меню, превращаем в клик в меню */
        _(document).on('click', 'a', function (e) {
            const self = _(this);

            if (self.closest('div.mobile_menu')) {
                if (self.hasClass('menu')) {
                    if (self.next('div.submenu')?.find('a.submenu')?.first()) {
                        e.preventDefault();

                        updateState(self.next('div.submenu').find('a.submenu').first().attr('href'));
                    }
                }
            }
        });

        /** НЕДИНАМИЧЕСКОЕ ОТКРЫТИЕ СКРЫТОГО КОНТЕНТА НА ПОДГРУЖЕННОЙ СТРАНИЦЕ */

        _(document).on('click', '.show_hidden', function () {
            const self = _(this);
            const hiddenContent = self.next('div.hidden')?.children();
            const hiddenContentAlternative = self.prev('div.overflown_content')?.children();

            if (hiddenContent?.asDomElements().length) {
                hiddenContent.each(function () {
                    self.insert(this, 'before');
                });

                self.next('div.hidden')?.remove();
            } else if (hiddenContentAlternative?.asDomElements().length) {
                hiddenContentAlternative.each(function () {
                    self.insert(this, 'before');
                });

                self.prev('div.overflown_content')?.remove();
            }

            self.remove();
        });

        _(document).on('click', 'a.show_hidden_table', function () {
            const self = _(this).closest('div.tr');
            const table = self.closest('.multi_objects_table');
            const hiddenContent = table?.find('div.tr.hidden');

            if (hiddenContent?.asDomElements().length) {
                hiddenContent.each(function () {
                    const self2 = _(this).removeClass('hidden');

                    self.insert(self2, 'before');
                });

                table?.find('div.tr.hidden')?.remove();
            }

            self.remove();
        });

        /** ДИНАМИЧЕСКИЕ ФУНКЦИИ ПОДГРУЗКИ НА ЛЕТУ РАЗЛИЧНЫХ ОБЪЕКТОВ */

        _(document).on('click', 'a.load_wall', function () {
            const self = _(this);

            self.addClass('transparentText');
            appendLoader(this);

            actionRequest({
                action: 'message/load_wall',
                obj_type: self.attr('obj_type'),
                obj_id: self.attr('obj_id'),
                obj_limit: self.attr('obj_limit'),
                sub_obj_type: self.attr('sub_obj_type')
            }, self);
        });

        _(document).on('click', 'a.load_users_list', function () {
            const self = _(this);

            self.addClass('transparentText');
            appendLoader(this);

            actionRequest({
                action: 'user/load_users_list',
                obj_type: self.attr('obj_type'),
                obj_id: self.attr('obj_id'),
                shown_limit: self.attr('shown_limit'),
                limit: self.attr('limit')
            }, self);
        });

        _(document).on('click', 'a.load_tasks_list', function () {
            const self = _(this);

            self.addClass('transparentText');
            appendLoader(this);

            actionRequest({
                action: 'task/load_tasks_list',
                obj_group: self.attr('obj_group'),
                obj_type: self.attr('obj_type'),
                obj_id: self.attr('obj_id'),
            }, self);
        });

        _(document).on('click', 'a.load_projects_communities_list', function () {
            const self = _(this);

            self.addClass('transparentText');
            appendLoader(this);

            actionRequest({
                action: 'project/load_projects_communities_list',
                obj_type: self.attr('obj_type'),
                limit: self.attr('limit')
            }, self);
        });

        _(document).on('click', 'a.load_conversation', function () {
            const self = _(this);

            self.addClass('transparentText');
            appendLoader(this);

            actionRequest({
                action: 'conversation/load_conversation',
                obj_id: self.attr('obj_id'),
                obj_limit: self.attr('obj_limit'),
                dynamic_load: false
            }, self);
        });

        _(document).on('click', 'a.load_library', function () {
            const self = _(this);

            self.addClass('transparentText');
            appendLoader(this);

            actionRequest({
                action: 'file/load_library',
                obj_type: self.attr('obj_type'),
                obj_id: self.attr('obj_id'),
                external: self.attr('external')
            }, self);
        });

        /** РАЗЛИЧНЫЕ ОТДЕЛЬНЫЕ ФУНКЦИИ */

        /** Антибот */
        _(document).on('click', 'a#approvement_link', function () {
            _('input[name="approvement[0]"]').val(justAnotherVar);
            _('form[id^="form_"]').find('button.main').click();
        });

        /** Подписка / отказ от подписки на новые события в объекте */
        _(document).on('click', 'a.subscribe, a.unsubscribe', function () {
            const self = _(this);

            actionRequest({
                action: `user/${(self.hasClass('subscribe') ? 'subscribe' : 'unsubscribe')}`,
                obj_type: self.attr('obj_type'),
                obj_id: self.attr('obj_id')
            }, self);
        });

        /** Динамическое изменение прав пользователя */
        _(document).on('click', 'a.user_rights_bar', function () {
            const self = _(this);

            if (self.is('[id]')) {
                actionRequest({
                    action: self.hasClass('selected') ? 'user/remove_rights' : 'user/add_rights',
                    user_id: self.parent().attr('user_id'),
                    rights_type: self.attr('id'),
                    obj_type: self.parent().attr('obj_type'),
                    obj_id: self.parent().attr('obj_id')
                }, self);
            }
        });

        /** Показ QR-code вместо кнопки, вызвавшей событие */
        _(document).on('click', '.show_qr_code', function () {
            const self = _(this);

            self.insert(`<img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${currentHref}" class="qr_code" />`, 'before');
            self.remove();
        });

        /** Фильтр задач: для разделов задач и проектов */
        _(document).on('change', 'select[name="tasklist_filter_obj"]', function () {
            const self = _(this).find('option:checked');
            let parentObjTable = _(this).closest('.tasklist_table')?.find('table');

            parentObjTable.find('tbody tr').hide();

            if (self.attr('obj_type') != 'all' && self.attr('obj_type') != 'personal' && self.attr('obj_type') != 'unread' && self.attr('obj_type') != 'responsible') {
                parentObjTable.find(`tr[obj_type="${self.attr('obj_type')}"][obj_id="${self.attr('obj_id')}"]`)?.show();
            } else if (self.attr('obj_type') == 'responsible') {
                parentObjTable.find(`tr[responsible_id="${self.attr('obj_id')}"]`)?.show();
            } else if (self.attr('obj_type') == 'all') {
                parentObjTable.find('tr')?.show();
            } else if (self.attr('obj_type') == 'personal') {
                parentObjTable.find('tr[obj_type="project"][obj_id=""]')?.show();
            } else if (self.attr('obj_type') == 'unread') {
                parentObjTable.find('tr.tasklist_unread')?.show();
            }

            parentObjTable.find('tr.date_header')?.show();
        });

        /** Обработки кнопки лайка */
        _(document).on('click', 'div.important_button', function () {
            const self = _(this);

            _('div.helper').remove();

            actionRequest({
                action: 'mark/mark_important',
                obj_id: self.attr('obj_id'),
                obj_type: self.attr('obj_type')
            }, self);
        });

        /** Всплывающие подсказки-хелперы */
        getHelperData('div.important_button', 'get_important');

        /** Дополнительная обработка открытия модальных окон */
        _(document).on('click', '.fraymmodal-window', function () {
            const self = _(this);

            if (self.parent().hasClass('community_conversation_name') || self.parent().hasClass('project_conversation_name')) {
                const parent = self.closest('[unread_count]');
                const unreadCount = parent.attr('unread_count');
                parent.attr('unread_count', '0');
                parent.find('span.red')?.removeClass('red');

                getNewEventsTimeout();

                if (self.closest('[id^="fraymtabs-"]')) {
                    const tab = self.closest('[id^="fraymtabs-"]');
                    const link = _(`a#${tab.attr('id').replace('fraymtabs-', '')}`);
                    const count = parseInt(unreadCount);

                    if (count > 0) {
                        count = parseInt(link.find('.red')?.text()) - count;

                        if (count > 0) {
                            link.find('.red')?.text(count);
                        } else {
                            link.find('.red')?.remove();
                        }
                    }
                }
            }
        });

        /** Автоматический клик на загрузку при различных событиях окна */
        _(window).on('resize scroll wheel touchmove', function () {
            autoClickLoads();
        });

        /** ДИНАМИЧЕСКИЕ ДЕЙСТВИЯ */

        actionRequestSupressErrorForActions.push('get_new_events');

        _arSuccess('get_new_events', function (jsonData, params) {
            _('div.conversations_widget').find('span.value').text(jsonData['response_text']);

            if (jsonData['online_array'] !== undefined) {
                _('div.photoName').each(function () {
                    const self = _(this);
                    const userId = self.attr('user_id');

                    self.toggleClass('online_marker', jsonData['online_array'][userId] !== undefined);
                })
            }

            if (params['show_list'] == true) {
                let html = `<div class="conversations_widget_list_header"><div class="conversations_widget_list_close sbi"></div>${jsonData['response_data']['online']['count']['count']} ${LOCALE.contactsCount}${jsonData['response_data']['online']['count']['ending']} ${LOCALE.contactsOnline}</div><div class="conversations_widget_list_scroll">`;

                _each(jsonData['response_data']['online'], function (value, key) {
                    if (key !== 'count') {
                        html += `<div class="conversations_widget_list_item online" obj_id="${value[`obj_id`]}" user_id="${value[`user_id`]}"><div class="conversations_widget_list_photos">${value['photoNameLink']}</div><div class="conversations_widget_list_item_name">${value[`dialog_name`]}</div><div class="clear"></div></div>`;
                    }
                })

                if (jsonData['response_data']['group']['count']['count'] > 0) {
                    html += `<div class="conversations_widget_list_item_separator">${jsonData['response_data']['group']['count']['count']} ${LOCALE.groupCount}${jsonData['response_data']['group']['count']['ending']} ${LOCALE.groupCount2}${jsonData['response_data']['group']['count']['ending2']}</div>`;

                    _each(jsonData['response_data']['group'], function (value, key) {
                        if (key != 'count') {
                            html += `<div class="conversations_widget_list_item group" obj_id="${value[`obj_id`]}" user_id="${value[`user_id`]}"><div class="conversations_widget_list_photos">${value['photoNameLink']}</div><div class="conversations_widget_list_item_name">${value[`dialog_name`]}</div><div class="clear"></div></div>`;
                        }
                    })
                }

                if (jsonData['response_data']['offline']['count']['count'] > 0) {
                    html += `<div class="conversations_widget_list_item_separator">${jsonData['response_data']['offline']['count']['count']} ${LOCALE.contactsCount}${jsonData['response_data']['offline']['count']['ending']} ${LOCALE.contactsOffline}</div>`;

                    _each(jsonData['response_data']['offline'], function (value, key) {
                        if (key != 'count') {
                            html += `<div class="conversations_widget_list_item offline" obj_id="${value[`obj_id`]}" user_id="${value[`user_id`]}"><div class="conversations_widget_list_photos">${value['photoNameLink']}</div><div class="conversations_widget_list_item_name">${value[`dialog_name`]}</div><div class="clear"></div></div>`;
                        }
                    })
                }

                html += `</div><div class="conversations_widget_list_search"><input type="text" id="conversations_widget_list_search_input" placehold="${LOCALE.dialogUsersSearch}"></div>`;

                _('div.conversations_widget_list > div.conversations_widget_list_container').html(html);
                _('div.conversations_widget_list').show();
                _('#conversations_widget_list_search_input').focus();

                fraymPlaceholder('#conversations_widget_list_search_input');

                loadSbiBackground();
            } else if (params['get_opened_dialogs'] == true) {
                if (jsonData['response_data'] !== undefined) {
                    _each(jsonData['response_data'], function (value, key) {
                        if (_(`div.conversations_widget_message_container[user_id="${value['user_id']}"]`).is(':visible')) {
                            //
                        } else {
                            actionRequest({
                                action: 'conversation/get_dialog',
                                obj_id: key,
                                user_id: value['user_id'],
                                set_position: true
                            });
                        }
                    })
                }
            }

            let playSound = false;

            _each(jsonData['new_events']['conversation'], function (value, key) {
                const messageDiv = _(`div.message[c_id=${key}]`);

                //предпринимаем активные действия, только если в диалоге есть непрочтенные сообщения
                if (value['count'] > 0) {
                    //прежде всего проверяем не находимся ли мы в этот момент прямо на странице указанного диалога в разделе /conversation/
                    if (el(`div.conversation_form input[type="hidden"][name="conversation_id"][value="${key}"]`)) {
                        //находимся, значит, просто обновляем текущее окошко с текстом
                        playSound = true;

                        actionRequest({
                            action: 'conversation/load_conversation',
                            obj_id: key,
                            obj_limit: 0,
                            show_limit: value['count'],
                            dynamic_load: true
                        }, _('a#bottom'));

                        jsonData['new_events_counters']['conversation'] -= value['count'];
                    } else {
                        //конкретно в этом случае ориентируемся жестко на key=obj_id, потому как могут оказаться два диалога с одним и тем же набором участников (через выход или добавление)
                        const unreadMessagesDivNode = el(`div.conversations_widget_container_avatar_unread_messages[obj_id="${key}"]`);
                        const dialogWindowNode = el(`div.conversations_widget_message_container[obj_id="${key}"]`);

                        if (unreadMessagesDivNode) {
                            const unreadMessagesDiv = _(unreadMessagesDivNode);
                            let oldCount;

                            if (unreadMessagesDiv.text() != '') {
                                oldCount = parseInt(unreadMessagesDiv.text());
                            } else {
                                oldCount = 0;
                            }

                            unreadMessagesDiv.text(value['count']).show();

                            if (oldCount < value['count']) {
                                if (dialogWindowNode) {
                                    const dialogWindow = _(dialogWindowNode);

                                    playSound = !dialogWindow.find('div.conversations_widget_list_sound')?.hasClass('mute');

                                    if (dialogWindow.is(':visible')) {
                                        unreadMessagesDiv.text('').hide();

                                        actionRequest({
                                            action: 'conversation/get_dialog',
                                            obj_id: dialogWindow.attr('obj_id'),
                                            user_id: dialogWindow.attr('user_id'),
                                            time: dialogWindow.attr('time')
                                        });
                                    }
                                } else {
                                    playSound = true;
                                }
                            }
                        } else {
                            /** Если мы не находимся в разделе сообщений, то открываем аватары диалогов */
                            if (el('div.conversation_message_switcher')) {
                            } else {
                                playSound = true;

                                /** Если уже открыто более 10 диалогов, новые не открываем */
                                if (elAll('div.conversations_widget_container_avatar').length < 10) {
                                    actionRequest({
                                        action: 'conversation/get_dialog_avatar',
                                        obj_id: key,
                                        user_id: value['user_id'],
                                        unread_messages: value['count']
                                    });
                                }
                            }
                        }
                    }

                    if (el(`div.message[c_id=${key}]`)) {
                        _('div.conversation_message_switcher_scroller').insert(messageDiv, 'begin');

                        messageDiv.find('div.content_preview')?.addClass('unread').addClass('counter');
                        messageDiv.find('div.content_preview')?.attr('data-content', value['count']);
                        messageDiv.find('div.content_preview')?.html(value['content_preview']);

                        playSound = !(`div.message[c_id=${key}]`).find('div.content_preview')?.hasClass('unread');
                    } else if (el('div.conversation_message_switcher')) {
                        //у нас нет этого диалога в списке вообще, хотя мы и находимся в разделе сообщений, поэтому перезагружаем список сообщений
                        const loadWall = elFromHTML('a.load_wall[obj_type="conversation"][obj_id="{main_conversation}"]');

                        _('div.conversation_message_switcher_scroller')
                            .html('')
                            .insert(loadWall, 'begin');

                        _(loadWall).click();
                    }
                } else if (value['count'] == '-1') {
                    //кто-то прочел наше сообщение за последние 30 секунд, убираем маркер
                    if (!messageDiv.find('div.content_preview')?.hasClass('counter')) {
                        messageDiv.find('div.content_preview')?.removeClass('unread');
                    }
                }
            })

            _('div.conversations_widget_container_avatar_unread_messages').each(function () {
                const self = _(this);

                if (self.text() != '' && (jsonData['new_events']['conversation'][self.attr('obj_id')] === undefined || jsonData['new_events']['conversation'][self.attr('obj_id')]['count'] == '-1')) {
                    const divMessage = _(`div.message[c_id=${self.attr('obj_id')}]`);

                    self.text('').hide();

                    if (divMessage.asDomElement()) {
                        divMessage.find('div.content_preview')?.removeClass('unread');
                    }
                }
            });

            if (jsonData['new_events_counters']['conversation'] > 0) {
                _('span#new_messages_counter').show().text(jsonData['new_events_counters']['conversation']);
            } else {
                _('span#new_messages_counter').hide().text('');
            }

            //если выставлен block_sound, меняем интервал обновления на 5 минут
            if (jsonData['block_sound'] === true) {
                getNewEventsTimeoutTimer = 300000;
                loadTasksTimeoutTimer = 300000;
            } else {
                getNewEventsTimeoutTimer = 30000;
                loadTasksTimeoutTimer = 120000;
            }

            //если выставлен block_sound, не играем звук
            if (playSound && jsonData['block_sound'] === true) {
                playSound = false;
            }

            if (playSound) {
                el('#new_message_alert').play();
            }

            const newConversationsCounter = jsonData['new_events_counters']['project_conversation'] + jsonData['new_events_counters']['community_conversation'];
            const newWallCounter = jsonData['new_events_counters']['project_wall'] + jsonData['new_events_counters']['community_wall'];
            const newTasksCounter = jsonData['new_events_counters']['task_comment'];
            const newApplicationsCounter = 0;
            const newPersonalCounter = newConversationsCounter + newWallCounter + newTasksCounter;

            showHideByValue('span#new_conversations_counter', newConversationsCounter);
            showHideByValue('span#new_wall_counter', newWallCounter);
            showHideByValue('span#new_tasks_counter', newTasksCounter);
            showHideByValue('span#new_applications_counter', newApplicationsCounter);
            showHideByValue('span#new_personal_counter', newPersonalCounter);

            if ('setAppBadge' in navigator) {
                navigator.setAppBadge(newPersonalCounter).catch((error) => {
                    console.log(error);
                });
            }

            if (
                (el('div.kind_project') && params['obj_id'] == projectControlId) ||
                (el('div.kind_tasklist') && params['obj_id'] == 'all')
            ) {
                const tasksByTypes = jsonData['new_events_counters']['task_comment_by_types'];

                showHideByValue('sup#new_tasks_counter_mine', tasksByTypes['mine']);
                showHideByValue('sup#new_tasks_counter_membered', tasksByTypes['membered']);
                showHideByValue('sup#new_tasks_counter_delayed', tasksByTypes['delayed']);
                showHideByValue('sup#new_tasks_counter_closed', tasksByTypes['closed']);
            }

            /** Оповещения о новых комментариях в заявках */
            let oneTimePlay = true;

            _each(jsonData['new_events']['application_comments'], function (data) {
                if (newApplicationCommentsIds.includes(data.comment_id)) {
                } else {
                    if (oneTimePlay) {
                        el('#new_message_alert').play();
                        oneTimePlay = false;
                    }

                    //если открыта страница заявки, пытаемся сразу добавить новое сообщение в нужное место
                    if (el('div.kind_application') && el(`input[type="hidden"][name="id[0]"][value="${data.application_id}"]`)) {
                        const parent = data.parent;
                        let appendToBlock;

                        if (parent > 0) {
                            const parentDiv = _('div.application_conversations').find(`div.conversation_message[message_id=${parent}]`);
                            const level = parseInt(parentDiv.attr('level'));

                            if (parentDiv.next('div.conversation_message_children_container')) {
                                appendToBlock = parentDiv.next('div.conversation_message_children_container');
                            } else {
                                appendToBlock = elFromHTML('<div class="conversation_message_children_container"></div>');
                                parentDiv.insert(appendToBlock, 'after');
                                appendToBlock = _(appendToBlock);
                            }

                            const result = elFromHTML(data.html);
                            _(result).attr('level', level + (level < 5 ? 1 : 0));

                            appendToBlock.insert(result, 'end');
                        } else {
                            _('div.application_conversations').find('div.conversation_form')?.insert(data.html, 'after');
                        }

                        actionRequest({
                            action: 'mark/mark_read',
                            obj_id: data.comment_id
                        });
                    } else {
                        newIngameApplicationCommentsIds.push(data.comment_id);

                        showMessage({
                            text: `<div class="new_application_comment"><a href="/application/${data.application_id}/#wmc_${data.comment_id}"><div class="wmc_name">${data.application_sorter}</div>${data.comment_text}</a></div>`,
                            type: 'information',
                            timeout: 60000
                        });
                    }
                }
            });

            /** Оповещения о новых комментариях в модуле игрока */
            oneTimePlay = true;

            _each(jsonData['new_events']['ingame_application_comments'], function (data) {
                if (newIngameApplicationCommentsIds.includes(data.comment_id)) {
                } else {
                    if (oneTimePlay) {
                        el('#new_message_alert').play();
                        oneTimePlay = false;
                    }

                    //если открыт модуль игрока и диалоги на экране, пытаемся сразу добавить новое сообщение в нужное место
                    if (el('div.kind_ingame') && _('div#fraymtabs-chat').is(':visible')) {
                        const parent = data.parent;
                        let appendToBlock;

                        if (parent > 0) {
                            const parentDiv = el('div#fraymtabs-chat').find(`div.conversation_message[message_id=${parent}]`);
                            const level = parseInt(parentDiv.attr('level'));

                            if (parentDiv.next('div.conversation_message_children_container')) {
                                appendToBlock = parentDiv.next('div.conversation_message_children_container');
                            } else {
                                appendToBlock = elFromHTML('<div class="conversation_message_children_container"></div>');
                                parentDiv.insert(appendToBlock, 'after');
                                appendToBlock = _(appendToBlock);
                            }

                            const result = elFromHTML(data.html);
                            _(result).attr('level', level + (level < 5 ? 1 : 0));

                            appendToBlock.insert(result, 'end');
                        } else {
                            _('div#fraymtabs-chat').find('div.conversation_form')?.insert(data.html, 'after');
                        }

                        actionRequest({
                            action: 'mark/mark_read',
                            obj_id: data.comment_id
                        });
                    } else {
                        newIngameApplicationCommentsIds.push(data.comment_id);

                        showMessage({
                            text: `<div class="new_application_comment"><a href="/ingame/${data.application_id}/#wmc_${data.comment_id}"><div class="wmc_name">${data.application_sorter}</div>${data.comment_text}</a></div>`,
                            type: 'information',
                            timeout: 60000
                        });
                    }
                }
            });

            loadSbiBackground();
        })

        actionRequestSupressErrorForActions.push('load_tasks_list');

        _arSuccess('load_tasks_list', function (jsonData, params, target) {
            if (target) {
                target.insert(jsonData['response_text'], 'before');

                target.remove();
            }
        })

        _arSuccess('get_captcha', function (jsonData, params, target) {
            _('input[name="hash[0]"]').val(jsonData['hash']);
            _('div[id="field_regstamp[0]"]').find('img')?.attr('src', `/scripts/captcha/hash=${jsonData['hash']}`);
        })

        _arSuccess('load_users_list', function (jsonData, params, target) {
            target
                .insert(jsonData['response_text'], 'before')
                .remove();
        })

        _arSuccess('add_rights', function (jsonData, params, target) {
            showMessageFromJsonData(jsonData);

            if (params['rights_type'] == 'delete_all') {
                target.closest('div.photoName').remove();
            } else {
                target.addClass('selected');
            }
        })

        _arSuccess('remove_rights', function (jsonData, params, target) {
            showMessageFromJsonData(jsonData);

            target.removeClass('selected');
        })

        _arSuccess('become_friends', function (jsonData, params, target) {
            showMessageFromJsonData(jsonData);
        })

        _arSuccess('get_authors', function (jsonData, params, target) {
            getHelpersSuccess(jsonData, params, target);
        })

        _arSuccess('get_important', function (jsonData, params, target) {
            getHelpersSuccess(jsonData, params, target);
        })

        _arSuccess('get_vote', function (jsonData, params, target) {
            getHelpersSuccess(jsonData, params, target);
        })

        _arSuccess('get_unread_people', function (jsonData, params, target) {
            getHelpersSuccess(jsonData, params, target);
        })

        _arSuccess('subscribe', function (jsonData, params, target) {
            showMessageFromJsonData(jsonData);

            target.text(jsonData['response_data']).removeClass('subscribe').addClass('unsubscribe');
        })

        _arSuccess('unsubscribe', function (jsonData, params, target) {
            showMessageFromJsonData(jsonData);

            target.text(jsonData['response_data']).removeClass('unsubscribe').addClass('subscribe');
        })

        /** Подписка на уведомления */
        if (el('div.header_right div.login_user_data')) {
            loadJsComponent('webpush');
        }

        /** Виджет диалогов */
        loadJsComponent('conversations_widget');

        /** Виджет задач */
        loadJsComponent('tasks_widget');

        /** Прочие часто используемые компоненты */
        loadJsComponent('library');
        loadJsComponent('wall_notion_conversation');
        loadJsComponent('conversation_form');
        loadJsComponent('actions');

        if (el('div.header_right div.login_user_data')) {
            actionRequest({
                action: 'user/get_new_events',
                get_opened_dialogs: true
            });
        }

        window['get_new_events'] = window.setTimeout(getNewEventsTimeout, getNewEventsTimeoutTimer);
    }

    /** Промотать страницу наверх */
    if (scrollPageTop) {
        scrollWindow(0);
    }

    autoClickLoads();

    /** Проверка наличия hash'а и открытие соответствующего элемента, если он есть */
    if (window.location.hash && (withDocumentEvents || popStateChanging || updateHash)) {
        customHashHandler(parseUri(currentHref));
    }

    showExecutionTime('projectInit end');
}

/** Показа формы для отправки сообщения */
function showConversationForm(self) {
    self.closest('.conversation_form').addClass('opened');
}

/** Автонажатия на различные ссылки для подгрузки различных типов данных */
function autoClickLoads() {
    autoClickLoad('a.load_wall', null, 'wall_notion_conversation');

    autoClickLoad('a.load_conversation', function (self) {
        ifDataLoaded(
            'conversation',
            `autoClickLoad.a.load_conversation`,
            self,
            function () {
                if (self.is('[obj_id]') && self.attr('obj_limit') == '0') {
                    self.trigger('click');
                } else {
                    self.isElementInViewport(function () { self.trigger('click') });
                }
            },
            'js'
        )
    })

    autoClickLoad('a.load_library', null, 'library');

    autoClickLoad('a.load_users_list');

    autoClickLoad('a.load_tasks_list');
}

function autoClickLoad(selector, callback, requiredJsComponent) {
    selector += ':not([loading])';

    const loadObject = elAll(selector);

    if (loadObject) {
        callback = defaultFor(callback, function (self) {
            if (requiredJsComponent) {
                ifDataLoaded(
                    requiredJsComponent,
                    `autoClickLoad.${selector}`,
                    self,
                    function () {
                        self.isElementInViewport(function () { self.trigger('click') });
                    }
                )
            } else {
                self.isElementInViewport(function () { self.trigger('click') });
            }
        })

        _(loadObject).each(function () {
            const self = _(this);
            self.attr('loading', '1');

            callback(self);
        }).destroy();
    }
}

/** Кастомная функция обработки всплывающих окон по .careful */
function customCarefulHandler(element) {
    const btn = _(element);

    if (el('select[name^="repeated_tasks_change"]')) {
        const dialog = new Noty({
            text: defaultFor(btn.attr('text'), LOCALE.areYouSure),
            modal: true,
            layout: 'center',
            buttons: [
                Noty.button(
                    defaultFor(btn.attr('ok_text'), LOCALE.sameTasksDeleteOne),
                    'btn btn-primary',
                    function () {
                        notyDeleteButton(btn);

                        dialog.close();
                    }
                ),

                Noty.button(
                    LOCALE.sameTasksDeleteAll,
                    'btn btn-primary',
                    function () {
                        notyDeleteButton(btn, '&all=true');

                        dialog.close();
                    }
                ),

                Noty.button(
                    defaultFor(btn.attr('cancel_text'), LOCALE.cancelCapitalized),
                    'btn btn-danger',
                    function () {
                        dialog.close();
                    }
                )
            ]
        });

        dialog.show();

        return true;
    }

    return false;
}

/** Кастомная функция обработки хэша */
function customHashHandler(newHrefParsed) {
    newHrefParsed = defaultFor(newHrefParsed, parseUri(newHrefParsed));

    const hash = newHrefParsed.anchor + '';

    if (/wall/.test(hash)) {
        const wallCommentNum = hash.substring(5);

        if (wallCommentNum > 0) {
            _('body').insert(`<a href="${document.location.protocol}//${document.location.hostname}${newHrefParsed.path}show=wall&bid=${wallCommentNum}" class="fraymmodal-window" style="display: none" id="autoloader">autoclick</a>`, 'append');
            _(`#autoloader`).click().remove();
        }
    }

    if (/wmc/.test(hash)) {
        let parent = _(`a[id="${hash}"]`).closest('div.conversation_message');

        if (_(`a[id="${hash}"]`).closest('div.task_message')) {
            parent = _(`a[id="${hash}"]`).closest('div.task_message');
        }

        while (el('div.task_message_container ~ a.show_hidden')) {
            _('div.task_message_container ~ a.show_hidden').first().click();

            if (parent.offset().top > 0) {
                break;
            }
        }

        while (el('div.application_conversations > a.show_hidden')) {
            _('div.application_conversations > a.show_hidden').first().click();

            if (parent.offset().top > 0) {
                break;
            }
        }

        scrollPageTop = false;

        scrollWindow(parent.offset().top);
    }

    if (/notion/.test(hash)) {
        if (el(`a[id="${hash}"]`)) {
            scrollPageTop = false;

            scrollWindow(_(`a[id="${hash}"]`).offset().top);
        }
    }
}

/** Обновление глобальной информации */
function getNewEventsTimeout() {
    window.clearTimeout(window['get_new_events']);

    if (el('div.header_right div.login_user_data')) {
        if (document.addEventListener === undefined || visibilityChangeHidden === undefined || !document[visibilityChangeHidden] || isInStandalone) {
            actionRequest({
                action: 'user/get_new_events',
                obj_type: newTasksCounterObjType,
                obj_id: newTasksCounterObjId
            });
        }

        window['get_new_events'] = window.setTimeout(getNewEventsTimeout, getNewEventsTimeoutTimer);
    }
}

/** Вывод различных helper'ов при наведении на родительский объект, например: лайкнувших или прочитавших сообщение пользователей */
function getHelperData(object, action) {
    _(document).on('click', object, function () {
        const self = _(this);

        if (self.find('div.helper')) {
            window.clearInterval(window[`helper${self['obj_id']}`]);

            self.find('div.helper').show();
        } else {
            window.clearInterval(window[`helper${self['obj_id']}`]);

            actionRequest({
                action: `popup_helper/${action}`,
                obj_id: self.attr('obj_id'),
                obj_type: self.attr('obj_type'),
                value: self.attr('value')
            }, self);
        }
    });

    _(document.body).observerDOMChange(() => {
        _(`${object}:not(.mouseEventsAdded)`).each(function () {
            this.classList.add('mouseEventsAdded');

            this.addEventListener('mouseenter', function () {
                const self = _(this);

                if (self.find('div.helper')) {
                    window.clearInterval(window[`helper${self['obj_id']}`]);

                    window[`helper${self['obj_id']}`] = setInterval(function () {
                        self.find('div.helper').show();

                        window.clearInterval(window[`helper${self['obj_id']}`]);
                    }, 1000);
                } else {
                    window.clearInterval(window[`helper${self['obj_id']}`]);

                    window[`helper${self['obj_id']}`] = setInterval(function () {
                        actionRequest({
                            action: `popup_helper/${action}`,
                            obj_id: self.attr('obj_id'),
                            obj_type: self.attr('obj_type'),
                            value: self.attr('value')
                        }, self);

                        window.clearInterval(window[`helper${self['obj_id']}`]);
                    }, 1000);
                }
            });

            this.addEventListener('mouseleave', function () {
                const self = _(this);

                window.clearInterval(window[`helper${self['obj_id']}`]);

                _('div.helper').hide();
            });
        });
    });
}

/** Стандартная методика показа всплывающего хелпера
 * 
 * @type {actionRequestCallbackSuccess}
*/
function getHelpersSuccess(jsonData, params, target) {
    if (jsonData['response_text'] != '') {
        let header = LOCALE.helperHeaderUnread;

        if (params.action == 'get_authors') {
            header = LOCALE.helperHeaderAuthors;
        } else if (params.action == 'get_important') {
            header = LOCALE.helperHeaderImportant;
        } else if (params.action == 'get_vote') {
            header = LOCALE.helperHeaderVote;
        } else if (params.action == 'show_user_info_from_rolelist') {
            header = LOCALE.helperHeaderUser;
        }

        target.insert(`<div class="helper shown" id="helper"><div class="helper_header"><nobr>${header}</nobr></div>${jsonData['response_text']}</div>`, 'begin');
    } else {
        target.find('div.helper')?.remove();
    }
}

/** Функция остановки видеопотока, запускаемого в модуле игрока */
function stopVideo() {
    if (window.QRstream) {
        _each(window.QRstream.getTracks(), function (track) {
            track.stop();
        });
    }

    _('div.qr-video-container').hide();

    if (flashlight) {
        flashlight.track.stop();
        flashlight = false;
    }

    _('div#qrcode_clicker_container').show();
}

/** Проверка наличия установленных приложений */
async function getInstalledApps() {
    const installedApps = await navigator.getInstalledRelatedApps();

    let nativeApp = false;
    let pwaApp = false;

    _each(installedApps, (app) => {
        if (app.platform === 'webapp') {
            pwaApp = true;
        } else if (app.platform === 'play') {
            nativeApp = true;
        }
    });

    if (nativeApp || pwaApp) {
        PWAsuccess = true;
        _('div.PWAinfo div.android').removeClass('transparentText').addClass('showSuccess');
    }
}

/** Выбор визуализации skeleton'а на время подгрузки в зависимости от раздела, в который идет переход */
function customUpdateState(newHrefParsed, hiding) {
    hiding = defaultFor(hiding, false);

    if (hiding) {
        _('div.fullpage').removeClass('shortened');
    } else {
        _('div.fullpage').addClass('shortened');
    }

    _('div#skeleton_main > div').hide();

    if (newHrefParsed.directory.match(/^\/(myapplication\/act=add|start\/)/) || newHrefParsed.directory === '/') {
        _('div#skeleton_1').show();
    } else if (newHrefParsed.directory.match(/\/(people|calendar_event|area|project|event|community|task|exchange|ruling|publication|report)\//)) {
        _('div#skeleton_3').show();
    } else {
        _('div#skeleton_2').show();
    }
}