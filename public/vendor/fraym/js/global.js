/*
 * This file is part of the Fraym package.
 *
 * (c) Alex Garshin <alxgarshin@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/** Настройки для дебага путем вывода в консоль времени исполнения операций */
const showTimeMode = false;
let startTime = new Date().getTime();

/** Токен авторизации */
let jwtToken;
let jwtTokenRefreshing = null;
const jwtTokenRefreshUrl = `${absolutePath()}/login/action=refresh_token`;

/** Переменная локали */
let LOCALE = {};

/** Проверка нахождения в режиме standalone (PWA-приложение) */
const isInStandalone = (window.navigator.standalone === true) || (window.matchMedia('(display-mode: standalone)').matches);

/** Проверка видимости документа на экране */
const visibilityChangeHidden = document.hidden !== undefined ? 'hidden' : (document.msHidden !== undefined ? 'msHidden' : (document.webkitHidden !== undefined ? 'webkitHidden' : ''));

/** Набор базовых переменных, связанных с отправкой данных */
let doDropfieldRefresh = true;
let doSubmit = false;
let blockDefaultSubmit = false;
let popStateChanging = false;
let currentHref = document.location.href;
let hrefAfterModalClose = null;
const filesDirProtectRegexp = document.location.protocol + '//' + document.location.hostname + '/uploads/';
const filesDirProtect = new RegExp(filesDirProtectRegexp, 'i');

/** Хранит состояние подгрузки различных блоков стилей (css) и логики (js) в разных разделах сайта, а также различных библиотек вендоров (libraries) и завязанных на них функций (functions)*/
const dataLoaded = {
    css: {},
    js: {},
    libraries: {},
    functions: {}
};

/** Хранит все FraymElement'ы
 * 
 * @type {Map<string, FraymElement>}
*/
const fraymElementsMap = new Map();

/** Хранит основной обсервер для listener'ов */
const globalFraymListenersObserver = new MutationObserver((mutations) => {
    if (activeListeners.size === 0) return;

    const nodesToProcess = [];

    for (const mutation of mutations) {
        if (mutation.addedNodes.length > 0) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType === 1) {
                    nodesToProcess.push(node);
                }
            }
        }
    }

    if (nodesToProcess.length === 0) return;

    processInBatches(nodesToProcess);
});

/** Хранит все listener'ы данной конкретной страницы
 * 
 * @type {Map<FraymElement, Object>}
*/
const activeListeners = new Map();

/** Хранит все коллбэки (успеха и провала) для соответствующих действий */
const actionRequestCallbacks = {
    'success': {},
    'error': {}
};

/** Массив действий, для которых необходимо при провале подавить вывод в формате noty полученного сообщения в actionRequest */
const actionRequestSupressErrorForActions = [];

/** Массив wysiwyg-элементов для дальнейшего доступа к ним при необходимости обрабатывать ввод, например */
const wysiwygObjs = new Map();

/** Массив FilePond-элементов для корректировки передаваемых на сервер значений */
const filepondObjs = new Map();

/** Массив предварительно загруженных изображений */
const preloadedImages = [];

/** Переменная, хранящая открытый Noty-диалог / промпт */
let notyDialog = null;

/** Реестр активных таймеров */
const _debounceTimers = {};

/** Массив сообщений */
window['messages'] = defaultFor(window['messages'], []);

/** Механизм сокрытия / показа полей в зависимости от других полей */
let dynamicFieldsList = [];
let currentDynamicFieldsList = [];
let dependencyItemsMap = new Map();

/** Проверка сделанных свайпов для переключения между вкладками на странице */
let touchingDevice = false;
let touchstartX = 0;
let touchendX = 0;
let touchstartY = 0;
let touchendY = 0;
let swipeTimeDiff = 0;
let swipeTimeThreshold = 200;
let swipeDiffThreshold = 130;

/** Переменные для автоподгрузки svg из бэкграундов в DOM (для управления через css) */
const sbiSelector = '.sbi:not(.loading):not(.loaded)';
const sbiObserver = new MutationObserver((mutations) => {
    const nodesToProcess = [];

    for (const mutation of mutations) {
        if (mutation.addedNodes.length > 0) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType === 1) {
                    if (node.matches(sbiSelector)) nodesToProcess.push(node);

                    if (node.firstElementChild) {
                        elAll(sbiSelector, node).forEach(el => nodesToProcess.push(el));
                    }

                }
            }
        }
    }

    if (nodesToProcess.length === 0) return;

    loadSbiBackground(nodesToProcess);
});

ready(() => {
    /** Проверяем, поддерживает ли браузер изменение адреса */
    if ('pushState' in history) {
        /** Заменяем базовый вариант истории первой открытой страницы на расширенный, с указанием url'а, чтобы соблюсти консистентность данных при
         *  дальнейшей навигации */
        history.replaceState({
            'href': document.location.href,
            'replacedDiv': 'div.maincontent_data'
        }, document.title, document.location.href);

        /** Автоматическое добавление заголовка динамической загрузки Fraym-Request=true к любому запросу страниц внутри сайта */
        _(document).on('click', `[href^="/"], [href*="${document.location.protocol}//${document.location.hostname}"]`, function (e) {
            if (!popStateChanging) {
                let self = _(this);

                if (!self.hasClass('no_dynamic_content') && !self.hasClass('fraymmodal-window') && !self.hasClass('careful') && self.attr('target') !== 'blank' && self.attr('target') !== '_blank' && !(filesDirProtect.test(self.attr('href')))) {
                    e.preventDefault();
                    updateState(self.attr('href'));
                } else if (self.attr('target') === '_blank') {
                    e.preventDefault();
                    window.open(self.attr('href'));
                } else if (self.is('button') && !self.hasClass('fraymmodal-window') && !self.hasClass('careful')) {
                    e.preventDefault();

                    if (self.attr('target') === '_blank') {
                        window.open(self.attr('href'));
                    } else {
                        window.location = self.attr('href');
                    }
                }
            }
        });

        /** Поддержка кнопок ведущих вовне */
        _(document).on('click', `button[href]:not(.careful):not(.fraymmodal-window):not([href^="/"]):not([href*="${document.location.protocol}//${document.location.hostname}"])`, function (e) {
            e.preventDefault();

            let self = _(this);

            if (self.attr('target') === '_blank') {
                window.open(self.attr('href'));
            } else {
                window.location = self.attr('href');
            }
        });

        /** Настраиваем перехват событий нажатия кнопок "назад" и "вперед" в браузере */
        window.onpopstate = function (e) {
            if (e.state) {
                popStateChanging = true;
                e.preventDefault();
                updateState(e.state.href, e.state.replacedDiv);
            }
        };
    } else {
        /** Альтернативные обработчики ссылок на кнопках для несовременных браузеров */
        _(document).on('click', `button[href]:not(.careful):not(.fraymmodal-window)`, function (e) {
            e.preventDefault();

            const self = _(this);

            if (self.attr('target') === '_blank') {
                window.open(self.attr('href'));
            } else {
                window.location = self.attr('href');
            }
        });
    }

    /** Подключение локалей с последующей инициализацией всех основных элементов */
    const localeUrls = elAll('.localeUrl');
    let localeLoadedCounter = 0;

    if (localeUrls.length === 0) {
        postLocalesLoad();
    } else {
        for (let item of localeUrls) {
            fetchData(item.href, { method: 'GET', json: true }).then(localeData => {
                LOCALE = Object.assign(LOCALE, localeData);
                localeLoadedCounter++;

                if (localeLoadedCounter === localeUrls.length) {
                    postLocalesLoad();
                }
            });
        }
    }

    function postLocalesLoad() {
        /** Полная инициализация всех основных элементов */
        fraymInit(true);
    }
});

