/** Модуль игрока */

let defaultCurrencyId = 0;

let qrCanvasWidth = 0;
let qrCanvasHeight = 0;

let videoAllowed = false;
let gCtx = null;
let videoOptions = { 'facingMode': 'environment' };
let nav = navigator;
let gUM = true;
let showTimerDataSeconds = [];

let settingGeolocId = false;

if (el('#qrcode_clicker_container')) {
    qrcodeApply();

    const qrcodeInput = 'input[name="qrcode"]';

    if (el(qrcodeInput)) {
        const qrCanvas = el('#qr-canvas');

        if (qrCanvas.getContext && qrCanvas.getContext('2d', { willReadFrequently: true }) && nav.mediaDevices && nav.mediaDevices.enumerateDevices) {
            gCtx = qrCanvas.getContext('2d', { willReadFrequently: true });

            nav.mediaDevices.enumerateDevices()
                .then(function (devices) {
                    _each(devices, function (device) {
                        if (device.kind === 'videoinput') {
                            videoAllowed = true;
                        }
                    });
                });
        }

        _('div#qrcode_clicker_container').on('click', function () {
            _('a#retry_qrcode_scanner').hide();
            _('div.qrcode_result').hide();

            if (videoAllowed) {
                startVideo();

                _('div.qr-video-container').show();

                if (!flashlight) {
                    flashlight = flashlightHandler;
                    flashlight.accessFlashlight();
                }
            } else {
                _(qrcodeInput).click();
            }
        });

        _('div.flashlight').on('click', function () {
            flashlight.setFlashlightStatus(!_(this).hasClass('turnoff'));
        });

        _(qrcodeInput).on('change', function () {
            const reader = new FileReader();

            reader.onload = function () {
                qrcode.decode(reader.result);
            };

            reader.readAsDataURL(this.files[0]);
        });

        _('a#retry_qrcode_scanner').on('click', function () {
            decodeQRData(_(this).attr('data'));
        });
    }
}

