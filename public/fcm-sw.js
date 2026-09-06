try {
    importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js');
    importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-messaging-compat.js');
    firebase.initializeApp({"apiKey":"REPLACE_WITH_FIREBASE_WEB_API_KEY","authDomain":"REPLACE_WITH_FIREBASE_AUTH_DOMAIN","projectId":"REPLACE_WITH_FIREBASE_PROJECT_ID","storageBucket":"REPLACE_WITH_FIREBASE_STORAGE_BUCKET","messagingSenderId":"REPLACE_WITH_FIREBASE_SENDER_ID","appId":"REPLACE_WITH_FIREBASE_APP_ID"});
    var messaging = firebase.messaging();
    messaging.onBackgroundMessage(function(payload) {
        var data = payload.data || {};
        return isPayloadForActiveUser(data).then(function(allowed) {
            if (!allowed) return;
            var notification = payload.notification || {};
            var title = notification.title || data.title || 'New Notification';
            return self.registration.showNotification(title, buildNotificationOptions(data, notification.body || data.body || ''));
        });
    });
} catch(e) {
    console.warn('Firebase SW init skipped:', e);
}

function buildNotificationOptions(data, body) {
    var primaryLabel = data.primary_action_label || '';
    var primaryUrl = data.primary_action_url || data.url || data.click_action || '/';
    var secondaryLabel = data.secondary_action_label || '';
    var secondaryUrl = data.secondary_action_url || '';
    var actions = [];

    if (primaryLabel && primaryUrl) actions.push({ action: 'primary', title: primaryLabel });
    if (secondaryLabel && secondaryUrl && (data.secondary_action_method || 'GET') === 'GET') {
        actions.push({ action: 'secondary', title: secondaryLabel });
    }

    return {
        body: body || '',
        icon: '/icon-192.png',
        badge: '/icon-192.png',
        tag: data.tag || 'crm-notification',
        requireInteraction: true,
        actions: actions.slice(0, 2),
        data: {
            recipient_user_id: data.recipient_user_id || '',
            url: data.url || data.click_action || primaryUrl || '/',
            primary_action_url: primaryUrl,
            secondary_action_url: secondaryUrl,
            sound_url: data.sound_url || '',
            sound_key: data.sound_key || ''
        }
    };
}

var OWNERSHIP_DB = 'crm-notification-ownership';
function setActiveUser(userId) {
    return new Promise(function(resolve) {
        var request = indexedDB.open(OWNERSHIP_DB, 1);
        request.onupgradeneeded = function() {
            if (!request.result.objectStoreNames.contains('state')) request.result.createObjectStore('state');
        };
        request.onerror = function() { resolve(); };
        request.onsuccess = function() {
            var tx = request.result.transaction('state', 'readwrite');
            var store = tx.objectStore('state');
            if (userId) store.put(String(userId), 'active_user_id');
            else store.delete('active_user_id');
            tx.oncomplete = function() { resolve(); };
            tx.onerror = function() { resolve(); };
        };
    });
}

function getActiveUser() {
    return new Promise(function(resolve) {
        var request = indexedDB.open(OWNERSHIP_DB, 1);
        request.onupgradeneeded = function() {
            if (!request.result.objectStoreNames.contains('state')) request.result.createObjectStore('state');
        };
        request.onerror = function() { resolve(''); };
        request.onsuccess = function() {
            var tx = request.result.transaction('state', 'readonly');
            var get = tx.objectStore('state').get('active_user_id');
            get.onsuccess = function() { resolve(String(get.result || '')); };
            get.onerror = function() { resolve(''); };
        };
    });
}

function isPayloadForActiveUser(data) {
    var recipient = String((data && data.recipient_user_id) || '');
    if (!recipient) return Promise.resolve(false);
    return getActiveUser().then(function(activeUser) { return !!activeUser && activeUser === recipient; });
}

self.addEventListener('message', function(event) {
    var message = event.data || {};
    if (message.type === 'SET_ACTIVE_USER') event.waitUntil(setActiveUser(message.userId));
    if (message.type === 'CLEAR_ACTIVE_USER') event.waitUntil(setActiveUser(''));
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    var data = event.notification.data || {};
    var url = event.action === 'secondary'
        ? (data.secondary_action_url || data.url || '/')
        : (event.action === 'primary' ? (data.primary_action_url || data.url || '/') : (data.url || '/'));
    event.waitUntil(isPayloadForActiveUser(data).then(function(allowed) {
        if (!allowed) return;
        return clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
            for (var i = 0; i < clientList.length; i++) {
                if (clientList[i].url.indexOf(url) !== -1 && 'focus' in clientList[i]) return clientList[i].focus();
            }
            if (clients.openWindow) return clients.openWindow(url);
        });
    }));
});
