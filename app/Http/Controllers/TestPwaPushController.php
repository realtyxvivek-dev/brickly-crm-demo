<?php

namespace App\Http\Controllers;

use App\Jobs\SendWebPushNotificationJob;
use App\Jobs\SendFcmNotificationJob;
use App\Models\FcmToken;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class TestPwaPushController extends Controller
{
    public function fcmDirectSend(Request $request)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);
        $user = \App\Models\User::findOrFail($request->user_id);
        $tokens = FcmToken::where('user_id', $user->id)->pluck('fcm_token')->toArray();

        if (empty($tokens)) {
            return back()->with('error', "No FCM tokens for {$user->name} (user_id={$user->id}). Total tokens in DB: " . FcmToken::count());
        }

        $credentialsPath = config('firebase.credentials');
        if (!file_exists($credentialsPath)) {
            return back()->with('error', "Service account file not found: {$credentialsPath}");
        }

        $results = [];
        try {
            $factory = (new \Kreait\Firebase\Factory)->withServiceAccount($credentialsPath);
            $messaging = $factory->createMessaging();

            $notification = \Kreait\Firebase\Messaging\Notification::create(
                'FCM Direct Test',
                'This is a direct FCM test notification at ' . now()->format('H:i:s')
            );

            $data = [
                'title' => 'FCM Direct Test',
                'body' => 'Direct test at ' . now()->format('H:i:s'),
                'url' => url('/'),
                'tag' => 'fcm-direct-test',
            ];

            foreach ($tokens as $i => $token) {
                try {
                    $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $token)
                        ->withNotification($notification)
                        ->withData($data);

                    $resp = $messaging->send($message);
                    $results[] = "Token #" . ($i + 1) . " (" . substr($token, 0, 20) . "...): SENT OK — response: " . json_encode($resp);
                } catch (\Exception $e) {
                    $results[] = "Token #" . ($i + 1) . " (" . substr($token, 0, 20) . "...): FAILED — " . get_class($e) . ": " . $e->getMessage();
                }
            }
        } catch (\Exception $e) {
            return back()->with('error', "Firebase init failed: " . get_class($e) . ": " . $e->getMessage());
        }

        return back()->with('success', "FCM Direct Send Results for {$user->name}:\n" . implode("\n", $results));
    }

    public function generateSw()
    {
        $c = config('firebase.web');
        $config = json_encode([
            'apiKey'            => $c['api_key'] ?? '',
            'authDomain'        => $c['auth_domain'] ?? '',
            'projectId'         => $c['project_id'] ?? '',
            'storageBucket'     => $c['storage_bucket'] ?? '',
            'messagingSenderId' => $c['messaging_sender_id'] ?? '',
            'appId'             => $c['app_id'] ?? '',
        ], JSON_UNESCAPED_SLASHES);

        $js = <<<SWJS
try {
    importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js');
    importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-messaging-compat.js');
    firebase.initializeApp({$config});
    var messaging = firebase.messaging();
    messaging.onBackgroundMessage(function(payload) {
        var data = payload.data || {};
        var notification = payload.notification || {};
        var title = notification.title || data.title || 'New Notification';
        return self.registration.showNotification(title, {
            body: notification.body || data.body || '',
            icon: '/icon-192.png',
            badge: '/icon-192.png',
            tag: data.tag || 'crm-notification',
            requireInteraction: true,
            data: { url: data.url || data.click_action || '/' }
        });
    });
} catch(e) {
    console.warn('Firebase SW init skipped:', e);
}

self.addEventListener('push', function(event) {
    if (event.data) {
        try {
            var payload = event.data.json();
            var n = payload.notification || payload.data || {};
            var title = n.title || 'New Notification';
            event.waitUntil(self.registration.showNotification(title, {
                body: n.body || '',
                icon: '/icon-192.png',
                badge: '/icon-192.png',
                tag: n.tag || 'crm-notification',
                requireInteraction: true,
                data: { url: n.url || n.click_action || '/' }
            }));
        } catch(e) {}
    }
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    var url = (event.notification.data && event.notification.data.url) || '/';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
            for (var i = 0; i < clientList.length; i++) {
                if (clientList[i].url.indexOf(url) !== -1 && 'focus' in clientList[i]) return clientList[i].focus();
            }
            if (clients.openWindow) return clients.openWindow(url);
        })
    );
});
SWJS;

        $path = public_path('fcm-sw.js');
        file_put_contents($path, $js);

        return redirect()->route('test.fcm-diagnose')->with('success', 'fcm-sw.js generated at ' . $path);
    }

    public function fcmDiagnose()
    {
        $saPath = config('firebase.credentials');
        $apiToken = session('api_token') ?? (auth()->check() ? auth()->user()->createToken('diag-token')->plainTextToken : '');

        $serverChecks = [
            'project_id'       => !empty(config('firebase.project_id')),
            'project_id_preview' => config('firebase.project_id') ? substr(config('firebase.project_id'), 0, 20) : '',
            'api_key'          => !empty(config('firebase.web.api_key')),
            'api_key_preview'  => config('firebase.web.api_key') ? substr(config('firebase.web.api_key'), 0, 15) . '...' : '',
            'sender_id'        => !empty(config('firebase.web.messaging_sender_id')),
            'sender_id_val'    => config('firebase.web.messaging_sender_id') ?: '',
            'app_id'           => !empty(config('firebase.web.app_id')),
            'app_id_preview'   => config('firebase.web.app_id') ? substr(config('firebase.web.app_id'), 0, 20) . '...' : '',
            'vapid_key'        => !empty(config('firebase.vapid_key')),
            'vapid_key_preview'=> config('firebase.vapid_key') ? substr(config('firebase.vapid_key'), 0, 20) . '...' : '',
            'sa_file_path'     => $saPath,
            'sa_file_exists'   => file_exists($saPath),
            'sa_file_readable' => is_readable($saPath),
            'kreait_installed' => class_exists(\Kreait\Firebase\Factory::class),
            'sw_file_exists'   => file_exists(public_path('fcm-sw.js')),
            'fcm_token_count'  => FcmToken::count(),
            'my_fcm_count'     => auth()->check() ? FcmToken::where('user_id', auth()->id())->count() : 0,
            'api_token'        => !empty($apiToken),
            'api_token_preview'=> $apiToken ? substr($apiToken, 0, 15) . '...' : '',
        ];

        return view('test.fcm-diagnose', compact('serverChecks'));
    }

    /**
     * One-click PWA push diagnostic for a user (e.g. Gold). Output copy-paste for support.
     */
    public function diagnose(Request $request)
    {
        $userId = $request->query('user_id');
        $user = null;
        if ($userId) {
            $user = User::find($userId);
        }
        if (! $user) {
            $user = User::where('is_active', true)
                ->where(function ($q) {
                    $q->where('name', 'like', '%Gold%')
                        ->orWhere('email', 'like', '%gold%');
                })
                ->first();
        }
        if (! $user) {
            $user = User::where('is_active', true)->orderBy('id')->first();
        }

        $report = [
            'generated_at' => now()->toIso8601String(),
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->name ?? null,
            ] : null,
        ];

        if (! $user) {
            $report['error'] = 'No user found (add ?user_id=19 to URL for specific user).';
            return view('test.pwa-diagnose', ['report' => $report, 'reportText' => $this->reportToText($report)]);
        }

        $subscriptions = PushSubscription::where('user_id', $user->id)->get();
        $report['push_subscriptions'] = [
            'count' => $subscriptions->count(),
            'rows' => $subscriptions->map(fn ($s) => [
                'id' => $s->id,
                'endpoint_preview' => strlen($s->endpoint) > 60 ? substr($s->endpoint, 0, 60) . '...' : $s->endpoint,
                'has_keys' => ! empty($s->keys),
                'created_at' => $s->created_at?->toIso8601String(),
            ])->toArray(),
        ];

        $vapidPublic = config('webpush.vapid_public');
        $vapidPrivate = config('webpush.vapid_private');
        $report['vapid'] = [
            'public_set' => ! empty($vapidPublic),
            'public_preview' => $vapidPublic ? substr($vapidPublic, 0, 20) . '...' : '',
            'private_set' => ! empty($vapidPrivate),
        ];

        $report['webpush_package'] = class_exists(\Minishlink\WebPush\WebPush::class);

        $report['queue'] = [
            'connection' => config('queue.default'),
        ];

        $logPath = storage_path('logs/laravel.log');
        $report['log_tail'] = [];
        if (File::exists($logPath)) {
            $lines = array_slice(file($logPath) ?: [], -100);
            $relevant = array_filter($lines, fn ($line) => preg_match('/Web Push|push_subscription|SendWebPushNotificationJob|VAPID|WebPush/i', $line));
            $report['log_tail'] = array_values(array_slice($relevant, -25));
        }

        $reportText = $this->reportToText($report);
        return view('test.pwa-diagnose', ['report' => $report, 'reportText' => $reportText]);
    }

    private function reportToText(array $report): string
    {
        $out = "--- PWA Push Diagnostic ---\n";
        $out .= "Generated: " . ($report['generated_at'] ?? '') . "\n\n";
        if (! empty($report['error'])) {
            $out .= "Error: " . $report['error'] . "\n";
            return $out;
        }
        $out .= "User: " . json_encode($report['user'] ?? [], JSON_PRETTY_PRINT) . "\n\n";
        $out .= "Push subscriptions: " . json_encode($report['push_subscriptions'] ?? [], JSON_PRETTY_PRINT) . "\n\n";
        $out .= "VAPID: " . json_encode($report['vapid'] ?? [], JSON_PRETTY_PRINT) . "\n\n";
        $out .= "Web Push package installed: " . (($report['webpush_package'] ?? false) ? 'yes' : 'no') . "\n\n";
        $out .= "Queue: " . json_encode($report['queue'] ?? [], JSON_PRETTY_PRINT) . "\n\n";
        $out .= "Relevant log lines:\n" . implode('', $report['log_tail'] ?? []);
        return $out;
    }

    /**
     * Show test page: select user and send a test PWA push notification.
     */
    public function index()
    {
        $users = User::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role_id'])
            ->load('role:id,name');

        $usersWithPush = PushSubscription::select('user_id')->distinct()->pluck('user_id')->flip();
        foreach ($users as $u) {
            $u->has_push_subscription = $usersWithPush->has($u->id);
        }

        return view('test.pwa-push', compact('users'));
    }

    /**
     * Send test PWA push to the selected user.
     */
    public function send(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);
        $title = 'New Lead Assigned (Test)';
        $body = 'You have been assigned a new lead. (This is a test notification.)';
        $url = $user->isTelecaller() || $user->isSalesExecutive()
            ? route('telecaller.tasks') . '?status=pending'
            : route('leads.index');

        $fcmCount = FcmToken::where('user_id', $user->id)->count();
        if ($fcmCount > 0) {
            SendFcmNotificationJob::dispatch($user->id, $title, $body, $url, 'test-pwa-push-' . time());
        } else {
            SendWebPushNotificationJob::dispatch($user->id, $title, $body, $url, 'test-pwa-push-' . time());
        }

        return redirect()->route('test.pwa-push')
            ->with('success', "Test notification queued for {$user->name} via " . ($fcmCount > 0 ? 'FCM' : 'VAPID') . ".");
    }
}
