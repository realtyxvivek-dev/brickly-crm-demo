(function () {
    'use strict';

    var userMeta = document.querySelector('meta[name="notification-user-id"]');
    var userId = userMeta ? String(userMeta.content || '') : '';
    var tokenKey = 'crm_current_fcm_token';
    var userKey = 'crm_notification_user_id';

    function authHeaders() {
        var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
        var tokenMeta = document.querySelector('meta[name="api-token"]');
        var token = tokenMeta && tokenMeta.content ? tokenMeta.content : '';
        if (!token) {
            try { token = localStorage.getItem('telecaller_token') || localStorage.getItem('auth_token') || ''; } catch (e) {}
        }
        if (token) headers.Authorization = 'Bearer ' + token;
        return headers;
    }

    function tellWorker(type) {
        if (!('serviceWorker' in navigator)) return Promise.resolve();
        return navigator.serviceWorker.ready.then(function (registration) {
            var worker = registration.active || registration.waiting || registration.installing;
            if (worker) worker.postMessage({ type: type, userId: userId });
        }).catch(function () {});
    }

    function claimFcm(fcmToken) {
        if (!fcmToken || !userId) return Promise.resolve(false);
        return fetch('/api/fcm-subscription', {
            method: 'POST',
            headers: authHeaders(),
            body: JSON.stringify({ fcm_token: fcmToken, device_type: 'web' })
        }).then(function (response) {
            if (!response.ok) return false;
            try {
                localStorage.setItem(tokenKey, fcmToken);
                localStorage.setItem(userKey, userId);
            } catch (e) {}
            tellWorker('SET_ACTIVE_USER');
            return true;
        }).catch(function () { return false; });
    }

    function isForCurrentUser(payload) {
        var recipient = payload && payload.data ? payload.data.recipient_user_id : '';
        return !!userId && String(recipient || '') === userId;
    }

    async function unregister() {
        var requests = [];
        var fcmToken = '';
        try { fcmToken = localStorage.getItem(tokenKey) || ''; } catch (e) {}

        if (fcmToken) {
            requests.push(fetch('/api/fcm-subscription', {
                method: 'DELETE',
                headers: authHeaders(),
                body: JSON.stringify({ fcm_token: fcmToken })
            }).catch(function () {}));
        }

        if ('serviceWorker' in navigator) {
            requests.push(navigator.serviceWorker.ready.then(function (registration) {
                return registration.pushManager.getSubscription();
            }).then(function (subscription) {
                if (!subscription) return;
                return fetch('/api/push-subscription', {
                    method: 'DELETE',
                    headers: authHeaders(),
                    body: JSON.stringify({ endpoint: subscription.endpoint })
                });
            }).catch(function () {}));
        }

        await Promise.allSettled(requests);
        try {
            localStorage.removeItem(tokenKey);
            localStorage.removeItem(userKey);
        } catch (e) {}
        await tellWorker('CLEAR_ACTIVE_USER');
    }

    function interceptWebLogout() {
        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!form || form.dataset.deviceLogoutDone === '1') return;
            var action = String(form.getAttribute('action') || '');
            if (!/\/logout(?:$|\?)/.test(action)) return;
            event.preventDefault();
            unregister().finally(function () {
                form.dataset.deviceLogoutDone = '1';
                form.submit();
            });
        }, true);
    }

    window.NotificationDeviceOwnership = {
        claimFcm: claimFcm,
        isForCurrentUser: isForCurrentUser,
        unregister: unregister,
        userId: userId
    };

    if (userId) {
        tellWorker('SET_ACTIVE_USER');
        interceptWebLogout();
    }
})();
