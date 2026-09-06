<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MetaOauthConnection;
use App\Models\MetaOauthEvent;
use App\Models\MetaOauthForm;
use App\Models\MetaOauthPage;
use App\Services\MetaOauthLeadProcessor;
use App\Services\MetaOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetaFacebookConnectorController extends Controller
{
    public function __construct(
        private readonly MetaOAuthService $metaOAuthService,
        private readonly MetaOauthLeadProcessor $leadProcessor
    )
    {
    }

    public function index()
    {
        $connections = MetaOauthConnection::with(['pages' => fn ($query) => $query->with(['forms' => fn ($forms) => $forms->orderBy('form_name')])->orderBy('page_name')])
            ->where('status', 'connected')
            ->latest()
            ->get();

        $disconnectedConnections = MetaOauthConnection::query()
            ->where('status', 'disconnected')
            ->latest()
            ->limit(5)
            ->get();

        $recentEvents = MetaOauthEvent::with(['crmLead', 'page'])
            ->latest()
            ->limit(20)
            ->get();

        return view('integrations.facebook-connector.index', [
            'connections' => $connections,
            'disconnectedConnections' => $disconnectedConnections,
            'recentEvents' => $recentEvents,
            'webhookUrl' => url('/api/webhooks/facebook/oauth-leads'),
            'appCredentialsConfigured' => filled(config('meta_oauth.app_id')) && filled(config('meta_oauth.app_secret')),
            'loginConfigConfigured' => filled(config('meta_oauth.login_config_id')),
            'webhookTokenConfigured' => filled(config('meta_oauth.webhook_verify_token')),
            'isConfigured' => filled(config('meta_oauth.app_id'))
                && filled(config('meta_oauth.app_secret'))
                && filled(config('meta_oauth.login_config_id'))
                && filled(config('meta_oauth.webhook_verify_token')),
            'graphVersion' => config('meta_oauth.graph_version'),
            'redirectUri' => config('meta_oauth.redirect_uri'),
        ]);
    }

    public function connect(Request $request)
    {
        try {
            $state = $this->metaOAuthService->newState();
            $request->session()->put('meta_oauth_state', $state);

            return redirect()->away($this->metaOAuthService->authorizationUrl($state));
        } catch (Throwable $e) {
            return redirect()
                ->route('integrations.facebook-connector.index')
                ->with('error', $e->getMessage());
        }
    }

    public function callback(Request $request)
    {
        if ($request->filled('error')) {
            return redirect()
                ->route('integrations.facebook-connector.index')
                ->with('error', $request->query('error_description', $request->query('error')));
        }

        $state = (string) $request->query('state');
        if ($state === '' || $state !== (string) $request->session()->pull('meta_oauth_state')) {
            return redirect()
                ->route('integrations.facebook-connector.index')
                ->with('error', 'Facebook connection state mismatch. Please start the connection again.');
        }

        $code = (string) $request->query('code');
        if ($code === '') {
            return redirect()
                ->route('integrations.facebook-connector.index')
                ->with('error', 'Facebook did not return an OAuth code.');
        }

        try {
            DB::transaction(function () use ($code) {
                $token = $this->metaOAuthService->exchangeCodeForLongLivedToken($code);
                $accessToken = (string) $token['access_token'];
                $user = $this->metaOAuthService->getUser($accessToken);
                $scopes = $this->metaOAuthService->getGrantedScopes($accessToken);
                $pages = $this->metaOAuthService->getPages($accessToken);

                $connection = MetaOauthConnection::create([
                    'user_id' => auth()->id(),
                    'meta_user_id' => $user['id'] ?? null,
                    'meta_user_name' => $user['name'] ?? null,
                    'user_access_token' => $accessToken,
                    'granted_scopes' => $scopes,
                    'token_expires_at' => $this->metaOAuthService->tokenExpiry($token['expires_in'] ?? null),
                    'status' => 'connected',
                    'last_connected_at' => now(),
                ]);

                foreach ($pages as $page) {
                    if (empty($page['id'])) {
                        continue;
                    }

                    MetaOauthPage::updateOrCreate(
                        [
                            'meta_oauth_connection_id' => $connection->id,
                            'page_id' => (string) $page['id'],
                        ],
                        [
                            'page_name' => $page['name'] ?? null,
                            'page_access_token' => $page['access_token'] ?? null,
                            'tasks' => $page['tasks'] ?? [],
                            'last_seen_at' => now(),
                            'last_error' => null,
                        ]
                    );
                }
            });

            return redirect()
                ->route('integrations.facebook-connector.index')
                ->with('success', 'Facebook connected. Select a Page and subscribe it to leadgen for App Review testing.');
        } catch (Throwable $e) {
            Log::warning('Meta OAuth callback failed', ['error' => $e->getMessage()]);

            return redirect()
                ->route('integrations.facebook-connector.index')
                ->with('error', 'Facebook connection failed: ' . $e->getMessage());
        }
    }

    public function subscribePage(MetaOauthPage $page)
    {
        if (!$page->page_access_token) {
            return redirect()
                ->route('integrations.facebook-connector.index')
                ->with('error', 'Page access token missing. Reconnect Facebook and select this Page again.');
        }

        try {
            $this->metaOAuthService->subscribePageToLeadgen($page->page_id, $page->page_access_token);
            $page->update([
                'leadgen_subscribed' => true,
                'subscribed_at' => now(),
                'last_error' => null,
            ]);

            return redirect()
                ->route('integrations.facebook-connector.index')
                ->with('success', $page->page_name . ' subscribed to leadgen webhook.');
        } catch (Throwable $e) {
            $page->update(['last_error' => $e->getMessage()]);

            return redirect()
                ->route('integrations.facebook-connector.index')
                ->with('error', 'Leadgen subscription failed: ' . $e->getMessage());
        }
    }

    public function refreshForms(MetaOauthPage $page)
    {
        if (!$page->page_access_token) {
            return redirect()
                ->route('integrations.facebook-connector.index')
                ->with('error', 'Page access token missing. Reconnect Facebook and select this Page again.');
        }

        try {
            $forms = $this->metaOAuthService->getPageLeadForms($page->page_id, $page->page_access_token);

            foreach ($forms as $form) {
                if (empty($form['id'])) {
                    continue;
                }

                MetaOauthForm::updateOrCreate(
                    [
                        'meta_oauth_page_id' => $page->id,
                        'form_id' => (string) $form['id'],
                    ],
                    [
                        'form_name' => $form['name'] ?? null,
                        'status' => $form['status'] ?? null,
                        'meta_created_time' => isset($form['created_time']) ? \Illuminate\Support\Carbon::parse($form['created_time']) : null,
                        'raw_payload' => $form,
                        'last_seen_at' => now(),
                    ]
                );
            }

            $page->update(['last_error' => null]);

            return redirect()
                ->route('integrations.facebook-connector.index')
                ->with('success', count($forms) . ' lead forms synced for ' . $page->page_name . '.');
        } catch (Throwable $e) {
            $page->update(['last_error' => $e->getMessage()]);

            return redirect()
                ->route('integrations.facebook-connector.index')
                ->with('error', 'Lead forms sync failed: ' . $e->getMessage());
        }
    }

    public function updatePageMode(Request $request, MetaOauthPage $page)
    {
        $validated = $request->validate([
            'lead_mode' => 'required|in:sandbox,create_leads',
            'auto_assign_leads' => 'nullable|boolean',
        ]);

        $page->update([
            'lead_mode' => $validated['lead_mode'],
            'auto_assign_leads' => $request->boolean('auto_assign_leads'),
            'last_error' => null,
        ]);

        return redirect()
            ->route('integrations.facebook-connector.index')
            ->with('success', $page->page_name . ' mode updated to ' . ($page->lead_mode === 'create_leads' ? 'Create CRM leads' : 'Sandbox store-only') . '.');
    }

    public function processEvent(MetaOauthEvent $event)
    {
        $this->leadProcessor->process($event);

        return redirect()
            ->route('integrations.facebook-connector.index')
            ->with('success', 'OAuth webhook event processed.');
    }

    public function disconnect(Request $request)
    {
        $connectionId = $request->integer('connection_id');
        $query = MetaOauthConnection::query()->where('status', 'connected');

        if ($connectionId > 0) {
            $query->whereKey($connectionId);
        }

        $query->update([
            'status' => 'disconnected',
            'disconnected_at' => now(),
        ]);

        return redirect()
            ->route('integrations.facebook-connector.index')
            ->with('success', 'Facebook connector disconnected. Old manual token flow was not changed.');
    }
}
