// cacheName for cache versioning
const cacheName = '20250902_1500';

self.addEventListener('install', function (event) {
    event.waitUntil(
        (async () => {
            const isStandalone = matchMedia('(display-mode: standalone)').matches;

            if (isStandalone) {
                const urlsToPrefetch = [
                    '/offline.html',

                    '/js/global.min.js',
                    '/js/roles.min.js',
                    '/css/global.min.css',

                    '/design/networks/fb_icon.svg',
                    '/design/networks/tg_icon.svg',
                    '/design/networks/vk_icon.svg',

                    '/design/qrpg/1.svg',
                    '/design/qrpg/2.svg',
                    '/design/qrpg/3.svg',
                    '/design/qrpg/4.svg',
                    '/design/qrpg/5.svg',
                    '/design/qrpg/6.svg',
                    '/design/qrpg/7.svg',
                    '/design/qrpg/8.svg',
                    '/design/qrpg/9.svg',
                    '/design/qrpg/10.svg',
                    '/design/qrpg/11.svg',
                    '/design/qrpg/12.svg',
                    '/design/qrpg/13.svg',
                    '/design/qrpg/14.svg',
                    '/design/qrpg/15.svg',
                    '/design/qrpg/16.svg',
                    '/design/qrpg/17.svg',
                    '/design/qrpg/18.svg',
                    '/design/qrpg/19.svg',
                    '/design/qrpg/20.svg',
                    '/design/qrpg/21.svg',
                    '/design/qrpg/22.svg',
                    '/design/qrpg/23.svg',
                    '/design/qrpg/24.svg',
                    '/design/qrpg/25.svg',
                    '/design/qrpg/26.svg',
                    '/design/qrpg/27.svg',
                    '/design/qrpg/28.svg',
                    '/design/qrpg/29.svg',
                    '/design/qrpg/30.svg',
                    '/design/qrpg/31.svg',
                    '/design/qrpg/32.svg',

                    '/design/roboto/roboto-v20-latin-ext_latin_cyrillic-ext_cyrillic-100.woff2',
                    '/design/roboto/roboto-v20-latin-ext_latin_cyrillic-ext_cyrillic-100italic.woff2',
                    '/design/roboto/roboto-v20-latin-ext_latin_cyrillic-ext_cyrillic-300.woff2',
                    '/design/roboto/roboto-v20-latin-ext_latin_cyrillic-ext_cyrillic-300italic.woff2',
                    '/design/roboto/roboto-v20-latin-ext_latin_cyrillic-ext_cyrillic-500.woff2',
                    '/design/roboto/roboto-v20-latin-ext_latin_cyrillic-ext_cyrillic-500italic.woff2',
                    '/design/roboto/roboto-v20-latin-ext_latin_cyrillic-ext_cyrillic-700.woff2',
                    '/design/roboto/roboto-v20-latin-ext_latin_cyrillic-ext_cyrillic-700italic.woff2',
                    '/design/roboto/roboto-v20-latin-ext_latin_cyrillic-ext_cyrillic-900.woff2',
                    '/design/roboto/roboto-v20-latin-ext_latin_cyrillic-ext_cyrillic-900italic.woff2',
                    '/design/roboto/roboto-v20-latin-ext_latin_cyrillic-ext_cyrillic-italic.woff2',
                    '/design/roboto/roboto-v20-latin-ext_latin_cyrillic-ext_cyrillic-regular.woff2',
                    '/design/roboto/stylesheet.min.css',

                    '/design/ajax-loader.gif',
                    '/design/alert.mp3',
                    '/design/application.svg',
                    '/design/arrow-down-01.svg',
                    '/design/arrow-menu-down-white.svg',
                    '/design/arrow-menu-down.svg',
                    '/design/arrow-menu-up.svg',
                    '/design/avatar-big-inverted.svg',
                    '/design/avatar-big.svg',
                    '/design/cetb_logo.svg',
                    '/design/check.svg',
                    '/design/close_blue.svg',
                    '/design/close_bold_gray.svg',
                    '/design/close_red.svg',
                    '/design/close_white.svg',
                    '/design/community.svg',
                    '/design/cross.svg',
                    '/design/edit_circle.svg',
                    '/design/edit.svg',
                    '/design/event_future.svg',
                    '/design/event_past.svg',
                    '/design/event_place.svg',
                    '/design/event_time.svg',
                    '/design/event.svg',
                    '/design/file-icon-1.svg',
                    '/design/file-icon-2.svg',
                    '/design/file-icon-3.svg',
                    '/design/file-icon-4.svg',
                    '/design/file-icon-5.svg',
                    '/design/file-icon-6.svg',
                    '/design/filter-icon.svg',
                    '/design/folder-icon-1.svg',
                    '/design/folder-icon-2.svg',
                    '/design/group-add-avatar.svg',
                    '/design/icon-discuss-number-filled.svg',
                    '/design/icon-discuss-number.svg',
                    '/design/icon-edit.svg',
                    '/design/input-info-b.svg',
                    '/design/ios-share.svg',
                    '/design/lightbulb-o.svg',
                    '/design/lightbulb.svg',
                    '/design/like.svg',
                    '/design/loader-tail-spin.svg',
                    '/design/loader-three-dots.svg',
                    '/design/logo.svg',
                    '/design/more.svg',
                    '/design/mute.png',
                    '/design/no_avatar_application.svg',
                    '/design/no_avatar_community.svg',
                    '/design/no_avatar_event.svg',
                    '/design/no_avatar_file.png',
                    '/design/no_avatar_folder.png',
                    '/design/no_avatar_link.png',
                    '/design/no_avatar_project.svg',
                    '/design/no_avatar_task.svg',
                    '/design/paperclip.svg',
                    '/design/payment_systems_logos.png',
                    '/design/people_circle_off.svg',
                    '/design/people_circle.svg',
                    '/design/plane.svg',
                    '/design/plus_circle_2.svg',
                    '/design/plus_circle_small.svg',
                    '/design/plus_circle.svg',
                    '/design/project-more-blue.svg',
                    '/design/project-more.svg',
                    '/design/project.svg',
                    '/design/qr_icon.svg',
                    '/design/qrcode_logo.png',
                    '/design/send.svg',
                    '/design/siren.mp3',
                    '/design/soc-fb.svg',
                    '/design/soc-tg.svg',
                    '/design/soc-vk.svg',
                    '/design/social_network_logo_box.png',
                    '/design/social_network_logo.png',
                    '/design/task_widget.svg',
                    '/design/task.svg',
                    '/design/user_menu_edit.svg',
                    '/design/vertical_dots.svg',
                    '/design/volume.png',

                    '/locale/EN/locale.json',
                    '/locale/ES/locale.json',
                    '/locale/RU/locale.json',

                    '/vendor/fraym/cmsvc/agreement.js',
                    '/vendor/fraym/cmsvc/application.js',
                    '/vendor/fraym/cmsvc/area.js',
                    '/vendor/fraym/cmsvc/article.js',
                    '/vendor/fraym/cmsvc/articles_edit.js',
                    '/vendor/fraym/cmsvc/bank_currency.js',
                    '/vendor/fraym/cmsvc/bank_rule.js',
                    '/vendor/fraym/cmsvc/bank_transaction.js',
                    '/vendor/fraym/cmsvc/banners_edit.js',
                    '/vendor/fraym/cmsvc/budget.js',
                    '/vendor/fraym/cmsvc/calendar.js',
                    '/vendor/fraym/cmsvc/calendar_event.js',
                    '/vendor/fraym/cmsvc/calendar_event_gallery.js',
                    '/vendor/fraym/cmsvc/calendar_event_group.js',
                    '/vendor/fraym/cmsvc/character.js',
                    '/vendor/fraym/cmsvc/community.js',
                    '/vendor/fraym/cmsvc/conversation.js',
                    '/vendor/fraym/cmsvc/csvimport.js',
                    '/vendor/fraym/cmsvc/document.js',
                    '/vendor/fraym/cmsvc/event.js',
                    '/vendor/fraym/cmsvc/eventlist.js',
                    '/vendor/fraym/cmsvc/exchange.js',
                    '/vendor/fraym/cmsvc/exchange_category_edit.js',
                    '/vendor/fraym/cmsvc/exchange_item_edit.js',
                    '/vendor/fraym/cmsvc/faq.js',
                    '/vendor/fraym/cmsvc/fbauth.js',
                    '/vendor/fraym/cmsvc/fee.js',
                    '/vendor/fraym/cmsvc/filterset.js',
                    '/vendor/fraym/cmsvc/gamemaster.js',
                    '/vendor/fraym/cmsvc/geoposition.js',
                    '/vendor/fraym/cmsvc/group.js',
                    '/vendor/fraym/cmsvc/help.js',
                    '/vendor/fraym/cmsvc/ingame.js',
                    '/vendor/fraym/cmsvc/ingame_bank_transaction.js',
                    '/vendor/fraym/cmsvc/login.js',
                    '/vendor/fraym/cmsvc/mobile.js',
                    '/vendor/fraym/cmsvc/myapplication.js',
                    '/vendor/fraym/cmsvc/news.js',
                    '/vendor/fraym/cmsvc/news_edit.js',
                    '/vendor/fraym/cmsvc/oferta.js',
                    '/vendor/fraym/cmsvc/org.js',
                    '/vendor/fraym/cmsvc/payment_type.js',
                    '/vendor/fraym/cmsvc/people.js',
                    '/vendor/fraym/cmsvc/photo.js',
                    '/vendor/fraym/cmsvc/plot.js',
                    '/vendor/fraym/cmsvc/portfolio.js',
                    '/vendor/fraym/cmsvc/privacy.js',
                    '/vendor/fraym/cmsvc/profile.js',
                    '/vendor/fraym/cmsvc/project.js',
                    '/vendor/fraym/cmsvc/publication.js',
                    '/vendor/fraym/cmsvc/publications_edit.js',
                    '/vendor/fraym/cmsvc/qrpg_code.js',
                    '/vendor/fraym/cmsvc/qrpg_generator.js',
                    '/vendor/fraym/cmsvc/qrpg_history.js',
                    '/vendor/fraym/cmsvc/qrpg_key.js',
                    '/vendor/fraym/cmsvc/register.js',
                    '/vendor/fraym/cmsvc/registration.js',
                    '/vendor/fraym/cmsvc/report.js',
                    '/vendor/fraym/cmsvc/roles.js',
                    '/vendor/fraym/cmsvc/rooms.js',
                    '/vendor/fraym/cmsvc/ruling.js',
                    '/vendor/fraym/cmsvc/ruling_edit.js',
                    '/vendor/fraym/cmsvc/ruling_item_edit.js',
                    '/vendor/fraym/cmsvc/ruling_question_edit.js',
                    '/vendor/fraym/cmsvc/ruling_tag_edit.js',
                    '/vendor/fraym/cmsvc/search.js',
                    '/vendor/fraym/cmsvc/setup.js',
                    '/vendor/fraym/cmsvc/start.js',
                    '/vendor/fraym/cmsvc/stats.js',
                    '/vendor/fraym/cmsvc/task.js',
                    '/vendor/fraym/cmsvc/tasklist.js',
                    '/vendor/fraym/cmsvc/transaction.js',
                    '/vendor/fraym/cmsvc/vkauth.js',
                    '/vendor/fraym/cmsvc/wall.js',
                    '/vendor/fraym/cmsvc/wall2.js',

                    '/vendor/fraym/cmsvc/agreement.css',
                    '/vendor/fraym/cmsvc/application.css',
                    '/vendor/fraym/cmsvc/area.css',
                    '/vendor/fraym/cmsvc/article.css',
                    '/vendor/fraym/cmsvc/articles_edit.css',
                    '/vendor/fraym/cmsvc/bank_currency.css',
                    '/vendor/fraym/cmsvc/bank_rule.css',
                    '/vendor/fraym/cmsvc/bank_transaction.css',
                    '/vendor/fraym/cmsvc/banners_edit.css',
                    '/vendor/fraym/cmsvc/budget.css',
                    '/vendor/fraym/cmsvc/calendar.css',
                    '/vendor/fraym/cmsvc/calendar_event.css',
                    '/vendor/fraym/cmsvc/calendar_event_gallery.css',
                    '/vendor/fraym/cmsvc/calendar_event_group.css',
                    '/vendor/fraym/cmsvc/character.css',
                    '/vendor/fraym/cmsvc/community.css',
                    '/vendor/fraym/cmsvc/conversation.css',
                    '/vendor/fraym/cmsvc/csvimport.css',
                    '/vendor/fraym/cmsvc/document.css',
                    '/vendor/fraym/cmsvc/event.css',
                    '/vendor/fraym/cmsvc/eventlist.css',
                    '/vendor/fraym/cmsvc/exchange.css',
                    '/vendor/fraym/cmsvc/exchange_category_edit.css',
                    '/vendor/fraym/cmsvc/exchange_item_edit.css',
                    '/vendor/fraym/cmsvc/faq.css',
                    '/vendor/fraym/cmsvc/fbauth.css',
                    '/vendor/fraym/cmsvc/fee.css',
                    '/vendor/fraym/cmsvc/filterset.css',
                    '/vendor/fraym/cmsvc/gamemaster.css',
                    '/vendor/fraym/cmsvc/geoposition.css',
                    '/vendor/fraym/cmsvc/group.css',
                    '/vendor/fraym/cmsvc/help.css',
                    '/vendor/fraym/cmsvc/ingame.css',
                    '/vendor/fraym/cmsvc/ingame_bank_transaction.css',
                    '/vendor/fraym/cmsvc/login.css',
                    '/vendor/fraym/cmsvc/mobile.css',
                    '/vendor/fraym/cmsvc/myapplication.css',
                    '/vendor/fraym/cmsvc/news.css',
                    '/vendor/fraym/cmsvc/news_edit.css',
                    '/vendor/fraym/cmsvc/oferta.css',
                    '/vendor/fraym/cmsvc/org.css',
                    '/vendor/fraym/cmsvc/payment_type.css',
                    '/vendor/fraym/cmsvc/people.css',
                    '/vendor/fraym/cmsvc/photo.css',
                    '/vendor/fraym/cmsvc/plot.css',
                    '/vendor/fraym/cmsvc/portfolio.css',
                    '/vendor/fraym/cmsvc/privacy.css',
                    '/vendor/fraym/cmsvc/profile.css',
                    '/vendor/fraym/cmsvc/project.css',
                    '/vendor/fraym/cmsvc/publication.css',
                    '/vendor/fraym/cmsvc/publications_edit.css',
                    '/vendor/fraym/cmsvc/qrpg_code.css',
                    '/vendor/fraym/cmsvc/qrpg_generator.css',
                    '/vendor/fraym/cmsvc/qrpg_history.css',
                    '/vendor/fraym/cmsvc/qrpg_key.css',
                    '/vendor/fraym/cmsvc/register.css',
                    '/vendor/fraym/cmsvc/registration.css',
                    '/vendor/fraym/cmsvc/report.css',
                    '/vendor/fraym/cmsvc/roles.css',
                    '/vendor/fraym/cmsvc/rooms.css',
                    '/vendor/fraym/cmsvc/ruling.css',
                    '/vendor/fraym/cmsvc/ruling_edit.css',
                    '/vendor/fraym/cmsvc/ruling_item_edit.css',
                    '/vendor/fraym/cmsvc/ruling_question_edit.css',
                    '/vendor/fraym/cmsvc/ruling_tag_edit.css',
                    '/vendor/fraym/cmsvc/search.css',
                    '/vendor/fraym/cmsvc/setup.css',
                    '/vendor/fraym/cmsvc/start.css',
                    '/vendor/fraym/cmsvc/stats.css',
                    '/vendor/fraym/cmsvc/task.css',
                    '/vendor/fraym/cmsvc/tasklist.css',
                    '/vendor/fraym/cmsvc/transaction.css',
                    '/vendor/fraym/cmsvc/vkauth.css',
                    '/vendor/fraym/cmsvc/wall.css',
                    '/vendor/fraym/cmsvc/wall2.css',

                    '/vendor/diff/diff_match_patch.js',
                    '/vendor/nlgraph/nlgraph.min.js',
                    '/vendor/openlayers/ol.min.css',
                    '/vendor/openlayers/ol.min.js',
                    '/vendor/pwacompat/pwacompat.min.js',
                    '/vendor/qrcodereader/qr_packed.min.js',

                    '/vendor/fraym/js/global.min.js',
                    '/vendor/fraym/css/global.min.css',

                    '/vendor/fraym/locale/EN/locale.json',
                    '/vendor/fraym/locale/ES/locale.json',
                    '/vendor/fraym/locale/RU/locale.json',

                    '/vendor/fraym/js/audioplayer/audioplayer.min.css',
                    '/vendor/fraym/js/audioplayer/audioplayer.min.js',
                    '/vendor/fraym/js/autocomplete/autocomplete.min.css',
                    '/vendor/fraym/js/autocomplete/autocomplete.min.js',
                    '/vendor/fraym/js/dragdrop/dragdrop.min.js',
                    '/vendor/fraym/js/filepond/locale/en-US.min.js',
                    '/vendor/fraym/js/filepond/locale/es-ES.min.js',
                    '/vendor/fraym/js/filepond/locale/ru-RU.min.js',
                    '/vendor/fraym/js/filepond/filepond.min.css',
                    '/vendor/fraym/js/filepond/filepond.min.js',
                    '/vendor/fraym/js/modal/modal.min.js',
                    '/vendor/fraym/js/noty/noty.min.css',
                    '/vendor/fraym/js/noty/noty.min.js',
                    '/vendor/fraym/js/quill/quill.min.js',
                    '/vendor/fraym/js/quill/quill.snow.min.css',
                    '/vendor/fraym/js/styler/styler.min.js',
                    '/vendor/fraym/js/tabs/tabs.min.js',

                    '/vendor/fraym/design/sbi/arrow-move.svg',
                    '/vendor/fraym/design/sbi/check.svg',
                    '/vendor/fraym/design/sbi/crosshairs.svg',
                    '/vendor/fraym/design/sbi/exchange.svg',
                    '/vendor/fraym/design/sbi/eye-striked.svg',
                    '/vendor/fraym/design/sbi/eye.svg',
                    '/vendor/fraym/design/sbi/file-filled.svg',
                    '/vendor/fraym/design/sbi/file.svg',
                    '/vendor/fraym/design/sbi/folder.svg',
                    '/vendor/fraym/design/sbi/hand-stop.svg',
                    '/vendor/fraym/design/sbi/info.svg',
                    '/vendor/fraym/design/sbi/key.svg',
                    '/vendor/fraym/design/sbi/link.svg',
                    '/vendor/fraym/design/sbi/list.svg',
                    '/vendor/fraym/design/sbi/minus.svg',
                    '/vendor/fraym/design/sbi/pencil.svg',
                    '/vendor/fraym/design/sbi/plus.svg',
                    '/vendor/fraym/design/sbi/question.svg',
                    '/vendor/fraym/design/sbi/refresh.svg',
                    '/vendor/fraym/design/sbi/search.svg',
                    '/vendor/fraym/design/sbi/star.svg',
                    '/vendor/fraym/design/sbi/stop.svg',
                    '/vendor/fraym/design/sbi/time.svg',
                    '/vendor/fraym/design/sbi/times.svg',
                    '/vendor/fraym/design/sbi/user.svg',
                    '/vendor/fraym/design/sbi/users.svg',
                    '/vendor/fraym/design/arrow-asterix.svg',
                    '/vendor/fraym/design/arrow-double-left.svg',
                    '/vendor/fraym/design/arrow-double-right.svg',
                    '/vendor/fraym/design/arrow-down.svg',
                    '/vendor/fraym/design/arrow-left.svg',
                    '/vendor/fraym/design/arrow-right.svg',
                    '/vendor/fraym/design/arrow-up.svg',
                    '/vendor/fraym/design/asterix.svg',
                    '/vendor/fraym/design/close_bold.svg',
                    '/vendor/fraym/design/close.svg',
                    '/vendor/fraym/design/icon-trash.svg',
                    '/vendor/fraym/design/input-checked.svg',
                    '/vendor/fraym/design/input-error.svg',
                    '/vendor/fraym/design/input-info.svg',
                    '/vendor/fraym/design/three-dots.svg',
                ];

                caches.open(cacheName).then(function (cache) {
                    var cachePromises = urlsToPrefetch.map(function (urlToPrefetch) {
                        var url = new URL(urlToPrefetch, location.href);
                        url.search += (url.search ? '&' : '?') + 'cache-bust=' + cacheName;

                        var request = new Request(url, { cache: "reload", mode: 'no-cors' });
                        return fetch(request).then(function (response) {
                            if (response.status >= 400) {
                                throw new Error('request for ' + urlToPrefetch +
                                    ' failed with status ' + response.statusText);
                            }

                            return cache.put(urlToPrefetch, response);
                        }).catch(function (error) {
                            console.error('Not caching ' + urlToPrefetch + ' due to ' + error);
                        });
                    });

                    return Promise.all(cachePromises).then(function () {
                        self.skipWaiting();
                    });
                }).catch(function (error) {
                    console.error('Pre-fetching failed:', error);
                })
            }
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (cacheNames) {
            return cacheNames.filter(function (checkCacheName) {
                return checkCacheName != cacheName;
            });
        }).then(function (cachesToDelete) {
            return Promise.all(cachesToDelete.map(function (cacheToDelete) {
                return caches.delete(cacheToDelete);
            }));
        }).then(function () {
            self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function (event) {
    if (typeof event.request == 'undefined' || (event.request.cache === 'only-if-cached' && event.request.mode !== 'same-origin')) {
        return;
    }

    if (event.request.headers.has('range')) {
        return;
    }

    const extension = get_url_extension(event.request.url);

    if (event.request.url.startsWith(self.location.origin)) {
        const ignoredExtensions = [
            'mp3',
            'js',
            'css',
            'png',
            'jpg',
            'jpeg',
            'svg',
            'woff',
            'woff2',
            'eot',
        ];

        if (ignoredExtensions.includes(extension) || event.request.url.match(/roles_image\.php/)) {
            event.respondWith(
                caches.match(event.request).then(function (cachedResponse) {
                    if (cachedResponse) {
                        return cachedResponse;
                    }

                    return caches.open(cacheName).then(function (cache) {
                        return fetch(event.request).then(function (response) {
                            // Put a copy of the response in the runtime cache.
                            return cache.put(event.request, response.clone()).then(function () {
                                return response;
                            });
                        });
                    });
                })
            );
        }
    }

    if (event.request.mode === "navigate") {
        event.respondWith(
            (async () => {
                try {
                    return await fetch(event.request);
                } catch (error) {
                    const cache = await caches.open(cacheName);
                    return await cache.match('/offline.html');
                }
            })()
        );
    }
});

function get_url_extension(url) {
    return url.split(/[#?]/)[0].split('.').pop().trim();
}