if (el('div.kind_ingame')) {
    /** Переключение родительских вкладок */
    if (window.location.hash !== '') {
        const hash = window.location.hash.substring(1);

        if (/wmc/.test(hash)) {
            const element = el(`a#${hash}`);

            ifDataLoaded(
                'fraymTabsApply',
                'ingameWmcScrollByHash',
                element,
                function () {
                    _('a#chat').parent().trigger('activate');

                    scrollPageTop = false;

                    scrollWindow(_(element).closest('div.conversation_message')?.offset().top || 0);
                }
            );
        }
    }

    /** Подгрузка ключей, свойств и предметов */
    if (el('div.qrpg_properties_list.not_loaded') && el('div.qrpg_keys_list.not_loaded')) {
        _('div.qrpg_properties_list.not_loaded').removeClass('not_loaded');
        _('div.qrpg_keys_list.not_loaded').removeClass('not_loaded');

        actionRequest({
            action: 'ingame/qrpg_get_keys_and_properties'
        });
    }

    /** Переход по клику на код внутри описания */
    _('.qrpg_code_data').on('click', function () {
        _('div.qrcode_result').html('').show();
        appendLoader('div.qrcode_result');

        actionRequest({
            action: 'ingame/qrpg_decode',
            data: _(this).attr('obj_data')
        }, _('div.qrcode_result'));
    });

    /** Показ и скрытие описаний свойств */
    _('div.qrpg_property_name').on('click', function () {
        const self = _(this);

        self.parent()?.find('div.qrpg_property_name')?.hide();
        self.prev()?.show();
    });

    _('div.qrpg_property_description.shown').on('click', function () {
        const self = _(this);

        self.hide();
        self.parent()?.find('div.qrpg_property_name')?.show();
    });

    /** Геолокация */
    if (geolocationId == 0 && !settingGeolocId) {
        settingGeolocId = true;

        geolocationId = navigator.geolocation.watchPosition(
            function (position) {
                settingGeolocId = false;
                _('.geoposition_info').show().removeClass('error').text(LOCALE.geoposition.active);

                actionRequest({
                    action: 'ingame/set_geoposition',
                    lat: position.coords.latitude,
                    long: position.coords.longitude,
                    acc: position.coords.accuracy
                });
            },
            function (PositionError) {
                if (PositionError.code == 1) {
                    _('.geoposition_info').show().addClass('error').text(LOCALE.geoposition.needAllow);
                } else {
                    _('.geoposition_info').show().addClass('error').text(LOCALE.geoposition.error);
                }
            },
            {
                enableHighAccuracy: true,
                timeout: 300000,
                maximumAge: 0
            }
        );
    }

    /** Генережка и оплата по QRpg-коду */
    _('select[name="bank_currency_id"], input[name="bank_generate_qrpg_amount"], input[name="bank_generate_qrpg_name"]').on('change keydown', function () {
        _('div.bank_generate_qrpg div.result button.main').show().enable();
        removeLoader('div.bank_generate_qrpg div.result button.main');
        _('div.bank_generate_qrpg div.result img').remove();
    });

    _('button#bank_generate_qrpg_button').on('click', function () {
        const self = _(this);

        actionRequest({
            action: 'ingame/prepare_qrpg_bank_code',
            bank_currency_id: self.closest('div.bank_generate_qrpg')?.find('select[name="bank_currency_id"]')?.val(),
            amount: self.closest('div.bank_generate_qrpg')?.find('input[name="bank_generate_qrpg_amount"]')?.val(),
            name: self.closest('div.bank_generate_qrpg')?.find('input[name="bank_generate_qrpg_name"]')?.val()
        }, self);

        _('div.bank_generate_qrpg div.result button.main').disable();
        appendLoader('div.bank_generate_qrpg div.result button.main');
    });

    /** Установка валюты по умолчанию */
    if (defaultCurrencyId > 0) {
        _('select[name^="bank_currency_id"]').val(defaultCurrencyId);
        _('select[name^="from_bank_currency_id"]').val(defaultCurrencyId);
    }

    /** Хакинг и ввод кода */
    _('div.qrpg_hacking_start').on('click', function () {
        const self = _(this);

        if (self.hasClass('text_to_access')) {
            actionRequest({
                action: 'ingame/qrpg_decode',
                qhi_id: self.closest('div.qrpg_hacking').attr("qhi_id"),
                text_to_access: _('input#text_to_access').val()
            }, _('div.qrcode_result'));
        } else {
            actionRequest({
                action: 'ingame/qrpg_hacking_start',
                qha_id: self.closest('div.qrpg_hacking').attr("qha_id")
            }, self);
        }
    });

    _('div.qrpg_hacking_matrix_col.selected').on('click', function () {
        const self = _(this);
        const div = self.closest('div.qrpg_hacking_active');
        const hackingSequence = objectToArray(JSON.parse(div.find('input[name="hacking_sequence"]').val()));
        const matrix = div.find('div.qrpg_hacking_matrix');
        const qrpgHackingInputElems = div.find('div.qrpg_hacking_input div.qrpg_hacking_input_elem');

        self.addClass('chosen');
        hackingSequence.push([self.attr('row'), self.attr('col')]);
        div.find('input[name="hacking_sequence"]').val(JSON.stringify(hackingSequence));
        matrix.find('.selected').removeClass('selected');

        if (matrix.attr('curType') == 'row') {
            matrix.attr('curType', 'col');
            matrix.find(`[col="${self.attr('col')}"]`).not('.chosen').addClass('selected');
        } else {
            matrix.attr('curType', 'row');
            matrix.find(`[row="${self.attr('row')}"]`).not('.chosen').addClass('selected');
        }

        qrpgHackingInputElems.not('.filled').first().text(self.text());
        qrpgHackingInputElems.not('.filled').first().addClass('filled');

        if (qrpgHackingInputElems.not('.filled')) {
            if (div.find('div.qrpg_hacking_input div.qrpg_hacking_input_elem.filled').length == div.find('input[name="sequences_length_sum"]').val()) {
                div.find('.finish').show();
            }
        } else {
            //закончились слоты
            window.clearInterval(window[`hacking_interval_${div.attr('qha_id')}`]);

            actionRequest({
                action: 'ingame/qrpg_decode',
                qha_id: div.attr('qha_id'),
                hacking_sequence: div.find('input[name="hacking_sequence"]').val()
            }, _('div.qrcode_result'));
        }
    });

    _('div.qrpg_hacking_matrix_container div.finish').on('click', function () {
        const self = _(this);
        const div = self.closest('div.qrpg_hacking_active');

        window.clearInterval(window[`hacking_interval_${div.attr('qha_id')}`]);
        actionRequest({
            action: 'ingame/qrpg_decode',
            qha_id: div.attr('qha_id'),
            hacking_sequence: div.find('input[name="hacking_sequence"]').val()
        }, _('div.qrcode_result'));
    });
}

