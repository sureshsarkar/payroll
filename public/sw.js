/**
 * MBS service worker — handles browser push notifications.
 *
 * Lifecycle:
 *  - install / activate: claim clients immediately so updates take effect
 *  - push: show notification with title + body + click URL
 *  - notificationclick: focus an existing tab matching the URL or open a new one
 *
 * Registered by /js/push.js on the frontend.
 */

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    if (!event.data) return;
    let payload;
    try {
        payload = event.data.json();
    } catch (e) {
        payload = { title: 'New notification', body: event.data.text() };
    }

    const title = payload.title || 'New notification';
    const options = {
        body: payload.body || '',
        icon: payload.icon || '/uploads/website-images/frontend-avatar.png',
        badge: payload.badge || '/favicon.ico',
        vibrate: payload.vibrate || [200, 100, 200],
        data: payload.data || {},
        tag: payload.tag || undefined,
        renotify: !!payload.renotify,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil((async () => {
        const allClients = await self.clients.matchAll({
            type: 'window',
            includeUncontrolled: true,
        });
        // If the target page is already open in a tab, focus it.
        for (const client of allClients) {
            if (client.url.includes(targetUrl)) {
                return client.focus();
            }
        }
        // Otherwise open a new tab.
        if (self.clients.openWindow) {
            return self.clients.openWindow(targetUrl);
        }
    })());
});