/** Инициализация всех основных элементов Fraym с последующим выполнением projectInit */
async function fraymInit(withDocumentEvents, updateHash) {
    /** Фиксируем время начала отработки */
    startTime = new Date().getTime();

    if (withDocumentEvents) {
        /** Запуск глобального observer'а для всех событий */
        globalFraymListenersObserver.observe(document.body, { childList: true, subtree: true });
    }

    /** Очищаем из fraymElementsMap всё кроме ключа document, т.е. установленных перманентных элементов / листенеров */
    for (const [key, value] of fraymElementsMap.entries()) {
        if (key !== document && key !== window) {
            let hasActiveElement = false;

            _each(value.DOMElements, (element) => {
                if (element && document.body.contains(element)) {
                    hasActiveElement = true;
                    return;
                }
            })

            if (!hasActiveElement) {
                value.destroy();
            }
        }
    }

    /** Очищаем переменные, хранившие объекты страницы */
    for (const [key, value] of filepondObjs.entries()) {
        if (!el(`input[type="hidden"][name="${key}"]`)) {
            filepondObjs.delete(key);
        }
    }

    for (const [key, value] of wysiwygObjs.entries()) {
        if (!el(`div.wysiwyg-editor[name="${key}"]`)) {
            wysiwygObjs.delete(key);
        }
    }

    /** Дальнейшая обработка */

    updateHash = defaultFor(updateHash, false);

    doDropfieldRefresh = true;
    doSubmit = false;

    fixateIndexerButtons();

    showExecutionTime('fraymInit prepare');

    _('input.inputfile[type=file]').each(function () {
        fraymFileUploadApply(this);
    });

    _('input[type="text"], input[type="password"], textarea').each(function () {
        if (_(this).is('[placehold]')) {
            fraymPlaceholder(this);
        }
    });

    if (el('.dpkr') || el('.dpkr_time')) {
        fraymDateTimePickerApply();
    }

    showExecutionTime('Timepickers, files, placeholders');

    _('.wysiwyg-editor').each(function () {
        fraymWysiwygApply(this);
    });

    showExecutionTime('WYSIWYG');

    _('.fraymtabs').each(function () {
        fraymTabsApply(this);
    });

    showExecutionTime('Tabs');

    if (withDocumentEvents) {
        /** Всплывающие уведомления */
        fraymNotyInit();

        /** Всплывающие подсказки / тултипы */
        _(document).on('mouseenter', '[title]:not([data-tooltip-text])', function () {
            const self = _(this, { noCache: true });
            const title = self.attr('title');

            if (title) {
                self.attr('data-tooltip-text', title);
            }

            self.attr('title', null).destroy();
        });

        /** Блокировка дефолтного поведения при нажатии на кнопку */
        _(document).on('click', 'button', function (e) {
            e.preventDefault();
        })

        /** Кнопка добавления группы объектов */
        _(document).on('click', 'button.add_group', function (e) {
            e.stopImmediatePropagation();

            const self = _(this);
            const h1 = self.prev('h1.data_h1');
            const elements = [];
            let sibling = h1.asDomElement().nextElementSibling;
            while (sibling && sibling !== self.asDomElement()) {
                if (sibling.matches('div.field[id$="[0]"]')) {
                    elements.push(sibling);
                }
                sibling = sibling.nextElementSibling;
            }
            const previousId = self.prev().attr('id').match(/\[([^\]]+)]$/);

            if (previousId) {
                const newIdNumber = +previousId[1] + 1;

                self.insert('<div class="field_group_separator"></div>', 'before');

                _each(elements, element => {
                    const self2 = element.cloneNode(true);
                    self2.id = self2.id.replace(/\[0]$/, `[${newIdNumber}]`);

                    const inputs = elAll('[id$="[0]"], [name$="[0]"], [id*="[0]["], [name*="[0]["], [for*="[0]["]', self2);

                    _each(inputs, input => {
                        if (input.id) {
                            if (input.id.match(/]\[0]\[[^\]]+\]$/)) {
                                input.id = input.id.replace(/]\[0](\[[^\]]+])$/, `][${newIdNumber}]$1`);
                            } else if (input.id.match(/\[0]$/)) {
                                input.id = input.id.replace(/\[0]$/, `[${newIdNumber}]`);
                            }
                        }

                        if (input.name) {
                            if (input.name.match(/]\[0]\[[^\]]+\]$/)) {
                                input.name = input.name.replace(/]\[0](\[[^\]]+])$/, `][${newIdNumber}]$1`);
                            } else if (input.name.match(/\[0]$/)) {
                                input.name = input.name.replace(/\[0]$/, `[${newIdNumber}]`);
                            }
                        }

                        if (input.getAttribute('for')) {
                            let forValue = input.getAttribute('for');

                            if (forValue.match(/]\[0]\[[^\]]+\]$/)) {
                                input.setAttribute('for', forValue.replace(/]\[0](\[[^\]]+])$/, `][${newIdNumber}]$1`));
                            } else if (forValue.match(/\[0]$/)) {
                                input.setAttribute('for', forValue.replace(/\[0]$/, `[${newIdNumber}]`));
                            }
                        }

                        if (input.checked) {
                            input.removeAttribute('checked');
                        } else if (input.tagName == 'TEXTAREA') {
                            input.textContent = '';
                        } else if (input.getAttribute('value')) {
                            input.removeAttribute('value');
                        }

                        if (input.classList.contains('dropfield')) {
                            _(input).empty();

                            const div = document.createElement('div');
                            div.textContent = LOCALE.dropFieldChoose;
                            input.appendChild(div);
                        }

                        if (input.classList.contains('helper')) {
                            const newTarget = input.getAttribute('target').replace(/1/, newIdNumber + 1);
                            const select = input.nextElementSibling;

                            input.setAttribute('target', newTarget);
                            input.removeAttribute('value');

                            if (select && select.tagName == 'SELECT') {
                                select.id = newTarget;
                                select.removeAttribute('value');
                            }
                        }
                    });

                    self.insert(self2.outerHTML, 'before');
                });

                let tabindex = 1;
                _('[tabindex]').each(function () {
                    this.setAttribute('tabindex', tabindex);
                    tabindex++;
                });
            }
        });

        /** ВЫПАДАЮЩИЙ МНОЖЕСТВЕННЫЙ СПИСОК */

        _(document).on('click', '.dropfield div.options a', function (e) {
            e.stopImmediatePropagation();

            const checkbox = el(`[id="${_(this).attr('rel')}"]`);
            checkbox.checked = !checkbox.checked;
            _(checkbox).trigger('change');
        });

        _(document).on('change', '.dropfield2 .inputcheckbox, .dropfield2 .inputradio', function () {
            if (doDropfieldRefresh) {
                const container = _(this).closest('.dropfield2');
                const searchInput = container.find('.dropfield2_search > input');

                container.prev('.dropfield').trigger('refresh');

                if (searchInput?.asDomElement()) {
                    filterDropfield(searchInput, searchInput.val());
                }
            }
        });

        _(document).on('refresh', '.dropfield', function () {
            const df = _(this);
            const df2 = df.next('.dropfield2');
            let type = 'checkbox';
            if (el('.inputradio', df2.asDomElement())) {
                type = 'radio';
            }
            let hasSome = false;

            df.empty();

            df2.find(type === 'checkbox' ? '.inputcheckbox' : '.inputradio').each(function () {
                const self = _(this);
                const text = self.next('label')?.asDomElement().innerHTML;

                if (self.is(':checked')) {
                    hasSome = true;

                    if (type === 'checkbox' && el(`a[rel="${self.attr('id')}"]`, df.asDomElement())) {
                        //не надо дублировать
                    } else {
                        df.insert(`<div class="options">${text}<a rel="${self.attr('id')}"></a></div>`, 'end');
                    }
                }
            });

            if (!hasSome) {
                df.insert(`<div>${LOCALE.dropFieldChoose}</div>`, 'end');
            }

            if (type === 'radio') {
                if (df2.is(':visible')) {
                    df.click();
                }
            }
        });

        _(document).on('keyup change', '.dropfield2_search input', function () {
            const self = _(this);

            /** Ждем немного возможного дальнейшего ввода */
            debounce('filterDropfield', filterDropfield, 200, self, self.val());
        });

        _(document).on('click', '.dropfield2_search a.create', function (e) {
            e.stopImmediatePropagation();

            const self = _(this);
            const name = self.next('input').val();

            if (name) {
                const dropfield2 = self.closest('.dropfield2');
                const id = dropfield2.attr('id').substring(7);
                const count = dropfield2.is('[new_count]') ? parseInt(dropfield2.attr('new_count')) + 1 : 0;

                dropfield2.attr('new_count', count);

                const field = elNew('div', { classList: 'dropfield2_field' });
                const checkbox = elNew('input', { type: 'checkbox', name: `${id}[new][${count}]`, id: `${id}[new][${count}]`, classList: 'inputcheckbox' });
                const label = elNew('label', { textContent: name });
                label.setAttribute('for', `${id}[new][${count}]`);
                const hidden = elNew('input', { type: 'hidden', name: `${id}[name][${count}]`, value: name });

                field.appendChild(checkbox);
                field.appendChild(label);
                field.appendChild(hidden);

                if (dropfield2.find('.dropfield2_field.created')) {
                    dropfield2.find('.dropfield2_field.created').last().insert(field.outerHTML, 'after');
                } else {
                    dropfield2.find('.dropfield2_selecter').insert(field.outerHTML, 'after');
                }

                _(checkbox).click();

                self.next('input').val('').trigger('keyup');
            }
        });

        _(document).on('keydown', '.dropfield2_search input', function (e) {
            if (e.keyCode === 13) {
                e.preventDefault();
                _(this).prev('a.create').click();
            }
        });

        _(document).on('click', '.dropfield2_select_all a', function (e) {
            e.stopImmediatePropagation();


            const parent = _(this).closest('.dropfield2');

            doDropfieldRefresh = false;
            parent.find('div.dropfield2_field:not(.hidden) > input:not(:checked):not(.disabled)').each(function () {
                this.checked = true;
            });
            doDropfieldRefresh = true;

            parent.prev('.dropfield').trigger('refresh');
            _(this).text(LOCALE.deselectAll).parent().addClass('dropfield2_deselect_all').removeClass('dropfield2_select_all');
        });

        _(document).on('click', '.dropfield2_deselect_all a', function (e) {
            e.stopImmediatePropagation();

            const parent = _(this).closest('.dropfield2');

            doDropfieldRefresh = false;
            parent.find('div.dropfield2_field:not(.hidden) > input:checked:not(.disabled)').each(function () {
                this.checked = false;
            });
            doDropfieldRefresh = true;

            parent.prev('.dropfield').trigger('refresh');
            _(this).text(LOCALE.selectAll).parent().addClass('dropfield2_select_all').removeClass('dropfield2_deselect_all');
        });

        _(document).on('click', '.dropfield2', function (e) {
            e.stopImmediatePropagation();
        });

        _(document).on('resize', '.dropfield2', function (e) {
            e.stopImmediatePropagation();
        });

        _(document).on('click', '.dropfield', function (e) {
            const df = _(this);
            const df2 = df.next('.dropfield2');

            _('.dropfield.hovered').not(df)?.removeClass('hovered');

            if (df.hasClass('hovered')) {
                df.removeClass('hovered');
            } else {
                df.addClass('hovered');

                delay(500).then(() => {
                    const searchField = df2.find('.dropfield2_search > input');

                    if (searchField) {
                        searchField.trigger('focus');
                    }
                })
            }
        });

        /** ВАЛИДАТОРЫ */

        _(document)
            .on('change keyup', 'input[type="text"][maxlength], input[type="password"][maxlength]', function () {
                const self = _(this);

                simpleValidate(self, self.val().length <= self.attr('maxlength'), LOCALE.maxLengthError);
            })
            .on('change keyup', 'input[type="text"][minlength], input[type="password"][minlength]', function () {
                const self = _(this);

                simpleValidate(self, self.val().length >= self.attr('minlength'), LOCALE.minLengthError);
            })
            .on('change keyup', 'input[type="password"]', function () {
                const self = _(this);
                const passFieldName = convertName(self.attr('name'), 0).replace(/2/g, '');

                if (passFieldName.length && passFieldName !== convertName(self.attr('name'), 0)) {
                    simpleValidate(self, self.val() == _(`input[type="password"][name="${passFieldName}"]`).val(), LOCALE.passwordsDoesntMatch);
                }
            })
            .on('change keyup', 'input[type="text"][name^="em"]', function () {
                const self = _(this);
                const regex = /^([а-яёЁa-zA-Z0-9_.\-+])+@(([а-яёЁa-zA-Z0-9\-])+\.)+([а-яёЁa-zA-Z0-9]{2,4})+$/;

                simpleValidate(self, regex.test(self.val()), LOCALE.wrongEmailFormat);
            })
            .on('change', '.inputtextarea', function () {
                const self = _(this);

                validateTextMaxLength(self, self.attr('maxchar'));
            })
            .on('change', '.inputnum', function () {
                const self = _(this);

                checkNumeric(self);
            });

        /** ПЕРЕКЛЮЧЕНИЕ МЕЖДУ ПОЛЯМИ ФОРМЫ */

        _(document).on('click', '.field', function (e) {
            const target = e.target;

            if (target.tagName.toLowerCase() === 'label') {
                return false;
            }

            const self = _(this);
            const name = convertName(self.attr('id'), 6);
            const selected = _('.fieldname.selected');
            let checkName = null;

            if (selected.asDomElement() && selected.is('[id]')) {
                checkName = convertName(selected.attr('id'), 5);
            }

            if (checkName !== name) {
                const df = _(`#selected_${name}`);
                _('.dropfield.hovered').not(df)?.removeClass('hovered');

                selected.removeClass('selected');

                showHelpAndName(name);

                if (el(`[name="${name}"]`)) {
                    _(`[name="${name}"]`).focus();
                }

                if (df.asDomElement() && !df.hasClass('hovered')) {
                    df.click();
                }
            } else {
                _(`#help_${name}:not(.fixed_help)`).toggle();
            }

            const checkboxSelector = `.inputcheckbox[name="${name}"]`;

            if (checkboxSelector && target.tagName.toLowerCase() !== 'input' && (!target.type || target.type.toLowerCase() !== 'checkbox')) {
                if (el(checkboxSelector)) {
                    const checkbox = _(checkboxSelector);
                    checkbox.asDomElement().checked = !checkbox.asDomElement().checked;
                    checkbox.trigger('change');
                }
            }
        });

        /** ПАНЕЛЬ ФИЛЬТРОВ */

        _(document).on('click', 'div.indexer_toggle', function () {
            const self = _(this);
            const indexer = _('div.indexer');
            const maincontentData = _('div.maincontent_data');

            indexer.toggle();
            self.toggleClass('indexer_shown');
            maincontentData.toggleClass('with_indexer');
        });

        _(document).on('change', 'select[name="searchAllTextFieldsValues"]', function () {
            const self = _(this);
            const textfield = _('input[name="searchAllTextFields"]');

            if (self.val() === 'search_empty' || self.val() === 'search_non_empty') {
                textfield.val('').disable();
            } else {
                textfield.enable();
            }
        });

        /** КНОПКИ И ЗАПРОСЫ НА СЕРВЕР */

        _(document).on('click', '[action_request]:not(.careful)', function (e) {
            e.preventDefault();

            const self = _(this);

            actionRequest({
                action: self.attr('action_request'),
                obj_id: self.attr('obj_id')
            }, self);
        });

        _(document).on('click', 'button.main', function (e) {
            e.preventDefault();

            const self = _(this);

            if (self.hasClass('fraymmodal-window')) {
                /** Не надо ничего делать: есть другой обработчик всех модальных окон */
            } else {
                self.addClass('triggered');
                self.closest('form')?.trigger('submit');
            }
        });

        _(document).on('keydown', 'input[type="text"], input[type="password"], select, textarea', function (e) {
            const self = _(this);

            if ((e.ctrlKey || self.is('input')) && e.keyCode == 13) {
                e.preventDefault();

                if (self.next('button')) {
                    self.next('button').click();
                    return;
                }

                let parentDiv = self.parent();

                if (parentDiv.is('.td')) {
                    parentDiv = parentDiv.parent().next('.tr.menu');
                } else if (parentDiv.next('button.main')) {
                    parentDiv = parentDiv.parent();
                } else if (parentDiv.find('button.main')) {
                } else if (parentDiv.parent().find('button.main')) {
                    parentDiv = parentDiv.parent();
                } else if (parentDiv.parent().parent().find('button.main')) {
                    parentDiv = parentDiv.parent().parent();
                }

                const button = parentDiv.find('button.main')?.first();

                if (button?.asDomElement()) {
                    button.click();
                }
            }
        });

        _(document).on('click', '.careful', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();

            let customCarefulHandled = false;

            if (typeof customCarefulHandler === 'function') {
                customCarefulHandled = customCarefulHandler(this);
            }

            if (!customCarefulHandled) {
                const btn = _(this);

                fraymNotyPrompt(btn, null, function (dialog) {
                    if (btn.is('[href]')) {
                        if (btn.hasClass('file_delete')) {
                            fetchData(
                                btn.attr('href'), {
                                json: true,
                                method: 'DELETE'
                            }).then(function (result) {

                                for (let key in result) {
                                    _(`a[href$="${key}"]`).parent().remove();
                                }

                                if (btn.is('[post_action]') && btn.is('[post_action_id]')) {
                                    actionRequest({
                                        action: btn.attr('post_action'),
                                        obj_id: btn.attr('post_action_id')
                                    }, btn);
                                }
                            })
                        } else {
                            notyDeleteButton(btn);
                        }
                    } else if (btn.is('[action_request]')) {
                        actionRequest({
                            action: btn.attr('action_request'),
                            obj_id: btn.attr('obj_id')
                        }, btn);
                    } else if (btn.hasClass('main')) {
                        btn.closest('form').trigger('submit');
                    }

                    dialog.close();
                });
            }

            return false;
        });

        _(document).on('click', '.noty_modal', function () {
            const notyDialogDom = getNotyDialogDOM();

            if (notyDialogDom.find('.btn.btn-danger')) {
                notyDialogDom.find('.btn.btn-danger').click();
            } else {
                notyDialog?.close();
            }
        });

        _(document).on('submit', 'form', function () {
            const self = _(this);

            if (self.attr('target') !== '_blank' && !blockDefaultSubmit) {
                const href = self.attr('action');
                const checkActionRequest = self.hasAttr('action_request_form');
                const noDynamicContent = self.hasAttr('no_dynamic_content');

                if (!doSubmit && !checkActionRequest) {
                    self.find('button.main')?.each(function () {
                        _(this).disable().destroy();
                        appendLoader(this);
                    });

                    let data;
                    const btnClicked = self.find('button.triggered');
                    let method = self.attr('method') || 'POST';

                    fraymFileUploadInputsFix();

                    data = new FormData();

                    if (btnClicked !== null && btnClicked.closest('div.multi_objects_entity') && !(btnClicked.closest('form').hasAttr('no_dynamic_content'))) {
                        let trs = [];
                        let btnTr = btnClicked.closest('div.tr');

                        while (btnTr?.prev()) {
                            btnTr = btnTr.prev();
                            if (btnTr.hasClass('menu')) {
                                break;
                            }
                            trs.push(btnTr);
                        }

                        trs.reverse();

                        for (const tr of trs) {
                            tr.find('input:not([type="file"]), select, textarea').each(function () {
                                const field = this;

                                if (field.name) {
                                    let value = field.value;

                                    if (field.matches('[type="checkbox"]')) {
                                        value = field.checked ? 'on' : null;
                                    } else if (field.matches('[type="radio"]')) {
                                        value = field.checked ? field.value : null;
                                    }

                                    if (value !== null) {
                                        data.append(field.name, value);
                                    }
                                }
                            });
                        }

                        const globalFormInputs = elAll(':scope > input[type="hidden"]', btnClicked.closest('form').asDomElement());

                        _each(globalFormInputs, input => {
                            data.append(input.getAttribute('name'), input.value);
                        })

                        method = btnClicked.attr('method') || method;
                    } else {
                        _(this).find('input:not([type="file"]), select, textarea').each(function () {
                            const field = this;

                            if (field.name) {
                                let value = field.value;

                                if (field.matches('[type="checkbox"]')) {
                                    value = field.checked ? 'on' : null;
                                } else if (field.matches('[type="radio"]')) {
                                    value = field.checked ? field.value : null;
                                }

                                if (value !== null) {
                                    data.append(field.name, value);
                                }
                            }
                        });

                        _each(elAll('div.wysiwyg-editor', this), editor => {
                            const html = wysiwygObjs.get(editor.getAttribute('id')).root.innerHTML;

                            data.append(editor.getAttribute('name'), html !== '<p><br></p>' ? html : '');
                        })
                    }

                    btnClicked?.removeClass('triggered');

                    if (!noDynamicContent) {
                        data.append('preRequestCheck', 'true');
                    }

                    fetchData(href, { method: method, json: true }, data).then(jsonData => {
                        doSubmit = false;

                        showMessagesFromJson(jsonData);

                        if (jsonData['redirect'] !== undefined) {
                            if (jsonData['redirect'] == 'stayhere') {
                                /** При обновлении проверяем нет ли модального окна. Если есть, обновляем контент в нем. */
                                if (el('.fraymmodal-overlay.shown')) {
                                    const hash = window.location.hash.substring(1);
                                    const hashElement = el('[hash="' + hash + '"]');

                                    if (hashElement) {
                                        _(hashElement).click();
                                    }
                                } else {
                                    updateState(currentHref);
                                }
                            } else if (jsonData['redirect'] == 'submit') {
                                doSubmit = true;
                                updateState(href, '', this);
                            } else {
                                if (self.is('[no_dynamic_content]')) {
                                    window.location = jsonData['redirect'];
                                } else {
                                    /** При прямом перенаправлении проверяем нет ли модального окна, закрываем его и только потом переходим */
                                    if (el('.fraymmodal-overlay.shown')) {
                                        hrefAfterModalClose = jsonData['redirect'];
                                        _('.fraymmodal-overlay.shown').click();
                                    } else {
                                        updateState(jsonData['redirect']);
                                    }
                                }
                            }
                        } else {
                            _('.red').removeClass('red');

                            if (jsonData['fields'] !== undefined) {
                                _each(jsonData['fields'], (data) => {
                                    if (isNumeric(data)) {
                                        self.find(`#line${data}`).addClass('red');
                                    } else {
                                        data = convertName(data, 0);
                                        _(`#name_${data}`).addClass('red');
                                    }
                                })
                            }

                            if (jsonData['fields'] === undefined) {
                                _('.timestamp').val(Math.round(new Date().getTime() / 1000) + 10);
                            }
                        }

                        self.find('button.main')?.each(function () {
                            _(this).enable().destroy();
                            removeLoader(this);
                        });
                    }).catch(error => {
                        showMessage({
                            text: LOCALE.formSubmitError,
                            type: 'error',
                            timeout: 5000
                        });

                        console.error(error.message);

                        self.find('button.main')?.each(function () {
                            _(this).enable().destroy();
                            removeLoader(this);
                        });
                    });

                    return false;
                } else if (checkActionRequest) {
                    doSubmit = false;

                    actionRequest({
                        action: href + self.find('input[name="action"]').first().val(),
                        dynamicForm: true
                    }, self);

                    return false;
                }

                doSubmit = false;

                return true;
            } else if (blockDefaultSubmit) {
                return false;
            }

            this.submit();

            return true;
        });

        _(document).on('submit', 'form', function () {
            _(this).find('input, textarea')?.each(function () {
                const self = _(this);

                if (self.val() === self.attr('placehold')) {
                    self.val('');
                }
            })
        });

        _(document).on('click', '.fraymmodal-window', function (e) {
            e.preventDefault();

            fraymModalApply(this);
        });

        _(document).on('change keyup', '.helper', function (e) {
            if (e.type === 'change' && this.tagName.toLowerCase() === 'input') {
                return false;
            }

            const self = _(this);
            const val = self.val();
            const url = `${self.attr('action')}input=${val}`;
            const target = _(`[name="${self.attr('target')}"]`);

            target.disable();
            target.empty();

            if (!(isNumeric(val)) && val.length < 3) {
                target.insert(`<option>${LOCALE.typeAtLeast3Symbols}</option>`, 'begin');

                return false;
            } else {
                target.insert(`<option>${LOCALE.searchOngoing}</option>`, 'begin');

                fetchData(url, { method: 'GET', json: true }).then(jsonData => {
                    target.empty();

                    let addedOptions = false;

                    _each(jsonData, (value) => {
                        if (value['id'] !== undefined && value['value'] !== undefined) {
                            target.insert(`<option value="${value['id']}">${value['value']}</option>`, 'end');
                            addedOptions = true;
                        }
                    })

                    if (addedOptions) {
                        target.insert(`<option value="">${LOCALE.dropFieldChooseFromFound}</option>`, 'begin');
                        target.find('option')?.asDomElements()[1].setAttribute('selected', true);
                        target.enable();
                    } else {
                        target.insert(`<option>${LOCALE.nothingFound}</option>`, 'begin');
                    }
                })
            }

            return true;
        });

        /** ЭЛЕМЕНТЫ ДИЗАЙНА */

        _(document).on('keydown', function (e) {
            const keyCode = e.keyCode || e.which;

            if (keyCode == 9) { //tab
                e.stopImmediatePropagation();
                e.preventDefault();

                let tabIndex = 1;
                const selected = el('.fieldname.selected') ? _(el('.fieldname.selected')) : null;
                const focused = _(document.activeElement);

                if (selected) {
                    tabIndex = +selected.attr('tabIndex') + 1;
                    self = selected;
                } else if (focused) {
                    if (focused.parent().is('[tabIndex]')) {
                        tabIndex = +focused.parent().attr('tabIndex') + 1;
                    } else {
                        tabIndex = +focused.attr('tabIndex') + 1;
                    }
                    self = focused;
                }

                let next = _('[tabIndex="' + tabIndex + '"]');

                if (next.is('div.fieldname')) {
                    next
                        .focus()
                        .click();
                } else if (next.is('div.td')) {
                    let firstChild = _(next.asDomElement().firstChild);

                    if (firstChild) {
                        firstChild
                            .focus()
                            .click();
                    } else {
                        next
                            .focus()
                            .click();
                    }
                } else {
                    next.focus();
                }
            }
        });

        /** Стрелочки направления сортировок Excel-подобной таблицы объектов */
        _(document).on('mouseenter', '.menu a', function () {
            const self = _(this);

            if (!self.hasClass('arrowAppended')) {
                if (!self.hasClass('arrow_up') && !self.hasClass('arrow_down')) {
                    self.addClass('arrowAppended');
                    self.addClass('arrow_down');
                } else if (!self.hasClass('arrowChanged')) {
                    self.addClass('arrowChanged');
                    self.toggleClass('arrow_up').toggleClass('arrow_down');
                }
            }
        });

        _(document).on('mouseleave', '.menu a', function () {
            const self = _(this);

            if (self.hasClass('arrowAppended')) {
                self.removeClass('arrowAppended');
                self.removeClass('arrow_down');
            } else if (self.hasClass('arrowChanged')) {
                self.removeClass('arrowChanged');
                self.toggleClass('arrow_up').toggleClass('arrow_down');
            }
        });

        /** В типе cardtable у созданных cardtable_card автоматически исправляем названия, если они есть */
        _(document).on('keyup', 'div.multi_objects_table.cards div.td input[name^="name"]', function () {
            const self = _(this);

            self.closest('div.tr')?.find('span.cardtable_card_num_name')?.text(self.val());
        });

        /** Глобальные события документа: click, scroll, resize, touch, wheel. Обязаны идти в самом конце, иначе они будут исполняться раньше времени и их нельзя будет отменить с помощью e.stopImmediatePropagation() */
        _(document)
            .on('touchstart', function () {
                touchingDevice = true;
            })
            .on('wheel mousedown', function () {
                _('body').addClass('no_outline');
            })
            .on('resize scroll wheel touchmove', function () {
                _('.help:not(.fixed_help)').hide();
                _('div.helper').hide();
            })
            .on('scroll', function () {
                fixateIndexerButtons();
            })
            .on('touchstart', function (e) {
                touchstartX = e.changedTouches[0].screenX;
                touchstartY = e.changedTouches[0].screenY;
                swipeTimeDiff = Date.now();
            })
            .on('touchend', function (e) {
                const changedTouch = e.changedTouches[0];
                touchendX = changedTouch.screenX;
                touchendY = changedTouch.screenY;

                handleSwipe(document.elementFromPoint(changedTouch.clientX, changedTouch.clientY));
            })
            .on('click', function (e) {
                if (!e.target.closest('.field') && !e.target.closest('.filtersBlock') && !e.target.closest('.td')) {
                    _('.help:not(.fixed_help)').hide();
                    _('.fieldname').removeClass('selected');
                    _('.dropfield.hovered').removeClass('hovered');
                }

                _('.closeOnDocumentClick').each(function () {
                    if (e.target.closest('.closeOnDocumentClick') !== this && e.target.closest('.shown')?.previousElementSibling !== this) {
                        _(this).trigger('close');
                    }
                });
            });

        /** Автоподгрузка фоновых svg */
        loadSbiBackground(elAll(sbiSelector));
        sbiObserver.observe(document.body, { childList: true, subtree: true });

        showExecutionTime('Document events end');
    }

    /** Защита от svg файлов без viewbox'а */
    fixSvgWithoutViewBox();

    _('select[name="searchAllTextFieldsValues"]').trigger('change');

    /** Проверка наличия hash'а и открытие модального окна, если есть */
    if (window.location.hash !== '' && (withDocumentEvents || popStateChanging || updateHash)) {
        const hash = window.location.hash.substring(1);
        const element = el(`.fraymmodal-window[hash="${hash}"]`);

        if (element) {
            const fraymTabs = _(element).closest('.fraymtabs');

            if (fraymTabs?.asDomElement()) {
                ifDataLoaded(
                    'fraymTabsApply',
                    'fraymModalSwitchTabByHash',
                    element,
                    function () {
                        delay()
                            .then(() => {
                                const panel = _(element).closest('.fraymtabs-panel');

                                if (panel?.asDomElement()) {
                                    _(`a#${panel.attr('id').replace('fraymtabs-', '')}`).parent().trigger('click');
                                }

                                fraymModalApply(element);
                            });
                    }
                );
            } else {
                window.location.hash = '';
                fraymModalApply(element);
            }
        }
    }

    /** Перемотка панели вкладок на выбранную */
    if (mobilecheck()) {
        ifDataLoaded(
            'fraymTabsApply',
            'fraymScrollToTab',
            el('.fraymtabs'),
            function () {
                if (this) {
                    delay()
                        .then(() => {
                            const selectedTab = el(':scope > ul > .fraymtabs-active', this);

                            if (selectedTab) {
                                el(':scope > ul', this).scrollLeft = selectedTab.offsetLeft;
                            }
                        });
                }
            }
        );
    }

    showMessages();

    showExecutionTime('fraymInit end');

    /** Инициализация всех дополнительных элементов */
    if (typeof projectInit === 'function') {
        await projectInit(withDocumentEvents, updateHash);
    } else {
        await loadJsCssForCMSVC();
    }
}