if (withDocumentEvents) {
    actionRequestSupressErrorForActions.push('set_geoposition');

    _arSuccess('qrpg_hacking_start', function (jsonData, params, target) {
        const div = target.closest('div.qrpg_hacking');

        div.addClass('qrpg_hacking_active');
        div.html(jsonData['response_data']['html']);

        _('div.qrpg_hacking_matrix').attr('curType', 'row');
        _('div.qrpg_hacking_matrix').find('[row="0"]').addClass('selected');

        scrollWindow(_('div.qrpg_hacking_header').offset().top - 65);

        window[`hacking_interval_${div.attr('qha_id')}`] = setInterval(function () {
            const timeleft = parseInt(_('div.qrpg_hacking_timer span').text());

            _('div.qrpg_hacking_timer span').text((timeleft - 1));

            if (timeleft - 1 == 0) {
                window.clearInterval(window[`hacking_interval_${div.attr('qha_id')}`]);

                actionRequest({
                    action: 'ingame/qrpg_decode',
                    qha_id: div.attr('qha_id'),
                    hacking_sequence: div.find('input[name="hacking_sequence"]').val()
                }, _('div.qrcode_result'));
            }
        }, 1000);
    })

    _arSuccess('qrpg_bank_pay', function (jsonData, params, target) {
        target = _('div.qrcode_result');
        removeLoader('div.qrcode_result');

        _('a#retry_qrcode_scanner').attr('data', '').hide();
        target.html(`<h1>${jsonData['response_data']['header`]}</h1><div class="qrpg_description">${jsonData[`response_data']['description']}</div>`);
    })

    _arError('qrpg_bank_pay', function (jsonData, params, target, error) {
        target = _('div.qrcode_result');
        removeLoader('div.qrcode_result');

        showMessageFromJsonData(jsonData);
    })

    _arSuccess('prepare_qrpg_bank_code', function (jsonData, params, target) {
        _(target).closest('div.bank_generate_qrpg')?.find('div.result')?.find('button.main')?.enable();

        removeLoader(target.closest('div.bank_generate_qrpg')?.find('div.result')?.find('button.main'));

        target.closest(`div.bank_generate_qrpg`)?.find('div.result')?.insert(`<img src="/scripts/qrcode/json_string=${JSON.stringify(jsonData['response_data'])}"/>`, 'append');

        target.closest('div.bank_generate_qrpg')?.find('div.result')?.find('button.main')?.hide();
    })

    _arError('prepare_qrpg_bank_code', function (jsonData, params, target, error) {
        _(target).closest('div.bank_generate_qrpg')?.find('div.result')?.find('button.main').enable();

        removeLoader(target.closest('div.bank_generate_qrpg')?.find('div.result')?.find('button.main'));
    })

    _arSuccess('set_geoposition', function () { })

    _arError('set_geoposition', function () { })

    _arSuccess('qrpg_decode', function (jsonData, params, target) {
        removeLoader(target);

        target.html('');

        if (jsonData['response_data']['headers'] !== undefined) {
            _each(jsonData['response_data']['headers'], function (element, index) {
                if (element == 'j3jkcnsmmxu82') {
                    const qhi_id = jsonData['response_data']['descriptions'][index];

                    if (qhi_id == -1) { /* это проваленный ввод кода */
                        target.html(`${target.html()}<h1>${LOCALE.qrpgTextAccess.failedTitle}</h1><div class="qrpg_description">${LOCALE.qrpgTextAccess.failedDescription}</div>`);
                    } else if (isNaN(qhi_id)) { /* это текст при проваленном вводе кода */
                        target.html(`${target.html()}<h1>${LOCALE.qrpgTextAccess.failedTitle}</h1><div class="qrpg_description">${qhi_id}</div>`);
                    } else {
                        target.html(`${target.html()}<h1>${LOCALE.qrpgTextAccess.title}</h1><div class="qrpg_description qrpg_hacking qrpg_hacking_text_input" qhi_id="${qhi_id}"><div class="qrpg_hacking_description">${LOCALE.qrpgTextAccess.description}</div><div class="qrpg_hacking_text"><input type="text" id="text_to_access"></div><div class="qrpg_hacking_start text_to_access">${LOCALE.qrpgTextAccess.start}</div></div>`);
                    }
                } else if (element != 'xk1kljd9cjsa3') {
                    target.html(`${target.html()}<h1>${element}</h1><div class="qrpg_description">${jsonData['response_data']['descriptions'][index]}</div>`);

                    if (el('div.mp3_player audio')) {
                        mp3Apply();
                    }
                } else {
                    const qha_id = jsonData['response_data']['descriptions'][index];

                    if (qha_id == -1) { /* это проваленный взлом */
                        target.html(`${target.html()}<h1>${LOCALE.qrpgHacking.failedTitle}</h1><div class="qrpg_description">${LOCALE.qrpgHacking.failedDescription}</div>`);
                        document.getElementById('hacking_alert').play();
                    } else if (isNaN(qha_id)) { /* это текст при проваленном взломе */
                        target.html(`${target.html()}<h1>${LOCALE.qrpgHacking.failedTitle}</h1><div class="qrpg_description">${qha_id}</div>`);
                    } else {
                        target.html(`${target.html()}<h1>${LOCALE.qrpgHacking.title}</h1><div class="qrpg_description qrpg_hacking" qha_id="${qha_id}"><div class="qrpg_hacking_description">${LOCALE.qrpgHacking.description}</div><div class="qrpg_hacking_start">${LOCALE.qrpgHacking.start}</div></div>`);
                    }
                }
            });
        } else {
            target.html(`<h1>${jsonData['response_data']['header`]}</h1><div class="qrpg_description">${jsonData[`response_data']['description']}</div>`);
        }

        _('a#retry_qrcode_scanner').attr('data', '').hide();

        _('button#approve_payment').on('click', function () {
            const self = _(this);

            actionRequest({
                action: 'ingame/qrpg_bank_pay',
                'account_num_to': self.closest('div.qrpg_description').find('input[name="account_num_to"]').val(),
                'bank_currency_id': self.closest('div.qrpg_description').find('input[name="bank_currency_id"]').val(),
                'amount': self.closest('div.qrpg_description').find('input[name="amount"]').val(),
                'name': self.closest('div.qrpg_description').find('input[name="name"]').val()
            }, self);

            self.closest('div.qrcode_result').html('').show();
            appendLoader(this);
        });

        scrollWindow(0);

        actionRequest({
            action: 'ingame/qrpg_get_keys_and_properties'
        });
    })

    _arError('qrpg_decode', function (jsonData, params, target, error) {
        removeLoader(target);

        _('a#retry_qrcode_scanner').attr('data', params['data']).show();
        target.hide();
    })

    _arSuccess('qrpg_get_keys_and_properties', function (jsonData, params, target) {
        _('div.qrpg_properties_list').html(jsonData['response_data']['qrpg_properties_list']);
        _('div.qrpg_keys_list').html(jsonData['response_data']['qrpg_keys_list']);

        if (_('div.mp3_player audio').length) {
            mp3Apply();
        }

        checkQRpgTimers();
    })
}

