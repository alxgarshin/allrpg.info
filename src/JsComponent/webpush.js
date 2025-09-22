/** Функции для браузерных push-уведомлений */

if (withDocumentEvents) {
    const publicVapidKey = 'BBIuA0-uI6qAvUjv4s2AIIJWZuiJFFoKA04iP74sUOCUk1SKRVKZdI_oZnehgKOvtBHoxAiBK9EEU4qXpgH4DUY';
    const permission = Notification.permission;

    _arSuccess('webpush_subscribe', function () {
        localStorage.setItem('webpush', 'true');
    });

    _arSuccess('webpush_unsubscribe', function () {
        localStorage.setItem('webpush', 'blocked');
        getExistingSubscription().then(function (subscription) { if (subscription) subscription.unsubscribe() })
    })

    /** Проверяем существующую подписку **/
    async function getExistingSubscription() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return null;

        const reg = await navigator.serviceWorker.register('/js/webpush-messaging-sw.min.js');
        const subscription = await reg.pushManager.getSubscription();

        return subscription;
    }

    /** Основной процесс подписки */
    window.subscribeFlow = async function (enforceDialog) {
        const subscription = await getExistingSubscription();

        if (localStorage.getItem('webpush') !== 'true') {
            _('input[name^="messaging_active"]').checked(false);
        }

        if (subscription === null) {
            if (permission !== 'granted') {
                localStorage.setItem('webpush', 'false');
            }

            if (localStorage.getItem('webpush') === 'true') {
                _('input[name^="messaging_active"]').checked(true);

                webpushInit();
            } else if (enforceDialog || localStorage.getItem('webpush') !== 'blocked') {
                let success = false;

                createPseudoPrompt(
                    `<div class="webpush_why">${LOCALE.webpush.request_why}${(permission === `denied` ? `<div class="webpush_denied">${LOCALE.webpush.checkbox_main_text}</div>` : ``)}</div>`,
                    LOCALE.webpush.header,
                    permission === `denied` ? [] : [
                        {
                            text: LOCALE.webpush.turn_on,
                            class: 'main',
                            click: function () {
                                if (permission === 'granted') {
                                    success = true;

                                    _('input[name^="messaging_active"]').checked(true);

                                    webpushInit();

                                    notyDialog?.close();
                                } else {
                                    showMessage({
                                        text: LOCALE.webpush.checkbox_main_text,
                                        type: 'error',
                                    });
                                }
                            }
                        }
                    ],
                    function () {
                        if (!success) {
                            localStorage.setItem('webpush', 'blocked');
                        }
                    }
                );
            }
        }
    };

    if (permission !== 'denied') {
        window.subscribeFlow();
    } else {
        const deviceId = getOrCreateDeviceId();

        actionRequest({
            action: 'user/webpush_unsubscribe',
            deviceId: deviceId,
        });
    }

    async function webpushInit() {
        const deviceId = getOrCreateDeviceId();
        const reg = await navigator.serviceWorker.register('/js/webpush-messaging-sw.min.js');

        // Разрешение на уведомления (по клику пользователя)
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') return;

        // Оформляем подписку
        const sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(publicVapidKey)
        });

        if (sub) {
            const subPrepared = JSON.parse(JSON.stringify(sub));

            actionRequest({
                action: 'user/webpush_subscribe',
                deviceId: deviceId,
                endpoint: subPrepared.endpoint,
                p256dh: subPrepared.keys.p256dh,
                auth: subPrepared.keys.auth,
                contentEncoding: 'aesgcm'
            });
        }
    }

    /** Преобразование публичного ключа в Uint8Array для подписки **/
    function urlBase64ToUint8Array(base64) {
        const padding = '='.repeat((4 - base64.length % 4) % 4);
        const b64 = (base64 + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(b64);
        const out = new Uint8Array(raw.length);

        for (let i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);

        return out;
    }
}
