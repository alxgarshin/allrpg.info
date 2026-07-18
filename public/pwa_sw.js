// cacheName for cache versioning
const cacheName = '20250905_0800';

self.addEventListener('install', function (event) {
    // Всегда активировать новый SW немедленно, не ждать закрытия вкладок
    self.skipWaiting();

    event.waitUntil(
        caches.open(cacheName).then(function (cache) {
            var urlsToPrefetch = [
                '/offline.html',

                '/js/global.min.js',
                '/css/global.min.css',

                '/js/roles.min.js',

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

                '/locale/EN/locale.json',
                '/locale/ES/locale.json',
                '/locale/RU/locale.json',

                '/vendor/fraym/cmsvc/start.js',
                '/vendor/fraym/cmsvc/start.css',

                '/vendor/fraym/js/global.min.js',
                '/vendor/fraym/css/global.min.css',

                '/vendor/fraym/locale/EN/locale.json',
                '/vendor/fraym/locale/ES/locale.json',
                '/vendor/fraym/locale/RU/locale.json',

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

            return Promise.all(cachePromises);
        }).catch(function (error) {
            console.error('Pre-fetching failed:', error);
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
            'json',
        ];

        if (ignoredExtensions.includes(extension)) {
            event.respondWith(
                caches.match(event.request).then(function (cachedResponse) {
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    return fetch(event.request).then(function (networkResponse) {
                        if (networkResponse && networkResponse.status === 200) {
                            var responseClone = networkResponse.clone();
                            caches.open(cacheName).then(function (cache) {
                                cache.put(event.request, responseClone);
                            });
                        }
                        return networkResponse;
                    });
                })
            );
        }
    }

    if (event.request.mode === "navigate") {
        event.respondWith(
            (async () => {
                try {
                    const response = await fetch(event.request);
                    /** 401 при истёкшей сессии — редирект на логин (критично для PWA, где нет адресной строки) */
                    if (response.status === 401) {
                        return Response.redirect('/login/', 302);
                    }
                    return response;
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