/** Распознание QR-кодов из видеопотока */
function qrcodeApply() {
    const scriptName = 'qrcodeApply';

    dataElementLoad(
        scriptName,
        document,
        () => {
            getScript('/vendor/qrcodereader/qr_packed.min.js').then(() => {
                dataLoaded['libraries'][scriptName] = true;
            });
        },
        function () {
            qrcode.callback = decodeQRData;
        }
    );
}

function startVideo() {
    _('div#qrcode_clicker_container').hide();

    if (nav.mediaDevices.getUserMedia) {
        nav.mediaDevices.getUserMedia({ video: videoOptions, audio: false }).then(function (stream) {
            videoSuccess(stream);
        }).catch(function (error) {
            videoError(error)
        });
    } else if (nav.getUserMedia) {
        nav.getUserMedia({ video: videoOptions, audio: false }, videoSuccess, videoError);
    } else if (nav.webkitGetUserMedia) {
        nav.webkitGetUserMedia({ video: videoOptions, audio: false }, videoSuccess, videoError);
    }
}

function videoSuccess(stream) {
    const videoBlock = el('#qr-video');

    window.QRstream = stream;
    videoBlock.srcObject = stream;
    videoBlock.play();

    gUM = true;
    setTimeout(captureToCanvas, 500);
}

function videoError(error) {
    gUM = false;
    console.log(error);
}

function captureToCanvas() {
    const qrCanvas = _('#qr-canvas');
    const videoBlock = el('#qr-video');

    if (gUM && videoBlock) {
        qrCanvasWidth = videoBlock.offsetWidth;
        qrCanvasHeight = videoBlock.offsetHeight;
        qrCanvas.attr('width', qrCanvasWidth).attr('height', qrCanvasHeight);
        gCtx.drawImage(videoBlock, 0, 0, qrCanvasWidth, qrCanvasHeight);

        try {
            qrcode.decode();
        } catch (e) {
            setTimeout(captureToCanvas, 500);
        }
    }
}

function decodeQRData(res) {
    const qrcodeResultDiv = _('div.qrcode_result');

    qrcodeResultDiv.hide();

    _('a#retry_qrcode_scanner').hide();

    if (res instanceof Error) {
        qrcodeResultDiv.html(`<h1>${LOCALE.qrpgNotFoundHeader}</h1><div class="qrpg_description">${LOCALE.qrpgNotFound}</div>`);
        qrcodeResultDiv.show();
    } else {
        stopVideo();

        qrcodeResultDiv.html('').show();
        appendLoader(this);

        actionRequest({
            action: 'ingame/qrpg_decode',
            data: res
        }, qrcodeResultDiv)
    }
}

