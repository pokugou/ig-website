// 通知を受け取って表示するだけの Service Worker（ページのキャッシュはしない）
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });

self.addEventListener('push', function (e) {
  var d = {};
  try { d = e.data ? e.data.json() : {}; } catch (x) { d = { title: 'IG TODO', body: e.data ? e.data.text() : '' }; }
  e.waitUntil(self.registration.showNotification(d.title || 'IG TODO', {
    body: d.body || '',
    tag: d.tag || undefined,
    icon: 'icon-192.png',
    badge: 'favicon.png',
    data: { url: d.url || 'todo.html' },
  }));
});

self.addEventListener('notificationclick', function (e) {
  e.notification.close();
  var url = new URL((e.notification.data && e.notification.data.url) || 'todo.html', self.registration.scope).href;
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
    for (var i = 0; i < list.length; i++) { if (list[i].url.indexOf('todo.html') >= 0 && 'focus' in list[i]) return list[i].focus(); }
    return self.clients.openWindow(url);
  }));
});
