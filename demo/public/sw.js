/* MEBRANDING service worker: houdt de app-schil offline beschikbaar. */
const VERSIE = 'mb-4a03ff446f';
const SCHIL = ['./', './index.html', './manifest.webmanifest',
  './icons/icoon-192.png', './icons/icoon-512.png', './icons/icoon-maskable-192.png', './icons/icoon-maskable-512.png',
  './icons/apple-touch-icon-180.png', './icons/mebranding-icoon.svg', './icons/favicon.ico'];
const FONTS = 'mb-fonts';

self.addEventListener('install', e => {
  e.waitUntil(caches.open(VERSIE).then(c => c.addAll(SCHIL)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k !== VERSIE && k !== FONTS).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});

const OFFLINE = `<!DOCTYPE html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="theme-color" content="#1F1D33"><title>MEBRANDING, offline</title>
<style>html,body{margin:0;height:100%;background:#1F1D33;color:#fff;font-family:system-ui,-apple-system,'Segoe UI',Arial,sans-serif}
main{min-height:100%;display:grid;place-items:center;padding:calc(24px + env(safe-area-inset-top)) 24px calc(24px + env(safe-area-inset-bottom));box-sizing:border-box;text-align:center}
.b{width:44px;height:5px;background:linear-gradient(90deg,#D9303E,#614ED9);margin:0 auto 18px}
h1{font-weight:900;text-transform:uppercase;font-size:28px;margin:0 0 10px;letter-spacing:-.01em}p{color:#C9C4E6;margin:0 0 22px;max-width:36ch;font-size:16px;line-height:1.5}
button{font:inherit;font-weight:700;background:#614ED9;color:#fff;border:0;border-radius:3px;padding:12px 20px;min-height:48px;cursor:pointer}</style></head>
<body><main><div><div class="b"></div><h1>Je bent offline</h1><p>MEBRANDING kan nu niet laden. Controleer je verbinding en probeer het opnieuw.</p><button onclick="location.reload()">Opnieuw proberen</button></div></main></body></html>`;

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  // Lettertypen: eerst uit de cache, op de achtergrond verversen
  if (url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com') {
    e.respondWith(caches.open(FONTS).then(async c => {
      const hit = await c.match(req);
      const net = fetch(req).then(r => { if (r && (r.ok || r.type === 'opaque')) c.put(req, r.clone()); return r; }).catch(() => hit);
      return hit || net;
    }));
    return;
  }
  if (url.origin !== location.origin) return;
  // Pagina: eerst het netwerk (nieuwste versie), anders de cache, anders een nette offlinepagina
  if (req.mode === 'navigate') {
    // het ingebedde prototype (schermen.html) apart bewaren, zodat het nooit de app-pagina overschrijft
    const key = /schermen\.html$/.test(url.pathname) ? './schermen.html' : './index.html';
    e.respondWith(fetch(req).then(r => { const k = r.clone(); caches.open(VERSIE).then(c => c.put(key, k)); return r; })
      .catch(async () => (await caches.match(key)) || (key === './index.html' && await caches.match('./')) || new Response(OFFLINE, { headers: { 'Content-Type': 'text/html; charset=utf-8' } })));
    return;
  }
  // Overige bestanden van de app: cache eerst
  e.respondWith(caches.match(req).then(hit => hit || fetch(req).then(r => { if (r.ok) { const k = r.clone(); caches.open(VERSIE).then(c => c.put(req, k)); } return r; })));
});