/** БАЗОВЫЕ ФУНКЦИИ */

/** Готовность документа к работе */
function ready(fn) {
    try {
        if (
            document.attachEvent
                ? document.readyState === 'complete'
                : document.readyState !== 'loading'
        ) {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    } catch (e) {
        console.log(e);
    }
}

/** Вывод в консоль времени исполнения элемента */
function showExecutionTime(message) {
    if (showTimeMode) {
        message = defaultFor(message, 'Execution time');
        const execTime = new Date().getTime() - startTime;

        console.log(`${message}: ${execTime}`);
        startTime = new Date().getTime();
    }
}

/** Проверка значения на установленность и возврата или значения, или значения по умолчанию */
function defaultFor(arg, val) {
    return arg !== undefined && arg !== '' && arg !== null ? arg : val;
}

/** Конвертирование имени, содержащего квадратные скобки */
function convertName(str, char) {
    if (str !== undefined) {
        str = str.substring(char);
        str = str.replace(/\[/g, '\\[');
        str = str.replace(/]/g, '\\]');
    }

    return str;
}

/** Очистка фигурных кавычек в строке */
function clearBraces(str) {
    str = str.replace('{', '');
    str = str.replace('}', '');

    return str;
}

/** Превращение <br> в перенос строки */
String.prototype.br2nl = function () {
    return this.replace(/<br\s*\/?>/gi, "\n");
};

/** Превращение переноса строки в <br> */
String.prototype.nl2br = function () {
    return this.replace(/\n/g, "<br />");
};

/** Превращение переноса строки в пробел */
String.prototype.nl2space = function () {
    return this.replace(/\n/g, " ");
};

/** Нормализуем json-данные в массивы вместо объектов */
function objectToArray(data) {
    data = Array.from(data, value => value);

    return data;
}

/** Нормализация входящего(-их) объекта(-ов) в массив */
function normalizeToArray(elements) {
    if (elements === undefined) {
        return [];
    }

    if (typeof elements === 'string') {
        elements = elAll(elements) || [];
    } else if (Array.isArray(elements) || elements instanceof NodeList) {
    } else if (elements instanceof HTMLCollection) {
        const collection = elements;

        elements = [];
        for (let i = 0; i < collection.length; i++) {
            elements.push(collection[i]);
        }
    }
    else {
        elements = [elements];
    }

    let results = [];
    try {
        _each(elements, elem => results = results.concat(domElementFromAnything(elem)));
    } catch (e) {
        console.error(e);
        console.error(elements);
    }

    return results;
}

/** Выбор элемента */
const el = (sel, parent) => (parent || document).querySelector(sel);

/** Выбор элементов */
const elAll = (sel, parent) => (parent || document).querySelectorAll(sel);

/** Упрощенное создание элемента
 * @return Element
*/
const elNew = (tag, props) => Object.assign(document.createElement(tag), props);

/** Создание элемента из HTML
 * @return Element
*/
const elFromHTML = (html) => {
    const template = document.createElement('template');
    template.innerHTML = html.trim();

    if (template.content.childElementCount > 1) {
        return template.content.children;
    }

    return template.content.firstChild;
};

/** Извлечение строго одного DOM-элемента из строки / FraymElement */
const domElementFromAnything = (element) =>
    element?.nodeType ?
        element :
        (
            typeof element === 'string' || element instanceof String ?
                el(element.toString()) :
                (
                    element instanceof FraymElement ?
                        element.asDomElement() :
                        element
                )
        );

/** Функция применения коллбэка к каждому элементу массива / объекта (замена $.each) */
const _each = (collection, callback) => {
    if (Array.isArray(collection) || collection instanceof Map) {
        collection.forEach(callback);
    } else if (collection instanceof Object && collection !== null) {
        Object.entries(collection).forEach(([key, value]) => callback(value, key, collection));
    } else {
        console.trace();
        console.warn("Unsupported collection type for _each.");
        console.log(collection);
    }
}

/**
 * @callback actionRequestCallbackSuccess
 * 
 * @param {Array|string} jsonData 
 * @param {Object|undefined} params 
 * @param {FraymElement|null|undefined} target
 */

/** Выставляет коллбэк для успешного actionRequest
 * 
 * @param {string} callbackName 
 * @param {actionRequestCallbackSuccess} callback 
 */
const _arSuccess = (callbackName, callback) => {
    actionRequestCallbacks.success[callbackName] = callback;
}

/**
 * @callback actionRequestCallbackError
 * 
 * @param {Array|string} jsonData 
 * @param {Object|undefined} params 
 * @param {FraymElement|null|undefined} target
 * @param {Response|undefined} error
 */

/** Выставляет коллбэк для неуспешного actionRequest
 * 
 * @param {string} callbackName 
 * @param {actionRequestCallbackError} callback 
 */
const _arError = (callbackName, callback) => {
    actionRequestCallbacks.error[callbackName] = callback;
}

/** Краткая функция, создающая FraymElement и инициализирующая его в карте
 * 
 * @return {FraymElement}
 */
function _(element, options) {
    let fraymElement;

    if (!element) {
        return null;
    }

    if (element instanceof String) {
        element = element.toString();
    }

    if (!options) {
        fraymElement = fraymElementsMap.get(element);

        if (!fraymElement) {
            fraymElement = new FraymElement(element);

            fraymElementsMap.set(element, fraymElement);
        } else {
            fraymElement.DOMElements = normalizeToArray(element);
        }
    } else if (options.noCache) {
        fraymElement = new FraymElement(element);
    } else {
        fraymElement = new FraymElement(element, options);
        fraymElementsMap.set(fraymElement.asDomElement(), fraymElement);
    }

    return fraymElement;
}

/** FraymElement */
class FraymElement {
    constructor(element, options) {
        this.DOMElements = [];
        this.observer = null;
        this.element = element;

        try {
            if (options) {
                this.DOMElements = [elNew(this.element, options)];
            } else {
                this.DOMElements = normalizeToArray(this.element);
            }
        } catch (e) {
            console.error(e);
        }
    }

    /** Снимаем все активные листенеры
     * 
     * @return {FraymElement}
     */
    destroy() {
        const fraymElement = this;

        if (activeListeners.get(fraymElement)) {
            activeListeners.delete(fraymElement);
        }

        fraymElementsMap.delete(this.element);

        return this;
    }

    /** @return {FraymElement} */
    first() {
        return _(this.DOMElements[0]);
    }

    /** @return {Element|null} */
    asDomElement() {
        return this.DOMElements[0] || null;
    }

    /** @return {Element[]} */
    asDomElements() {
        return this.DOMElements;
    }

    /** @return {FraymElement} */
    children() {
        return _(this.asDomElement().children);
    }

    /** @return {FraymElement} */
    last() {
        return _(this.DOMElements.at(-1));
    }

    /** @return {FraymElement|Element|null} */
    eq(index, asDomElement) {
        return asDomElement ? this.DOMElements[index] || null : _(this.DOMElements[index]);
    }

    /** @return {FraymElement} */
    each(functionData) {
        _each(this.DOMElements, elem => functionData.call(elem));

        return this;
    }

    /** @return {FraymElement} */
    trigger(event) {
        this.each(function () {
            this.dispatchEvent(new CustomEvent(event, { bubbles: true }));
        });

        return this;
    }

    /** @return {FraymElement} */
    focus() {
        this.asDomElement()?.focus();

        return this;
    }

    /** @return {FraymElement} */
    click() {
        this.trigger('click');

        return this;
    }

    /** @return {FraymElement} */
    change() {
        this.trigger('change');

        return this;
    }

    /** @return {FraymElement} */
    submit() {
        this.trigger('submit');

        return this;
    }

    /** @return {FraymElement} */
    checked(checked) {
        this.each(function () {
            this.checked = checked;
        });

        return this;
    }

    /** @return {FraymElement} */
    remove() {
        this.each(function () {
            this.remove();
        });

        return this;
    }

    /** @return {FraymElement} */
    empty() {
        const element = this.asDomElement();

        while (element?.firstChild) {
            element.firstChild.remove();
        }

        return this;
    }

    /**
     * @template {boolean} [B=false]
     * 
     * @param {string} selector
     * @param {B} returnAsDomElements
     * @param {string|false|undefined} containsText
     * @param {string|false|undefined} notContainsText
     * 
     * @return {B extends true ? Element[] : FraymElement}
    */
    find(selector, returnAsDomElements, containsText, notContainsText) {
        returnAsDomElements = defaultFor(returnAsDomElements, false);

        let preResults = [];

        this.each(function () {
            preResults = preResults.concat(elAll(selector, this));
        });

        let results = [];
        try {
            _each(preResults, nodeList => {
                _each(nodeList, function (elem) {
                    if (
                        (!containsText || elem.textContent.toLowerCase().includes(containsText.toLowerCase())) &&
                        (!notContainsText || !elem.textContent.toLowerCase().includes(notContainsText.toLowerCase()))
                    ) {
                        results = results.concat(elem);
                    }
                });
            });
        } catch (e) {
            console.error(e);
            console.error(preResults);
        }

        if (results.length === 0) {
            return null;
        }

        return !returnAsDomElements ? _(results) : results;
    }

    /** @return {FraymElement|null} */
    closest(selector) {
        const closest = this.asDomElement()?.closest(selector);

        return closest ? _(closest) : null;
    }

    /** @return {FraymElement|null} */
    parent() {
        const parent = this.asDomElement()?.parentElement;

        return parent ? _(parent) : null;
    }

    /** @return {FraymElement|null} */
    prev(selector, untilSelector) {
        const currentElement = this.asDomElement();
        let foundPreviousSibling = null;
        let foundPreviousSiblings = [];

        if (currentElement) {
            let prevSibling = currentElement.previousElementSibling;

            if (selector) {
                while (prevSibling) {
                    if (untilSelector && prevSibling.matches(untilSelector)) {
                        return _(foundPreviousSiblings);
                    }

                    if (prevSibling.matches(selector)) {
                        if (untilSelector) {
                            foundPreviousSiblings.push(prevSibling);
                        } else {
                            foundPreviousSibling = prevSibling;
                            break;
                        }
                    }

                    prevSibling = prevSibling.previousElementSibling;
                }
            } else {
                foundPreviousSibling = prevSibling;
            }
        }

        if (untilSelector) {
            return _(foundPreviousSiblings);
        }

        return foundPreviousSibling ? _(foundPreviousSibling) : null;
    }

    /** @return {FraymElement|null} */
    next(selector, untilSelector) {
        const currentElement = this.asDomElement();
        let foundNextSibling = null;
        let foundNextSiblings = [];

        if (currentElement) {
            let nextSibling = currentElement.nextElementSibling;

            if (selector) {
                while (nextSibling) {
                    if (untilSelector && nextSibling.matches(untilSelector)) {
                        return _(foundNextSiblings);
                    }

                    if (nextSibling.matches(selector)) {
                        if (untilSelector) {
                            foundNextSiblings.push(nextSibling);
                        } else {
                            foundNextSibling = nextSibling;
                            break;
                        }
                    }

                    nextSibling = nextSibling.nextElementSibling;
                }
            } else {
                foundNextSibling = nextSibling;
            }
        }

        if (untilSelector) {
            return _(foundNextSiblings);
        }

        return foundNextSibling ? _(foundNextSibling) : null;
    }

    /** @return {FraymElement|null} */
    not(selectorOrElement) {
        let results = [];

        _each(this.asDomElements(), element => {
            if (typeof selectorOrElement === 'string' && !element.matches(selectorOrElement)) {
                results.push(element);
            } else if (selectorOrElement instanceof FraymElement && element !== selectorOrElement.asDomElement()) {
                results.push(element);
            }
        })

        if (results.length === 0) {
            return null;
        }

        return _(results);
    }

    /** @return {FraymElement|string|null} */
    css(ruleName, value) {
        if (value !== undefined) {
            this.each(function () {
                this.style[ruleName] = value;
            });

            return this;
        }

        const self = this.asDomElement();

        if (!self) return null;

        return getComputedStyle(self)[ruleName];
    }

    /**
     * @param {number|undefined} value
     * 
     * @return {FraymElement|number|null}
    */
    outerHeight(value) {
        if (value !== undefined) {
            return this.css('height', `${value}px`);
        }

        return this.asDomElement()?.offsetHeight;
    }

    /**
     * @param {number|undefined} value
     * 
     * @return {FraymElement|number|null}
    */
    outerWidth(value) {
        if (value !== undefined) {
            return this.css('width', `${value}px`);
        }

        return this.asDomElement()?.offsetWidth;
    }

    /** @return {FraymElement|string|null} */
    attr(name, value) {
        if (value !== undefined) {
            if (value === null) {
                this.each(function () {
                    this.removeAttribute(name);
                });
            } else {
                this.each(function () {
                    this.setAttribute(name, value);
                });
            }

            return this;
        } else {
            const self = this.asDomElement();

            if (!self) return null;

            return self.getAttribute(name);
        }
    }

    /** @return {boolean} */
    hasAttr(name) {
        const self = this.asDomElement();

        if (!self) return false;

        return self.hasAttribute(name);
    }

    /** @return {boolean} */
    is(selector) {
        const self = this.asDomElement();

        if (!self) return false;

        if (selector === ':visible') {
            return self.offsetWidth > 0 && self.offsetHeight > 0;
        }

        return self.matches(selector);
    }

    /** @return {number|null} */
    index() {
        const self = this.asDomElement();

        if (!self) return null;

        return [...self.parentNode.children].indexOf(self);
    }

    /** @return {boolean} */
    hasClass(className) {
        const self = this.asDomElement();

        if (!self) return false;

        return self.classList.contains(className);
    }

    /** @return {FraymElement} */
    addClass(className) {
        this.each(function () {
            this.classList.add(className);
        });

        return this;
    }

    /** @return {FraymElement} */
    removeClass(className) {
        this.each(function () {
            this.classList.remove(className);
        });

        return this;
    }

    /**
     * @param {string} className
     * @param {boolean|undefined} addClass
     *  
     * @return {FraymElement}
    */
    toggleClass(className, addClass) {
        this.each(function () {
            if (addClass === true) {
                this.classList.add(className);
            } else if (addClass === false) {
                this.classList.remove(className);
            } else {
                this.classList.toggle(className);
            }
        });

        return this;
    }

    /** @return {FraymElement} */
    show() {
        this.each(function () {
            _(this).addClass('shown').removeClass('hidden');
        });

        return this;
    }

    /** @return {FraymElement} */
    hide() {
        this.each(function () {
            _(this).removeClass('shown').addClass('hidden');
        });

        return this;
    }

    /**
     * @param {boolean|undefined} show
     * 
     * @return {FraymElement}
    */
    toggle(show) {
        this.each(function () {
            const self = _(this);

            if (show === true) {
                self.show();
            } else if (show === false) {
                self.hide();
            } else {
                if (self.is(':visible')) {
                    self.hide();
                } else {
                    self.show();
                }
            }
        })

        return this;
    }

    /** @return {FraymElement} */
    enable() {
        this.each(function () {
            _(this).attr('disabled', null);
        });

        return this;
    }

    /** @return {FraymElement} */
    disable() {
        this.each(function () {
            _(this).attr('disabled', 'true');
        });

        return this;
    }

    /** @return {FraymElement|string|string[]|null} */
    val(value) {
        if (value !== undefined) {
            this.each(function () {
                this.value = value;
            });

            return this;
        } else {
            const self = this.asDomElement();

            if (self) {
                if (self.options && self.multiple) {
                    return [...self.options].filter(option => option.selected).map(option => option.value);
                } else {
                    return self.value;
                }
            }

            return null;
        }
    }

    /** @return {FraymElement|string|null} */
    text(value) {
        if (value !== undefined) {
            this.each(function () {
                this.textContent = value;
            });
            return this;
        } else {
            return this.asDomElement()?.textContent;
        }
    }

    /** @return {FraymElement|string|null} */
    html(value) {
        if (value !== undefined) {
            this.each(function () {
                this.innerHTML = value;
            });

            return this;
        } else {
            return this.asDomElement()?.innerHTML;
        }
    }

    /**
     * @param {String} listeners
     * @param {String} elementSelectorOrHandler
     * @param {Function} handler
     *
     * @also
     *
     * @param {String} listeners
     * @param {Function} elementSelectorOrHandler
     * 
     * @return {FraymElement}
     */
    on(listeners, elementSelectorOrHandler, handler) {
        if (listeners !== undefined) {
            if (handler === undefined) {
                handler = elementSelectorOrHandler;
                elementSelectorOrHandler = null;
            }

            const element = this.element;
            const elementDOMName = element === document || typeof element === 'string' ? `${element === document ? '' : element}${elementSelectorOrHandler ? (element === document ? '' : ' ') + elementSelectorOrHandler : ''}` : null;
            const handlerHash = hashCode(listeners + elementDOMName + handler.toString());

            if (typeof element === 'object' && !elementSelectorOrHandler) {
                _each(listeners.split(' '), listener => {
                    _each(this.DOMElements, elem => elem.addEventListener(listener, handler));
                })
            } else {
                if (!activeListeners.get(this)) {
                    activeListeners.set(this, {});
                }

                const elementInActiveListenersMap = activeListeners.get(this);

                if (elementInActiveListenersMap[elementDOMName] === undefined) {
                    elementInActiveListenersMap[elementDOMName] = {};
                }

                const elementDOMNameHandlerMap = elementInActiveListenersMap[elementDOMName];

                if (elementDOMNameHandlerMap[handlerHash] === undefined) {
                    elementDOMNameHandlerMap[handlerHash] = { handler: handler, listeners: [] };
                }

                const handlerDataInHandlersMap = elementDOMNameHandlerMap[handlerHash];

                _each(listeners.split(' '), listener => {
                    if (!handlerDataInHandlersMap.listeners.includes(listener)) {
                        const elements = elAll(elementDOMName);

                        if (elements.length) {
                            elements.forEach(el => el.addEventListener(listener, handler));
                        }

                        handlerDataInHandlersMap.listeners.push(listener);
                    }
                })
            }
        }

        return this;
    }

    /** @return {FormData} */
    objectToFormData() {
        const formData = new FormData();

        _each(this.DOMElements, (value, key) => {
            formData.append(key, value);
        });

        return formData;
    }

    /**
     * @param {Function} callback
     * 
     * @return {FraymElement}
     */
    isElementInViewport(callback) {
        this.observer = new window.IntersectionObserver(([entry]) => {
            if (entry.isIntersecting) {
                callback();
            }
        }, {
            root: null,
            threshold: 0,
        });

        _each(this.DOMElements, elem => {
            try {
                this.observer.observe(elem);
            } catch (e) {
                console.log(e);
            }
        });

        return this;
    }

    /** @return {FraymElement} */
    insert(htmlOrElement, position) {
        this.each(function () {
            if (typeof htmlOrElement === 'string') {
                const positions = {
                    before: 'beforebegin',
                    after: 'afterend',
                    begin: 'afterbegin',
                    end: 'beforeend',
                    prepend: 'afterbegin',
                    append: 'beforeend'
                };

                this.insertAdjacentHTML(positions[position] || position, htmlOrElement);
            } else {
                htmlOrElement = domElementFromAnything(htmlOrElement);

                if (position === 'before') {
                    this.before(htmlOrElement);
                } else if (position === 'after') {
                    this.after(htmlOrElement);
                } else if (position === 'begin' || position === 'prepend') {
                    this.prepend(htmlOrElement);
                } else if (position === 'end' || position === 'append') {
                    this.append(htmlOrElement);
                }
            }
        });

        return this;
    }

    /** @return {{top:number,left:number}|null} */
    offset() {
        const self = this.asDomElement();

        if (!self) return null;

        const box = self.getBoundingClientRect();
        const docElem = document.documentElement;

        return {
            top: box.top + window.pageYOffset - docElem.clientTop,
            left: box.left + window.pageXOffset - docElem.clientLeft
        };
    }

    /** @return {{top:number,left:number}|null} */
    position() {
        const self = this.asDomElement();

        if (!self) return null;

        const { top, left } = self.getBoundingClientRect();
        const { marginTop, marginLeft } = getComputedStyle(self);

        return {
            top: top - parseInt(marginTop, 10),
            left: left - parseInt(marginLeft, 10)
        };
    }

    /** @return {FraymElement} */
    animate(name, value) {
        if (name === 'scrollLeft') {
            this.scrollLeft(value);
        } else if (name === 'scrollTop') {
            this.scrollTop(value);
        }

        return this;
    }

    /** @return {FraymElement} */
    scrollLeft(left) {
        this.each(function () {
            this.scroll({ left: left, behavior: 'smooth' });
        });

        return this;
    }

    /** @return {FraymElement} */
    scrollTop(top) {
        this.each(function () {
            this.scroll({ top: top, behavior: 'smooth' });
        });

        return this;
    }
}

/** Подключение листенеров пачками с паузами для прорисовки */
function processInBatches(items, startIndex = 0) {
    const BATCH_SIZE = 50;
    const endIndex = Math.min(startIndex + BATCH_SIZE, items.length);

    for (let i = startIndex; i < endIndex; i++) {
        const node = items[i];

        _each(activeListeners, (fraymElementListeners) => {
            _each(fraymElementListeners, (handlerHashesData, elementDOMName) => {
                let verifyElement = node.matches(elementDOMName);
                let childElements = [];

                if (node.childElementCount > 0) {
                    childElements = elAll(elementDOMName, node);
                }

                if (verifyElement || childElements.length > 0) {
                    _each(handlerHashesData, (data) => {
                        _each(data.listeners, listener => {
                            if (verifyElement) {
                                node.addEventListener(listener, data.handler);
                            }

                            if (childElements.length > 0) {
                                childElements.forEach(child => child.addEventListener(listener, data.handler));
                            }
                        });
                    });
                }
            });
        });
    }

    if (endIndex < items.length) {
        setTimeout(() => {
            processInBatches(items, endIndex);
        }, 0);
    }
}

/** Прокрутка окна на нужную высоту */
function scrollWindow(height) {
    _(window, { noCache: true }).scrollTop(height).destroy();
}

/** Проверка браузера */
function mobilecheck() {
    let check = false;

    (function (a) {
        if (/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series([46])0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i.test(a) || /1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br([ev])w|bumb|bw-([nu])|c55\/|capi|ccwa|cdm-|cell|chtm|cldc|cmd-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc-s|devi|dica|dmob|do([cp])o|ds(12|-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly([-_])|g1 u|g560|gene|gf-5|g-mo|go(.w|od)|gr(ad|un)|haie|hcit|hd-([mpt])|hei-|hi(pt|ta)|hp( i|ip)|hs-c|ht(c([- _agpst])|tp)|hu(aw|tc)|i-(20|go|ma)|i230|iac([ -\/])|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja([tv])a|jbro|jemu|jigs|kddi|keji|kgt([ \/])|klon|kpt |kwc-|kyo([ck])|le(no|xi)|lg( g|\/([klu])|50|54|-[a-w])|libw|lynx|m1-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t([- ov])|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30([02])|n50([025])|n7(0([01])|10)|ne(([cm])-|on|tf|wf|wg|wt)|nok([6i])|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan([adt])|pdxg|pg(13|-([1-8]|c))|phil|pire|pl(ay|uc)|pn-2|po(ck|rt|se)|prox|psio|pt-g|qa-a|qc(07|12|21|32|60|-[2-7]|i-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h-|oo|p-)|sdk\/|se(c([-01])|47|mc|nd|ri)|sgh-|shar|sie([-m])|sk-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h-|v-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl-|tdg-|tel([im])|tim-|t-mo|to(pl|sh)|ts(70|m-|m3|m5)|tx-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c([- ])|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas-|your|zeto|zte-/i.test(a.substring(0, 4)) || touchingDevice) check = true;
    })(navigator.userAgent || navigator.vendor || window.opera);

    return check;
}

/** Создаём/читаем deviceId из localStorage (уникален для этого браузера/устройства) **/
function getOrCreateDeviceId() {
    const deviceKey = 'deviceId';
    let id = localStorage.getItem(deviceKey);

    if (!id) {
        id = crypto.randomUUID();
        localStorage.setItem(deviceKey, id);
    }

    return id;
}

/** Создание уникального hash'а из строки */
function hashCode(s) {
    let hash = 0,
        i, l, char;

    s = String(s);

    if (s.length == 0) return hash;

    for (i = 0, l = s.length; i < l; i++) {
        char = s.charCodeAt(i);
        hash = ((hash << 5) - hash) + char;
        hash |= 0; // Convert to 32bit integer
    }

    return hash;
}

/** Таймаут в структуре delay(200).then(() => {}); */
function delay(ms) {
    ms = defaultFor(ms, 200);

    return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Выполняет функцию с задержкой, автоматически очищая предыдущий таймер по ID.
 * @param {string} id
 * @param {Function} func
 * @param {number} delay
 * @param {...*} [args] - Аргументы, которые будут переданы в функцию func
 */
function debounce(id, func, delay, ...args) {
    if (_debounceTimers[id]) {
        clearTimeout(_debounceTimers[id]);
    }

    _debounceTimers[id] = setTimeout(() => {
        func(...args);
        delete _debounceTimers[id];
    }, delay);
}

/** Получение корректных цифр выделения в textarea */
function getCursorPosition(element) {
    const input = domElementFromAnything(element);

    if (!input) return 0; // No (input) element found

    if ('selectionStart' in input) {
        // Standard-compliant browsers
        return input.selectionStart;
    } else if (document.selection) {
        // IE
        input.focus();
        const sel = document.selection.createRange();
        const selLen = document.selection.createRange().text.length;
        sel.moveStart('character', -input.value.length);
        return sel.text.length - selLen;
    }

    return 0;
}

/** Смена раскладки с английской на русскую */
function autoLayoutKeyboard(str) {
    const replacer = {
        "q": "й", "w": "ц", "e": "у", "r": "к", "t": "е", "y": "н", "u": "г",
        "i": "ш", "o": "щ", "p": "з", "[": "х", "]": "ъ", "a": "ф", "s": "ы",
        "d": "в", "f": "а", "g": "п", "h": "р", "j": "о", "k": "л", "l": "д",
        ";": "ж", "'": "э", "z": "я", "x": "ч", "c": "с", "v": "м", "b": "и",
        "n": "т", "m": "ь", ",": "б", ".": "ю", "/": "."
    };

    return str.replace(/[A-z/,.;']/g, function (x) {
        return x == x.toLowerCase() ? replacer[x] : replacer[x.toLowerCase()].toUpperCase();
    });
}

/** Получение расширения файла */
function getExtension(file_name) {
    return file_name.substr((file_name.lastIndexOf('.') + 1));
}

/** Получение базового пути сайта */
function absolutePath() {
    return 'https://' + window.location.hostname;
}

/** Парсинг URL'ов */
function parseUri(str) {
    const o = {
        strictMode: false,
        key: ["source", "protocol", "authority", "userInfo", "user", "password", "host", "port", "relative", "path", "directory", "file", "query", "anchor"],
        q: {
            name: "queryKey",
            parser: /(?:^|&)([^&=]*)=?([^&]*)/g
        },
        parser: {
            strict: /^(?:([^:\/?#]+):)?(?:\/\/((?:(([^:@]*)(?::([^:@]*))?)?@)?([^:\/?#]*)(?::(\d*))?))?((((?:[^?#\/]*\/)*)([^?#]*))(?:\?([^#]*))?(?:#(.*))?)/,
            loose: /^(?:(?![^:@]+:[^:@\/]*@)([^:\/?#.]+):)?(?:\/\/)?((?:(([^:@]*)(?::([^:@]*))?)?@)?([^:\/?#]*)(?::(\d*))?)(((\/(?:[^?#](?![^?#\/]*\.[^?#\/.]+(?:[?#]|$)))*\/?)?([^?#\/]*))(?:\?([^#]*))?(?:#(.*))?)/
        }
    };

    let m = o.parser[o.strictMode ? "strict" : "loose"].exec(str),
        uri = {},
        i = 14;

    while (i--) uri[o.key[i]] = m[i] || "";

    uri[o.q.name] = {};
    uri[o.key[12]].replace(o.q.parser, function ($0, $1, $2) {
        if ($1) uri[o.q.name][$1] = $2;
    });

    return uri;
}

/** Проверка значения на то, что оно число */
function isNumeric(num) {
    if (typeof num === 'number') return num - num === 0;
    if (typeof num === 'string' && num.trim() !== '')
        return Number.isFinite(+num);

    return false;
}

/** Проверка значения на то, что оно int */
function isInt(num) {
    return !isNaN(num) && (function (x) { return (x | 0) === x; })(parseFloat(num));
}

/** Отсечение смайликов */
function removeInvalidChars(field) {
    const ranges = [
        '\ud83c[\udf00-\udfff]', // U+1F300 to U+1F3FF
        '\ud83d[\udc00-\ude4f]', // U+1F400 to U+1F64F
        '\ud83d[\ude80-\udeff]'  // U+1F680 to U+1F6FF
    ];

    field.value = field.value.replace(new RegExp(ranges.join('|'), 'g'), '');
}

/** Импортируем svg в код страницы, чтобы управлять ими через css */
function loadSbiBackground(sbiElements) {
    const itemsToLoad = new Map();

    for (const el of sbiElements) {
        if (el.classList.contains('loading') || el.classList.contains('loaded')) continue;

        const style = window.getComputedStyle(el);
        const bgImage = style.getPropertyValue('background-image');

        if (bgImage && bgImage !== 'none') {
            const url = bgImage.replace(/^url\(['"]?/, '').replace(/['"]?\)$/, '');

            if (!itemsToLoad.has(url)) {
                itemsToLoad.set(url, []);
            }

            let itemsToLoadByUrl = itemsToLoad.get(url);
            itemsToLoadByUrl.push(el);
        }
    }

    itemsToLoad.forEach((elements, url) => {
        const allSvgParents = _(elements, { noCache: true });

        allSvgParents.addClass('loading');

        fetchData(url, { method: 'GET' }).then(function (data) {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = data;
            const rawSvg = tempDiv.querySelector('svg');

            if (!rawSvg) return;

            rawSvg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
            rawSvg.setAttribute('width', '100%');
            rawSvg.setAttribute('height', '100%');
            rawSvg.removeAttribute('xmlns:a');
            if (!rawSvg.hasAttribute('viewBox')) {
                rawSvg.setAttribute('viewBox', '0 0 100 100');
            }

            sbiObserver.disconnect();
            globalFraymListenersObserver.disconnect();

            for (let i = 0; i < elements.length; i++) {
                const el = elements[i];

                el.appendChild(rawSvg.cloneNode(true));

                el.style.backgroundImage = 'none';
                el.classList.remove('loading');
                el.classList.add('loaded');
            }

            globalFraymListenersObserver.observe(document.body, { childList: true, subtree: true });
            sbiObserver.observe(document.body, { childList: true, subtree: true });

            tempDiv.remove();
        })

        allSvgParents.destroy();
    })
}

/** Исправление svg без viewBox */
function fixSvgWithoutViewBox() {
    if (el('svg:not([viewBox])')) {
        _('svg:not([viewBox])').each(function () {
            const self = _(this);

            if (self.attr('width') && self.attr('height')) {
                this.setAttribute('viewBox', `0 0 ${self.attr('width').replace('px', '')} ${self.attr('height').replace('px', '')}`);
            }

            self.destroy();
        }).destroy();
    }
}

/** Определение текущей локали */
function getLocale() {
    return LOCALE.localeCode;
}

/** Открытие и закрытие любых меню */
function submenuToggle(button, submenu) {
    _(button).on('click close', function (e) {
        e.stopImmediatePropagation();

        _(button).toggleClass('opened').toggleClass('closeOnDocumentClick');
        _(submenu).toggle();
    });

    _(submenu + ' a').on('click', function () {
        const self = _(this);

        if (self.hasClass('no_dynamic_content')) {
        } else if (_(button).is(':visible')) {
            _(button).removeClass('opened').removeClass('closeOnDocumentClick');
            _(submenu).hide();
        }
    })
}

/** В зависимости от значения показать div со вставленным значением или же скрыть его и обнулить значение */
const showHideByValue = function (selector, value) {
    const element = _(selector, { noCache: true });

    if (value > 0) {
        element.show().text(value);
    } else {
        element.hide().text('');
    }

    element.destroy();
}

/** Отслеживание свайпов */
function handleSwipe(elem) {
    if (elem) {
        let check = _(elem);

        while (check.parent()?.asDomElement()) {
            /** Если у любого родительского элемента overflow-x: auto, значит пользователь просто прокручивал элемент */
            if (check.css('overflow-x') == 'auto') {
                return false;
            }

            check = check.parent();

            if (check.parent()?.asDomElement() instanceof HTMLElement) {
                break;
            }
        }

        let index = -1;

        if (
            (Date.now() - swipeTimeDiff < swipeTimeThreshold) &&
            (Math.abs(touchendX - touchstartX) > Math.abs(touchendY - touchstartY)) &&
            (Math.abs(touchendX - touchstartX) > swipeDiffThreshold)
        ) {
            const self = _('body div.fraymtabs').first();
            /** @type {FraymElement|null} */
            const navPanel = self.find(':scope > ul');
            const controls = navPanel.find(':scope > li');
            /** @type {FraymElement|null} */
            const control = navPanel.find(':scope > .fraymtabs-active');

            if (touchendX > touchstartX) {
                if (self.asDomElement()) {
                    index = control.index();

                    if (index > 0) {
                        index--;
                    } else {
                        index = -1;
                    }
                }
            } else if (touchendX < touchstartX) {
                if (self.asDomElement()) {
                    index = control.index();

                    if (index < self.find(':scope > ul li').DOMElements.length - 1) {
                        index++;
                    } else {
                        index = -1;
                    }
                }
            }

            if (index > -1 && (touchendX < touchstartX || touchendX > touchstartX)) {
                /** @type {FraymElement|null} */
                const selectedControl = controls.eq(index);
                const offsetLeft = selectedControl.asDomElement().offsetLeft;

                navPanel.animate('scrollLeft', offsetLeft);

                delay()
                    .then(() => {
                        const anchor = selectedControl.find('a[id]');
                        const ahref = selectedControl.find('a[href]');

                        if (ahref.asDomElement()) {
                            updateState(ahref.attr('href'));
                        } else if (anchor.asDomElement()) {
                            selectedControl.click();
                        }
                    });
            }
        }
    }
}


/** ФУНКЦИИ ПОДГРУЗКИ ДАННЫХ */

/** Изменение текущего адреса страницы методами javascript и динамической подгрузки содержания */
function updateState(newHref, replacedDiv, form) {
    replacedDiv = defaultFor(replacedDiv, 'div.maincontent_data');

    const currentHrefParsed = parseUri(currentHref);
    const newHrefParsed = parseUri(newHref);

    if (newHrefParsed.file !== 'index.php') {
        if (popStateChanging && currentHrefParsed.path === newHrefParsed.path) {
            /** Если изменилась только вкладка / модальная страница и это было изменение из истории браузера */
            if (currentHrefParsed.anchor !== newHrefParsed.anchor) {
                if (newHrefParsed.anchor === '' && el(`li.fraymtabs-active a#${currentHrefParsed.anchor}`)) {
                    /** Если хэша больше нет, а до этого была открыта tab-вкладка по хэшу, то переходим на вкладку по умолчанию */
                    _(`li.fraymtabs-active a#${currentHrefParsed.anchor}`).closest('ul')?.find('a')?.first().click();
                } else if (newHrefParsed.anchor === '' && el('.fraymmodal-overlay.shown')) {
                    /** Если хэша больше нет, а до этого было открыто модальное окно, то отдаем закрытие ему на обработку */
                    _('.fraymmodal-overlay.shown').click();
                } else if (newHrefParsed.anchor != '') {
                    if (el(`#${newHrefParsed.anchor}`)) {
                        _(`#${newHrefParsed.anchor}`).click();
                    } else if (el(`[hash="${newHrefParsed.anchor}"]`)) {
                        _(`[hash="${newHrefParsed.anchor}"]`).click();
                    } else {
                        /** Это нестандартный хэш, передаем его кастомной функции обработки */
                        if (typeof customHashHandler === 'function') {
                            customHashHandler(newHrefParsed);
                        }
                    }
                } else {
                    /** Хэша нет, но и обработчики фреймворка не знают, что делать. Передаем url кастомной функции обработки. */
                    if (typeof customHashHandler === 'function') {
                        customHashHandler(newHrefParsed);
                    }
                }

                currentHref = newHref;
            } else {
                //хэш точно такой же, значит, где-то баг с управлением историей
                console.error('popStateChanging, но хэш не изменился');
                console.trace();
            }
        } else {
            if ("pushState" in history) {
                let link = document.location.protocol + '//' + document.location.hostname + newHrefParsed.path + (/\/$/.test(newHrefParsed.path) ? (newHrefParsed.query ? '?' + newHrefParsed.query + '&' : '') : '') + (newHrefParsed.anchor != '' ? '#' + newHrefParsed.anchor : '');
                let data = null;

                if (doSubmit) {
                    link = document.location.protocol + '//' + document.location.hostname + newHrefParsed.path + (newHrefParsed.anchor != '' ? '#' + newHrefParsed.anchor : '');

                    if (form) {
                    } else {
                        form = el(`form[action="${newHref}"]`);
                    }

                    if (form.hasAttribute('no_dynamic_content')) {
                        form.submit();

                        return false;
                    } else {
                        data = new FormData(form);
                    }
                }

                if (el('div.fullpage_cover')) {
                    _('div.fullpage_cover').show().destroy();

                    if (typeof customUpdateState === 'function') {
                        customUpdateState(newHrefParsed);
                    }
                }

                showExecutionTime('updateState before request');

                fetchData(link, { method: (doSubmit ? 'POST' : 'GET'), json: true }, data).then(result => {
                    showExecutionTime('updateState beginning of success');

                    showMessagesFromJson(result);

                    if (result.redirect !== undefined && result.redirect !== '') {
                        updateState(result.redirect, replacedDiv);

                        return false;
                    }

                    currentHref = newHref;

                    /** Меняем url в истории на корректный, если это не переход по истории */
                    if (!popStateChanging && (currentHrefParsed.path !== newHrefParsed.path || currentHrefParsed.anchor !== newHrefParsed.anchor)) {
                        history.pushState({
                            'href': newHref,
                            'replacedDiv': replacedDiv
                        }, result.pageTitle, newHref);
                    }

                    showExecutionTime('updateState history.pushState');

                    /** Меняем шапку сайта */
                    document.title = result.pageTitle;

                    /** Меняем og реквизиты, если есть */
                    if (el('meta[property="og:title"]')) {
                        _('meta[property="og:title"]').attr('content', result.pageTitle);
                    }
                    if (el('meta[property="og:url"]')) {
                        _('meta[property="og:url"]').attr('content', newHref);
                    }

                    /** Выставляем динамический контент в тело сайта */
                    _('div.indexer').remove();

                    showExecutionTime('updateState indexer and doubles removed');

                    if (el(replacedDiv)) {
                        _(replacedDiv).first().insert(`<div id="replacedDivSpot" />`, 'before');

                        const parentElem = el(replacedDiv).parentNode;
                        const replacedElems = elAll(`:scope > ${replacedDiv}`, parentElem);

                        _each(replacedElems, replacedElem => {
                            parentElem.removeChild(replacedElem);
                        })

                        showExecutionTime('updateState old content removed');

                        _(`#replacedDivSpot`).insert(result.html, 'before').remove();

                        showExecutionTime('updateState new content inserted');

                        const scripts = _(replacedDiv).asDomElement()?.getElementsByTagName('script');

                        if (scripts) {
                            for (let ix = 0; ix < scripts.length; ix++) {
                                eval(scripts[ix].text);
                            }
                        }

                        showExecutionTime('updateState scripts in new content executed');
                    } else {
                        window.location = newHref;
                    }

                    /** Переинициализируем все элементы */
                    fraymInit(false, true).then(() => {
                        showExecutionTime('updateState pre additional actions');

                        if (newHrefParsed.anchor === '' && el('.fraymmodal-overlay.shown')) {
                            /** Если хэша больше нет, а до этого было открыто модальное окно, то отдаем закрытие ему на обработку */
                            _('.fraymmodal-overlay.shown').click();
                        }

                        popStateChanging = false;

                        if (el('div.fullpage_cover')) {
                            _('div.fullpage_cover').hide().destroy();

                            if (typeof customUpdateState === "function") {
                                customUpdateState(newHrefParsed, true);
                            }
                        }

                        scrollWindow(0);

                        showExecutionTime('updateState additional actions');

                        return true;
                    })
                }).catch(error => {
                    popStateChanging = false;

                    if (el('div.fullpage_cover')) {
                        _('div.fullpage_cover').hide().destroy();

                        if (typeof customUpdateState === "function") {
                            customUpdateState(newHrefParsed, true);
                        }
                    }

                    showMessage({
                        text: LOCALE.switchPageError,
                        type: 'error',
                        timeout: 5000
                    });

                    console.error(error.message);
                    console.error(error.stack);
                });
            } else {
                if (currentHrefParsed.path === newHrefParsed.path && currentHrefParsed.anchor !== newHrefParsed.anchor) {
                    window.location.hash = newHrefParsed.anchor;
                } else {
                    currentHref = newHref;
                    window.location = newHref;
                }
            }
        }

        doSubmit = false;
    }

    return true;
}

/** Изменение состояния хэша в истории различными отдельными компонентами: модальными окнами и вкладками */
function componentsUpdateState(new_anchor) {
    if ("pushState" in history) {
        if (!popStateChanging) {
            /** Проверяем, внесен ли этот url уже в историю с таким же хэшем, и если это не так, то заносим */
            const currentHrefParsed = parseUri(currentHref);

            if (currentHrefParsed.anchor !== new_anchor) {
                const hrefParsed = parseUri(document.location.href);
                const link = document.location.protocol + '//' + document.location.hostname + hrefParsed.path + (new_anchor != '' ? '#' : '') + new_anchor;

                history.pushState({ "href": link, "replacedDiv": 'div.maincontent_data' }, document.title, link);
                currentHref = link;
            }

            /** Если нам нужно было куда-то перейти после закрытия модального окна */
            if (hrefAfterModalClose) {
                updateState(hrefAfterModalClose);
                hrefAfterModalClose = null;
            } else {
                /** Переинициализируем все элементы */
                fraymInit(false);
            }
        } else {
            popStateChanging = false;
        }
    } else {
        window.location.hash = new_anchor;
    }
}

/** Получение данных
 * @param {string} url
 * @param {object} options
 * @param {object|null} data
 * 
 * @returns {Promise<Array|String>}
*/
async function fetchData(url, options = {}, data = null) {
    if (checkHttpUrl(url)) {
        options.json = defaultFor(options.json, false);
        options.method = defaultFor(options.method, 'POST');

        let headers = {
            'Fraym-Request': true
        };

        if (jwtToken) {
            headers.Authorization = 'Bearer ' + jwtToken;
        }

        let requestOptions;
        if (options.method === 'POST' || options.method === 'PUT') {
            data = new URLSearchParams(data);

            requestOptions = {
                method: options.method,
                headers: headers,
                body: data
            };
        } else {
            requestOptions = {
                method: options.method,
                headers: headers,
            };
        }

        const response = await fetch(url, requestOptions);

        if (!response.ok) {
            return new Response(null, { status: 0, statusText: "NetworkError" });
        }

        return (options.json ? await response.json() : await response.text());
    } else {
        return new Response(null, { status: 0, statusText: "NetworkError" });
    }
}

/** Корректировка работы fetch на автоматическую обработку api-токенов */
window.fetch = new Proxy(window.fetch, {
    async apply(fetch, thisArg, args) {
        let [url, options = {}] = args;
        options.headers = options.headers || {};

        const isLocalUrl = function (url) {
            try {
                const u = new URL(typeof url === 'string' ? url : url.url, location.href);
                if (u.href === REFRESH_URL) return false;
                return u.origin === location.origin;
            } catch {
                return false;
            }
        }

        if (isLocalUrl) {
            if (!jwtToken && !options.headers.Authorization) {
                if (jwtTokenRefreshing) {
                    await jwtTokenRefreshing.catch(() => { });
                } else {
                    jwtTokenRefreshing = fetch(jwtTokenRefreshUrl, { method: 'GET' })
                        .then(r => r.text())
                        .then(token => { jwtToken = token; })
                        .finally(() => { jwtTokenRefreshing = null; });

                    await jwtTokenRefreshing.catch(() => { });
                }
            }

            if (jwtToken) {
                options.headers.Authorization = 'Bearer ' + jwtToken;
            }
        }

        let response;
        try {
            response = await fetch.apply(thisArg, [url, options]);
        } catch {
            return new Response(null, { status: 0, statusText: "NetworkError" });
        }

        return response;
    },
});

/** Функция проверки подгруженности элемента данных */
function dataElementLoad(dataName, element, loadingFunction, loadedFunction, options, dataElementCategory) {
    if (
        dataName === undefined ||
        element === undefined ||
        loadingFunction === undefined ||
        loadedFunction === undefined
    ) {
        return;
    }

    dataElementCategory = defaultFor(dataElementCategory, 'libraries');

    if (dataLoaded[dataElementCategory][dataName] === undefined) {
        dataLoaded[dataElementCategory][dataName] = false;
        loadingFunction.call();
    }

    if (dataLoaded[dataElementCategory][dataName] === false) {
        delay(50)
            .then(() => {
                dataElementLoad(dataName, element, loadingFunction, loadedFunction, options, dataElementCategory);
            });
    } else if (dataLoaded[dataElementCategory][dataName] === true) {
        loadedFunction.call(element, options);
    }
}

/** Функция проверки подгруженности конкретной библиотеки для действия по зависимой функции */
function verifyDataLoaded(dataCategory, dataName, functionName) {
    const intervalId = setInterval(function () {
        if (dataLoaded[dataCategory][dataName]) {
            dataLoaded['functions'][functionName] = true;
            clearInterval(intervalId);
        }
    }, 50);

    delay(5000)
        .then(() => {
            clearInterval(intervalId);
        });
}

/** Функция исполнения коллбэка по факту проверки подгруженности родительского скрипта */
function ifDataLoaded(dataName, functionName, element, callback, dataCategory) {
    dataCategory = defaultFor(dataCategory, 'libraries');

    dataElementLoad(
        functionName,
        element,
        () => {
            verifyDataLoaded(dataCategory, dataName, functionName);
        },
        callback,
        {},
        'functions'
    );
}

/** Подгрузка javascript на лету */
const getScript = url => new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = url;
    script.async = true;

    script.onerror = reject;

    script.onload = script.onreadystatechange = function () {
        const loadState = this.readyState;

        if (loadState && loadState !== 'loaded' && loadState !== 'complete') return;

        script.onload = script.onreadystatechange = null;

        resolve();
    }

    document.head.appendChild(script);
})

/** Подключение css на лету */
const cssLoad = (name, url) => new Promise((resolve, reject) => {
    if (!dataLoaded['css'][name] && !el(`link[href="${url}"]`)) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.type = 'text/css';
        link.href = url;

        link.onload = () => {
            dataLoaded['css'][name] = true;

            resolve();
        };

        link.onerror = reject

        document.head.appendChild(link);
    } else {
        resolve();
    }
})

/** Подключение javascript и css соответствующего раздела в процессе init */
const loadJsCssForCMSVC = cmsvc => new Promise((resolve, reject) => {
    if (!cmsvc) {
        const currentHrefParsed = parseUri(currentHref);
        const currentHrefParsedSplited = currentHrefParsed.path.split('/');

        if (currentHrefParsedSplited.length > 2) {
            cmsvc = currentHrefParsed.path.split('/')[1];
        }

        cmsvc = defaultFor(cmsvc, 'start');
    }

    cssLoad(cmsvc, `/vendor/fraym/cmsvc/${cmsvc}.css`).then(() => {
        if (dataLoaded.js[cmsvc] !== false) {
            if (dataLoaded.js[cmsvc] === undefined) {
                getScript(`/vendor/fraym/cmsvc/${cmsvc}.js`).then(() => {
                    dataLoaded.js[cmsvc](true);

                    resolve();
                }).catch(function () {
                    dataLoaded.js[cmsvc] = false;

                    resolve();
                })
            } else {
                dataLoaded.js[cmsvc](false);

                resolve();
            }
        } else {
            resolve();
        }
    })
})

/** Подключение javascript компоненты из-под другого javascript в процессе init */
const loadJsComponent = component => new Promise((resolve, reject) => {
    if (!component) {
        resolve();
    }

    if (dataLoaded.libraries[component] !== false) {
        if (dataLoaded.libraries[component] === undefined) {
            getScript(`/vendor/fraym/cmsvc/${component}.js?component=1`).then(() => {
                dataLoaded.libraries[component](true);

                resolve();
            }).catch(function () {
                dataLoaded.libraries[component] = false;

                resolve();
            })
        } else {
            dataLoaded.libraries[component](false);

            resolve();
        }
    } else {
        resolve();
    }
})

/** Предзагрузка изображений, картинку которых нужно менять на лету без мигания */
function preload(arrayOfImages) {
    _each(arrayOfImages, image => {
        if (preloadedImages.indexOf(image) == -1) {
            const img = new Image();
            img.src = image;

            preloadedImages.push(image);
        }
    })
}

/** Обработка всех динамических запросов, не требующих перезагрузки страницы */
function actionRequest(params, target) {
    //URL получаем из params.action
    const paramsAction = params.action;
    const n = paramsAction.lastIndexOf('/');

    params.action = paramsAction.substring(n + 1);
    params.kind = paramsAction.substring((paramsAction.substring(0, 1) == '/' ? 1 : 0), n);

    const url = `${absolutePath()}/${params.kind}/action=${params.action}`;

    let data;
    if (params.dynamicForm && target) {
        target.find('button.main')?.each(function () {
            _(this, { noCache: true }).disable().destroy();
            appendLoader(this);
        });

        target.find('input[type="text"], textarea')?.each(function () {
            removeInvalidChars(this);
        });

        fraymFileUploadInputsFix();

        data = new FormData(domElementFromAnything(target));
    } else {
        data = params;
    }

    let headers = {};
    if (jwtToken != '') {
        headers.Authorization = 'Bearer ' + jwtToken;
    }

    fetchData(url, {
        method: 'POST',
        headers: headers,
        json: true
    }, data).then(function (jsonData) {
        if (el('div.fullpage_cover')) {
            _('div.fullpage_cover', { noCache: true }).hide().destroy();
        }

        if (jsonData['response'] === 'success') {
            actionRequestCallbacks.success[params.action](jsonData, params, target);
        } else {
            if (!actionRequestSupressErrorForActions.includes(params.action) && navigator.onLine) {
                showMessageFromJsonData(jsonData);
            }

            actionRequestCallbacks.error[params.action](jsonData, params, target);
        }
    }).catch(function (error) {
        if (el('div.fullpage_cover')) {
            _('div.fullpage_cover', { noCache: true }).hide().destroy();
        }

        console.error(error.message);
        console.error(error.stack);

        if (!actionRequestSupressErrorForActions.includes(params.action) && navigator.onLine) {
            showMessage({
                text: LOCALE.dynamicRequestError,
                type: 'error',
                timeout: 5000
            });
        }

        actionRequestCallbacks.error[params.action](null, params, target, error);
    });

    if (params.dynamicForm && target) {
        target.find('button.main')?.each(function () {
            _(this, { noCache: true }).enable().destroy();
            removeLoader(this);
        });
    }
}

/** ВАЛИДАЦИИ */

/** Выделение проблемного поля */
function simpleValidate(self, condition, title) {
    if (self.val() === '') {
        self
            .removeClass('ok')
            .removeClass('attention_red')
            .closest('.fieldvalue')?.attr('data-tooltip-text', null);
    } else if (!condition) {
        self
            .removeClass('ok')
            .addClass('attention_red')
            .closest('.fieldvalue')?.attr('data-tooltip-text', title);
    } else {
        self
            .addClass('ok')
            .removeClass('attention_red')
            .closest('.fieldvalue')?.attr('data-tooltip-text', null);
    }
}

/** Проверка заполненности поля исключительно цифрами */
function checkNumeric(self) {
    if (self.val()) {
        const t = parseFloat(self.val());

        if (isNaN(t)) {
            self.val('0');

            showMessage({
                text: LOCALE.onlyNumbersAreAllowed,
                type: 'error',
                timeout: 5000
            });
        }
    }
}

/** Подсчет длины текста в текстовом поле */
function validateTextMaxLength(field, maxlimit) {
    const val = field.val();

    if (maxlimit && val.length > maxlimit) {
        field.val(val.substring(0, maxlimit));

        showMessage({
            text: LOCALE.maxLengthError2 + " " + maxlimit + ".",
            type: 'error',
            timeout: 5000
        });
    }
}

/** Проверка url'ов */
function checkHttpUrl(string) {
    if (!string) {
        return false;
    }

    if (string.startsWith('/')) {
        return true;
    }

    let givenURL;

    try {
        givenURL = new URL(string);
    } catch (error) {
        return false;
    }

    return givenURL.protocol === "http:" || givenURL.protocol === "https:";
}

/** Валидация email'ов */
function validateEmail(email = '') {
    const validEmailPattern = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;

    return validEmailPattern.test(email.trim());
}


/** СТАНДАРТНЫЕ КОМПОНЕНТЫ FRAYM */

/** Обработка вывода подсказки и выделения текущего поля в форме */
function showHelpAndName(nameOrElement, parentElement) {
    _('.help:not(.fixed_help)').hide();

    const isName = typeof nameOrElement === 'string';

    const helpDiv = isName ? el(`.help#help_${nameOrElement}`) : domElementFromAnything(nameOrElement);
    parentElement = defaultFor(parentElement, (isName ? el(`#field_${nameOrElement}`) : null));
    const nameDiv = isName ? el(`#name_${nameOrElement}`) : null;

    if (helpDiv) {
        _(helpDiv).show();
        helpDiv.style.marginTop = `${-Math.floor(helpDiv.getBoundingClientRect().height)}px`;

        if (parentElement) {
            helpDiv.style.width = `${parentElement.offsetWidth}px`;
        }
    }

    if (nameDiv) {
        _(nameDiv).addClass('selected');
    }
}

/** Закрепление кнопок фильтрации и сброса фильтра */
function fixateIndexerButtons() {
    if (el('div.indexer')) {
        const element = _('div.indexer div.filtersBlock').last();
        const indexerElementBottom = element.position().top + element.asDomElement().offsetHeight - window.innerHeight;

        if (_('div.indexer').hasClass('shown') && document.body.scrollTop < indexerElementBottom) {
            _('div.indexer button').addClass('floaty');
        } else {
            _('div.indexer button').removeClass('floaty');
        }
    }
}

/** Добавление loader'а */
function appendLoader(element) {
    element = domElementFromAnything(element);

    if (!el('#circleG', element)) {
        const circleG = elNew('div', { id: 'circleG' });

        for (let i = 1; i <= 3; i++) {
            const circle = elNew('div', { id: `circleG_${i} `, classList: 'circleG' });
            circleG.appendChild(circle);
        }

        element.prepend(circleG);
        element.classList.add('loader_appended');
    }
}

/** Уничтожение loader'а */
function removeLoader(element) {
    element = domElementFromAnything(element);

    const loader = el('#circleG', element);
    if (loader) {
        loader.remove();
    }

    element.classList.remove('loader_appended');
}

/** Создание placeholder в поле формы */
function fraymPlaceholder(field) {
    if (field !== undefined) {
        field = _(domElementFromAnything(field));

        if (field.is('[placehold]')) {
            const placehold = field.attr('placehold');
            if (/msie/.test(navigator.userAgent.toLowerCase())) {
                if (field.val() === '') {
                    field.val(placehold);
                    field.addClass('placeholded');
                }

                field.on('keydown', function () {
                    const self = _(this);

                    if (self.val() === placehold) {
                        self.val('');
                        self.removeClass('placeholded');
                    }
                });

                field.on('blur', function () {
                    const self = _(this);

                    if (self.val() === '' || self.val() === placehold) {
                        self.val(placehold);
                        self.addClass('placeholded');
                    }
                });
            } else {
                field.attr('placeholder', field.attr('placehold'));
            }
        }
    }
}

/** Сортировка выпадающего множественного списка */
function filterDropfield(el, string) {
    if (el instanceof FraymElement) {
        const container = el.closest('.dropfield2');
        const createLink = el.prev('.create');

        if (string == LOCALE.dropFieldSearchAdd || string === '') {
            container.find('.dropfield2_field')?.show();

            container.find('.dropfield2_field[pl]')?.each(function () {
                const self = _(this);
                if (self.attr('pl')) {
                    self.asDomElement().style.paddingLeft = `${self.attr('pl')}px`;
                    self.attr('pl', null);
                }
            });

            createLink?.hide();
        } else {
            let alreadyShownIds = [];

            container.find('.dropfield2_field')?.hide();

            container.find('.dropfield2_field', false, string)?.each(function () {
                const self = _(this);

                if (!alreadyShownIds.includes(self.attr('id'))) {
                    alreadyShownIds.push(self.attr('id'));

                    self.attr('pl', self.asDomElement().style.paddingLeft);
                    self.show().asDomElement().style.paddingLeft = '0px';
                }
            });

            createLink?.show();
        }

        if (container.find('div.dropfield2_field:not(.hidden) > input:not(:checked):not(.disabled)')) {
            container.find('.dropfield2_selecter > a')?.text(LOCALE.selectAll).parent().addClass('dropfield2_select_all').removeClass('dropfield2_deselect_all');
        } else {
            container.find('.dropfield2_selecter > a')?.text(LOCALE.deselectAll).parent().addClass('dropfield2_deselect_all').removeClass('dropfield2_select_all');
        }
    }
}

/** Показ и сокрытие динамических полей
 * 
 * @param {HTMLElement} [element]
*/
function toggleDynamicFields(element = null) {
    const selfType = element?.tagName === 'SELECT' ? 'select' : 'multiselect';
    const selfName = element?.getAttribute('name');

    if (element) {
        let elemName = element.getAttribute('name');

        if (selfType == 'multiselect' && element.getAttribute('type') == 'checkbox') {
            elemName = elemName.replace(/\[\d+\]$/, '');
        }

        dependencyItemsMap.set(
            elemName,
            getDependencyItemsMapElementValues(elemName)
        );
    }

    _each(currentDynamicFieldsList, function (item) {
        let selfAffectsElem = !element;
        let hideElem = true;

        _each(item.dependencies, function (dependencyItems) {
            let foundFullGroup = true;

            if (element) {
                _each(dependencyItems, function (dependencyItem) {
                    if (selfType == dependencyItem.type && (dependencyItem.name == selfName || `${dependencyItem.name}[${dependencyItem.value}]` == selfName)) {
                        selfAffectsElem = true;
                        return;
                    }
                });
            }

            if (selfAffectsElem) {
                _each(dependencyItems, function (dependencyItem) {
                    if (!dependencyItemsMap.get(dependencyItem.name).includes(dependencyItem.value)) {
                        foundFullGroup = false;
                    }
                })
            }

            if (foundFullGroup) {
                hideElem = false;
            }
        })

        if (selfAffectsElem) {
            let domElem = el(`[id^="field_${item.name}"]`);

            if (!domElem) {
                try {
                    domElem = el(item.name);
                } catch (e) { }
            }

            if (domElem) {
                if (hideElem) {
                    const elem = _(domElem);

                    elem.hide();
                    elem.find('input[type="text"], select, textarea')?.val('').trigger('change');
                    doDropfieldRefresh = false;
                    elem.find('input:checked:not(:disabled)')?.each(function () {
                        _(this, { noCache: true })
                            .checked(false)
                            .change()
                            .destroy();
                    })
                    doDropfieldRefresh = true;
                    _(`[id="selected_${item.name}"]`).trigger('refresh');
                } else if (element) {
                    _(domElem).show().trigger('change');
                }
            }
        }
    })
}

/** Формирование карты значений динамических полей */
function getDependencyItemsMapElementValues(elemName) {
    let value = [];

    const checkSelect = el(`select[name="${elemName}"]`);

    if (checkSelect) {
        value.push(checkSelect.value);
    } else {
        const checkMultiselect = elAll(`input[type="checkbox"][name^="${elemName}["]`);

        if (checkMultiselect.length > 0) {
            checkMultiselect.forEach(input => {
                if (input.checked) {
                    const match = input.getAttribute('name').match(/\[(\d+)\]$/);

                    if (match) {
                        value.push(match[1]);
                    }
                }
            })
        } else {
            const checkMultiselectRadio = el(`input[type="radio"][name="${elemName}"]:checked`);

            if (checkMultiselectRadio) {
                value.push(el(`input[type="radio"][name="${elemName}"]:checked`).value);
            } else {
                const staticElements = elAll(`[id^="${elemName}["]`);

                staticElements.forEach(input => {
                    const match = input.getAttribute('id').match(/\[(\d+)\]$/);

                    if (match) {
                        value.push(match[1]);
                    }
                })
            }
        }
    }

    return value;
}

/** Инициализация динамических полей */
function initDynamicFields() {
    dependencyItemsMap = new Map();

    let responsibleItems = new Map();

    if (dynamicFieldsList.length > 0) {
        currentDynamicFieldsList = dynamicFieldsList;

        //структура массива currentDynamicFieldsList: {name = name скрываемого элемента, dependencies = массив объектов родителей, по которым происходит скрытие}. В объекте родителе структура {type = тип (select, multiselect), name = name элемента, value = value, при которой скрываемый элемент показывается}
        //ВАЖНО: все поля, от которых зависит один элемент, должны быть в одной записи массива dynamicFieldsList (нельзя на один элемент несколько dynamicFieldsList[] делать)

        _each(currentDynamicFieldsList.reverse(), function (item) {
            _each(item.dependencies, function (dependencyItems) {
                _each(dependencyItems, function (dependencyItem) {
                    let responsibleItem;

                    if (dependencyItem.type == 'select') {
                        responsibleItem = el(`select[name="${dependencyItem.name}"]`);
                    } else if (dependencyItem.type == 'multiselect') {
                        responsibleItem = el(`input[type="radio"][name="${dependencyItem.name}"]`);

                        if (!responsibleItem) {
                            responsibleItem = el(`input[name="${dependencyItem.name}[${dependencyItem.value}]"]`);
                        }
                    }

                    const elemName = dependencyItem.name;

                    if (!dependencyItemsMap.has(elemName)) {
                        dependencyItemsMap.set(
                            elemName,
                            getDependencyItemsMapElementValues(elemName)
                        );
                    }

                    if (responsibleItem) {
                        if (!responsibleItems.has(responsibleItem)) {
                            responsibleItems.set(responsibleItem, true);

                            responsibleItem.addEventListener('change', function () {
                                if (currentDynamicFieldsList.length) {
                                    toggleDynamicFields(responsibleItem);
                                }
                            })
                        }
                    } else {
                        let domElem = el(`[id^="field_${item.name}"]`);

                        if (!domElem) {
                            try {
                                domElem = el(item.name);
                            } catch (e) { }
                        }

                        if (domElem) {
                            if (!el(`[id="${dependencyItem.name}[${dependencyItem.value}]"]`)) {
                                const elem = _(domElem);

                                elem.hide();
                            }
                        }
                    }
                })
            })
        })

        toggleDynamicFields();
    } else {
        currentDynamicFieldsList = [];
    }

    dynamicFieldsList = [];
}


/** ФУНКЦИИ ДАТ, ВРЕМЕНИ И СООТВЕТСТВУЮЩИХ ПОЛЕЙ */

/** Четыре функции логики обработки различных полей date и datetime-local */
function dateFromLogicTimepicker(dateFromField) {
    const dateFromFieldValue = new Date(dateFromField.value);
    const dateToField = el('input.dpkr_time[name="date_to[0]"]');
    let dateToFieldValue = new Date(dateToField.value);

    if (dateToFieldValue < dateFromFieldValue) {
        dateToFieldValue = dateFromFieldValue;
        dateToFieldValue.setHours(dateToFieldValue.getHours() + 1);
        dateToField.value = dateFormat(dateToFieldValue, true);
    }

    const repeatUntil = el('input.dpkr[name="repeat_until[0]"]');

    if (repeatUntil) {
        let repeatUntilValue = new Date(repeatUntil.value);

        if (repeatUntilValue < dateFromFieldValue) {
            repeatUntilValue = dateFromFieldValue;
            repeatUntilValue.setHours(repeatUntilValue.getHours() + 1);
            repeatUntil.value = dateFormat(repeatUntilValue, true);
        }
    }
}

function dateFromLogic(dateFromField) {
    const dateFromFieldValue = new Date(dateFromField.value);
    const dateToField = el('input.dpkr[name="date_to[0]"]');
    let dateToFieldValue = new Date(dateToField.value);

    if (dateToFieldValue < dateFromFieldValue) {
        dateToField.value = dateFormat(dateFromFieldValue);
    }
}

function dateToLogicTimepicker(dateToField) {
    const dateFromField = el('input.dpkr_time[name="date_from[0]"]');
    const dateFromFieldValue = new Date(dateFromField.value);

    dateToField.min = dateFormat(dateFromFieldValue, true);

    if (dateToField.value < dateToField.min) {
        dateToField.value = dateToField.min;
    }
}

function dateToLogic(dateToField) {
    const dateFromField = el('input.dpkr[name="date_from[0]"]');
    const dateFromFieldValue = new Date(dateFromField.value);

    dateToField.min = dateFormat(dateFromFieldValue);

    if (dateToField.value < dateToField.min) {
        dateToField.value = dateToField.min;
    }
}

/** Вывод объекта даты или строки в стандартном UTC представлении вида: 2023-10-28T19:00 */
function dateFormat(date, withTime) {
    withTime = defaultFor(withTime, false);
    let local = new Date(date);
    local.setMinutes(date.getMinutes() - date.getTimezoneOffset());
    return local.toJSON().slice(0, (withTime ? 16 : 10));
}

/** Вывод объекта даты в стандартном для локали представлении: 28.10.2023 19:00 */
function dateFormatText(date, withTime, locale) {
    locale = defaultFor(locale, 'ru-RU');
    return withTime ? date.toLocaleDateString(locale, { dateStyle: "short" }) + " " + date.toLocaleTimeString(locale, { timeStyle: "short" }) : date.toLocaleDateString(locale);
}

/** Превращение строковой даты в js-дату */
function dateFromString(str) {
    const date = str.substring(0, 10);
    const time = str.substring(11, 16);
    const dateFrom = date.split('.');
    const timeFrom = time.split(':');
    return new Date(parseInt(dateFrom[2]), parseInt(dateFrom[1]) - 1, parseInt(dateFrom[0]), parseInt(timeFrom[0]), parseInt(timeFrom[1]));
}

/** Хелпер для денег */
function formatMoney(amount, currency = 'USD', locale = navigator.language) {
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: currency,
    }).format(amount);
}

/** Хелпер для дат */
function formatDate(dateString, locale = navigator.language) {
    const date = new Date(dateString);

    return new Intl.DateTimeFormat(locale, {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    }).format(date);
}

/** Инициализация функций полей date и datetime-local */
function fraymDateTimePickerApply() {
    _(document)
        .on('change', 'input.dpkr_time[name="date_from[0]"]', function () {
            dateFromLogicTimepicker(this);
        })
        .on('change', 'input.dpkr[name="date_from[0]"]', function () {
            dateFromLogic(this);
        })
        .on('change', 'input.dpkr_time[name="date_to[0]"]', function () {
            dateToLogicTimepicker(this);
        })
        .on('change', 'input.dpkr[name="date_to[0]"]', function () {
            dateToLogic(this);
        });

    _('input.dpkr_time[name="date_to[0]"]').each(function () {
        dateToLogicTimepicker(this);
    });

    _('input.dpkr[name="date_to[0]"]').each(function () {
        dateToLogic(this);
    });
}


/** ДОПОЛНИТЕЛЬНО ПОДГРУЖАЕМЫЕ КОМПОНЕНТЫ FRAYM */

/** Инициализация инпутов загрузки файлов (стайлера) */
function fraymFileBrowseStylerApply(element) {
    const scriptName = 'fraymFileBrowseStylerApply';

    dataElementLoad(
        scriptName,
        element,
        () => {
            getScript('/vendor/fraym/js/styler/styler.min.js').then(() => {
                dataLoaded['libraries'][scriptName] = true;
            });
        },
        function () {
            const self = domElementFromAnything(this);

            if (!_(self).hasClass('fraymStylerApplied')) {
                new FraymStyler(
                    self,
                    {
                        filePlaceholder: LOCALE.file.filePlaceholder,
                        fileBrowse: LOCALE.file.fileBrowse,
                        fileNumber: LOCALE.file.fileNumber
                    }
                );

                fraymFileUploadApply(self);
            }
        }
    );
}

/** Инициализация загрузчика файлов */
function fraymFileUploadApply(element) {
    const scriptName = 'fraymFileUploadApply';

    dataElementLoad(
        scriptName,
        element,
        () => {
            cssLoad('fraymFilepond', '/vendor/fraym/js/filepond/filepond.min.css');

            getScript('/vendor/fraym/js/filepond/filepond.min.js').then(() => {
                getScript(`/vendor/fraym/js/filepond/locale/${getLocale()}.min.js`).then(() => {
                    FilePond.setOptions(FilePondLocale);

                    window['isLoadingCheck'] = function (filepondObj) {
                        const isLoading = filepondObj.getFiles().filter(x => x.status !== 5 && x.status !== 2 && x.status !== 8).length !== 0;
                        const form = _(`input[name="${filepondObj.name}"]`).closest('form');

                        if (isLoading) {
                            form.find('button.main').disable();
                        } else {
                            form.find('button.main').enable();
                        }
                    }

                    dataLoaded['libraries'][scriptName] = true;
                });
            });
        },
        function () {
            const self = domElementFromAnything(this);

            if (self) {
                const obj = _(self);

                if (!obj.hasClass('filePondApplied')) {
                    obj.addClass('filePondApplied');

                    const uploadPath = obj.attr('data-upload-path');
                    const uploadNum = obj.attr('data-upload-num');
                    const uploadName = obj.attr('data-upload-name');
                    const previouslyUploadedFiles = JSON.parse(obj.attr('data-uploaded-files')) || [];

                    let previouslyUploadedFilesDecoded = [];

                    _each(previouslyUploadedFiles, function (file) {
                        previouslyUploadedFilesDecoded.push({
                            source: uploadPath + file['path'],
                            options: {
                                type: 'local',
                                metadata: {
                                    name: file['name'],
                                    nameShown: file['name_shown'],
                                }
                            },
                        });
                    });

                    const filepondObj = FilePond.create(self, {
                        allowMultiple: obj.hasAttr('multiple'),
                        maxFiles: null,
                        files: previouslyUploadedFilesDecoded,
                        server: {
                            process: (fieldName, file, metadata, load, error, progress, abort, transfer, options) => {
                                const formData = new FormData();
                                formData.append(obj.hasAttr('multiple') ? fieldName.substring(0, fieldName.length - 2) : fieldName, file, file.name);

                                const request = new XMLHttpRequest();
                                request.open('POST', `${uploadPath}?type=${uploadNum}`);

                                request.upload.onprogress = (e) => {
                                    progress(e.lengthComputable, e.loaded, e.total);
                                };

                                request.onload = function () {
                                    if (request.status >= 200 && request.status < 300) {
                                        const response = JSON.parse(request.responseText);
                                        const errorText = response[uploadName][0]['error'];

                                        if (errorText !== undefined) {
                                            showMessage({
                                                text: errorText,
                                                type: 'error'
                                            });

                                            error(errorText);
                                        } else {
                                            load(response[uploadName][0]['name']);
                                        }
                                    } else {
                                        error(request.statusText);
                                    }
                                };

                                request.send(formData);

                                return {
                                    abort: () => {
                                        request.abort();

                                        abort();
                                    },
                                };
                            },
                            revert: (uniqueFileId, load) => {
                                const deleteLink = `${uploadPath}?type=${uploadNum}&${uploadName}=${uniqueFileId}`;

                                fetchData(deleteLink, { method: 'DELETE' }).then(() => {
                                    load();
                                })
                            },
                            load: (uniqueFileId, load, error, progress, abort, headers) => {
                                fetch(uniqueFileId)
                                    .then(response => {
                                        if (response.ok) {
                                            progress(true, 0, 1);

                                            response.blob().then(function (myBlob) {
                                                let name = response.headers.get('content-disposition').match(/"([^"]+)"/);
                                                myBlob.name = name[1];

                                                load(myBlob);
                                            });
                                        } else {
                                            throw new Error('Failed to load file');
                                        }
                                    })
                                    .catch(err => {
                                        error(err.message);
                                    });

                                return {
                                    abort: () => {
                                        abort();
                                    }
                                };
                            },
                            restore: null,
                            fetch: null
                        },
                        onaddfilestart: () => { isLoadingCheck(filepondObj) },
                        onprocessfile: () => { isLoadingCheck(filepondObj) },
                        onaddfile: () => { isLoadingCheck(filepondObj) }
                    });

                    filepondObjs.set(obj.attr('name'), filepondObj);
                }
            }
        }
    );
}

function fraymFileUploadInputsFix() {
    _each(filepondObjs, filepondObj => {
        const filepondFiles = filepondObj.getFiles();
        const inputs = _(`input[type="hidden"][name="${filepondObj.name}"]`);

        _each(filepondFiles, (file, key) => {
            const value = `{${file.filename}:${file.getMetadata('name') || file.serverId}}`;
            const input = inputs.DOMElements[key];

            if (!input.classList.contains('filepondNameConverted')) {
                input.value = value;
                input.classList.add('filepondNameConverted');
            }
        })
    })
}

/** Инициализация wysiwyg*/
function fraymWysiwygApply(element) {
    const scriptName = 'fraymWysiwygApply';

    const options = {
        debug: false,
        modules: {
            toolbar: {
                container: [
                    ['bold', 'italic', 'underline', 'strike', { 'script': 'sub' }, { 'script': 'super' }],
                    [{ 'align': [] }],
                    ['link', 'image'],

                    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                    [{ 'indent': '-1' }, { 'indent': '+1' }],

                    [{ 'size': ['small', false, 'large', 'huge'] }],
                    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],

                    [{ 'color': [] }, { 'background': [] }],

                    ['clean'],

                    [{ 'viewCode': true }]
                ],
                handlers: {
                    image: function () {
                        const range = this.quill.getSelection();
                        let imageUrl = prompt(LOCALE.wysiwyg.inputImageUrl);
                        if (imageUrl) {
                            this.quill.insertEmbed(range.index, 'image', imageUrl, Quill.sources.USER);
                        }
                    },
                    viewCode: function () {
                        const quillContainer = _(this.quill.container);
                        const textarea = quillContainer.prev('textarea');

                        if (quillContainer.hasClass('hidden')) {
                            this.quill.clipboard.dangerouslyPasteHTML(textarea.val());
                            quillContainer.show();
                            textarea.hide();
                            this.quill.focus();
                        } else {
                            textarea.val(this.quill.root.innerHTML);
                            quillContainer.hide();
                            textarea.show();
                            textarea.asDomElement().focus();
                        }
                    }
                },
            }
        },
        theme: 'snow'
    };

    dataElementLoad(
        scriptName,
        element,
        () => {
            cssLoad('fraymQuill', '/vendor/fraym/js/quill/quill.snow.min.css');

            getScript('/vendor/fraym/js/quill/quill.min.js').then(() => {
                dataLoaded['libraries'][scriptName] = true;

                let icons = Quill.import('ui/icons');
                icons['viewCode'] = '<span class="ql-custom-text"><></span>';
            });
        },
        function (options) {
            const self = domElementFromAnything(this);
            const obj = _(self);

            if (!obj.hasClass('quillApplied')) {
                wysiwygObjs.set(obj.attr('id'), new Quill(obj.asDomElement(), options));
                obj.addClass('quillApplied');
                obj.asDomElement().insertAdjacentHTML('beforeBegin', `<textarea class="${obj.hasClass('obligatory') ? 'obligatory' : ''}" id="fraym_${obj.attr('id')}" data-wysiwyg-id="${obj.attr('id')}"></textarea>`);
            }
        },
        options
    );
}

/** Инициализация simplemodal */
function fraymModalApply(element) {
    const scriptName = 'fraymModalApply';

    const currentHrefParsed = parseUri(currentHref);

    const options = {
        savedHash: currentHrefParsed.anchor,
        onOpen: (element) => {
            componentsUpdateState(element.attr('hash'));
        },
        onClose: () => {
            componentsUpdateState(options.savedHash);
        }
    };

    dataElementLoad(
        scriptName,
        element,
        () => {
            getScript('/vendor/fraym/js/modal/modal.min.js').then(() => {
                dataLoaded['libraries'][scriptName] = true;
            });
        },
        function () {
            const self = domElementFromAnything(this);

            new FraymModal(self, options);
        },
        options
    );
}

/** Инициализация вкладок */
function fraymTabsApply(element) {
    const scriptName = 'fraymTabsApply';

    dataElementLoad(
        scriptName,
        element,
        () => {
            getScript('/vendor/fraym/js/tabs/tabs.min.js').then(() => {
                dataLoaded['libraries'][scriptName] = true;
            });
        },
        function () {
            const self = domElementFromAnything(this);

            new FraymTabs(self);
        }
    );
}

/** Инициализация noty */
function fraymNotyInit() {
    const scriptName = 'fraymNotyInit';

    dataElementLoad(
        scriptName,
        document,
        () => {
            getScript('/vendor/fraym/js/noty/noty.min.js').then(() => {
                dataLoaded['libraries'][scriptName] = true;
            });
        },
        function () {
            Noty.overrideDefaults({
                layout: 'bottomLeft',
                theme: 'fraym',
                closeWith: ['click']
            });
        }
    );
}

/** Создание диалогового запроса на подтверждение noty */
function fraymNotyPrompt(button, text, callback, callbackCancel) {
    ifDataLoaded(
        'fraymNotyInit',
        'fraymNotyPrompt',
        document,
        function () {
            notyDialog?.close();

            callbackCancel = defaultFor(callbackCancel, function () {
                notyDialog?.close();
            });

            let okText = LOCALE.yes;
            let cancelText = LOCALE.cancelCapitalized;

            if (button) {
                text = defaultFor(text, defaultFor(button.attr('text'), LOCALE.areYouSure));
                okText = defaultFor(button.attr('ok_text'), okText);
                cancelText = defaultFor(button.attr('cancel_text'), cancelText);
            } else {
                text = defaultFor(text, LOCALE.areYouSure);
            }

            notyDialog = new Noty({
                text: text,
                modal: true,
                layout: 'center',
                buttons: [
                    Noty.button(
                        okText,
                        'btn btn-primary',
                        callback
                    ),

                    Noty.button(
                        cancelText,
                        'btn btn-danger',
                        callbackCancel
                    )
                ]
            });

            notyDialog.on('afterClose', function () {
                notyDialog = null;
            });

            notyDialog.show();
        }
    );
}

/** Настройка кнопки удаления объекта через диалоговый запрос noty */
function notyDeleteButton(button, params) {
    ifDataLoaded(
        'fraymNotyInit',
        'notyDeleteButton',
        document,
        function () {
            const href = defaultFor(button.attr('href'), null);
            const method = defaultFor(button.attr('method'), 'GET');
            params = defaultFor(params, '');

            if (href) {
                if (button.is('button')) {
                    button.disable();
                    appendLoader(button);
                }

                const options = {
                    json: true,
                    method: method
                };

                fetchData(href + params, options).then(function (jsonData) {
                    if (jsonData['redirect'] !== undefined) {
                        if (jsonData['redirect'] === 'stayhere') {
                            updateState(currentHref);
                        } else {
                            /** При прямом перенаправлении проверяем нет ли модального окна, закрываем его и только потом переходим */
                            if (el('.fraymmodal-overlay.shown')) {
                                hrefAfterModalClose = jsonData['redirect'];
                                _('.fraymmodal-overlay.shown').click();
                            } else {
                                if (button.hasClass('no_dynamic_content')) {
                                    window.location = jsonData['redirect'];
                                } else {
                                    updateState(jsonData['redirect']);
                                }
                            }
                        }
                    } else {
                        if (jsonData['messages'] !== undefined) {
                            _each(jsonData['messages'], (data) => {
                                if (data[0] !== 'success_delete') {
                                    showMessage({
                                        text: data[1],
                                        type: data[0],
                                    });
                                } else {
                                    /** Это данные для удаления определенной строки из табличного представления или карточки */
                                    _(`div.tr[obj_id="${data[1]}"]`).trigger('remove').remove();
                                }
                            })
                        }

                        if (button.is('button')) {
                            button.enable();
                            removeLoader(button);
                        }
                    }
                })
            }
        }
    );
}

/** Конструктор окошек запроса данных */
function createPseudoPrompt(html, title, additionalButtons, onClose, onShow) {
    ifDataLoaded(
        'fraymNotyInit',
        'createPseudoPrompt',
        document,
        function () {
            notyDialog?.close();

            let buttons = [];

            _each(additionalButtons, function (buttonData) {
                const button = Noty.button(
                    buttonData.text,
                    `btn btn-primary ${buttonData.class}`,
                    buttonData.click
                );

                buttons.push(button);
            })

            buttons.push(Noty.button(
                LOCALE.closeCapitalized,
                'btn btn-danger',
                function () {
                    notyDialog?.close();
                }
            ));

            notyDialog = new Noty({
                text: title + html,
                modal: true,
                layout: 'center',
                buttons: buttons,
                closeWith: ['button']
            });

            if (onClose) {
                notyDialog.on('onClose', onClose);
            }

            if (onShow) {
                notyDialog.on('onShow', onShow);
            }

            notyDialog.on('afterClose', function () {
                notyDialog = null;
            });

            notyDialog.show();
        }
    );
}

/** Получение DOM-элемента noty диалога */
function getNotyDialogDOM() {
    const dom = notyDialog?.layoutDom;

    if (dom) {
        return _(dom);
    }

    return null;
}

/** Вывод сообщения-нотификации */
function showMessage(options) {
    let timeout = options.timeout || (options.text.length * 25);
    if (timeout < 5000) {
        timeout = 5000;
    }

    //options.type: 'error', 'warning', 'info', 'information', 'success', 'alert', 'notification',

    ifDataLoaded(
        'fraymNotyInit',
        'showMessage',
        document,
        function () {
            new Noty({
                text: options.text,
                type: options.type,
                timeout: timeout
            }).show();
        }
    );
}

/** Вывод сообщений-нотификаций из массива */
function showMessages() {
    for (let message of messages) {
        showMessage({
            text: message[1],
            type: message[0]
        });
    }
    messages = [];
}

/** Вывод сообщений-нотификаций из JSON-ответа **/
function showMessagesFromJson(jsonData) {
    if (jsonData['messages'] !== undefined) {
        _each(jsonData['messages'], (data) => {
            messages.push(data);
        });

        showMessages();
    }
}

/** Вывод одиночного сообщения-нотификаций из JSON-ответа динамического действия **/
function showMessageFromJsonData(jsonData) {
    showMessage({
        text: jsonData['response_text'],
        type: jsonData['response'],
        timeout: 5000
    });
}

/** Инициализация autocomplete */
function fraymAutocompleteApply(element, options) {
    const scriptName = 'fraymAutocompleteApply';

    dataElementLoad(
        scriptName,
        element,
        () => {
            cssLoad('fraymAutocomplete', '/vendor/fraym/js/autocomplete/autocomplete.min.css');

            getScript('/vendor/fraym/js/autocomplete/autocomplete.min.js').then(() => {
                dataLoaded['libraries'][scriptName] = true;
            });
        },
        function () {
            const self = domElementFromAnything(this);

            new FraymAutocomplete(self, options);
        },
        options
    );
}

/** Инициализация drag&drop */
function fraymDragDropApply(element, options) {
    const scriptName = 'fraymDragDropApply';

    dataElementLoad(
        scriptName,
        element,
        () => {
            getScript('/vendor/fraym/js/dragdrop/dragdrop.min.js').then(() => {
                dataLoaded['libraries'][scriptName] = true;
            });
        },
        function () {
            if (typeof this === 'string' || this instanceof String) {
                new FraymDragDrop(this.toString(), options);
            } else {
                const self = domElementFromAnything(this);

                new FraymDragDrop(self, options);
            }
        },
        options
    );
}