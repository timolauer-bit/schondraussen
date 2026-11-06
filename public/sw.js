// Minimaler Service Worker: macht die Seite installierbar. Es werden keine Nutzerdaten oder API-Antworten zwischengespeichert.
const C = 'sd-offline-v1';
self.addEventListener('install', e => { e.waitUntil(caches.open(C).then(c => c.put('/offline.html', new Response(
  '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline</title><body style="font:16px system-ui;margin:12vh 24px;text-align:center"><h1>Keine Verbindung</h1><p>Schondraussen.de braucht Internet. Bitte versuche es gleich noch einmal.</p>',
  { headers: { 'Content-Type': 'text/html; charset=utf-8' } }))).then(() => self.skipWaiting())); });
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));
self.addEventListener('fetch', e => {
  if (e.request.mode !== 'navigate') return; // alles andere geht direkt ans Netz
  e.respondWith(fetch(e.request).catch(() => caches.match('/offline.html')));
});
