/*
 * Service worker van de MEBRANDING-webapp: toont pushmeldingen en opent bij een tik de juiste pagina.
 * Geen offline-cache: het portaal is altijd live, zodat niemand oude gegevens ziet.
 */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

// Nodig om als app te kunnen installeren; laat alle verzoeken gewoon door naar het netwerk.
self.addEventListener('fetch', () => {});

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = { body: event.data ? event.data.text() : '' };
    }

    event.waitUntil(
        self.registration.showNotification(data.title || 'MEBRANDING', {
            body: data.body || '',
            icon: '/brand/app-icoon-192.png',
            badge: '/brand/app-badge-96.png',
            tag: data.tag,
            data: { url: data.url || self.registration.scope },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || self.registration.scope;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((vensters) => {
            for (const venster of vensters) {
                if ('focus' in venster && venster.url.startsWith(self.registration.scope)) {
                    venster.navigate(url);
                    return venster.focus();
                }
            }
            return self.clients.openWindow(url);
        }),
    );
});