/** Таймер в QRpg ключах и свойствах */
function checkQRpgTimers() {
    if (el('div.qrpg_property_name[timer]') || el('div.qrpg_key[timer]')) {
        _('div.qrpg_property_name[timer], div.qrpg_key[timer]').each(function () {
            const self = _(this);
            const timerId = self.attr('id');

            showTimerDataSeconds[timerId] = new Date();
            showTimerDataSeconds[timerId].setMilliseconds(showTimerDataSeconds[timerId].getMilliseconds() + (parseInt(self.attr('timer')) * 1000));

            window.clearInterval(window[`show_timer_data_interval_${timerId}`]);

            self.insert('<div class="timer"></div>', 'append');

            checkQRpgTimersInterval(timerId);

            window[`show_timer_data_interval_${timerId}`] = setInterval(function () {
                checkQRpgTimersInterval(timerId);
            }, 1000);
        })
    }
}

function checkQRpgTimersInterval(timerId) {
    if (el(`div#${timerId} div.timer`)) {
        const curDate = new Date();
        const distance = parseInt((showTimerDataSeconds[timerId].valueOf() - curDate.valueOf()) / 1000);

        if (distance < 0) {
            window.clearInterval(window[`show_timer_data_interval_${timerId}`]);

            actionRequest({
                action: 'ingame/qrpg_get_keys_and_properties'
            });
        } else {
            const days = Math.floor(distance / (60 * 60 * 24));
            const hours = Math.floor((distance % (60 * 60 * 24)) / (60 * 60));
            const minutes = Math.floor((distance % (60 * 60)) / 60);
            const seconds = Math.floor((distance % 60));
            let showTimerDataText = '';

            if (days > 0) {
                showTimerDataText = showTimerDataText + days + LOCALE.timer.days;
            }

            if (hours > 0) {
                showTimerDataText = showTimerDataText + hours + LOCALE.timer.hours;
            }

            if (minutes < 10) {
                showTimerDataText = showTimerDataText + '0';
            }

            showTimerDataText = showTimerDataText + minutes + LOCALE.timer.minutes;

            if (seconds < 10) {
                showTimerDataText = showTimerDataText + '0';
            }

            showTimerDataText = showTimerDataText + seconds + LOCALE.timer.seconds;

            _(`div#${timerId} div.timer`).text(showTimerDataText);
        }
    } else {
        window.clearInterval(window[`show_timer_data_interval_${timerId}`]);
    }
}

/** Подгрузка mp3-плеера */
function mp3Apply() {
    const scriptName = 'mp3Apply';

    dataElementLoad(
        scriptName,
        document,
        () => {
            cssLoad('fraymAudioplayer', '/vendor/fraym/js/audioplayer/audioplayer.min.css');

            getScript('/vendor/fraym/js/audioplayer/audioplayer.min.js').then(() => {
                dataLoaded['libraries'][scriptName] = true;
            });
        },
        function () {
            _('div.mp3_player audio').each(function () {
                new FraymAudioPlayer(this, {
                    strPlay: '',
                    strPause: '',
                    strVolume: ''
                });
            })
        }
    );
}

/** Класс фонарика */
class flashlightHandler {
    static track;

    static accessFlashlight() {
        if (!('mediaDevices' in window.navigator)) {
            console.log("Media Devices not available. Use HTTPS!");

            _('div.flashlight').hide();

            flashlight = false;

            return;
        }

        window.navigator.mediaDevices.enumerateDevices().then((devices) => {
            const cameras = devices.filter((device) => device.kind === 'videoinput');

            if (cameras.length === 0) {
                console.log("No camera found. If your device has camera available, check permissions.");

                _('div.flashlight').hide();

                flashlight = false;

                return;
            }

            const camera = cameras[cameras.length - 1];

            window.navigator.mediaDevices.getUserMedia({
                video: {
                    deviceId: camera.deviceId
                }
            }).then((stream) => {
                this.track = stream.getVideoTracks()[0];

                if (!(this.track.getCapabilities().torch)) {
                    console.log("No torch available.");

                    _('div.flashlight').hide();

                    flashlight = false;

                    this.track.stop();
                }
            });
        });
    }

    static setFlashlightStatus(status) {
        if (this.track && this.track.getCapabilities().torch) {
            this.track.applyConstraints({
                advanced: [{
                    torch: status
                }]
            })

            _('div.flashlight').toggleClass('turnoff', status);
        }
    }
}