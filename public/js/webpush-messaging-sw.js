// Получаем пуш и показываем уведомление
self.addEventListener('push', (event) => {
    const data = event.data ? event.data.json() : {};
    const title = data.title || 'allrpg.info';
    const options = {
        body: data.body,
        icon: data.icon || '/favicons/google-splash-icon-384x384.png',
        badge: data.badge || null,
        data: { url: data.url || '/' }
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

// Обрабатываем клик по уведомлению
self.addEventListener('notificationclick', (event) => {
    const url = event.notification.data?.url || '/';

    event.notification.close();
    event.waitUntil(clients.openWindow(url));
});