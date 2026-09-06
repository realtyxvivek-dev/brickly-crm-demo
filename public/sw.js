// Service Worker for Real Estate CRM
const CACHE_NAME = 'real-estate-crm-v6-manager-live';
const urlsToCache = [
  '/',
  '/login',
  '/install-app',
  '/favicon.ico',
  '/manifest.json',
  '/icon-192.png',
  '/icon-512.png'
];

const OWNERSHIP_DB = 'crm-notification-ownership';
function setActiveUser(userId) {
  return new Promise((resolve) => {
    const request = indexedDB.open(OWNERSHIP_DB, 1);
    request.onupgradeneeded = () => {
      if (!request.result.objectStoreNames.contains('state')) request.result.createObjectStore('state');
    };
    request.onerror = () => resolve();
    request.onsuccess = () => {
      const tx = request.result.transaction('state', 'readwrite');
      const store = tx.objectStore('state');
      if (userId) store.put(String(userId), 'active_user_id');
      else store.delete('active_user_id');
      tx.oncomplete = () => resolve();
      tx.onerror = () => resolve();
    };
  });
}

function getActiveUser() {
  return new Promise((resolve) => {
    const request = indexedDB.open(OWNERSHIP_DB, 1);
    request.onupgradeneeded = () => {
      if (!request.result.objectStoreNames.contains('state')) request.result.createObjectStore('state');
    };
    request.onerror = () => resolve('');
    request.onsuccess = () => {
      const tx = request.result.transaction('state', 'readonly');
      const get = tx.objectStore('state').get('active_user_id');
      get.onsuccess = () => resolve(String(get.result || ''));
      get.onerror = () => resolve('');
    };
  });
}

function isPayloadForActiveUser(data) {
  const recipient = String((data && data.recipient_user_id) || '');
  if (!recipient) return Promise.resolve(false);
  return getActiveUser().then((activeUser) => !!activeUser && activeUser === recipient);
}

self.addEventListener('message', (event) => {
  const message = event.data || {};
  if (message.type === 'SET_ACTIVE_USER') event.waitUntil(setActiveUser(message.userId));
  if (message.type === 'CLEAR_ACTIVE_USER') event.waitUntil(setActiveUser(''));
});

// Install event - cache resources
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('Service Worker: Cache opened');
        // Cache resources one by one to handle failures gracefully
        return Promise.allSettled(
          urlsToCache.map(url => {
            return fetch(url)
              .then(response => {
                if (response.ok) {
                  return cache.put(url, response);
                }
              })
              .catch(error => {
                console.warn(`Service Worker: Failed to cache ${url}:`, error);
              });
          })
        );
      })
      .then(() => {
        console.log('Service Worker: Installation complete');
      })
      .catch((error) => {
        console.error('Service Worker: Cache failed', error);
      })
  );
  self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.log('Service Worker: Deleting old cache', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  return self.clients.claim();
});

// Push notification - show notification when app is in background (Android PWA)
self.addEventListener('push', function(event) {
  if (!event.data) return;
  var data = {};
  try {
    data = event.data.json();
  } catch (e) {
    data = { title: 'Brickly', body: event.data.text() || 'New update' };
  }
  var notificationData = data.data || data;
  var title = data.title || notificationData.title || 'Brickly';
  var options = {
    body: data.body || data.message || 'New notification',
    icon: '/icon-192.png',
    badge: '/icon-192.png',
    tag: data.tag || 'crm-notification',
    data: { url: data.url || notificationData.url || '/', sound_url: data.sound_url || '', sound_key: data.sound_key || '', ...notificationData },
    requireInteraction: !!data.requireInteraction
  };
  event.waitUntil(isPayloadForActiveUser(notificationData).then((allowed) => {
    if (!allowed) return;
    return self.registration.showNotification(title, options);
  }));
});

// Click on notification - open app to URL
self.addEventListener('notificationclick', function(event) {
  event.notification.close();
  var url = event.notification.data && event.notification.data.url ? event.notification.data.url : '/';
  event.waitUntil(isPayloadForActiveUser(event.notification.data || {}).then(function(allowed) {
    if (!allowed) return;
    return self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
      for (var i = 0; i < clientList.length; i++) {
        if (clientList[i].url && 'focus' in clientList[i]) {
          clientList[i].navigate(url);
          return clientList[i].focus();
        }
      }
      if (self.clients.openWindow) return self.clients.openWindow(url);
    });
  }));
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', (event) => {
  const offlineFallback = () => new Response('Offline', {
    status: 503,
    statusText: 'Offline',
    headers: { 'Content-Type': 'text/plain' }
  });

  // Only handle GET requests
  if (event.request.method !== 'GET') {
    return;
  }

  // Skip cross-origin requests
  if (!event.request.url.startsWith(self.location.origin)) {
    return;
  }

  const requestUrl = new URL(event.request.url);
  const isApiRequest = requestUrl.pathname.startsWith('/api/');
  const isAuthRequest = requestUrl.pathname.includes('/login') ||
    requestUrl.pathname.includes('/logout') ||
    requestUrl.pathname.includes('/fcm-subscription') ||
    requestUrl.pathname.includes('/push-subscription');
  const isAppDocumentRequest =
    event.request.destination === 'document' &&
    !['/', '/login', '/install-app'].includes(requestUrl.pathname);

  if (isApiRequest || isAuthRequest) {
    event.respondWith(
      fetch(event.request).catch(() => offlineFallback())
    );
    return;
  }

  if (isAppDocumentRequest) {
    event.respondWith(
      fetch(event.request).catch(() => offlineFallback())
    );
    return;
  }

  // Skip caching for HTML pages (always fetch fresh)
  if (event.request.destination === 'document' || 
      event.request.headers.get('accept').includes('text/html')) {
    event.respondWith(
      fetch(event.request).catch(() => {
        // Fallback to cache only if network fails
        return caches.match(event.request).then((cached) => cached || offlineFallback());
      })
    );
    return;
  }

  const isSafeStaticAsset =
    requestUrl.pathname.startsWith('/build/') ||
    requestUrl.pathname.startsWith('/images/') ||
    requestUrl.pathname.startsWith('/icons/') ||
    requestUrl.pathname === '/favicon.ico' ||
    requestUrl.pathname === '/manifest.json' ||
    /^\/icon-\d+\.png$/.test(requestUrl.pathname);

  if (!isSafeStaticAsset) {
    event.respondWith(
      fetch(event.request).catch(() => caches.match(event.request).then((cached) => cached || offlineFallback()))
    );
    return;
  }

  event.respondWith(
    caches.match(event.request)
      .then((response) => {
        if (response) {
          return response;
        }
        return fetch(event.request)
          .then((networkResponse) => {
            if (networkResponse && networkResponse.status === 200) {
              const responseToCache = networkResponse.clone();
              caches.open(CACHE_NAME).then((cache) => {
                cache.put(event.request, responseToCache);
              }).catch(() => {
                // Ignore cache errors
              });
            }
            return networkResponse;
          });
      })
      .catch(() => fetch(event.request))
      .catch(() => offlineFallback())
  );
});
