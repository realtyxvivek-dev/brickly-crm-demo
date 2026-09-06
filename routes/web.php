<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DemoQuickLoginController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Firebase Messaging Service Worker (must be at root scope, config injected dynamically)
Route::get('/fcm-sw.js', function () {
    $c = config('firebase.web');
    $js = "importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js');\n"
        . "importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-messaging-compat.js');\n\n"
        . "firebase.initializeApp(" . json_encode([
            'apiKey'            => $c['api_key'] ?? '',
            'authDomain'        => $c['auth_domain'] ?? '',
            'projectId'         => $c['project_id'] ?? '',
            'storageBucket'     => $c['storage_bucket'] ?? '',
            'messagingSenderId' => $c['messaging_sender_id'] ?? '',
            'appId'             => $c['app_id'] ?? '',
        ]) . ");\n\n"
        . "var messaging = firebase.messaging();\n\n"
        . "messaging.onBackgroundMessage(function(payload) {\n"
        . "    var data = payload.data || {};\n"
        . "    var notification = payload.notification || {};\n"
        . "    var title = notification.title || data.title || 'New Notification';\n"
        . "    var isCritical = data.full_screen === '1' || data.display_mode === 'critical';\n"
        . "    var options = {\n"
        . "        body: notification.body || data.body || '',\n"
        . "        icon: '/icon-192.png',\n"
        . "        badge: '/icon-192.png',\n"
        . "        tag: data.tag || 'crm-notification',\n"
        . "        requireInteraction: isCritical,\n"
        . "        data: { url: data.url || data.click_action || '/' }\n"
        . "    };\n"
        . "    return self.registration.showNotification(title, options);\n"
        . "});\n\n"
        . "self.addEventListener('notificationclick', function(event) {\n"
        . "    event.notification.close();\n"
        . "    var url = (event.notification.data && event.notification.data.url) || '/';\n"
        . "    event.waitUntil(\n"
        . "        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {\n"
        . "            for (var i = 0; i < clientList.length; i++) {\n"
        . "                if (clientList[i].url.indexOf(url) !== -1 && 'focus' in clientList[i]) return clientList[i].focus();\n"
        . "            }\n"
        . "            if (clients.openWindow) return clients.openWindow(url);\n"
        . "        })\n"
        . "    );\n"
        . "});\n";
    return response($js, 200)->header('Content-Type', 'application/javascript')->header('Service-Worker-Allowed', '/');
});

// Installation Routes (must be before other routes)
Route::prefix('install')->group(function () {
    Route::get('/', [\App\Http\Controllers\InstallController::class, 'index'])->name('install.index');
    Route::post('/check-requirements', [\App\Http\Controllers\InstallController::class, 'checkRequirements'])->name('install.check-requirements');
    Route::post('/test-database', [\App\Http\Controllers\InstallController::class, 'testDatabase'])->name('install.test-database');
    Route::post('/install', [\App\Http\Controllers\InstallController::class, 'install'])->name('install.install');
});

// Developer API documentation — only accessible via unique URL (no link in app)
Route::get('/developer/docs/{access_key}', [\App\Http\Controllers\DeveloperDocsController::class, 'show'])->name('developer.docs');

Route::get('/security-owner-access/{token}', function (Illuminate\Http\Request $request, string $token) {
    $fileLock = \App\Support\SecurityLockControl::read();
    $expectedToken = (string) ($fileLock['secret'] ?: \App\Models\SystemSettings::get('security_lock_owner_secret', ''));

    abort_if($expectedToken === '' || !hash_equals($expectedToken, $token), 404);

    $request->session()->put('security_lock_owner_bypass', true);
    $request->session()->put('security_lock_owner_bypass_at', now()->toIso8601String());

    return redirect()->route('login')->with('status', 'Owner security access verified. Please login.');
})->name('security.owner-access');

Route::get('/', function () {
    if (auth()->check()) {
        $user = auth()->user();
        return redirect(app(\App\Services\AuthRedirectService::class)->redirectPathFor($user));
    }
    return redirect()->route('login');
});

Route::get('/landing-preview', [\App\Http\Controllers\LandingPageController::class, 'show'])
    ->name('landing.preview');
Route::post('/demo-request', [\App\Http\Controllers\LandingPageController::class, 'storeDemoRequest'])
    ->middleware('throttle:5,1')
    ->name('demo-request.store');

Route::view('/privacy-policy', 'legal.privacy-policy')->name('legal.privacy');
Route::view('/terms-of-service', 'legal.terms-of-service')->name('legal.terms');
Route::view('/data-deletion', 'legal.data-deletion')->name('legal.data-deletion');
Route::view('/privacy', 'legal.privacy-policy')->name('legal.privacy.short');
Route::view('/terms', 'legal.terms-of-service')->name('legal.terms.short');
Route::view('/delete-data', 'legal.data-deletion')->name('legal.data-deletion.short');
Route::view('/crm/privacy-policy', 'legal.privacy-policy');
Route::view('/crm/terms-of-service', 'legal.terms-of-service');
Route::view('/crm/data-deletion', 'legal.data-deletion');
Route::view('/crm/privacy', 'legal.privacy-policy');
Route::view('/crm/terms', 'legal.terms-of-service');
Route::view('/crm/delete-data', 'legal.data-deletion');
Route::get('/employee-detail-form/{token}', [\App\Http\Controllers\EmployeeDetailFormController::class, 'show'])->name('employee-detail-form.show');
Route::post('/employee-detail-form/{token}', [\App\Http\Controllers\EmployeeDetailFormController::class, 'store'])->name('employee-detail-form.store')->middleware('throttle:10,1');
Route::get('/crm/api/webhooks/instagram', [\App\Http\Controllers\Api\InstagramWebhookController::class, 'verify']);
Route::post('/crm/api/webhooks/instagram', [\App\Http\Controllers\Api\InstagramWebhookController::class, 'receive']);

// Legacy home (backup)
Route::get('/legacy-home', function () {
    return view('welcome');
})->name('legacy.home');

Route::middleware('auth')->get('/storage-file', function (Illuminate\Http\Request $request) {
    $path = trim((string) $request->query('path', ''));
    $hint = trim((string) $request->query('hint', ''));

    abort_if($path === '' || filter_var($path, FILTER_VALIDATE_URL), 404);

    $normalized = preg_replace('#^/?storage/#i', '', $path);
    $normalized = preg_replace('#^/?public/#i', '', (string) $normalized);
    $normalized = ltrim((string) $normalized, '/');

    $candidates = [$normalized];
    if (!str_starts_with($normalized, 'public/')) {
        $candidates[] = 'public/' . $normalized;
    }
    if ($hint !== '' && !str_contains($normalized, '/')) {
        $candidates[] = trim($hint, '/') . '/' . $normalized;
        $candidates[] = 'public/' . trim($hint, '/') . '/' . $normalized;
    }

    $resolvedPath = null;
    foreach (array_unique($candidates) as $candidate) {
        if ($candidate !== '' && Storage::disk('public')->exists($candidate)) {
            $resolvedPath = $candidate;
            break;
        }
    }

    abort_if($resolvedPath === null, 404);

    return response()->file(Storage::disk('public')->path($resolvedPath));
})->name('storage.proxy');

// PWA Install Page - single URL for 1-click install (Android) + notification permission
Route::get('/install-app', function () {
    return view('install-app');
})->name('install-app');

// PWA Test Page
Route::get('/pwa-test', function () {
    return view('pwa-test');
})->name('pwa.test')->middleware('restrict.test');

// PWA Notification Test Page (new lead assigned message + View Lead / See Task)
Route::get('/pwa-notification-test', function () {
    return view('pwa-notification-test');
})->name('pwa.notification-test')->middleware('restrict.test');

// Save Icons (from client-side canvas)
Route::get('/save-icons', function () {
    return redirect()->route('pwa.test');
})->middleware(['auth', 'role:admin,crm']);

Route::post('/save-icons', function (Illuminate\Http\Request $request) {
    try {
        $icon192 = $request->input('icon192');
        $icon512 = $request->input('icon512');
        
        if (!$icon192 || !$icon512) {
            return response()->json([
                'success' => false,
                'message' => 'Icon data missing. Received: ' . ($icon192 ? 'icon192' : 'no icon192') . ', ' . ($icon512 ? 'icon512' : 'no icon512')
            ], 400);
        }
        
        // Decode base64 and save
        $icon192Data = base64_decode($icon192, true);
        $icon512Data = base64_decode($icon512, true);
        
        if ($icon192Data === false || $icon512Data === false) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid base64 data'
            ], 400);
        }
        
        $publicPath = public_path();
        
        // Ensure public directory is writable
        if (!is_writable($publicPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Public directory is not writable. Please check permissions.'
            ], 500);
        }
        
        // Save icon-192.png
        $saved192 = file_put_contents($publicPath . '/icon-192.png', $icon192Data);
        
        // Save icon-512.png
        $saved512 = file_put_contents($publicPath . '/icon-512.png', $icon512Data);
        
        if ($saved192 === false || $saved512 === false) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to write icon files. Check file permissions.'
            ], 500);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Icons saved successfully',
            'files' => [
                'icon-192.png' => file_exists($publicPath . '/icon-192.png') ? 'exists' : 'missing',
                'icon-512.png' => file_exists($publicPath . '/icon-512.png') ? 'exists' : 'missing'
            ],
            'sizes' => [
                'icon-192.png' => filesize($publicPath . '/icon-192.png'),
                'icon-512.png' => filesize($publicPath . '/icon-512.png')
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 500);
    }
})->middleware(['auth', 'role:admin,crm']);

// Generate Icons (Server-side)
Route::get('/generate-icons-server', function () {
    $publicPath = public_path();
    $results = [];
    
    // Check if GD library is available
    if (!extension_loaded('gd')) {
        return response()->json([
            'error' => 'GD library not installed',
            'message' => 'Please use /create-icon.html to generate icons manually',
            'url' => url('/create-icon.html')
        ], 500);
    }
    
    function createIcon($size, $filename) {
        $img = imagecreatetruecolor($size, $size);
        $green = imagecolorallocate($img, 32, 90, 68); // #205A44
        $white = imagecolorallocate($img, 255, 255, 255);
        
        imagefilledrectangle($img, 0, 0, $size, $size, $green);
        
        // Use larger font for better visibility
        $fontSize = max(1, (int)($size / 8));
        $baseY = $size * 0.35;
        $crmY = $size * 0.65;
        $lineY = $size * 0.52;
        $lineHeight = max(2, (int)($size * 0.02));
        $lineWidth = $size * 0.6;
        $lineX = ($size - $lineWidth) / 2;
        
        // Draw line
        imagefilledrectangle($img, $lineX, $lineY, $lineX + $lineWidth, $lineY + $lineHeight, $white);
        
        // Draw text using built-in font (simple but works)
        $font = 5;
        $baseText = 'BASE';
        $crmText = 'CRM';
        
        $baseX = ($size - imagefontwidth($font) * strlen($baseText)) / 2;
        $crmX = ($size - imagefontwidth($font) * strlen($crmText)) / 2;
        
        imagestring($img, $font, $baseX, $baseY, $baseText, $white);
        imagestring($img, $font, $crmX, $crmY, $crmText, $white);
        
        $success = imagepng($img, $filename);
        imagedestroy($img);
        
        return $success && file_exists($filename);
    }
    
    // Create icon-192.png
    if (createIcon(192, $publicPath . '/icon-192.png')) {
        $results['icon-192.png'] = 'created';
    } else {
        $results['icon-192.png'] = 'failed';
    }
    
    // Create icon-512.png
    if (createIcon(512, $publicPath . '/icon-512.png')) {
        $results['icon-512.png'] = 'created';
    } else {
        $results['icon-512.png'] = 'failed';
    }
    
    return response()->json([
        'success' => true,
        'message' => 'Icons generated',
        'results' => $results,
        'next_step' => 'Refresh /pwa-test page to verify'
    ]);
});

// Route to generate PWA icons
Route::get('/generate-icons', function () {
    return response()->json([
        'message' => 'Please visit /create-icon.html to generate icon files',
        'url' => url('/create-icon.html')
    ]);
});

// Debug/Test route
Route::get('/test-csrf', function () {
    return view('test-csrf');
})->name('test.csrf')->middleware('restrict.test');

Route::get('/test/verification-api', function () {
    return view('test-verification-api');
})->name('test.verification-api')->middleware('auth');

Route::get('/test/crm-auth', function () {
    return view('test-crm-auth');
})->name('test.crm-auth')->middleware('auth');

Route::get('/test/crm-error', function () {
    return view('test-crm-error');
})->name('test.crm-error')->middleware('auth');

Route::post('/test/generate-token', function () {
    $user = auth()->user();
    if (!$user) {
        return response()->json(['error' => 'Not logged in'], 401);
    }
    
    // Create a new token
    $token = $user->createToken('test-token-' . time())->plainTextToken;
    
    return response()->json([
        'success' => true,
        'token' => $token,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role->name ?? 'N/A',
        ]
    ]);
})->middleware('auth')->name('test.generate-token');

// Authentication Routes
Route::get('/quick-login', [DemoQuickLoginController::class, 'index'])->name('demo.quick-login');
Route::post('/quick-login/{role}', [DemoQuickLoginController::class, 'login'])
    ->middleware('throttle:30,1')
    ->name('demo.quick-login.login');
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login/email/resolve', [\App\Http\Controllers\Auth\EmailOtpLoginController::class, 'resolveEmail'])->name('login.email.resolve')->middleware('throttle:5,1');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');
Route::post('/login/email/send-otp', [\App\Http\Controllers\Auth\EmailOtpLoginController::class, 'sendOtp'])->name('login.email.send-otp')->middleware('throttle:5,1');
Route::post('/login/email/verify-otp', [\App\Http\Controllers\Auth\EmailOtpLoginController::class, 'verifyOtp'])->name('login.email.verify-otp')->middleware('throttle:10,1');
Route::post('/login/email/resend-otp', [\App\Http\Controllers\Auth\EmailOtpLoginController::class, 'resendOtp'])->name('login.email.resend-otp')->middleware('throttle:3,1');
Route::post('/login/email/reset', [\App\Http\Controllers\Auth\EmailOtpLoginController::class, 'resetOtpState'])->name('login.email.reset');
Route::get('/login/security-lock', [\App\Http\Controllers\Auth\LoginSecurityController::class, 'lock'])->name('login.security-lock');
Route::post('/login/security/request-access', [\App\Http\Controllers\Auth\LoginSecurityController::class, 'requestAccess'])->name('login.security.request-access')->middleware('throttle:3,1');
Route::post('/login/firebase', [LoginController::class, 'loginWithFirebase'])->middleware('throttle:login');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Route::get('/logout', [LoginController::class, 'logout'])->name('logout.get'); // Fallback for expired sessions

// Password Reset via OTP
Route::get('/forgot-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'showForgotForm'])->name('password.forgot');
Route::post('/forgot-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'sendOtp'])->name('password.send-otp')->middleware('throttle:5,1');
Route::get('/verify-otp', [\App\Http\Controllers\Auth\PasswordResetController::class, 'showOtpForm'])->name('password.otp.form');
Route::post('/verify-otp', [\App\Http\Controllers\Auth\PasswordResetController::class, 'verifyOtp'])->name('password.verify-otp')->middleware('throttle:10,1');
Route::post('/resend-otp', [\App\Http\Controllers\Auth\PasswordResetController::class, 'resendOtp'])->name('password.resend-otp')->middleware('throttle:3,1');
Route::get('/reset-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'showResetForm'])->name('password.reset.form');
Route::post('/reset-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'resetPassword'])->name('password.update');

Route::get('/advisor/{slug}', [\App\Http\Controllers\PublicAdvisorProfileController::class, 'show'])->name('advisor.public.show');
Route::get('/advisor/{slug}/contact', [\App\Http\Controllers\PublicAdvisorProfileController::class, 'downloadContact'])->name('advisor.public.contact');
Route::get('/advisor/{slug}/review', [\App\Http\Controllers\PublicAdvisorProfileController::class, 'showReviewForm'])->name('advisor.public.review.show');
Route::post('/advisor/{slug}/review', [\App\Http\Controllers\PublicAdvisorProfileController::class, 'submitReview'])->name('advisor.public.review.store');
Route::get('/payslip/verify/{token}', [\App\Http\Controllers\PayslipVerificationController::class, 'show'])->name('payslips.verify');

// Sales Executive Routes (no auth required)
Route::get('/sales-executive/dashboard', [\App\Http\Controllers\SalesExecutiveController::class, 'dashboard'])->name('sales-executive.dashboard');
Route::get('/sales-executive/tasks', [\App\Http\Controllers\SalesExecutiveController::class, 'tasks'])->name('sales-executive.tasks');
Route::get('/sales-executive/leads', [\App\Http\Controllers\SalesExecutiveController::class, 'leads'])->name('sales-executive.leads');
Route::get('/sales-executive/reports', [\App\Http\Controllers\SalesExecutiveController::class, 'reports'])->name('sales-executive.reports');
Route::get('/sales-executive/verification-pending', [\App\Http\Controllers\SalesExecutiveController::class, 'verificationPending'])->name('sales-executive.verification-pending');
Route::get('/sales-executive/profile', [\App\Http\Controllers\SalesExecutiveController::class, 'profile'])->name('sales-executive.profile');

// Telecaller workspace
Route::get('/telecaller/dashboard', [\App\Http\Controllers\TelecallerController::class, 'dashboard'])->name('telecaller.dashboard');
Route::get('/telecaller/tasks', [\App\Http\Controllers\TelecallerController::class, 'tasks'])->name('telecaller.tasks');
Route::get('/telecaller/leads', [\App\Http\Controllers\TelecallerController::class, 'leads'])->name('telecaller.leads');
Route::get('/telecaller/reports', [\App\Http\Controllers\TelecallerController::class, 'reports'])->name('telecaller.reports');
Route::get('/telecaller/verification-pending', [\App\Http\Controllers\TelecallerController::class, 'verificationPending'])->name('telecaller.verification-pending');
Route::get('/telecaller/profile', [\App\Http\Controllers\TelecallerController::class, 'profile'])->name('telecaller.profile');

// Sales Head Routes (protected)
Route::middleware(['auth'])->prefix('sales-head')->name('sales-head.')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\SalesHeadController::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboard/data', [\App\Http\Controllers\SalesHeadController::class, 'getDashboardData'])->name('dashboard.data');
});

// Sales Manager Routes (protected - Sales Head will be redirected)
Route::middleware(['auth'])->prefix('sales-manager')->name('sales-manager.')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\SalesManagerController::class, 'dashboard'])->name('dashboard');
    Route::get('/team', [\App\Http\Controllers\SalesManagerController::class, 'team'])->name('team');
    Route::get('/overview', [\App\Http\Controllers\SalesManager\LeadOverviewController::class, 'index'])->name('overview');
    Route::get('/overview/data', [\App\Http\Controllers\SalesManager\LeadOverviewController::class, 'data'])->name('overview.data');
    Route::get('/leads/create', [\App\Http\Controllers\SalesManager\LeadCreateController::class, 'create'])->name('leads.create');
    Route::get('/leads/check-duplicate', [\App\Http\Controllers\SalesManager\LeadCreateController::class, 'checkDuplicate'])->name('leads.check-duplicate');
    Route::post('/leads', [\App\Http\Controllers\SalesManager\LeadCreateController::class, 'store'])->name('leads.store');
    Route::get('/leads', [\App\Http\Controllers\SalesManagerController::class, 'leads'])->middleware('profile.heavy')->name('leads');
    Route::get('/lead-downloads', [\App\Http\Controllers\SalesManagerLeadDownloadController::class, 'index'])->name('lead-downloads.index');
    Route::post('/lead-downloads/preview', [\App\Http\Controllers\SalesManagerLeadDownloadController::class, 'preview'])->name('lead-downloads.preview');
    Route::post('/lead-downloads', [\App\Http\Controllers\SalesManagerLeadDownloadController::class, 'store'])->name('lead-downloads.store');
    Route::get('/lead-downloads/{leadDownloadRequest}/download', [\App\Http\Controllers\SalesManagerLeadDownloadController::class, 'download'])->name('lead-downloads.download');
    Route::get('/prospects', [\App\Http\Controllers\SalesManagerController::class, 'prospects'])->middleware('profile.heavy')->name('prospects');
    Route::get('/prospects/{id}', [\App\Http\Controllers\SalesManagerController::class, 'showProspect'])->name('prospects.show');
    Route::get('/prospects/{id}/edit', [\App\Http\Controllers\SalesManagerController::class, 'editProspect'])->name('prospects.edit');
    Route::put('/prospects/{id}', [\App\Http\Controllers\SalesManagerController::class, 'updateProspect'])->name('prospects.update');
    Route::delete('/prospects/{id}', [\App\Http\Controllers\SalesManagerController::class, 'destroyProspect'])->name('prospects.destroy');
    Route::get('/tasks', [\App\Http\Controllers\SalesManagerController::class, 'tasks'])->middleware('profile.heavy')->name('tasks');
    Route::get('/reports', [\App\Http\Controllers\SalesManagerController::class, 'reports'])->name('reports');
    Route::get('/verifications', [\App\Http\Controllers\Crm\VerificationController::class, 'index'])->name('verifications');
    Route::get('/verifications/media', [\App\Http\Controllers\Crm\VerificationController::class, 'media'])->name('verifications.media');
    Route::get('/verifications/closers/{siteVisit}/kyc', [\App\Http\Controllers\Crm\VerificationController::class, 'closerKyc'])->name('verifications.closer-kyc');
    Route::get('/profile', [\App\Http\Controllers\SalesManagerController::class, 'profile'])->name('profile');
    Route::get('/attendance', [\App\Http\Controllers\SalesManagerController::class, 'attendance'])->name('attendance');
    Route::get('/settings', [\App\Http\Controllers\SalesManagerController::class, 'settings'])->name('settings');
    Route::post('/settings/dashboard-visibility', [\App\Http\Controllers\SalesManagerController::class, 'updateDashboardSettings'])->name('settings.update');
    Route::get('/meetings', [\App\Http\Controllers\SalesManagerController::class, 'meetings'])->middleware('profile.heavy')->name('meetings');
    Route::get('/meetings/create', [\App\Http\Controllers\SalesManagerController::class, 'createMeeting'])->name('meetings.create');
        Route::get('/site-visits', [\App\Http\Controllers\SalesManagerController::class, 'siteVisits'])->middleware('profile.heavy')->name('site-visits');
        Route::get('/site-visits/create', [\App\Http\Controllers\SalesManagerController::class, 'createSiteVisit'])->name('site-visits.create');
    Route::get('/closed', [\App\Http\Controllers\SalesManagerController::class, 'closedLeads'])->name('closed');
});

Route::get('/share/project/{token}', [\App\Http\Controllers\ProjectPublicPageController::class, 'showShare'])->name('projects.public-share.show');
Route::post('/share/project/{token}/events', [\App\Http\Controllers\ProjectPublicPageController::class, 'recordShareEvent'])->name('projects.public-share.events');
Route::get('/share/project/{token}/travel-time/suggestions', [\App\Http\Controllers\ProjectPublicPageController::class, 'shareTravelSuggestions'])->name('projects.public-share.travel-time.suggestions');
Route::post('/share/project/{token}/travel-time/route', [\App\Http\Controllers\ProjectPublicPageController::class, 'shareTravelRoute'])->name('projects.public-share.travel-time.route');
Route::get('/share/project/{token}/variants/{variant}/details-pdf', [\App\Http\Controllers\ProjectPublicPageController::class, 'shareVariantPdf'])->name('projects.public-share.variant-pdf');
Route::get('/share/project/{token}/assets/{asset}', [\App\Http\Controllers\ProjectPublicPageController::class, 'shareAsset'])->name('projects.public-share.asset');
Route::get('/proposal/{token}', [\App\Http\Controllers\LeadProposalController::class, 'showPublic'])->name('lead-proposals.public.show');
Route::post('/proposal/{token}/events', [\App\Http\Controllers\LeadProposalController::class, 'recordEvent'])->name('lead-proposals.public.events');

// Protected Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/account/password/change', [\App\Http\Controllers\AccountPasswordController::class, 'edit'])->name('account.password.edit');
    Route::post('/account/password/change', [\App\Http\Controllers\AccountPasswordController::class, 'update'])->name('account.password.update');

    Route::get('/my-public-profile', [\App\Http\Controllers\AdvisorPublicProfileController::class, 'show'])->name('advisor.profile.show');
    Route::post('/my-public-profile', [\App\Http\Controllers\AdvisorPublicProfileController::class, 'update'])->name('advisor.profile.update');
    Route::post('/my-public-profile/testimonials', [\App\Http\Controllers\AdvisorPublicProfileController::class, 'storeTestimonial'])->name('advisor.profile.testimonials.store');
    Route::post('/my-public-profile/testimonials/{review}/delete', [\App\Http\Controllers\AdvisorPublicProfileController::class, 'destroyTestimonial'])->name('advisor.profile.testimonials.destroy');
    Route::post('/my-public-profile/gallery', [\App\Http\Controllers\AdvisorPublicProfileController::class, 'storeGallery'])->name('advisor.profile.gallery.store');
    Route::post('/my-public-profile/gallery/{item}/delete', [\App\Http\Controllers\AdvisorPublicProfileController::class, 'destroyGallery'])->name('advisor.profile.gallery.destroy');
    
    // CRM dashboard (Sales Executive Performance): Sale Head ko chhod kar sabko dikhega (CRM bhi dashboard dikhega)
    Route::get('/dashboard', function () {
        $user = auth()->user();
        if (!$user) {
            return redirect()->route('login');
        }
        
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }
        
        // Sales Executive / Telecaller: Telecaller-style dashboard
        if ($user->isTelecaller()) {
            return redirect()->route('sales-executive.dashboard');
        }
        // Sale Head: apna dashboard
        if ($user->isSalesHead()) {
            return redirect()->route('sales-head.dashboard');
        }
        // Assistant Sales Manager: Sales Manager dashboard (CRM dashboard nahi)
        if ($user->isAssistantSalesManager()) {
            return redirect()->route('sales-manager.dashboard');
        }
        // Manager (senior_manager): Sales Manager dashboard, CRM nahi
        if ($user->isSeniorManager()) {
            return redirect()->route('sales-manager.dashboard');
        }
        // Senior Manager (sales_manager, not Sales Head): Sales Manager dashboard
        if ($user->isSalesManager()) {
            return redirect()->route('sales-manager.dashboard');
        }
        if ($user->isJuniorHr()) {
            return redirect()->route('junior-hr.dashboard');
        }
        // HR Manager: dedicated hiring queue
        if ($user->isHrManager()) {
            return redirect()->route('hr-manager.dashboard');
        }
        if ($user->isMarketingUser()) {
            return redirect()->route('marketing.dashboard');
        }
        if ($user->isAdManager()) {
            return redirect()->route('ad-manager.dashboard');
        }
        // CRM + Admin + baaki sab: CRM dashboard open
        $startDate = now()->copy()->startOfMonth();
        $endDate = now()->copy()->endOfMonth();

        $sourceDistribution = \App\Models\Lead::query()
            ->select('source', \Illuminate\Support\Facades\DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('source')
            ->get()
            ->reduce(function (array $carry, \App\Models\Lead $lead) {
                $label = \App\Models\Lead::displaySourceLabel($lead->source);
                $carry[$label] = ($carry[$label] ?? 0) + (int) ($lead->total ?? 0);
                return $carry;
            }, []);

        $initialSourceDistribution = collect($sourceDistribution)
            ->map(fn (int $value, string $source) => [
                'source' => $source,
                'value' => $value,
            ])
            ->sortByDesc('value')
            ->values()
            ->all();

        return view('crm.dashboard', [
            'initialSourceDistribution' => $initialSourceDistribution,
        ]);
    })->name('dashboard');

    Route::middleware(['auth', 'role:hr_manager,sales_manager,senior_manager,assistant_sales_manager'])->prefix('hr-manager')->name('hr-manager.')->group(function () {
        Route::get('/verifications', [\App\Http\Controllers\Hr\VerificationController::class, 'index'])->name('verifications');
    });

    Route::middleware(['auth', 'role:hr_manager'])->prefix('hr-manager')->name('hr-manager.')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Hr\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/hiring', [\App\Http\Controllers\HrHiringController::class, 'index'])->name('hiring.index');
        Route::get('/hiring/{lead}', [\App\Http\Controllers\HrHiringController::class, 'show'])->name('hiring.show');
        Route::put('/hiring/{lead}', [\App\Http\Controllers\HrHiringController::class, 'update'])->name('hiring.update');
        Route::get('/payroll', [\App\Http\Controllers\Hr\PayrollController::class, 'index'])->name('payroll.index');
        Route::post('/payroll/prepare', [\App\Http\Controllers\Hr\PayrollController::class, 'prepare'])->name('payroll.prepare');
        Route::post('/payroll/adjustments', [\App\Http\Controllers\Hr\PayrollController::class, 'storeAdjustment'])->name('payroll.adjustments.store');
        Route::post('/payroll/{freeze}/release-employee', [\App\Http\Controllers\Hr\PayrollController::class, 'releaseToEmployee'])->name('payroll.release-employee');
        Route::post('/payroll/{freeze}/submit-admin', [\App\Http\Controllers\Hr\PayrollController::class, 'submitToAdmin'])->name('payroll.submit-admin');
        Route::get('/payroll/payslips/{payslip}/preview', [\App\Http\Controllers\Hr\PayrollController::class, 'previewPayslip'])->name('payroll.payslips.preview');
        Route::post('/payroll/payslips/{payslip}/attendance-overwrite', [\App\Http\Controllers\Hr\PayrollController::class, 'overwriteAttendance'])->name('payroll.payslips.attendance-overwrite');
        Route::post('/payroll/payslips/{payslip}/attendance-clear', [\App\Http\Controllers\Hr\PayrollController::class, 'clearAttendanceOverride'])->name('payroll.payslips.attendance-clear');
        Route::post('/payroll/payslips/{payslip}/recalculate', [\App\Http\Controllers\Hr\PayrollController::class, 'recalculatePayslip'])->name('payroll.payslips.recalculate');
        Route::post('/payroll/payslips/{payslip}/resend-preview', [\App\Http\Controllers\Hr\PayrollController::class, 'resendPreview'])->name('payroll.payslips.resend-preview');
        Route::post('/payroll/payslips/{payslip}/resolve', [\App\Http\Controllers\Hr\PayrollController::class, 'resolveCorrection'])->name('payroll.payslips.resolve');
        Route::post('/targets/bulk-set', [\App\Http\Controllers\Admin\TargetController::class, 'bulkSet'])->name('targets.bulk-set');
        Route::post('/targets/copy-previous', [\App\Http\Controllers\Admin\TargetController::class, 'copyPrevious'])->name('targets.copy-previous');
        Route::resource('/targets', \App\Http\Controllers\Admin\TargetController::class);
    });

    Route::middleware(['auth', 'role:hr_manager,junior_hr'])->prefix('hr-manager')->name('hr-manager.')->group(function () {
        Route::get('/attendance/sheet', [\App\Http\Controllers\Hr\AttendanceSheetController::class, 'index'])->name('attendance.sheet');
        Route::redirect('/attendance/register', '/hr-manager/attendance/sheet')->name('attendance.index');
        Route::post('/attendance/sheet/override', [\App\Http\Controllers\Hr\AttendanceSheetController::class, 'storeOverride'])->middleware('role:hr_manager')->name('attendance.sheet.override');
        Route::post('/attendance/sheet/override/clear', [\App\Http\Controllers\Hr\AttendanceSheetController::class, 'clearOverride'])->middleware('role:hr_manager')->name('attendance.sheet.clear');
        Route::post('/attendance/productive-items/{type}/{id}/verify', [\App\Http\Controllers\Hr\AttendanceSheetController::class, 'verifyProductiveItem'])->name('attendance.productive-items.verify');
        Route::post('/attendance/productive-items/{type}/{id}/reject', [\App\Http\Controllers\Hr\AttendanceSheetController::class, 'rejectProductiveItem'])->name('attendance.productive-items.reject');
        Route::redirect('/attendance/problems', '/hr-manager/attendance/sheet')->name('attendance.problems');
        Route::get('/attendance/reports', [\App\Http\Controllers\Hr\AttendanceReportController::class, 'index'])->middleware('role:hr_manager')->name('attendance.reports');
        Route::get('/attendance/fraud-reviews', [\App\Http\Controllers\Hr\FraudReviewController::class, 'index'])->middleware('role:hr_manager')->name('attendance.fraud-reviews');
        Route::get('/attendance/leaves', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'leaves'])->name('attendance.leaves');
        Route::get('/attendance/regularizations', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'regularizations'])->name('attendance.regularizations');
        Route::redirect('/attendance/overtimes', '/hr-manager/attendance/sheet')->middleware('role:hr_manager')->name('attendance.overtimes');
        Route::get('/attendance/outside-punches', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'outsidePunches'])->name('attendance.outside-punches');
        Route::post('/attendance/outside-punches/{mapping}/toggle-request', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'toggleOutsidePunchRequest'])->middleware('role:hr_manager')->name('attendance.outside-punches.toggle-request');
        Route::post('/attendance/outside-punches/{mapping}/toggle-direct-allow', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'toggleOutsidePunchDirectAllow'])->middleware('role:hr_manager')->name('attendance.outside-punches.toggle-direct-allow');
        Route::post('/attendance/outside-punches/{mapping}/window', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'saveOutsidePunchWindow'])->middleware('role:hr_manager')->name('attendance.outside-punches.window');
        Route::post('/attendance/leaves/{leaveRequest}/approve', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'approveLeave'])->name('attendance.leaves.approve');
        Route::post('/attendance/leaves/{leaveRequest}/reject', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'rejectLeave'])->name('attendance.leaves.reject');
        Route::post('/attendance/regularizations/{regularization}/approve', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'approveRegularization'])->name('attendance.regularizations.approve');
        Route::post('/attendance/regularizations/{regularization}/reject', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'rejectRegularization'])->name('attendance.regularizations.reject');
        Route::post('/attendance/outside-punches/{outsidePunchRequest}/approve', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'approveOutsidePunch'])->name('attendance.outside-punches.approve');
        Route::post('/attendance/outside-punches/{outsidePunchRequest}/reject', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'rejectOutsidePunch'])->name('attendance.outside-punches.reject');
        Route::post('/attendance/overtimes/{overtime}/approve', [\App\Http\Controllers\Hr\AttendanceOvertimeController::class, 'approve'])->middleware('role:hr_manager')->name('attendance.overtimes.approve');
        Route::post('/attendance/overtimes/{overtime}/reject', [\App\Http\Controllers\Hr\AttendanceOvertimeController::class, 'reject'])->middleware('role:hr_manager')->name('attendance.overtimes.reject');
        Route::post('/attendance/suspicion/{log}', [\App\Http\Controllers\Hr\AttendanceSuspicionReviewController::class, 'update'])->middleware('role:hr_manager')->name('attendance.suspicion.update');
        Route::post('/attendance/fraud-reviews/{review}/approve', [\App\Http\Controllers\Hr\FraudReviewController::class, 'approve'])->middleware('role:hr_manager')->name('attendance.fraud-reviews.approve');
        Route::post('/attendance/fraud-reviews/{review}/reject', [\App\Http\Controllers\Hr\FraudReviewController::class, 'reject'])->middleware('role:hr_manager')->name('attendance.fraud-reviews.reject');
        Route::get('/attendance/reports/export/attendance', [\App\Http\Controllers\Api\AttendanceReportExportController::class, 'attendance'])->middleware('role:hr_manager')->name('attendance.reports.export.attendance');
        Route::get('/attendance/reports/export/payroll', [\App\Http\Controllers\Api\AttendanceReportExportController::class, 'payroll'])->middleware('role:hr_manager')->name('attendance.reports.export.payroll');
        Route::get('/attendance/reports/export/suspicious', [\App\Http\Controllers\Api\AttendanceReportExportController::class, 'suspicious'])->middleware('role:hr_manager')->name('attendance.reports.export.suspicious');
    });

    Route::middleware(['auth', 'role:junior_hr'])->prefix('junior-hr')->name('junior-hr.')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\JuniorHrController::class, 'dashboard'])->name('dashboard');
        Route::get('/hiring', [\App\Http\Controllers\HrHiringController::class, 'index'])->name('hiring.index');
        Route::get('/hiring/{lead}', [\App\Http\Controllers\HrHiringController::class, 'show'])->name('hiring.show');
        Route::put('/hiring/{lead}', [\App\Http\Controllers\HrHiringController::class, 'update'])->name('hiring.update');
        Route::get('/profile', [\App\Http\Controllers\JuniorHrController::class, 'profile'])->name('profile');
    });

    Route::middleware(['auth'])->prefix('attendance')->name('attendance.')->group(function () {
        Route::get('/leaves', [\App\Http\Controllers\AttendanceRequestController::class, 'leaves'])->name('leaves');
        Route::post('/leaves', [\App\Http\Controllers\AttendanceRequestController::class, 'storeLeave'])->name('leaves.store');
        Route::get('/regularizations', [\App\Http\Controllers\AttendanceRequestController::class, 'regularizations'])->name('regularizations');
        Route::post('/regularizations', [\App\Http\Controllers\AttendanceRequestController::class, 'storeRegularization'])->name('regularizations.store');
        Route::get('/overtimes', [\App\Http\Controllers\AttendanceRequestController::class, 'overtimes'])->name('overtimes');
        Route::get('/payslips', [\App\Http\Controllers\AttendanceRequestController::class, 'payslips'])->name('payslips');
        Route::post('/payslips/{payslip}/correction', [\App\Http\Controllers\AttendanceRequestController::class, 'requestPayslipCorrection'])->name('payslips.correction');
        Route::get('/payslips/{payslip}/download', [\App\Http\Controllers\AttendanceRequestController::class, 'downloadPayslip'])->name('payslips.download');
    });

    Route::middleware(['auth', 'role:hr_manager'])->prefix('hr-manager/settings')->name('hr-manager.settings.')->group(function () {
        Route::prefix('attendance')->name('attendance.')->group(function () {
            Route::get('/offices', [\App\Http\Controllers\Admin\AttendanceOfficeController::class, 'index'])->name('offices.index');
            Route::post('/offices', [\App\Http\Controllers\Admin\AttendanceOfficeController::class, 'store'])->name('offices.store');
            Route::put('/offices/{office}', [\App\Http\Controllers\Admin\AttendanceOfficeController::class, 'update'])->name('offices.update');
            Route::delete('/offices/{office}', [\App\Http\Controllers\Admin\AttendanceOfficeController::class, 'destroy'])->name('offices.destroy');
            Route::get('/policies', [\App\Http\Controllers\Admin\AttendancePolicyController::class, 'index'])->name('policies.index');
            Route::post('/policies', [\App\Http\Controllers\Admin\AttendancePolicyController::class, 'store'])->name('policies.store');
            Route::put('/policies/{policy}', [\App\Http\Controllers\Admin\AttendancePolicyController::class, 'update'])->name('policies.update');
            Route::delete('/policies/{policy}', [\App\Http\Controllers\Admin\AttendancePolicyController::class, 'destroy'])->name('policies.destroy');
            Route::post('/weekoffs/apply', [\App\Http\Controllers\Admin\AttendancePolicyController::class, 'applyWeekoffs'])->name('weekoffs.apply');
            Route::get('/user-mappings', [\App\Http\Controllers\Admin\UserAttendanceMappingController::class, 'index'])->name('user-mappings.index');
            Route::post('/user-mappings/bulk', [\App\Http\Controllers\Admin\UserAttendanceMappingController::class, 'bulkStore'])->name('user-mappings.bulk-store');
            Route::post('/user-mappings', [\App\Http\Controllers\Admin\UserAttendanceMappingController::class, 'store'])->name('user-mappings.store');
            Route::put('/user-mappings/{mapping}', [\App\Http\Controllers\Admin\UserAttendanceMappingController::class, 'update'])->name('user-mappings.update');
            Route::delete('/user-mappings/{mapping}', [\App\Http\Controllers\Admin\UserAttendanceMappingController::class, 'destroy'])->name('user-mappings.destroy');
            Route::get('/leave-types', [\App\Http\Controllers\Admin\LeaveTypeController::class, 'index'])->name('leave-types.index');
            Route::post('/leave-types', [\App\Http\Controllers\Admin\LeaveTypeController::class, 'store'])->name('leave-types.store');
            Route::put('/leave-types/{leaveType}', [\App\Http\Controllers\Admin\LeaveTypeController::class, 'update'])->name('leave-types.update');
            Route::delete('/leave-types/{leaveType}', [\App\Http\Controllers\Admin\LeaveTypeController::class, 'destroy'])->name('leave-types.destroy');
            Route::get('/simulator', [\App\Http\Controllers\Admin\AttendancePolicySimulatorController::class, 'index'])->name('simulator.index');
            Route::get('/outside-punch-permissions', [\App\Http\Controllers\Admin\AttendanceOutsidePunchPermissionController::class, 'index'])->name('outside-punch-permissions.index');
            Route::post('/outside-punch-permissions', [\App\Http\Controllers\Admin\AttendanceOutsidePunchPermissionController::class, 'store'])->name('outside-punch-permissions.store');
            Route::put('/outside-punch-permissions/{permission}', [\App\Http\Controllers\Admin\AttendanceOutsidePunchPermissionController::class, 'update'])->name('outside-punch-permissions.update');
            Route::delete('/outside-punch-permissions/{permission}', [\App\Http\Controllers\Admin\AttendanceOutsidePunchPermissionController::class, 'destroy'])->name('outside-punch-permissions.destroy');
            Route::get('/overtimes', [\App\Http\Controllers\Hr\AttendanceOvertimeController::class, 'index'])->name('overtimes.index');
        });

        Route::prefix('hr')->name('hr.')->group(function () {
            Route::get('/employees', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'index'])->name('employees.index');
            Route::get('/employees/create', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'create'])->name('employees.create');
            Route::post('/employees', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'store'])->name('employees.store');
            Route::get('/employees/{employee}', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'show'])->name('employees.show');
            Route::get('/employees/{employee}/edit', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'edit'])->name('employees.edit');
            Route::put('/employees/{employee}', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'update'])->name('employees.update');
            Route::post('/employees/{employee}/documents', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'storeDocument'])->name('employees.documents.store');
            Route::delete('/employees/{employee}/documents/{document}', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'destroyDocument'])->name('employees.documents.destroy');
            Route::post('/employees/{employee}/detail-link', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'generateDetailLink'])->name('employees.detail-link.generate');
            Route::post('/employees/{employee}/detail-link/{link}/revoke', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'revokeDetailLink'])->name('employees.detail-link.revoke');
            Route::post('/employees/{employee}/assets', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'storeAsset'])->name('employees.assets.store');
            Route::post('/employees/{employee}/assets/{asset}/status', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'updateAssetStatus'])->name('employees.assets.status');
            Route::post('/employees/{employee}/salary-revisions', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'storeSalaryRevision'])->name('employees.salary-revisions.store');
            Route::post('/employees/{employee}/exit-workflow', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'updateExitWorkflow'])->name('employees.exit-workflow.update');
            Route::get('/document-center', [\App\Http\Controllers\Admin\Hr\DocumentCenterController::class, 'index'])->name('document-center.index');
            Route::get('/document-center/{document}/download', [\App\Http\Controllers\Admin\Hr\DocumentCenterController::class, 'download'])->name('document-center.download');
            Route::get('/salary-revisions', [\App\Http\Controllers\Admin\Hr\EmployeeSalaryRevisionController::class, 'index'])->name('salary-revisions.index');
            Route::get('/exit-cases', [\App\Http\Controllers\Admin\Hr\EmployeeExitWorkflowController::class, 'index'])->name('exit-workflows.index');
            Route::get('/incentives', [\App\Http\Controllers\Admin\Hr\EmployeeIncentiveController::class, 'index'])->name('incentives.index');
            Route::get('/salary-structures', [\App\Http\Controllers\Admin\Hr\SalaryStructureController::class, 'index'])->name('salary-structures.index');
            Route::post('/salary-structures', [\App\Http\Controllers\Admin\Hr\SalaryStructureController::class, 'store'])->name('salary-structures.store');
            Route::post('/salary-structures/employee-breakup', [\App\Http\Controllers\Admin\Hr\SalaryStructureController::class, 'storeEmployeeBreakup'])->name('salary-structures.employee-breakup.store');
            Route::put('/salary-structures/{salaryStructure}', [\App\Http\Controllers\Admin\Hr\SalaryStructureController::class, 'update'])->name('salary-structures.update');
            Route::get('/salary-profiles', [\App\Http\Controllers\Admin\Hr\SalaryProfileController::class, 'index'])->name('salary-profiles.index');
            Route::post('/salary-profiles', [\App\Http\Controllers\Admin\Hr\SalaryProfileController::class, 'store'])->name('salary-profiles.store');
            Route::get('/deduction-heads', [\App\Http\Controllers\Admin\Hr\DeductionHeadController::class, 'index'])->name('deduction-heads.index');
            Route::post('/deduction-heads', [\App\Http\Controllers\Admin\Hr\DeductionHeadController::class, 'store'])->name('deduction-heads.store');
            Route::get('/payslip-settings', [\App\Http\Controllers\Admin\Hr\PayslipSettingsController::class, 'index'])->name('payslip-settings.index');
            Route::post('/payslip-settings', [\App\Http\Controllers\Admin\Hr\PayslipSettingsController::class, 'update'])->name('payslip-settings.update');
            Route::get('/fraud-settings', [\App\Http\Controllers\Admin\Hr\FraudSettingsController::class, 'index'])->name('fraud-settings.index');
            Route::post('/fraud-settings/{policy}', [\App\Http\Controllers\Admin\Hr\FraudSettingsController::class, 'update'])->name('fraud-settings.update');
            Route::get('/reports', [\App\Http\Controllers\Admin\Hr\ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/export/attendance', [\App\Http\Controllers\Api\AttendanceReportExportController::class, 'attendance'])->name('reports.export.attendance');
            Route::get('/reports/export/payroll', [\App\Http\Controllers\Api\AttendanceReportExportController::class, 'payroll'])->name('reports.export.payroll');
            Route::get('/reports/export/suspicious', [\App\Http\Controllers\Api\AttendanceReportExportController::class, 'suspicious'])->name('reports.export.suspicious');
        });
    });

    Route::get('/calls/{callLog}/recording', [\App\Http\Controllers\CallLogController::class, 'streamRecording'])
        ->name('calls.recording');

    // Test: Lead assigned notification (1-click test for popup + email)
    Route::get('/test/lead-notification', [\App\Http\Controllers\TestLeadNotificationController::class, 'index'])->name('test.lead-notification')->middleware('role:admin,crm');
    Route::post('/test/lead-notification/simulate', [\App\Http\Controllers\TestLeadNotificationController::class, 'simulate'])->name('test.lead-notification.simulate')->middleware('role:admin,crm');

    // Test: PWA Push – select user and send test notification (Admin/CRM only)
    Route::get('/test/lead-delivery', [\App\Http\Controllers\TestLeadDeliveryController::class, 'index'])->name('test.lead-delivery')->middleware('role:admin,crm');
    Route::post('/test/lead-delivery/send', [\App\Http\Controllers\TestLeadDeliveryController::class, 'send'])->name('test.lead-delivery.send')->middleware('role:admin,crm');
    Route::post('/test/lead-delivery/send-notification', [\App\Http\Controllers\TestLeadDeliveryController::class, 'sendNotification'])->name('test.lead-delivery.send-notification')->middleware('role:admin,crm');
    Route::get('/test/reminder-delivery', [\App\Http\Controllers\TestLeadDeliveryController::class, 'indexReminder'])->name('test.reminder-delivery')->middleware('role:admin,crm');
    Route::post('/test/reminder-delivery/send', [\App\Http\Controllers\TestLeadDeliveryController::class, 'sendReminder'])->name('test.reminder-delivery.send')->middleware('role:admin,crm');
    Route::get('/test/pwa-push', [\App\Http\Controllers\TestPwaPushController::class, 'index'])->name('test.pwa-push')->middleware('role:admin,crm');
    Route::get('/test/fcm-diagnose', [\App\Http\Controllers\TestPwaPushController::class, 'fcmDiagnose'])->name('test.fcm-diagnose')->middleware('role:admin,crm');
    Route::post('/test/fcm-generate-sw', [\App\Http\Controllers\TestPwaPushController::class, 'generateSw'])->name('test.fcm-generate-sw')->middleware('role:admin,crm');
    Route::post('/test/fcm-direct-send', [\App\Http\Controllers\TestPwaPushController::class, 'fcmDirectSend'])->name('test.fcm-direct-send')->middleware('role:admin,crm');
    Route::get('/test/pwa-push/diagnose', [\App\Http\Controllers\TestPwaPushController::class, 'diagnose'])->name('test.pwa-diagnose')->middleware('role:admin,crm');
    Route::post('/test/pwa-push/send', [\App\Http\Controllers\TestPwaPushController::class, 'send'])->name('test.pwa-push.send')->middleware('role:admin,crm');
    
    // Users Management
    Route::post('users/{user}/send-credentials-email', [\App\Http\Controllers\UserController::class, 'sendCredentialsEmail'])->name('users.send-credentials-email');
    Route::post('users/{user}/send-password-reset-email', [\App\Http\Controllers\UserController::class, 'sendPasswordResetEmail'])->name('users.send-password-reset-email');
    Route::post('users/{user}/update-password', [\App\Http\Controllers\UserController::class, 'updatePasswordByAdmin'])->name('users.update-password');
    Route::post('users/{user}/toggle-two-factor', [\App\Http\Controllers\UserController::class, 'toggleTwoFactorMode'])->name('users.toggle-two-factor');
    Route::post('users/{user}/toggle-login-access', [\App\Http\Controllers\UserController::class, 'toggleLoginAccess'])->name('users.toggle-login-access');
    Route::post('users/{user}/team-members', [\App\Http\Controllers\UserController::class, 'attachTeamMember'])->name('users.team-members.attach');
    Route::delete('users/{user}/team-members/{member}', [\App\Http\Controllers\UserController::class, 'detachTeamMember'])->name('users.team-members.detach');
    Route::get('/users/{user}/transfer-delete', [\App\Http\Controllers\UserController::class, 'showTransferDelete'])->name('users.transfer-delete');
    Route::post('/users/{user}/transfer-delete', [\App\Http\Controllers\UserController::class, 'transferDelete'])->name('users.transfer-delete.store');
    Route::resource('users', \App\Http\Controllers\UserController::class);
    Route::prefix('admin/phone-privacy')->name('admin.phone-privacy.')->middleware('role:admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\PhonePrivacyController::class, 'index'])->name('index');
        Route::put('/users/{user}', [\App\Http\Controllers\Admin\PhonePrivacyController::class, 'update'])->name('users.update');
        Route::put('/bulk', [\App\Http\Controllers\Admin\PhonePrivacyController::class, 'bulkUpdate'])->name('bulk.update');
    });
    Route::prefix('admin/sessions')->name('admin.sessions.')->middleware('role:admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\AdminSessionController::class, 'index'])->name('index');
        Route::post('/users/{user}/revoke', [\App\Http\Controllers\Admin\AdminSessionController::class, 'revokeUserSessions'])->name('users.revoke');
        Route::post('/revoke-all-non-admin', [\App\Http\Controllers\Admin\AdminSessionController::class, 'revokeAllNonAdminSessions'])->name('revoke-all-non-admin');
        Route::post('/revoke-all', [\App\Http\Controllers\Admin\AdminSessionController::class, 'revokeAllSessions'])->name('revoke-all');
    });
    
    // Builders Management (CRM/Admin only)
    Route::resource('builders', \App\Http\Controllers\BuilderController::class)->middleware('role:crm,admin');
    
    // Closers Management (Admin/CRM/Sales Manager/Sales Head)
    Route::get('/closers', [\App\Http\Controllers\CloserController::class, 'index'])->name('closers.index')->middleware(['auth', 'role:admin,crm,sales_manager,sales_head']);
    
    // Unified routes for Prospects, Meetings, and Site Visits (Admin/CRM/Sales Manager/Sales Head)
    Route::middleware(['auth', 'role:admin,crm,sales_manager,sales_head'])->group(function () {
        Route::get('/prospects', [\App\Http\Controllers\SalesManagerController::class, 'prospects'])->name('prospects.index');
        Route::get('/meetings', [\App\Http\Controllers\SalesManagerController::class, 'meetings'])->name('meetings.index');
        Route::get('/meetings/create', [\App\Http\Controllers\SalesManagerController::class, 'createMeeting'])->name('meetings.create');
        Route::get('/site-visits', [\App\Http\Controllers\SalesManagerController::class, 'siteVisits'])->name('site-visits.index');
        Route::get('/site-visits/create', [\App\Http\Controllers\SalesManagerController::class, 'createSiteVisit'])->name('site-visits.create');
    });

    // Projects Management - View accessible to all, CUD only for Admin/CRM
    // Specific routes must come before parameterized routes
    Route::get('/projects', [\App\Http\Controllers\ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects-list', [\App\Http\Controllers\ProjectController::class, 'list'])->name('projects.list');
    
    Route::middleware('role:crm,admin')->group(function () {
        Route::get('/projects/create', [\App\Http\Controllers\ProjectController::class, 'create'])->name('projects.create');
        Route::post('/projects', [\App\Http\Controllers\ProjectController::class, 'store'])->name('projects.store');
        Route::get('/projects/{project}/edit', [\App\Http\Controllers\ProjectController::class, 'edit'])->name('projects.edit');
        Route::put('/projects/{project}', [\App\Http\Controllers\ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{project}', [\App\Http\Controllers\ProjectController::class, 'destroy'])->name('projects.destroy');
        Route::get('/projects/import/template', [\App\Http\Controllers\ProjectImportController::class, 'template'])->name('projects.import.template');
        Route::get('/projects/import', [\App\Http\Controllers\ProjectImportController::class, 'index'])->name('projects.import.index');
        Route::post('/projects/import/preview', [\App\Http\Controllers\ProjectImportController::class, 'preview'])->name('projects.import.preview');
        Route::post('/projects/import/create', [\App\Http\Controllers\ProjectImportController::class, 'create'])->name('projects.import.create');
        Route::get('/projects/import/url', [\App\Http\Controllers\ProjectUrlImportController::class, 'index'])->name('projects.import-url.index');
        Route::post('/projects/import/url/extract', [\App\Http\Controllers\ProjectUrlImportController::class, 'extract'])->name('projects.import-url.extract');
        Route::get('/projects/import/url/review/{token}', [\App\Http\Controllers\ProjectUrlImportController::class, 'review'])->name('projects.import-url.review');
        Route::get('/projects/import/url/review/{token}/images', [\App\Http\Controllers\ProjectUrlImportController::class, 'downloadImages'])->name('projects.import-url.download-images');
        Route::post('/projects/import/url/create', [\App\Http\Controllers\ProjectUrlImportController::class, 'create'])->name('projects.import-url.create');
        Route::get('/projects/public-page/wizard/create', [\App\Http\Controllers\ProjectPublicPageController::class, 'create'])->name('projects.public-pages.create');
        Route::post('/projects/public-page/wizard/save', [\App\Http\Controllers\ProjectPublicPageController::class, 'saveDraft'])->name('projects.public-pages.save');
        Route::get('/projects/{project}/public-page/wizard', [\App\Http\Controllers\ProjectPublicPageController::class, 'edit'])->name('projects.public-pages.edit');
        Route::post('/projects/public-page/resolve-map-coordinates', [\App\Http\Controllers\ProjectPublicPageController::class, 'resolveMapCoordinates'])->name('projects.public-pages.resolve-map-coordinates');
        Route::post('/projects/public-page/fetch-nearby-landmarks', [\App\Http\Controllers\ProjectPublicPageController::class, 'fetchNearbyLandmarks'])->name('projects.public-pages.fetch-nearby-landmarks');
        Route::post('/projects/{project}/public-page/publish', [\App\Http\Controllers\ProjectPublicPageController::class, 'publish'])->name('projects.public-pages.publish');
        Route::get('/projects/{project}/public-page/preview', [\App\Http\Controllers\ProjectPublicPageController::class, 'preview'])->name('projects.public-pages.preview');
        Route::get('/projects/{project}/public-page/analytics', [\App\Http\Controllers\ProjectPublicPageController::class, 'analytics'])->name('projects.public-pages.analytics');
        Route::post('/projects/{project}/public-page/events', [\App\Http\Controllers\ProjectPublicPageController::class, 'recordPreviewEvent'])->name('projects.public-pages.preview-events');
        Route::get('/projects/{project}/public-page/travel-time/suggestions', [\App\Http\Controllers\ProjectPublicPageController::class, 'previewTravelSuggestions'])->name('projects.public-pages.travel-time.suggestions');
        Route::post('/projects/{project}/public-page/travel-time/route', [\App\Http\Controllers\ProjectPublicPageController::class, 'previewTravelRoute'])->name('projects.public-pages.travel-time.route');
        Route::get('/projects/{project}/public-page/variants/{variant}/details-pdf', [\App\Http\Controllers\ProjectPublicPageController::class, 'previewVariantPdf'])->name('projects.public-pages.variant-pdf');
        Route::post('/projects/{project}/public-page/variants/{variant}/regenerate-pdf', [\App\Http\Controllers\ProjectPublicPageController::class, 'regenerateVariantPdf'])->name('projects.public-pages.variant-pdf.regenerate');
        Route::post('/projects/{project}/public-page/variants/regenerate-all', [\App\Http\Controllers\ProjectPublicPageController::class, 'regenerateAllVariantPdfs'])->name('projects.public-pages.variant-pdf.regenerate-all');
        Route::get('/projects/{project}/public-page/assets/{asset}', [\App\Http\Controllers\ProjectPublicPageController::class, 'previewAsset'])->name('projects.public-pages.asset');
        Route::post('/projects/public-page/share-links/{shareLink}/revoke', [\App\Http\Controllers\ProjectPublicPageController::class, 'revoke'])->name('projects.public-pages.share-links.revoke');
        Route::post('/projects/public-page/share-links/{shareLink}/reactivate', [\App\Http\Controllers\ProjectPublicPageController::class, 'reactivate'])->name('projects.public-pages.share-links.reactivate');
    });
    
    // Parameterized routes come after specific routes
    Route::get('/projects/{project}', [\App\Http\Controllers\ProjectController::class, 'show'])->name('projects.show');
    
    // Project pricing and unit types (for web forms)
    Route::post('/projects/{project}/pricing', [\App\Http\Controllers\Api\PricingController::class, 'update'])->middleware(['auth', 'role:crm,admin'])->name('projects.pricing.update');
    Route::post('/projects/{project}/unit-types', [\App\Http\Controllers\Api\UnitTypeController::class, 'store'])->middleware(['auth', 'role:crm,admin'])->name('projects.unit-types.store');
    Route::post('/projects/{project}/collaterals', [\App\Http\Controllers\Api\ProjectCollateralController::class, 'store'])->middleware(['auth', 'role:crm,admin'])->name('projects.collaterals.store');
    
    // Verifications (CRM/Admin/Sales Head)
    Route::middleware(['role:crm,admin,sales_head'])->prefix('crm')->name('crm.')->group(function () {
        Route::get('/verifications', [\App\Http\Controllers\Crm\VerificationController::class, 'index'])->name('verifications');
        Route::get('/verifications/media', [\App\Http\Controllers\Crm\VerificationController::class, 'media'])->name('verifications.media');
        Route::get('/verifications/closers/{siteVisit}/kyc', [\App\Http\Controllers\Crm\VerificationController::class, 'closerKyc'])->name('verifications.closer-kyc');
        Route::get('/meta-review', [\App\Http\Controllers\Crm\MetaReviewController::class, 'index'])->name('meta-review');
        Route::post('/meta-review/{lead}', [\App\Http\Controllers\Crm\MetaReviewController::class, 'update'])->name('meta-review.update');
        Route::middleware('role:crm,admin')->group(function () {
            Route::get('/meta-lead-check', [\App\Http\Controllers\Crm\MetaLeadCheckController::class, 'index'])->name('meta-lead-check.index');
            Route::post('/meta-lead-check/preview', [\App\Http\Controllers\Crm\MetaLeadCheckController::class, 'preview'])->name('meta-lead-check.preview');
            Route::post('/meta-lead-check/import', [\App\Http\Controllers\Crm\MetaLeadCheckController::class, 'import'])->name('meta-lead-check.import');
            Route::get('/meta-lead-check/extension/download', [\App\Http\Controllers\Admin\MobileAppUpdateController::class, 'downloadFacebookLeadCenterExtension'])->name('meta-lead-check.extension.download');
            Route::post('/meta-lead-check/extension/generate-token', [\App\Http\Controllers\Admin\MobileAppUpdateController::class, 'generateFacebookLeadCenterExtensionToken'])->name('meta-lead-check.extension.generate-token');
            Route::post('/meta-lead-check/extension/test-connection', [\App\Http\Controllers\Admin\MobileAppUpdateController::class, 'testFacebookLeadCenterExtensionConnection'])->name('meta-lead-check.extension.test-connection');
        });
        Route::get('/lead-board', [\App\Http\Controllers\SalesManager\LeadOverviewController::class, 'index'])->name('lead-board');
        Route::get('/lead-board/data', [\App\Http\Controllers\SalesManager\LeadOverviewController::class, 'data'])->name('lead-board.data');

        // Target Management (CRM/Admin/Sales Head) - CRM-friendly URLs
        Route::post('targets/bulk-set', [\App\Http\Controllers\Admin\TargetController::class, 'bulkSet'])->name('targets.bulk-set');
        Route::post('targets/copy-previous', [\App\Http\Controllers\Admin\TargetController::class, 'copyPrevious'])->name('targets.copy-previous');
        Route::resource('targets', \App\Http\Controllers\Admin\TargetController::class);
    });

    Route::middleware(['role:admin,crm'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/verifications', [\App\Http\Controllers\Crm\VerificationController::class, 'index'])->name('verifications');
        Route::get('/lead-board', [\App\Http\Controllers\SalesManager\LeadOverviewController::class, 'index'])->name('lead-board');
        Route::get('/lead-board/data', [\App\Http\Controllers\SalesManager\LeadOverviewController::class, 'data'])->name('lead-board.data');
        Route::get('/reports/99acres-lead-quality', [\App\Http\Controllers\Admin\DataIntelligenceController::class, 'legacyLeadQuality'])->name('reports.99acres-lead-quality');
    });

    Route::middleware(['role:admin'])->prefix('admin/mail-center')->name('admin.mail-center.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\MailCenterController::class, 'index'])->name('index');
        Route::get('/daily-report-preview', [\App\Http\Controllers\Admin\MailCenterController::class, 'previewDailyReport'])->name('daily-report-preview');
        Route::get('/daily-report-pdf', [\App\Http\Controllers\Admin\MailCenterController::class, 'downloadDailyReportPdf'])->name('daily-report-pdf');
        Route::post('/daily-report-send', [\App\Http\Controllers\Admin\MailCenterController::class, 'sendDailyReport'])->name('daily-report-send');
        Route::post('/high-budget-recipients', [\App\Http\Controllers\Admin\MailCenterController::class, 'updateHighBudgetRecipients'])->name('high-budget-recipients.update');
        Route::post('/high-budget-test-send', [\App\Http\Controllers\Admin\MailCenterController::class, 'sendHighBudgetTest'])->name('high-budget-test-send');
        Route::get('/{mailLog}', [\App\Http\Controllers\Admin\MailCenterController::class, 'show'])->name('show');
        Route::post('/{mailLog}/resend', [\App\Http\Controllers\Admin\MailCenterController::class, 'resend'])->name('resend');
    });

    Route::middleware(['role:admin,crm'])->prefix('admin/whatsapp-automation')->name('admin.whatsapp-automation.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'index'])->name('index');
        Route::get('/journeys/create', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'createJourney'])->name('journeys.create');
        Route::post('/journeys', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'storeJourney'])->name('journeys.store');
        Route::get('/journeys/{journey}/edit', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'editJourney'])->name('journeys.edit');
        Route::put('/journeys/{journey}', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'updateJourney'])->name('journeys.update');
        Route::post('/journeys/{journey}/toggle', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'toggleJourney'])->name('journeys.toggle');
        Route::post('/journeys/{journey}/clone', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'cloneJourney'])->name('journeys.clone');
        Route::get('/rules/create', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'createRule'])->name('rules.create');
        Route::post('/rules', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'storeRule'])->name('rules.store');
        Route::get('/rules/{rule}/edit', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'editRule'])->name('rules.edit');
        Route::put('/rules/{rule}', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'updateRule'])->name('rules.update');
        Route::post('/rules/{rule}/toggle', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'toggleRule'])->name('rules.toggle');
        Route::post('/rules/preview', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'previewRule'])->name('rules.preview');
    });

    Route::middleware(['role:admin,crm'])->prefix('admin/instagram-automation')->name('admin.instagram-automation.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'index'])->name('index');
        Route::get('/connect', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'connect'])->name('connect');
        Route::get('/callback', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'callback'])->name('callback');
        Route::post('/accounts/{account}/disconnect', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'disconnect'])->name('accounts.disconnect');
        Route::post('/accounts/{account}/refresh-token', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'refreshToken'])->name('accounts.refresh-token');
        Route::post('/rules', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'storeRule'])->name('rules.store');
        Route::put('/rules/{rule}', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'updateRule'])->name('rules.update');
        Route::post('/rules/{rule}/toggle', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'toggleRule'])->name('rules.toggle');
        Route::post('/flows', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'storeFlow'])->name('flows.store');
        Route::put('/flows/{flow}', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'updateFlow'])->name('flows.update');
        Route::post('/flows/{flow}/toggle', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'toggleFlow'])->name('flows.toggle');
        Route::post('/settings', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'updateSettings'])->name('settings.update');
        Route::post('/conversations/{conversation}/takeover', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'takeOverConversation'])->name('conversations.takeover');
        Route::post('/conversations/{conversation}/resume', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'resumeConversation'])->name('conversations.resume');
    });

    Route::middleware(['role:admin,crm'])->prefix('crm/whatsapp-automation')->name('crm.whatsapp-automation.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'index'])->name('index');
        Route::get('/journeys/create', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'createJourney'])->name('journeys.create');
        Route::post('/journeys', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'storeJourney'])->name('journeys.store');
        Route::get('/journeys/{journey}/edit', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'editJourney'])->name('journeys.edit');
        Route::put('/journeys/{journey}', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'updateJourney'])->name('journeys.update');
        Route::post('/journeys/{journey}/toggle', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'toggleJourney'])->name('journeys.toggle');
        Route::post('/journeys/{journey}/clone', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'cloneJourney'])->name('journeys.clone');
        Route::get('/rules/create', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'createRule'])->name('rules.create');
        Route::post('/rules', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'storeRule'])->name('rules.store');
        Route::get('/rules/{rule}/edit', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'editRule'])->name('rules.edit');
        Route::put('/rules/{rule}', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'updateRule'])->name('rules.update');
        Route::post('/rules/{rule}/toggle', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'toggleRule'])->name('rules.toggle');
        Route::post('/rules/preview', [\App\Http\Controllers\Admin\WhatsAppAutomationController::class, 'previewRule'])->name('rules.preview');
    });

    Route::middleware(['role:admin,crm'])->prefix('crm/instagram-automation')->name('crm.instagram-automation.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'index'])->name('index');
        Route::get('/connect', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'connect'])->name('connect');
        Route::get('/callback', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'callback'])->name('callback');
        Route::post('/accounts/{account}/disconnect', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'disconnect'])->name('accounts.disconnect');
        Route::post('/accounts/{account}/refresh-token', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'refreshToken'])->name('accounts.refresh-token');
        Route::post('/rules', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'storeRule'])->name('rules.store');
        Route::put('/rules/{rule}', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'updateRule'])->name('rules.update');
        Route::post('/rules/{rule}/toggle', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'toggleRule'])->name('rules.toggle');
        Route::post('/flows', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'storeFlow'])->name('flows.store');
        Route::put('/flows/{flow}', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'updateFlow'])->name('flows.update');
        Route::post('/flows/{flow}/toggle', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'toggleFlow'])->name('flows.toggle');
        Route::post('/settings', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'updateSettings'])->name('settings.update');
        Route::post('/conversations/{conversation}/takeover', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'takeOverConversation'])->name('conversations.takeover');
        Route::post('/conversations/{conversation}/resume', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'resumeConversation'])->name('conversations.resume');
    });

    Route::middleware(['role:admin,crm'])->prefix('crm/admin/instagram-automation')->name('crm.admin.instagram-automation.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'index'])->name('index');
        Route::get('/connect', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'connect'])->name('connect');
        Route::get('/callback', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'callback'])->name('callback');
        Route::post('/accounts/{account}/disconnect', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'disconnect'])->name('accounts.disconnect');
        Route::post('/accounts/{account}/refresh-token', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'refreshToken'])->name('accounts.refresh-token');
        Route::post('/rules', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'storeRule'])->name('rules.store');
        Route::put('/rules/{rule}', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'updateRule'])->name('rules.update');
        Route::post('/rules/{rule}/toggle', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'toggleRule'])->name('rules.toggle');
        Route::post('/flows', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'storeFlow'])->name('flows.store');
        Route::put('/flows/{flow}', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'updateFlow'])->name('flows.update');
        Route::post('/flows/{flow}/toggle', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'toggleFlow'])->name('flows.toggle');
        Route::post('/settings', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'updateSettings'])->name('settings.update');
        Route::post('/conversations/{conversation}/takeover', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'takeOverConversation'])->name('conversations.takeover');
        Route::post('/conversations/{conversation}/resume', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'resumeConversation'])->name('conversations.resume');
    });

    // Finance Manager Routes
    Route::middleware(['auth', 'role:finance_manager'])->prefix('finance-manager')->name('finance-manager.')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Finance\PayrollController::class, 'dashboard'])->name('dashboard');
        Route::get('/booked-customers', [\App\Http\Controllers\Finance\PayrollController::class, 'bookedCustomers'])->name('booked-customers');
        Route::get('/booked-customers/file', [\App\Http\Controllers\Finance\PayrollController::class, 'bookedCustomerFile'])->name('booked-customers.file');
        Route::get('/booked-customers/{siteVisit}/kyc', [\App\Http\Controllers\Finance\PayrollController::class, 'showBookedCustomerKyc'])->name('booked-customers.kyc.show');
        Route::post('/booked-customers/{siteVisit}/kyc', [\App\Http\Controllers\Finance\PayrollController::class, 'updateBookedCustomerKyc'])->name('booked-customers.kyc.update');
        Route::post('/booked-customers/{siteVisit}/revenue', [\App\Http\Controllers\Finance\PayrollController::class, 'updateBookedCustomerRevenue'])->name('booked-customers.revenue.update');
        Route::post('/booked-customers/incentives/{incentive}/approve', [\App\Http\Controllers\Finance\PayrollController::class, 'approveBookedCustomerIncentive'])->name('booked-customers.incentives.approve');
        Route::post('/booked-customers/incentives/{incentive}/reject', [\App\Http\Controllers\Finance\PayrollController::class, 'rejectBookedCustomerIncentive'])->name('booked-customers.incentives.reject');
        Route::get('/direct-closers/create', [\App\Http\Controllers\Finance\DirectCloserController::class, 'create'])->name('direct-closers.create');
        Route::post('/direct-closers', [\App\Http\Controllers\Finance\DirectCloserController::class, 'store'])->name('direct-closers.store');
        Route::get('/incentives', function () {
            return view('finance-manager.incentives');
        })->name('incentives');
        Route::prefix('expenses')->name('expenses.')->group(function () {
            Route::get('/dashboard', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'dashboard'])->name('dashboard');
            Route::get('/queue', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'queue'])->name('queue');
            Route::get('/monthly-report', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'monthlyReport'])->name('monthly-report');
            Route::get('/summary/print', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'printSummary'])->name('summary.print');
            Route::get('/summary/export', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'exportSummary'])->name('summary.export');
            Route::get('/entries', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'index'])->name('entries.index');
            Route::get('/entries/export', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'export'])->name('entries.export');
            Route::get('/entries/create', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'create'])->name('entries.create');
            Route::post('/entries', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'store'])->name('entries.store');
            Route::get('/entries/{entry}/attachment', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'downloadAttachment'])->name('entries.attachment.download');
            Route::get('/entries/{entry}/edit', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'edit'])->name('entries.edit');
            Route::put('/entries/{entry}', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'update'])->name('entries.update');
            Route::delete('/entries/{entry}', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'destroy'])->name('entries.destroy');
            Route::post('/entries/{entry}/approve', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'approve'])->name('entries.approve');
            Route::post('/entries/{entry}/reject', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'reject'])->name('entries.reject');
            Route::get('/companies', [\App\Http\Controllers\Expenses\ExpenseCompanyController::class, 'index'])->name('companies.index');
            Route::post('/companies', [\App\Http\Controllers\Expenses\ExpenseCompanyController::class, 'store'])->name('companies.store');
            Route::put('/companies/{company}', [\App\Http\Controllers\Expenses\ExpenseCompanyController::class, 'update'])->name('companies.update');
            Route::get('/categories', [\App\Http\Controllers\Expenses\ExpenseCategoryController::class, 'index'])->name('categories.index');
            Route::post('/categories', [\App\Http\Controllers\Expenses\ExpenseCategoryController::class, 'store'])->name('categories.store');
            Route::put('/categories/{category}', [\App\Http\Controllers\Expenses\ExpenseCategoryController::class, 'update'])->name('categories.update');
            Route::get('/subcategories', [\App\Http\Controllers\Expenses\ExpenseSubcategoryController::class, 'index'])->name('subcategories.index');
            Route::post('/subcategories', [\App\Http\Controllers\Expenses\ExpenseSubcategoryController::class, 'store'])->name('subcategories.store');
            Route::put('/subcategories/{subcategory}', [\App\Http\Controllers\Expenses\ExpenseSubcategoryController::class, 'update'])->name('subcategories.update');
        });
        Route::get('/settings', [\App\Http\Controllers\Finance\ExpenseSettingsController::class, 'index'])->name('settings');
        Route::post('/settings/payment-methods', [\App\Http\Controllers\Finance\ExpenseSettingsController::class, 'storePaymentMethod'])->name('settings.payment-methods.store');
        Route::put('/settings/payment-methods/{paymentMethod}', [\App\Http\Controllers\Finance\ExpenseSettingsController::class, 'updatePaymentMethod'])->name('settings.payment-methods.update');
        Route::post('/settings/po-access', [\App\Http\Controllers\Finance\ExpenseSettingsController::class, 'updatePoAccess'])->name('settings.po-access.update');
        Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Finance\PurchaseOrderController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\Finance\PurchaseOrderController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Finance\PurchaseOrderController::class, 'store'])->name('store');
            Route::get('/{purchaseOrder}', [\App\Http\Controllers\Finance\PurchaseOrderController::class, 'show'])->name('show');
            Route::get('/{purchaseOrder}/edit', [\App\Http\Controllers\Finance\PurchaseOrderController::class, 'edit'])->name('edit');
            Route::put('/{purchaseOrder}', [\App\Http\Controllers\Finance\PurchaseOrderController::class, 'update'])->name('update');
            Route::post('/{purchaseOrder}/submit', [\App\Http\Controllers\Finance\PurchaseOrderController::class, 'submit'])->name('submit');
            Route::post('/{purchaseOrder}/cancel', [\App\Http\Controllers\Finance\PurchaseOrderController::class, 'cancel'])->name('cancel');
            Route::post('/{purchaseOrder}/delete', [\App\Http\Controllers\Finance\PurchaseOrderController::class, 'destroy'])->name('destroy');
            Route::post('/{purchaseOrder}/request-delete', [\App\Http\Controllers\Finance\PurchaseOrderController::class, 'requestDelete'])->name('request-delete');
            Route::post('/{purchaseOrder}/mark-paid', [\App\Http\Controllers\Finance\PurchaseOrderController::class, 'markPaid'])->name('mark-paid');
            Route::post('/{purchaseOrder}/receive', [\App\Http\Controllers\Finance\PurchaseOrderController::class, 'receive'])->name('receive');
            Route::get('/{purchaseOrder}/attachment', [\App\Http\Controllers\Finance\PurchaseOrderController::class, 'downloadAttachment'])->name('attachment.download');
            Route::get('/{purchaseOrder}/pdf', [\App\Http\Controllers\Finance\PurchaseOrderController::class, 'downloadPdf'])->name('pdf.download');
            Route::get('/payments/{payment}/proof', [\App\Http\Controllers\Finance\PurchaseOrderController::class, 'downloadPaymentProof'])->name('payments.proof.download');
        });
        Route::get('/payroll', [\App\Http\Controllers\Finance\PayrollController::class, 'payroll'])->name('payroll');
        Route::post('/payroll/freeze', [\App\Http\Controllers\Finance\PayrollController::class, 'freeze'])->name('payroll.freeze');
        Route::post('/payroll/{freeze}/release', [\App\Http\Controllers\Finance\PayrollController::class, 'release'])->name('payroll.release');
        Route::get('/payroll/export', [\App\Http\Controllers\Finance\PayrollController::class, 'export'])->name('payroll.export');
        Route::get('/payroll/export-rich', [\App\Http\Controllers\Api\AttendanceReportExportController::class, 'payroll'])->name('payroll.export-rich');
        Route::get('/payslips', [\App\Http\Controllers\Finance\PayslipController::class, 'index'])->name('payslips.index');
        Route::post('/payslips/adjustments', [\App\Http\Controllers\Finance\PayslipController::class, 'storeAdjustment'])->name('payslips.adjustments.store');
        Route::post('/payslips/generate', [\App\Http\Controllers\Finance\PayslipController::class, 'generate'])->name('payslips.generate');
        Route::post('/payslips/{payslip}/mark-paid', [\App\Http\Controllers\Finance\PayslipController::class, 'markPaid'])->name('payslips.mark-paid');
        Route::get('/payslips/{payslip}/download', [\App\Http\Controllers\Finance\PayslipController::class, 'download'])->name('payslips.download');
    });

    Route::middleware(['auth', 'role:admin,finance_manager'])->prefix('post-sales')->name('post-sales.')->group(function () {
        Route::post('/initialize', [\App\Http\Controllers\PostSalesController::class, 'initialize'])->name('initialize');
        Route::get('/', [\App\Http\Controllers\PostSalesController::class, 'index'])->name('index');
        Route::get('/data', [\App\Http\Controllers\PostSalesController::class, 'data'])->name('data');
        Route::get('/export', [\App\Http\Controllers\PostSalesController::class, 'export'])->name('export');
        Route::post('/bulk-update', [\App\Http\Controllers\PostSalesController::class, 'bulkUpdate'])->name('bulk-update');
        Route::post('/cases/{postSaleCase}/activate', [\App\Http\Controllers\PostSalesController::class, 'activate'])->name('cases.activate');
        Route::post('/cases/{postSaleCase}/cancel', [\App\Http\Controllers\PostSalesController::class, 'cancel'])->name('cases.cancel');
        Route::post('/cases/{postSaleCase}/demands', [\App\Http\Controllers\PostSalesController::class, 'storeDemand'])->name('demands.store');
        Route::post('/cases/{postSaleCase}/transactions', [\App\Http\Controllers\PostSalesController::class, 'storeTransaction'])->name('transactions.store');
        Route::post('/transactions/{transaction}/verify', [\App\Http\Controllers\PostSalesController::class, 'verifyTransaction'])->name('transactions.verify');
        Route::post('/documents/{document}', [\App\Http\Controllers\PostSalesController::class, 'updateDocument'])->name('documents.update');
        Route::post('/cases/{postSaleCase}/claims', [\App\Http\Controllers\PostSalesController::class, 'storeClaim'])->name('claims.store');
        Route::post('/claims/{claim}/receipts', [\App\Http\Controllers\PostSalesController::class, 'storeReceipt'])->name('receipts.store');
        Route::post('/projects/quick-store', [\App\Http\Controllers\PostSalesController::class, 'quickStoreProject'])->name('projects.quick-store');
        Route::post('/templates', [\App\Http\Controllers\PostSalesController::class, 'storeTemplate'])->name('templates.store');
        Route::put('/schemes/{scheme}', [\App\Http\Controllers\PostSalesController::class, 'updateScheme'])->name('schemes.update');
        Route::post('/invoices', [\App\Http\Controllers\PostSalesController::class, 'createInvoice'])->name('invoices.store');
        Route::get('/invoices/{invoice}/edit', [\App\Http\Controllers\PostSalesController::class, 'editInvoice'])->name('invoices.edit');
        Route::put('/invoices/{invoice}', [\App\Http\Controllers\PostSalesController::class, 'updateInvoice'])->name('invoices.update');
        Route::post('/invoices/{invoice}/issue', [\App\Http\Controllers\PostSalesController::class, 'issueInvoice'])->name('invoices.issue');
        Route::get('/invoices/{invoice}/download', [\App\Http\Controllers\PostSalesController::class, 'downloadInvoice'])->name('invoices.download');
        Route::post('/demands/{demand}/send-test', [\App\Http\Controllers\PostSalesController::class, 'sendTestDemand'])->name('demands.send-test');
        Route::get('/files/{type}/{id}', [\App\Http\Controllers\PostSalesController::class, 'file'])->name('files.download');
    });

    Route::middleware(['auth', 'role:admin,marketing_manager,marketing_executive'])->prefix('marketing')->name('marketing.')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\MarketingController::class, 'dashboard'])->name('dashboard');
        Route::get('/profile', [\App\Http\Controllers\MarketingController::class, 'profile'])->name('profile');
        Route::post('/self-todos', [\App\Http\Controllers\MarketingSelfTodoController::class, 'store'])->name('self-todos.store');
        Route::put('/self-todos/{todo}', [\App\Http\Controllers\MarketingSelfTodoController::class, 'update'])->name('self-todos.update');
        Route::patch('/self-todos/{todo}/complete', [\App\Http\Controllers\MarketingSelfTodoController::class, 'toggleComplete'])->name('self-todos.complete');
        Route::delete('/self-todos/{todo}', [\App\Http\Controllers\MarketingSelfTodoController::class, 'destroy'])->name('self-todos.destroy');
        Route::post('/manager-notes', [\App\Http\Controllers\MarketingManagerNoteController::class, 'store'])->name('manager-notes.store');
        Route::put('/manager-notes/{note}', [\App\Http\Controllers\MarketingManagerNoteController::class, 'update'])->name('manager-notes.update');
        Route::patch('/manager-notes/{note}/archive', [\App\Http\Controllers\MarketingManagerNoteController::class, 'archive'])->name('manager-notes.archive');
    });

    Route::middleware(['auth'])->prefix('purchase-orders')->name('purchase-orders.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PurchaseOrderController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\PurchaseOrderController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\PurchaseOrderController::class, 'store'])->name('store');
        Route::get('/{purchaseOrder}', [\App\Http\Controllers\PurchaseOrderController::class, 'show'])->name('show');
        Route::get('/{purchaseOrder}/edit', [\App\Http\Controllers\PurchaseOrderController::class, 'edit'])->name('edit');
        Route::put('/{purchaseOrder}', [\App\Http\Controllers\PurchaseOrderController::class, 'update'])->name('update');
        Route::post('/{purchaseOrder}/submit', [\App\Http\Controllers\PurchaseOrderController::class, 'submit'])->name('submit');
        Route::post('/{purchaseOrder}/cancel', [\App\Http\Controllers\PurchaseOrderController::class, 'cancel'])->name('cancel');
        Route::get('/{purchaseOrder}/attachment', [\App\Http\Controllers\PurchaseOrderController::class, 'downloadAttachment'])->name('attachment.download');
        Route::get('/{purchaseOrder}/pdf', [\App\Http\Controllers\PurchaseOrderController::class, 'downloadPdf'])->name('pdf.download');
    });
    
    // Announcements (Admin/CRM/HR)
    Route::middleware(['auth', 'role:admin,crm,hr_manager'])->group(function () {
        Route::get('/admin/broadcast', function () {
            return view('admin.broadcast');
        })->name('admin.broadcast');
    });

    Route::middleware(['auth'])->get('/lead-quality-auditor/dashboard', [\App\Http\Controllers\Admin\LeadAuditorDashboardController::class, 'index'])
        ->name('lead-quality-auditor.dashboard');
    Route::middleware(['auth', 'role:lead_quality_auditor'])->prefix('lead-quality-auditor')->name('lead-quality-auditor.')->group(function () {
        Route::get('/profile', [\App\Http\Controllers\Admin\InsightSheetController::class, 'profile'])->name('profile');
        Route::get('/dashboard/summary', [\App\Http\Controllers\Admin\LeadAuditorDashboardController::class, 'summary'])->name('dashboard.summary');
        Route::get('/dashboard/untouched', [\App\Http\Controllers\Admin\LeadAuditorDashboardController::class, 'untouched'])->name('dashboard.untouched');
        Route::get('/dashboard/tasks', [\App\Http\Controllers\Admin\LeadAuditorDashboardController::class, 'tasks'])->name('dashboard.tasks');
        Route::get('/dashboard/details', [\App\Http\Controllers\Admin\LeadAuditorDashboardController::class, 'details'])->name('dashboard.details');
        Route::get('/activity-calendar', [\App\Http\Controllers\Admin\ActivityCalendarController::class, 'index'])->name('activity-calendar.index');
        Route::get('/activity-calendar/events', [\App\Http\Controllers\Admin\ActivityCalendarController::class, 'events'])->name('activity-calendar.events');
        Route::get('/activity-calendar/leads', [\App\Http\Controllers\Admin\ActivityCalendarController::class, 'leads'])->name('activity-calendar.leads');
        Route::get('/activity-calendar/{type}/{id}', [\App\Http\Controllers\Admin\ActivityCalendarController::class, 'show'])->name('activity-calendar.show');
        Route::post('/activity-calendar', [\App\Http\Controllers\Admin\ActivityCalendarController::class, 'store'])->name('activity-calendar.store');
        Route::patch('/activity-calendar/{type}/{id}', [\App\Http\Controllers\Admin\ActivityCalendarController::class, 'update'])->name('activity-calendar.update');
        Route::post('/activity-calendar/{type}/{id}/complete', [\App\Http\Controllers\Admin\ActivityCalendarController::class, 'complete'])->name('activity-calendar.complete');
        Route::get('/detailed-lead-off', [\App\Http\Controllers\TelecallerStatusController::class, 'index'])->name('lead-off.index');
        Route::post('/detailed-lead-off', [\App\Http\Controllers\TelecallerStatusController::class, 'updateStatus'])->name('lead-off.update');
    });

    Route::middleware(['auth'])->prefix('admin/insight-sheet')->name('admin.insight-sheet.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\InsightSheetController::class, 'index'])->name('index');
        Route::get('/data', [\App\Http\Controllers\Admin\InsightSheetController::class, 'data'])->name('data');
        Route::get('/audit', [\App\Http\Controllers\Admin\InsightSheetController::class, 'audit'])->name('audit');
        Route::get('/remarks', [\App\Http\Controllers\Admin\InsightSheetController::class, 'remarks'])->name('remarks');
        Route::get('/details', [\App\Http\Controllers\Admin\InsightSheetController::class, 'details'])->name('details');
        Route::post('/transfer', [\App\Http\Controllers\Admin\InsightSheetController::class, 'transferLead'])->name('transfer');
        Route::post('/details/override', [\App\Http\Controllers\Admin\InsightSheetController::class, 'saveDetailsOverride'])->name('details.override');
        Route::get('/completion-details', [\App\Http\Controllers\Admin\InsightSheetController::class, 'completionDetails'])->name('completion-details');
        Route::post('/layout', [\App\Http\Controllers\Admin\InsightSheetController::class, 'updateLayout'])->name('layout.update');
        Route::get('/access', [\App\Http\Controllers\Admin\InsightSheetController::class, 'access'])->name('access');
        Route::post('/access', [\App\Http\Controllers\Admin\InsightSheetController::class, 'updateAccess'])->name('access.update');
        Route::post('/cells', [\App\Http\Controllers\Admin\InsightSheetController::class, 'saveCell'])->name('cells.save');
        Route::post('/cells/bulk', [\App\Http\Controllers\Admin\InsightSheetController::class, 'saveBulk'])->name('cells.bulk-save');
        Route::delete('/cells', [\App\Http\Controllers\Admin\InsightSheetController::class, 'resetCell'])->name('cells.reset');
        Route::get('/export', [\App\Http\Controllers\Admin\InsightSheetController::class, 'export'])->name('export');
    });

    Route::middleware(['auth'])->get('/announcements/{broadcast}/attachment', [\App\Http\Controllers\Api\BroadcastController::class, 'downloadAttachment'])
        ->name('announcements.attachment');

    // CRM danger: delete all leads (password required)
    Route::middleware(['auth', 'role:admin,crm'])->post('/crm/danger/delete-all-leads', [\App\Http\Controllers\Crm\CrmDangerController::class, 'deleteAllLeads'])->name('crm.danger.delete-all-leads');

    Route::middleware(['auth', 'role:admin,crm'])->prefix('whatsapp-control-center')->name('whatsapp-control-center.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\WhatsAppControlCenterController::class, 'index'])->name('index');
        Route::post('/conversations/{conversation}/assign', [\App\Http\Controllers\Admin\WhatsAppControlCenterController::class, 'assignConversation'])->name('conversations.assign');
        Route::post('/conversations/{conversation}/status', [\App\Http\Controllers\Admin\WhatsAppControlCenterController::class, 'updateConversationStatus'])->name('conversations.status');
        Route::post('/conversations/{conversation}/notes', [\App\Http\Controllers\Admin\WhatsAppControlCenterController::class, 'storeConversationNote'])->name('conversations.notes');
        Route::post('/quick-replies', [\App\Http\Controllers\Admin\WhatsAppControlCenterController::class, 'storeQuickReply'])->name('quick-replies.store');
        Route::put('/quick-replies/{quickReply}', [\App\Http\Controllers\Admin\WhatsAppControlCenterController::class, 'updateQuickReply'])->name('quick-replies.update');
        Route::delete('/quick-replies/{quickReply}', [\App\Http\Controllers\Admin\WhatsAppControlCenterController::class, 'deleteQuickReply'])->name('quick-replies.delete');
        Route::post('/routing-rules', [\App\Http\Controllers\Admin\WhatsAppControlCenterController::class, 'storeRoutingRule'])->name('routing-rules.store');
        Route::put('/routing-rules/{routingRule}', [\App\Http\Controllers\Admin\WhatsAppControlCenterController::class, 'updateRoutingRule'])->name('routing-rules.update');
        Route::delete('/routing-rules/{routingRule}', [\App\Http\Controllers\Admin\WhatsAppControlCenterController::class, 'deleteRoutingRule'])->name('routing-rules.delete');
        Route::post('/campaign-recipients/{recipient}/retry', [\App\Http\Controllers\Admin\WhatsAppControlCenterController::class, 'retryRecipient'])->name('campaign-recipients.retry');
        Route::post('/leads/{lead}/whatsapp-opt-out', [\App\Http\Controllers\Admin\WhatsAppControlCenterController::class, 'updateLeadOptOut'])->name('leads.opt-out');
    });

    Route::middleware(['auth', 'role:admin,crm'])->prefix('template_management')->name('template-management.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\TemplateManagementController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Admin\TemplateManagementController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Admin\TemplateManagementController::class, 'store'])->name('store');
        Route::get('/{template}/edit', [\App\Http\Controllers\Admin\TemplateManagementController::class, 'edit'])->name('edit');
        Route::put('/{template}', [\App\Http\Controllers\Admin\TemplateManagementController::class, 'update'])->name('update');
    });

    // Admin Dashboard (Admin only)
    // Integration Routes (Admin only)
    Route::middleware(['auth', 'role:admin'])->prefix('integrations')->name('integrations.')->group(function () {
        Route::get('/', [\App\Http\Controllers\IntegrationController::class, 'index'])->name('index');
        Route::get('/sheet-integration', function () {
            return view('integrations.sheet-integration');
        })->name('sheet-integration');
        Route::get('/sheet-sync', function () {
            return view('integrations.sheet-sync');
        })->name('sheet-sync');
        Route::get('/email', function () {
            return view('integrations.coming-soon', ['integration' => 'Email']);
        })->name('email');
        Route::get('/calendar', function () {
            return view('integrations.coming-soon', ['integration' => 'Calendar']);
        })->name('calendar');
        Route::get('/click2api-whatsapp', function () {
            return view('integrations.click2api-whatsapp', [
                'webhookUrl' => url('/api/webhooks/whatsapp/incoming'),
                'recommendedTitle' => 'Base Infra CRM Webhook',
                'whatsappNumber' => '919919944407',
                'events' => [
                    'Incoming Messages',
                    'Failed',
                    'Sent',
                    'Delivered',
                    'Read',
                    'Deleted',
                    'Template',
                    'Phone Number',
                    'Account',
                    'Outgoing Messages',
                ],
            ]);
        })->name('click2api-whatsapp');
        // WhatsApp Integration Routes
        Route::get('/whatsapp', [\App\Http\Controllers\Admin\WhatsAppIntegrationController::class, 'index'])->name('whatsapp');
        Route::post('/whatsapp/update', [\App\Http\Controllers\Admin\WhatsAppIntegrationController::class, 'updateSettings'])->name('whatsapp.update');
        Route::post('/whatsapp/automation', [\App\Http\Controllers\Admin\WhatsAppIntegrationController::class, 'updateAutomation'])->name('whatsapp.automation');
        Route::post('/whatsapp/verify', [\App\Http\Controllers\Admin\WhatsAppIntegrationController::class, 'verifyConnection'])->name('whatsapp.verify');
        Route::post('/whatsapp/test', [\App\Http\Controllers\Admin\WhatsAppIntegrationController::class, 'testMessage'])->name('whatsapp.test');

        Route::prefix('meta-waba')->name('meta-waba.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\MetaWabaIntegrationController::class, 'index'])->name('index');
            Route::post('/update', [\App\Http\Controllers\Admin\MetaWabaIntegrationController::class, 'update'])->name('update');
            Route::post('/embedded-signup/start', [\App\Http\Controllers\Admin\MetaWabaIntegrationController::class, 'startEmbeddedSignup'])->name('embedded.start');
            Route::post('/embedded-signup/finish', [\App\Http\Controllers\Admin\MetaWabaIntegrationController::class, 'finishEmbeddedSignup'])->name('embedded.finish');
            Route::post('/disconnect', [\App\Http\Controllers\Admin\MetaWabaIntegrationController::class, 'disconnect'])->name('disconnect');
            Route::post('/set-default', [\App\Http\Controllers\Admin\MetaWabaIntegrationController::class, 'setDefault'])->name('set-default');
            Route::post('/verify', [\App\Http\Controllers\Admin\MetaWabaIntegrationController::class, 'verify'])->name('verify');
            Route::post('/register-phone', [\App\Http\Controllers\Admin\MetaWabaIntegrationController::class, 'registerPhone'])->name('register-phone');
            Route::post('/templates/create', [\App\Http\Controllers\Admin\MetaWabaIntegrationController::class, 'createTemplate'])->name('templates.create');
            Route::post('/templates/sync', [\App\Http\Controllers\Admin\MetaWabaIntegrationController::class, 'syncTemplates'])->name('templates.sync');
            Route::delete('/templates/{template}', [\App\Http\Controllers\Admin\MetaWabaIntegrationController::class, 'deleteTemplate'])->name('templates.delete');
            Route::post('/test-template', [\App\Http\Controllers\Admin\MetaWabaIntegrationController::class, 'testTemplate'])->name('test-template');
            Route::post('/calls/refresh', [\App\Http\Controllers\Admin\MetaWabaIntegrationController::class, 'refreshCallSettings'])->name('calls.refresh');
            Route::post('/calls/update', [\App\Http\Controllers\Admin\MetaWabaIntegrationController::class, 'updateCallSettings'])->name('calls.update');
            Route::post('/campaigns/preview', [\App\Http\Controllers\Admin\MetaWabaIntegrationController::class, 'previewCampaign'])->name('campaigns.preview');
            Route::post('/campaigns', [\App\Http\Controllers\Admin\MetaWabaIntegrationController::class, 'storeCampaign'])->name('campaigns.store');
        });
        
        // WhatsApp Template Management Routes
        Route::post('/whatsapp/templates/create', [\App\Http\Controllers\Admin\WhatsAppIntegrationController::class, 'createTemplate'])->name('whatsapp.templates.create');
        Route::get('/whatsapp/templates/{id}', [\App\Http\Controllers\Admin\WhatsAppIntegrationController::class, 'getTemplate'])->name('whatsapp.templates.show');
        Route::delete('/whatsapp/templates/{id}', [\App\Http\Controllers\Admin\WhatsAppIntegrationController::class, 'deleteTemplate'])->name('whatsapp.templates.delete');
        
        // WhatsApp Groups Routes (Optional)
        Route::get('/whatsapp/groups', [\App\Http\Controllers\Admin\WhatsAppIntegrationController::class, 'getGroups'])->name('whatsapp.groups.index');
        Route::post('/whatsapp/groups', [\App\Http\Controllers\Admin\WhatsAppIntegrationController::class, 'createGroup'])->name('whatsapp.groups.create');
        Route::put('/whatsapp/groups/{id}', [\App\Http\Controllers\Admin\WhatsAppIntegrationController::class, 'updateGroup'])->name('whatsapp.groups.update');
        Route::delete('/whatsapp/groups/{id}', [\App\Http\Controllers\Admin\WhatsAppIntegrationController::class, 'removeGroup'])->name('whatsapp.groups.delete');
        
        // WhatsApp Contacts Routes (Optional)
        Route::post('/whatsapp/contacts/import', [\App\Http\Controllers\Admin\WhatsAppIntegrationController::class, 'importContact'])->name('whatsapp.contacts.import');
        Route::put('/whatsapp/contacts/{id}', [\App\Http\Controllers\Admin\WhatsAppIntegrationController::class, 'updateContact'])->name('whatsapp.contacts.update');
        Route::delete('/whatsapp/contacts/{id}', [\App\Http\Controllers\Admin\WhatsAppIntegrationController::class, 'removeContact'])->name('whatsapp.contacts.delete');
        Route::post('/whatsapp/contacts/bulk', [\App\Http\Controllers\Admin\WhatsAppIntegrationController::class, 'addContacts'])->name('whatsapp.contacts.bulk');
        
        // WhatsApp Debug Routes
        Route::get('/whatsapp/debug', [\App\Http\Controllers\Admin\WhatsAppDebugController::class, 'testConnection'])->name('whatsapp.debug');
        Route::post('/whatsapp/debug/post', [\App\Http\Controllers\Admin\WhatsAppDebugController::class, 'testPostEndpoint'])->name('whatsapp.debug.post');
        Route::post('/whatsapp/debug/curl', [\App\Http\Controllers\Admin\WhatsAppDebugController::class, 'testRawCurl'])->name('whatsapp.debug.curl');

        // WhatsApp Quick Test Routes
        Route::get('/whatsapp/quick-test', [\App\Http\Controllers\Admin\WhatsAppTestController::class, 'quickTest'])->name('whatsapp.quick-test');
        Route::post('/whatsapp/quick-test/send', [\App\Http\Controllers\Admin\WhatsAppTestController::class, 'sendQuickTest'])->name('whatsapp.quick-test.send');
        
        // Pabbly Integration Routes
        Route::get('/pabbly', [\App\Http\Controllers\Admin\PabblyIntegrationController::class, 'index'])->name('pabbly');
        Route::post('/pabbly/update', [\App\Http\Controllers\Admin\PabblyIntegrationController::class, 'updateSettings'])->name('pabbly.update');
        Route::post('/pabbly/test', [\App\Http\Controllers\Admin\PabblyIntegrationController::class, 'testWebhook'])->name('pabbly.test');
        Route::get('/pabbly/logs', [\App\Http\Controllers\Admin\PabblyIntegrationController::class, 'getWebhookLogs'])->name('pabbly.logs');

        // MCube Integration Routes
        Route::prefix('mcube')->name('mcube.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\McubeIntegrationController::class, 'index'])->name('index');
            Route::get('/outbound', [\App\Http\Controllers\Admin\McubeIntegrationController::class, 'outbound'])->name('outbound');
            Route::post('/settings', [\App\Http\Controllers\Admin\McubeIntegrationController::class, 'updateSettings'])->name('settings.update');
            Route::get('/generate-token', [\App\Http\Controllers\Admin\McubeIntegrationController::class, 'generateToken'])->name('generate-token');
            Route::post('/test', [\App\Http\Controllers\Admin\McubeIntegrationController::class, 'testWebhook'])->name('test');
            Route::post('/test-outbound', [\App\Http\Controllers\Admin\McubeIntegrationController::class, 'testOutboundCall'])->name('test-outbound');
        });

        Route::prefix('bulksmsplans-ivr')->name('bulksmsplans-ivr.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\BulkSmsPlansIvrIntegrationController::class, 'index'])->name('index');
            Route::post('/settings', [\App\Http\Controllers\Admin\BulkSmsPlansIvrIntegrationController::class, 'updateSettings'])->name('settings.update');
            Route::get('/generate-token', [\App\Http\Controllers\Admin\BulkSmsPlansIvrIntegrationController::class, 'generateToken'])->name('generate-token');
            Route::post('/test', [\App\Http\Controllers\Admin\BulkSmsPlansIvrIntegrationController::class, 'testWebhook'])->name('test');
        });
        
        // Google Sheets Integration Route (redirects to lead import page)
        Route::get('/google-sheets', function () {
            return redirect()->route('lead-import.index');
        })->name('google-sheets');
        
        // Form Integration Routes (Google Sheets Form Integration)
        Route::prefix('form-integration')->name('form-integration.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\FormIntegrationController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\Admin\FormIntegrationController::class, 'create'])->name('create');
            Route::post('/step1', [\App\Http\Controllers\Admin\FormIntegrationController::class, 'storeStep1'])->name('store-step1');
            Route::get('/step2/{id}', [\App\Http\Controllers\Admin\FormIntegrationController::class, 'step2'])->name('step2');
            Route::post('/step2/{id}', [\App\Http\Controllers\Admin\FormIntegrationController::class, 'storeStep2'])->name('store-step2');
            Route::get('/step3/{id}', [\App\Http\Controllers\Admin\FormIntegrationController::class, 'step3'])->name('step3');
            Route::post('/step3/{id}', [\App\Http\Controllers\Admin\FormIntegrationController::class, 'storeStep3'])->name('store-step3');
            Route::get('/step4/{id}', [\App\Http\Controllers\Admin\FormIntegrationController::class, 'step4'])->name('step4');
            Route::post('/step4/{id}', [\App\Http\Controllers\Admin\FormIntegrationController::class, 'storeStep4'])->name('store-step4');
            Route::get('/step5/{id}', [\App\Http\Controllers\Admin\FormIntegrationController::class, 'step5'])->name('step5');
            Route::post('/step5/{id}', [\App\Http\Controllers\Admin\FormIntegrationController::class, 'storeStep5'])->name('store-step5');
            Route::get('/step6/{id}', [\App\Http\Controllers\Admin\FormIntegrationController::class, 'step6'])->name('step6');
            Route::post('/auto-detect-columns', [\App\Http\Controllers\Admin\FormIntegrationController::class, 'autoDetectColumns'])->name('auto-detect-columns');
            Route::get('/generate-script/{id}', [\App\Http\Controllers\Admin\FormIntegrationController::class, 'generateScript'])->name('generate-script');
            Route::post('/test/{id}', [\App\Http\Controllers\Admin\FormIntegrationController::class, 'test'])->name('test');
            Route::post('/rotate-secret/{id}', [\App\Http\Controllers\Admin\FormIntegrationController::class, 'rotateSecret'])->name('rotate-secret');
            Route::get('/template', [\App\Http\Controllers\Admin\FormIntegrationController::class, 'getFormTemplate'])->name('template');
            Route::post('/toggle/{id}', [\App\Http\Controllers\Admin\FormIntegrationController::class, 'toggle'])->name('toggle');
        });

        Route::prefix('website')->name('website.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\WebsiteIntegrationController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\Admin\WebsiteIntegrationController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Admin\WebsiteIntegrationController::class, 'store'])->name('store');
            Route::get('/{website}/edit', [\App\Http\Controllers\Admin\WebsiteIntegrationController::class, 'edit'])->name('edit');
            Route::put('/{website}', [\App\Http\Controllers\Admin\WebsiteIntegrationController::class, 'update'])->name('update');
            Route::post('/preview', [\App\Http\Controllers\Admin\WebsiteIntegrationController::class, 'preview'])->name('preview');
            Route::post('/templates', [\App\Http\Controllers\Admin\WebsiteIntegrationController::class, 'templates'])->name('templates');
            Route::post('/{website}/test', [\App\Http\Controllers\Admin\WebsiteIntegrationController::class, 'test'])->name('test');
            Route::post('/{website}/regenerate-key', [\App\Http\Controllers\Admin\WebsiteIntegrationController::class, 'regenerateApiKey'])->name('regenerate-key');
        });
        
        // Meta Sheet Integration Routes (Meta/Facebook only)
        Route::prefix('meta-sheet')->name('meta-sheet.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\MetaSheetController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\Admin\MetaSheetController::class, 'create'])->name('create');
            Route::post('/step1', [\App\Http\Controllers\Admin\MetaSheetController::class, 'storeStep1'])->name('store-step1');
            Route::get('/step2/{id}', [\App\Http\Controllers\Admin\MetaSheetController::class, 'step2'])->name('step2');
            Route::post('/step2/{id}', [\App\Http\Controllers\Admin\MetaSheetController::class, 'storeStep2'])->name('store-step2');
            Route::get('/step3/{id}', [\App\Http\Controllers\Admin\MetaSheetController::class, 'step3'])->name('step3');
            Route::post('/step3/{id}', [\App\Http\Controllers\Admin\MetaSheetController::class, 'storeStep3'])->name('store-step3');
            Route::get('/step4/{id}', [\App\Http\Controllers\Admin\MetaSheetController::class, 'step4'])->name('step4');
            Route::post('/step4/{id}', [\App\Http\Controllers\Admin\MetaSheetController::class, 'storeStep4'])->name('store-step4');
            Route::get('/step5/{id}', [\App\Http\Controllers\Admin\MetaSheetController::class, 'step5'])->name('step5');
            Route::post('/step5/{id}', [\App\Http\Controllers\Admin\MetaSheetController::class, 'storeStep5'])->name('store-step5');
            Route::get('/step6/{id}', [\App\Http\Controllers\Admin\MetaSheetController::class, 'step6'])->name('step6');
            Route::post('/auto-detect-columns', [\App\Http\Controllers\Admin\MetaSheetController::class, 'autoDetectColumns'])->name('auto-detect-columns');
            Route::post('/create-custom-field', [\App\Http\Controllers\Admin\MetaSheetController::class, 'createCustomField'])->name('create-custom-field');
            Route::post('/save-draft/{id}', [\App\Http\Controllers\Admin\MetaSheetController::class, 'saveDraft'])->name('save-draft');
            Route::get('/generate-script/{id}', [\App\Http\Controllers\Admin\MetaSheetController::class, 'generateScript'])->name('generate-script');
            Route::post('/test/{id}', [\App\Http\Controllers\Admin\MetaSheetController::class, 'test'])->name('test');
            Route::post('/rotate-secret/{id}', [\App\Http\Controllers\Admin\MetaSheetController::class, 'rotateSecret'])->name('rotate-secret');
            Route::post('/sync/{id}', [\App\Http\Controllers\Admin\MetaSheetController::class, 'sync'])->name('sync');
            Route::post('/delete/{id}', [\App\Http\Controllers\Admin\MetaSheetController::class, 'delete'])->name('delete');
            Route::post('/toggle/{id}', [\App\Http\Controllers\Admin\MetaSheetController::class, 'toggle'])->name('toggle');
        });

        // Google Sheet import cron monitor
        Route::get('/google-sheet-import-monitor', [\App\Http\Controllers\Admin\GoogleSheetImportMonitorController::class, 'index'])
            ->name('google-sheet-import-monitor');
        
        Route::get('/facebook', function () {
            return view('integrations.coming-soon', ['integration' => 'Facebook Meta']);
        })->name('facebook');

        // Facebook OAuth Connector (parallel App Review flow; old manual token flow remains separate)
        Route::prefix('facebook-connector')->name('facebook-connector.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\MetaFacebookConnectorController::class, 'index'])->name('index');
            Route::get('/connect', [\App\Http\Controllers\Admin\MetaFacebookConnectorController::class, 'connect'])->name('connect');
            Route::get('/callback', [\App\Http\Controllers\Admin\MetaFacebookConnectorController::class, 'callback'])->name('callback');
            Route::post('/pages/{page}/subscribe', [\App\Http\Controllers\Admin\MetaFacebookConnectorController::class, 'subscribePage'])->name('pages.subscribe');
            Route::post('/pages/{page}/forms/refresh', [\App\Http\Controllers\Admin\MetaFacebookConnectorController::class, 'refreshForms'])->name('pages.forms.refresh');
            Route::post('/pages/{page}/mode', [\App\Http\Controllers\Admin\MetaFacebookConnectorController::class, 'updatePageMode'])->name('pages.mode');
            Route::post('/events/{event}/process', [\App\Http\Controllers\Admin\MetaFacebookConnectorController::class, 'processEvent'])->name('events.process');
            Route::post('/disconnect', [\App\Http\Controllers\Admin\MetaFacebookConnectorController::class, 'disconnect'])->name('disconnect');
        });

        // Facebook Lead Ads (standalone - direct webhook + Graph API; does not touch Meta Sheet / Form Integration)
        Route::prefix('facebook-lead-ads')->name('facebook-lead-ads.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'index'])->name('index');
            Route::get('/settings', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'settings'])->name('settings');
            Route::post('/settings', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'updateSettings'])->name('settings.update');
            Route::post('/test-connection', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'testConnection'])->name('test-connection');
            Route::post('/test-marketing-connection', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'testMarketingConnection'])->name('test-marketing-connection');
            Route::get('/forms', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'forms'])->name('forms');
            Route::get('/diagnostics', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'diagnostics'])->name('diagnostics');
            Route::get('/missing-checker', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'missingChecker'])->name('missing-checker');
            Route::post('/missing-checker/preview', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'previewMissingChecker'])->name('missing-checker.preview');
            Route::post('/missing-checker/import', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'importMissingLeads'])->name('missing-checker.import');
            Route::prefix('bulk-recovery')->name('bulk-recovery.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'bulkRecoveryIndex'])->name('index');
                Route::post('/scans', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'bulkRecoveryStoreScan'])->name('scans.store');
                Route::get('/scans/{scan}', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'bulkRecoveryShow'])->name('show');
                Route::post('/scans/{scan}/import', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'bulkRecoveryImport'])->name('import');
            });
            Route::post('/retry-failed-webhooks', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'retryFailedWebhooks'])->name('retry-failed-webhooks');
            Route::post('/portfolios', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'storePortfolio'])->name('portfolios.store');
            Route::put('/portfolios/{portfolio}', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'updatePortfolio'])->name('portfolios.update');
            Route::post('/sync-forms', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'syncForms'])->name('forms.sync-all');
            Route::get('/pages/{page}/detail', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'pageDetail'])->name('pages.detail');
            Route::post('/pages/{page}/callback', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'callbackPageLeads'])->name('pages.callback');
            Route::post('/pages/{page}/portfolio', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'assignPagePortfolio'])->name('pages.portfolio');
            Route::post('/pages/{page}/sync-forms', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'syncForms'])->name('pages.forms.sync');
            Route::get('/mapping/{formId}', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'mapping'])->name('mapping');
            Route::post('/save-mapping', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'saveMapping'])->name('save-mapping');
            Route::get('/forms/{form}/detail', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'formDetail'])->name('forms.detail');
            Route::post('/forms/{form}/callback', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'callbackFormLeads'])->name('forms.callback');
            Route::post('/forms/{form}/toggle', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'toggleForm'])->name('forms.toggle');
            Route::post('/forms/{form}/automation', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'assignAutomation'])->name('forms.automation');
            Route::post('/forms/state', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'setFormState'])->name('forms.state');
            Route::post('/custom-field', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'storeCustomField'])->name('custom-field');
            Route::post('/add-page', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'addPage'])->name('add-page');
            Route::post('/remove-page', [\App\Http\Controllers\Admin\FacebookLeadAdsController::class, 'removePage'])->name('remove-page');
        });
        Route::get('/magic-bricks', function () {
            return view('integrations.coming-soon', ['integration' => 'Magic Bricks']);
        })->name('magic-bricks');
        Route::get('/housing', function () {
            return view('integrations.coming-soon', ['integration' => 'Housing']);
        })->name('housing');
        Route::prefix('99acres')->name('99acres.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\NinetyNineAcresIntegrationController::class, 'index'])->name('index');
            Route::post('/settings', [\App\Http\Controllers\Admin\NinetyNineAcresIntegrationController::class, 'update'])->name('update');
            Route::post('/regenerate-key', [\App\Http\Controllers\Admin\NinetyNineAcresIntegrationController::class, 'regenerateApiKey'])->name('regenerate-key');
            Route::post('/test', [\App\Http\Controllers\Admin\NinetyNineAcresIntegrationController::class, 'test'])->name('test');
        });
        Route::get('/configuration', function () {
            return view('integrations.coming-soon', ['integration' => 'Configuration']);
        })->name('configuration');
    });
    
    // Dynamic Forms Management (Admin only)
    Route::middleware(['role:admin'])->prefix('admin/forms')->name('admin.forms.')->group(function () {
        Route::get('/test-field-type', function () {
            return view('admin.forms.test-field-type');
        })->name('test-field-type');
        Route::get('/existing-preview/{formPath}', [\App\Http\Controllers\Admin\DynamicFormController::class, 'previewExistingForm'])->name('existing-preview')->where('formPath', '.+');
        Route::get('/', [\App\Http\Controllers\Admin\DynamicFormController::class, 'index'])->name('index');
        Route::get('/open-existing', [\App\Http\Controllers\Admin\DynamicFormController::class, 'openExistingEditor'])->name('open-existing');
        Route::get('/create', [\App\Http\Controllers\Admin\DynamicFormController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Admin\DynamicFormController::class, 'store'])->name('store');
        Route::get('/{dynamicForm}/edit', [\App\Http\Controllers\Admin\DynamicFormController::class, 'edit'])->name('edit');
        Route::put('/{dynamicForm}', [\App\Http\Controllers\Admin\DynamicFormController::class, 'update'])->name('update');
        Route::delete('/{dynamicForm}', [\App\Http\Controllers\Admin\DynamicFormController::class, 'destroy'])->name('destroy');
    });

    // Lead Form Builder (Admin only)
    Route::middleware(['role:admin'])->prefix('admin/lead-form-builder')->name('admin.lead-form-builder.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\LeadFormBuilderController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Admin\LeadFormBuilderController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Admin\LeadFormBuilderController::class, 'store'])->name('store');
        Route::get('/{leadFormField}', [\App\Http\Controllers\Admin\LeadFormBuilderController::class, 'show'])->name('show');
        Route::get('/{leadFormField}/edit', [\App\Http\Controllers\Admin\LeadFormBuilderController::class, 'edit'])->name('edit');
        Route::put('/{leadFormField}', [\App\Http\Controllers\Admin\LeadFormBuilderController::class, 'update'])->name('update');
        Route::delete('/{leadFormField}', [\App\Http\Controllers\Admin\LeadFormBuilderController::class, 'destroy'])->name('destroy');
        Route::post('/reorder', [\App\Http\Controllers\Admin\LeadFormBuilderController::class, 'reorder'])->name('reorder');
        Route::post('/{leadFormField}/toggle-active', [\App\Http\Controllers\Admin\LeadFormBuilderController::class, 'toggleActive'])->name('toggle-active');
    });

    // Automation Rules (Admin only)
    Route::middleware(['auth', 'role:admin'])->prefix('admin/automation')->name('admin.automation.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\AutomationController::class, 'index'])->name('index');
        Route::get('/cnp', [\App\Http\Controllers\Admin\AsmCnpAutomationController::class, 'index'])->name('cnp.index');
        Route::post('/cnp', [\App\Http\Controllers\Admin\AsmCnpAutomationController::class, 'update'])->name('cnp.update');
        Route::post('/cnp/toggle', [\App\Http\Controllers\Admin\AsmCnpAutomationController::class, 'toggle'])->name('cnp.toggle');
        Route::post('/cnp/quarantined/{lead}/reactivate', [\App\Http\Controllers\Admin\AsmCnpAutomationController::class, 'reactivate'])->name('cnp.quarantined.reactivate');
        Route::get('/99acres', [\App\Http\Controllers\Admin\AutomationController::class, 'editNinetyNineAcres'])->name('99acres.edit');
        Route::post('/99acres', [\App\Http\Controllers\Admin\AutomationController::class, 'updateNinetyNineAcres'])->name('99acres.update');
        Route::get('/create', [\App\Http\Controllers\Admin\AutomationController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Admin\AutomationController::class, 'store'])->name('store');
        Route::get('/{rule}/edit', [\App\Http\Controllers\Admin\AutomationController::class, 'edit'])->name('edit');
        Route::put('/{rule}', [\App\Http\Controllers\Admin\AutomationController::class, 'update'])->name('update');
        Route::delete('/{rule}', [\App\Http\Controllers\Admin\AutomationController::class, 'destroy'])->name('destroy');
        Route::post('/{rule}/toggle', [\App\Http\Controllers\Admin\AutomationController::class, 'toggle'])->name('toggle');
        Route::get('/{rule}/history', [\App\Http\Controllers\Admin\AutomationController::class, 'history'])->name('history');
    });

    // Deployment Routes (Admin only)
    Route::middleware(['auth', 'role:admin'])->prefix('admin/deploy')->name('admin.deploy.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\DeploymentController::class, 'index'])->name('index');
        Route::post('/deploy', [\App\Http\Controllers\Admin\DeploymentController::class, 'deploy'])->name('deploy');
        Route::get('/status', [\App\Http\Controllers\Admin\DeploymentController::class, 'checkGitStatus'])->name('status');
        Route::get('/logs', [\App\Http\Controllers\Admin\DeploymentController::class, 'getLogs'])->name('logs');
    });

    Route::middleware(['auth', 'role:admin,crm,finance_manager,ad_manager', 'relief.block'])->prefix('data-intelligence')->name('data-intelligence.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\DataIntelligenceController::class, 'index'])->name('index');
        Route::get('/advisor-performance', [\App\Http\Controllers\Admin\DataIntelligenceController::class, 'advisorPerformance'])->name('advisor-performance');
        Route::get('/advisor-performance/export', [\App\Http\Controllers\Admin\DataIntelligenceController::class, 'exportAdvisorPerformance'])->name('advisor-performance.export');
        Route::get('/lead-quality', [\App\Http\Controllers\Admin\DataIntelligenceController::class, 'leadQuality'])->name('lead-quality');
        Route::post('/site-visits/{siteVisit}/revenue', [\App\Http\Controllers\Admin\DataIntelligenceController::class, 'updateRevenue'])->name('site-visits.revenue.update');
    });

    Route::middleware(['auth', 'role:admin,crm,finance_manager,ad_manager', 'relief.block'])->prefix('admin/data-intelligence')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\DataIntelligenceController::class, 'legacyIndex']);
        Route::get('/advisor-performance', [\App\Http\Controllers\Admin\DataIntelligenceController::class, 'legacyAdvisorPerformance']);
        Route::get('/advisor-performance/export', [\App\Http\Controllers\Admin\DataIntelligenceController::class, 'legacyExportAdvisorPerformance']);
        Route::get('/lead-quality', [\App\Http\Controllers\Admin\DataIntelligenceController::class, 'legacyLeadQuality']);
    });

    Route::middleware(['auth', 'role:ad_manager'])->prefix('ad-manager')->name('ad-manager.')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\AdManager\DashboardController::class, 'index'])
            ->middleware(['relief.block', 'throttle:20,1'])
            ->name('dashboard');
        Route::get('/leads', [\App\Http\Controllers\AdManager\LeadMonitorController::class, 'index'])->name('leads.index');
        Route::get('/leads/{lead}', [\App\Http\Controllers\AdManager\LeadMonitorController::class, 'show'])->name('leads.show');

        Route::get('/meta', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'index'])->name('meta.index');
        Route::get('/meta/whatsapp', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'whatsapp'])->name('meta.whatsapp');
        Route::post('/meta/whatsapp/update', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'updateWhatsapp'])->name('meta.whatsapp.update');
        Route::post('/meta/whatsapp/automation', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'updateWhatsappAutomation'])->name('meta.whatsapp.automation');
        Route::post('/meta/whatsapp/verify', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'verifyWhatsapp'])->name('meta.whatsapp.verify');
        Route::post('/meta/whatsapp/test', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'testWhatsapp'])->name('meta.whatsapp.test');
        Route::get('/meta/whatsapp/debug', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'whatsappDebug'])->name('meta.whatsapp.debug');
        Route::post('/meta/whatsapp/debug/post', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'whatsappDebugPost'])->name('meta.whatsapp.debug.post');
        Route::post('/meta/whatsapp/debug/curl', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'whatsappDebugCurl'])->name('meta.whatsapp.debug.curl');
        Route::get('/meta/whatsapp/quick-test', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'whatsappQuickTest'])->name('meta.whatsapp.quick-test');
        Route::post('/meta/whatsapp/quick-test/send', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'sendWhatsappQuickTest'])->name('meta.whatsapp.quick-test.send');

        Route::prefix('meta/waba')->name('meta-waba.')->group(function () {
            Route::get('/', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'waba'])->name('index');
            Route::post('/update', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'updateWaba'])->name('update');
            Route::post('/embedded-signup/start', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'startWabaEmbeddedSignup'])->name('embedded.start');
            Route::post('/embedded-signup/finish', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'finishWabaEmbeddedSignup'])->name('embedded.finish');
            Route::post('/disconnect', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'disconnectWaba'])->name('disconnect');
            Route::post('/set-default', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'setDefaultWaba'])->name('set-default');
            Route::post('/verify', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'verifyWaba'])->name('verify');
            Route::post('/register-phone', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'registerWabaPhone'])->name('register-phone');
            Route::post('/templates/create', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'createWabaTemplate'])->name('templates.create');
            Route::post('/templates/sync', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'syncWabaTemplates'])->name('templates.sync');
            Route::delete('/templates/{template}', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'deleteWabaTemplate'])->name('templates.delete');
            Route::post('/test-template', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'testWabaTemplate'])->name('test-template');
            Route::post('/calls/refresh', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'refreshWabaCallSettings'])->name('calls.refresh');
            Route::post('/calls/update', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'updateWabaCallSettings'])->name('calls.update');
            Route::post('/campaigns/preview', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'previewWabaCampaign'])->name('campaigns.preview');
            Route::post('/campaigns', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'storeWabaCampaign'])->name('campaigns.store');
        });

        Route::get('/meta/instagram', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'index'])->name('meta.instagram.index');
        Route::get('/meta/instagram/connect', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'connect'])->name('meta.instagram.connect');
        Route::get('/meta/instagram/callback', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'callback'])->name('meta.instagram.callback');
        Route::post('/meta/instagram/accounts/{account}/disconnect', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'disconnect'])->name('meta.instagram.accounts.disconnect');
        Route::post('/meta/instagram/accounts/{account}/refresh-token', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'refreshToken'])->name('meta.instagram.accounts.refresh-token');
        Route::post('/meta/instagram/rules', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'storeRule'])->name('meta.instagram.rules.store');
        Route::put('/meta/instagram/rules/{rule}', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'updateRule'])->name('meta.instagram.rules.update');
        Route::post('/meta/instagram/rules/{rule}/toggle', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'toggleRule'])->name('meta.instagram.rules.toggle');
        Route::post('/meta/instagram/flows', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'storeFlow'])->name('meta.instagram.flows.store');
        Route::put('/meta/instagram/flows/{flow}', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'updateFlow'])->name('meta.instagram.flows.update');
        Route::post('/meta/instagram/flows/{flow}/toggle', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'toggleFlow'])->name('meta.instagram.flows.toggle');
        Route::post('/meta/instagram/settings', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'updateSettings'])->name('meta.instagram.settings.update');
        Route::post('/meta/instagram/conversations/{conversation}/takeover', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'takeOverConversation'])->name('meta.instagram.conversations.takeover');
        Route::post('/meta/instagram/conversations/{conversation}/resume', [\App\Http\Controllers\Admin\InstagramAutomationController::class, 'resumeConversation'])->name('meta.instagram.conversations.resume');

        Route::prefix('meta/facebook-lead-ads')->name('meta.facebook-lead-ads.')->group(function () {
            Route::get('/', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'facebookLeadAdsIndex'])->name('index');
            Route::get('/settings', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'facebookLeadAdsSettings'])->name('settings');
            Route::post('/settings', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'updateFacebookLeadAdsSettings'])->name('settings.update');
            Route::post('/test-connection', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'testFacebookLeadAdsConnection'])->name('test-connection');
            Route::post('/test-marketing-connection', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'testFacebookMarketingConnection'])->name('test-marketing-connection');
            Route::get('/forms', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'facebookLeadAdsForms'])->name('forms');
            Route::get('/diagnostics', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'facebookLeadAdsDiagnostics'])->name('diagnostics');
            Route::get('/missing-checker', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'facebookLeadAdsMissingChecker'])->name('missing-checker');
            Route::post('/missing-checker/preview', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'previewMissingChecker'])->name('missing-checker.preview');
            Route::post('/missing-checker/import', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'importMissingLeads'])->name('missing-checker.import');
            Route::prefix('bulk-recovery')->name('bulk-recovery.')->group(function () {
                Route::get('/', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'bulkRecoveryIndex'])->name('index');
                Route::post('/scans', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'bulkRecoveryStoreScan'])->name('scans.store');
                Route::get('/scans/{scan}', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'bulkRecoveryShow'])->name('show');
                Route::post('/scans/{scan}/import', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'bulkRecoveryImport'])->name('import');
            });
            Route::post('/retry-failed-webhooks', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'retryFailedWebhooks'])->name('retry-failed-webhooks');
            Route::post('/portfolios', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'storePortfolio'])->name('portfolios.store');
            Route::put('/portfolios/{portfolio}', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'updatePortfolio'])->name('portfolios.update');
            Route::post('/sync-forms', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'syncForms'])->name('forms.sync-all');
            Route::get('/pages/{page}/detail', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'pageDetail'])->name('pages.detail');
            Route::post('/pages/{page}/callback', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'callbackPageLeads'])->name('pages.callback');
            Route::post('/pages/{page}/portfolio', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'assignPagePortfolio'])->name('pages.portfolio');
            Route::post('/pages/{page}/sync-forms', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'syncForms'])->name('pages.forms.sync');
            Route::get('/mapping/{formId}', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'mapping'])->name('mapping');
            Route::post('/save-mapping', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'saveMapping'])->name('save-mapping');
            Route::get('/forms/{form}/detail', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'formDetail'])->name('forms.detail');
            Route::post('/forms/{form}/callback', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'callbackFormLeads'])->name('forms.callback');
            Route::post('/forms/{form}/toggle', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'toggleForm'])->name('forms.toggle');
            Route::post('/forms/{form}/automation', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'assignFormAutomation'])->name('forms.automation');
            Route::post('/forms/state', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'setFormState'])->name('forms.state');
            Route::post('/custom-field', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'storeCustomField'])->name('custom-field');
            Route::post('/add-page', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'addPage'])->name('add-page');
            Route::post('/remove-page', [\App\Http\Controllers\AdManager\MetaOpsController::class, 'removePage'])->name('remove-page');
        });

        Route::prefix('automation')->name('automation.')->group(function () {
            Route::get('/', [\App\Http\Controllers\AdManager\SourceAutomationController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\AdManager\SourceAutomationController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\AdManager\SourceAutomationController::class, 'store'])->name('store');
            Route::get('/{rule}/edit', [\App\Http\Controllers\AdManager\SourceAutomationController::class, 'edit'])->name('edit');
            Route::put('/{rule}', [\App\Http\Controllers\AdManager\SourceAutomationController::class, 'update'])->name('update');
            Route::post('/{rule}/toggle', [\App\Http\Controllers\AdManager\SourceAutomationController::class, 'toggle'])->name('toggle');
        });
    });

    Route::middleware(['auth', 'role:admin,crm'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/advisor-profiles', [\App\Http\Controllers\Admin\AdvisorPublicProfileModerationController::class, 'index'])->name('advisor-profiles.index');
        Route::get('/advisor-profiles/{profile}/edit', [\App\Http\Controllers\Admin\AdvisorPublicProfileModerationController::class, 'editProfile'])->name('advisor-profiles.edit');
        Route::post('/advisor-profiles/{profile}/edit', [\App\Http\Controllers\Admin\AdvisorPublicProfileModerationController::class, 'updateProfile'])->name('advisor-profiles.update');
        Route::get('/advisor-profiles/{profile}/preview', [\App\Http\Controllers\Admin\AdvisorPublicProfileModerationController::class, 'previewProfile'])->name('advisor-profiles.preview');
        Route::post('/advisor-profiles/{profile}/approve', [\App\Http\Controllers\Admin\AdvisorPublicProfileModerationController::class, 'approveProfile'])->name('advisor-profiles.approve');
        Route::post('/advisor-profiles/{profile}/reject', [\App\Http\Controllers\Admin\AdvisorPublicProfileModerationController::class, 'rejectProfile'])->name('advisor-profiles.reject');
        Route::post('/advisor-profiles/reviews/{review}/approve', [\App\Http\Controllers\Admin\AdvisorPublicProfileModerationController::class, 'approveReview'])->name('advisor-profiles.reviews.approve');
        Route::post('/advisor-profiles/reviews/{review}/reject', [\App\Http\Controllers\Admin\AdvisorPublicProfileModerationController::class, 'rejectReview'])->name('advisor-profiles.reviews.reject');
        Route::post('/advisor-profiles/gallery/{item}/approve', [\App\Http\Controllers\Admin\AdvisorPublicProfileModerationController::class, 'approveGalleryItem'])->name('advisor-profiles.gallery.approve');
        Route::post('/advisor-profiles/gallery/{item}/reject', [\App\Http\Controllers\Admin\AdvisorPublicProfileModerationController::class, 'rejectGalleryItem'])->name('advisor-profiles.gallery.reject');
        // Centralised Builder Logo Library (single upload point for every advisor).
        Route::get('/builder-logos', [\App\Http\Controllers\Admin\BuilderLogoController::class, 'index'])->name('builder-logos.index');
        Route::post('/builder-logos', [\App\Http\Controllers\Admin\BuilderLogoController::class, 'store'])->name('builder-logos.store');
        Route::put('/builder-logos/{builderLogo}', [\App\Http\Controllers\Admin\BuilderLogoController::class, 'update'])->name('builder-logos.update');
        Route::delete('/builder-logos/{builderLogo}', [\App\Http\Controllers\Admin\BuilderLogoController::class, 'destroy'])->name('builder-logos.destroy');
        Route::get('/loan-partners', [\App\Http\Controllers\Admin\LoanPartnerBankController::class, 'index'])->name('loan-partners.index');
        Route::post('/loan-partners', [\App\Http\Controllers\Admin\LoanPartnerBankController::class, 'store'])->name('loan-partners.store');
        Route::put('/loan-partners/{loanPartner}', [\App\Http\Controllers\Admin\LoanPartnerBankController::class, 'update'])->name('loan-partners.update');
        Route::delete('/loan-partners/{loanPartner}', [\App\Http\Controllers\Admin\LoanPartnerBankController::class, 'destroy'])->name('loan-partners.destroy');
        Route::get('/lead-audit', [\App\Http\Controllers\Admin\LeadAuditController::class, 'index'])->name('lead-audit.index');
        Route::get('/lead-audit/export', [\App\Http\Controllers\Admin\LeadAuditController::class, 'export'])->name('lead-audit.export');
        Route::get('/lead-duplicates', [\App\Http\Controllers\Admin\LeadDuplicateReportController::class, 'index'])->name('lead-duplicates.index');
    });

    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'dashboard'])->name('dashboard');
        Route::get('/dashboard/data', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'getDashboardData'])
            ->middleware(['relief.block', 'throttle:20,1'])
            ->name('dashboard.data');
        Route::get('/dashboard/lead-quality', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'leadQualityOverview'])
            ->middleware(['relief.block', 'throttle:20,1'])
            ->name('dashboard.lead-quality');
        Route::post('/dashboard/meta-spend-sync', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'syncMetaSpend'])->name('dashboard.meta-spend-sync');
        Route::post('/dashboard/score-columns', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'updateSalesScoreColumns'])->name('dashboard.score-columns.update');
        Route::post('/dashboard/response-time/{user}/reset', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'resetResponseTime'])->name('dashboard.response-time.reset');
        Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\PurchaseOrderApprovalController::class, 'index'])->name('index');
            Route::get('/{purchaseOrder}', [\App\Http\Controllers\Admin\PurchaseOrderApprovalController::class, 'show'])->name('show');
            Route::post('/{purchaseOrder}/approve', [\App\Http\Controllers\Admin\PurchaseOrderApprovalController::class, 'approve'])->name('approve');
            Route::post('/{purchaseOrder}/reject', [\App\Http\Controllers\Admin\PurchaseOrderApprovalController::class, 'reject'])->name('reject');
            Route::post('/{purchaseOrder}/approve-delete', [\App\Http\Controllers\Admin\PurchaseOrderApprovalController::class, 'approveDelete'])->name('approve-delete');
            Route::post('/{purchaseOrder}/reject-delete', [\App\Http\Controllers\Admin\PurchaseOrderApprovalController::class, 'rejectDelete'])->name('reject-delete');
            Route::get('/{purchaseOrder}/attachment', [\App\Http\Controllers\Admin\PurchaseOrderApprovalController::class, 'downloadAttachment'])->name('attachment.download');
            Route::get('/{purchaseOrder}/pdf', [\App\Http\Controllers\Admin\PurchaseOrderApprovalController::class, 'downloadPdf'])->name('pdf.download');
        });
        Route::get('/payroll-approvals', [\App\Http\Controllers\Admin\PayrollApprovalController::class, 'index'])->name('payroll-approvals.index');
        Route::post('/payroll-approvals/{freeze}/approve', [\App\Http\Controllers\Admin\PayrollApprovalController::class, 'approve'])->name('payroll-approvals.approve');
        Route::post('/payroll-approvals/{freeze}/reject', [\App\Http\Controllers\Admin\PayrollApprovalController::class, 'reject'])->name('payroll-approvals.reject');
        Route::get('/profile', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'profile'])->name('profile');
        Route::get('/lead-download-requests', [\App\Http\Controllers\Admin\LeadDownloadRequestController::class, 'index'])->name('lead-download-requests.index');
        Route::post('/lead-download-requests/{leadDownloadRequest}/approve', [\App\Http\Controllers\Admin\LeadDownloadRequestController::class, 'approve'])->name('lead-download-requests.approve');
        Route::post('/lead-download-requests/{leadDownloadRequest}/reject', [\App\Http\Controllers\Admin\LeadDownloadRequestController::class, 'reject'])->name('lead-download-requests.reject');
        Route::get('/login-security', [\App\Http\Controllers\Admin\LoginSecurityController::class, 'index'])->name('login-security.index');
        Route::get('/login-security/{event}', [\App\Http\Controllers\Admin\LoginSecurityController::class, 'show'])->name('login-security.show');
        Route::post('/login-security/{event}/unlock', [\App\Http\Controllers\Admin\LoginSecurityController::class, 'unlock'])->name('login-security.unlock');
        Route::post('/login-security/{event}/reject', [\App\Http\Controllers\Admin\LoginSecurityController::class, 'reject'])->name('login-security.reject');
        Route::get('/password-change-pending', [\App\Http\Controllers\Admin\PasswordChangeReportController::class, 'index'])->name('password-change-pending.index');
        
        // Flow Testing (Admin and CRM)
        Route::get('/flow-test', [\App\Http\Controllers\Admin\FlowTestController::class, 'index'])->name('flow-test');
        Route::get('/verification-routing', [\App\Http\Controllers\Admin\VerificationRoutingController::class, 'index'])->name('verification-routing.index');
        Route::put('/verification-routing/settings', [\App\Http\Controllers\Admin\VerificationRoutingController::class, 'updateSettings'])->name('verification-routing.settings.update');
        Route::post('/verification-routing/mappings', [\App\Http\Controllers\Admin\VerificationRoutingController::class, 'storeMapping'])->name('verification-routing.mappings.store');
        Route::put('/verification-routing/mappings/{mapping}', [\App\Http\Controllers\Admin\VerificationRoutingController::class, 'updateMapping'])->name('verification-routing.mappings.update');
        Route::patch('/verification-routing/mappings/{mapping}/disable', [\App\Http\Controllers\Admin\VerificationRoutingController::class, 'disableMapping'])->name('verification-routing.mappings.disable');
        
        // Company Settings Routes
        Route::prefix('company-settings')->name('company-settings.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\CompanySettingsController::class, 'index'])->name('index');
            Route::post('/company-profile', [\App\Http\Controllers\Admin\CompanySettingsController::class, 'updateCompanyProfile'])->name('company-profile.update');
            Route::post('/branding', [\App\Http\Controllers\Admin\CompanySettingsController::class, 'updateBranding'])->name('branding.update');
            Route::post('/apply-template', [\App\Http\Controllers\Admin\CompanySettingsController::class, 'applyTemplate'])->name('apply-template');
            Route::post('/upload-file', [\App\Http\Controllers\Admin\CompanySettingsController::class, 'uploadFile'])->name('upload-file');
            Route::delete('/file/{id}', [\App\Http\Controllers\Admin\CompanySettingsController::class, 'deleteFile'])->name('file.delete');
            Route::get('/api/settings', [\App\Http\Controllers\Admin\CompanySettingsController::class, 'getSettings'])->name('api.settings');
            Route::get('/preview', [\App\Http\Controllers\Admin\CompanySettingsController::class, 'previewBranding'])->name('preview');
        });
        
        // System Settings Routes
        Route::prefix('system-settings')->name('system-settings.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'index'])->name('index');
    Route::post('/maintenance/toggle', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'toggleMaintenanceMode'])->name('maintenance.toggle');
    Route::post('/user-notifications/update', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'updateUserNotificationSettings'])->name('user-notifications.update');
    Route::post('/notification-sounds/upload', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'uploadNotificationSound'])->name('notification-sounds.upload');
    Route::post('/notification-sounds/reset', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'resetNotificationSound'])->name('notification-sounds.reset');
    Route::post('/notification-sounds/apply-default', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'applyDefaultNotificationSound'])->name('notification-sounds.apply-default');
    Route::post('/mail-settings/update', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'updateMailSettings'])->name('mail-settings.update');
    Route::post('/mail-settings/test', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'testMailSettings'])->name('mail-settings.test');
    Route::post('/meta-review/update', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'updateMetaReviewSettings'])->name('meta-review.update');
    Route::post('/meta-review/mapping', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'updateMetaReviewMapping'])->name('meta-review.mapping.update');
    Route::post('/dashboard-kpi/update', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'updateDashboardKpiSettings'])->name('dashboard-kpi.update');
    Route::post('/public-page-defaults/update', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'updatePublicPageDefaults'])->name('public-page-defaults.update');
    Route::post('/travel-time/update', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'updateTravelTimeSettings'])->name('travel-time.update');
    Route::get('/test-email', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'testEmailPage'])->name('test-email');
            Route::post('/test-email', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'sendTestEmail'])->name('test-email.send');
            Route::get('/mail-debug', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'mailDebugPage'])->name('mail-debug');
            Route::post('/mail-debug/send', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'sendTestEmailDebug'])->name('mail-debug.send');
            Route::post('/files/upload', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'uploadFiles'])->name('files.upload');
            Route::post('/files/deploy', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'deployFiles'])->name('files.deploy');
            Route::post('/migrations/run', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'runMigrations'])->name('migrations.run');
            Route::post('/command/run', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'runCommand'])->name('command.run');
            // Database and Environment Settings
            Route::post('/database/test', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'testDatabaseConnection'])->name('database.test');
            Route::post('/database/update', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'updateDatabaseSettings'])->name('database.update');
            Route::get('/env/get', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'getEnvSettings'])->name('env.get');
            Route::post('/env/update', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'updateEnvSettings'])->name('env.update');
        });

        Route::prefix('mobile-app-update')->name('mobile-app-update.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\MobileAppUpdateController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\Admin\MobileAppUpdateController::class, 'update'])->name('update');
            Route::post('/quick-publish', [\App\Http\Controllers\Admin\MobileAppUpdateController::class, 'quickPublish'])->name('quick-publish');
            Route::post('/notify', [\App\Http\Controllers\Admin\MobileAppUpdateController::class, 'notifyUsers'])->name('notify');
            Route::post('/users/{user}/notify', [\App\Http\Controllers\Admin\MobileAppUpdateController::class, 'notifyUser'])->name('users.notify');
            Route::post('/users/{user}/force', [\App\Http\Controllers\Admin\MobileAppUpdateController::class, 'forceUser'])->name('users.force');
            Route::post('/users/{user}/diagnostics/enable', [\App\Http\Controllers\Admin\MobileAppUpdateController::class, 'enableDiagnostics'])->name('users.diagnostics.enable');
            Route::post('/users/{user}/diagnostics/disable', [\App\Http\Controllers\Admin\MobileAppUpdateController::class, 'disableDiagnostics'])->name('users.diagnostics.disable');
            Route::get('/whatsapp-extension/download', [\App\Http\Controllers\Admin\MobileAppUpdateController::class, 'downloadWhatsAppExtension'])->name('whatsapp-extension.download');
            Route::post('/whatsapp-extension/generate-token', [\App\Http\Controllers\Admin\MobileAppUpdateController::class, 'generateWhatsAppExtensionToken'])->name('whatsapp-extension.generate-token');
            Route::post('/whatsapp-extension/test-connection', [\App\Http\Controllers\Admin\MobileAppUpdateController::class, 'testWhatsAppExtensionConnection'])->name('whatsapp-extension.test-connection');
            Route::get('/facebook-lead-center-extension/download', [\App\Http\Controllers\Admin\MobileAppUpdateController::class, 'downloadFacebookLeadCenterExtension'])->name('facebook-lead-center-extension.download');
            Route::post('/facebook-lead-center-extension/generate-token', [\App\Http\Controllers\Admin\MobileAppUpdateController::class, 'generateFacebookLeadCenterExtensionToken'])->name('facebook-lead-center-extension.generate-token');
            Route::post('/facebook-lead-center-extension/test-connection', [\App\Http\Controllers\Admin\MobileAppUpdateController::class, 'testFacebookLeadCenterExtensionConnection'])->name('facebook-lead-center-extension.test-connection');
        });
        Route::get('/extensions', [\App\Http\Controllers\Admin\MobileAppUpdateController::class, 'extensions'])->name('extensions.index');

        Route::prefix('desktop-issue-reports')->name('desktop-issue-reports.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\DesktopIssueReportController::class, 'index'])->name('index');
            Route::get('/{desktopIssueReport}', [\App\Http\Controllers\Admin\DesktopIssueReportController::class, 'show'])->name('show');
            Route::post('/{desktopIssueReport}/status', [\App\Http\Controllers\Admin\DesktopIssueReportController::class, 'updateStatus'])->name('status');
        });

        Route::prefix('storage-manager')->name('storage-manager.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\StorageManagerController::class, 'index'])->name('index');
            Route::post('/trash', [\App\Http\Controllers\Admin\StorageManagerController::class, 'trash'])->name('trash');
            Route::post('/bulk-trash', [\App\Http\Controllers\Admin\StorageManagerController::class, 'bulkTrash'])->name('bulk-trash');
            Route::post('/trash/{trashItem}/restore', [\App\Http\Controllers\Admin\StorageManagerController::class, 'restore'])->name('restore');
            Route::delete('/trash/{trashItem}', [\App\Http\Controllers\Admin\StorageManagerController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('attendance')->name('attendance.')->group(function () {
            Route::get('/offices', [\App\Http\Controllers\Admin\AttendanceOfficeController::class, 'index'])->name('offices.index');
            Route::post('/offices', [\App\Http\Controllers\Admin\AttendanceOfficeController::class, 'store'])->name('offices.store');
            Route::put('/offices/{office}', [\App\Http\Controllers\Admin\AttendanceOfficeController::class, 'update'])->name('offices.update');
            Route::delete('/offices/{office}', [\App\Http\Controllers\Admin\AttendanceOfficeController::class, 'destroy'])->name('offices.destroy');
            Route::get('/policies', [\App\Http\Controllers\Admin\AttendancePolicyController::class, 'index'])->name('policies.index');
            Route::post('/policies', [\App\Http\Controllers\Admin\AttendancePolicyController::class, 'store'])->name('policies.store');
            Route::put('/policies/{policy}', [\App\Http\Controllers\Admin\AttendancePolicyController::class, 'update'])->name('policies.update');
            Route::delete('/policies/{policy}', [\App\Http\Controllers\Admin\AttendancePolicyController::class, 'destroy'])->name('policies.destroy');
            Route::post('/weekoffs/apply', [\App\Http\Controllers\Admin\AttendancePolicyController::class, 'applyWeekoffs'])->name('weekoffs.apply');
            Route::get('/user-mappings', [\App\Http\Controllers\Admin\UserAttendanceMappingController::class, 'index'])->name('user-mappings.index');
            Route::post('/user-mappings/bulk', [\App\Http\Controllers\Admin\UserAttendanceMappingController::class, 'bulkStore'])->name('user-mappings.bulk-store');
            Route::post('/user-mappings', [\App\Http\Controllers\Admin\UserAttendanceMappingController::class, 'store'])->name('user-mappings.store');
            Route::put('/user-mappings/{mapping}', [\App\Http\Controllers\Admin\UserAttendanceMappingController::class, 'update'])->name('user-mappings.update');
            Route::delete('/user-mappings/{mapping}', [\App\Http\Controllers\Admin\UserAttendanceMappingController::class, 'destroy'])->name('user-mappings.destroy');
            Route::get('/leave-types', [\App\Http\Controllers\Admin\LeaveTypeController::class, 'index'])->name('leave-types.index');
            Route::post('/leave-types', [\App\Http\Controllers\Admin\LeaveTypeController::class, 'store'])->name('leave-types.store');
            Route::put('/leave-types/{leaveType}', [\App\Http\Controllers\Admin\LeaveTypeController::class, 'update'])->name('leave-types.update');
            Route::delete('/leave-types/{leaveType}', [\App\Http\Controllers\Admin\LeaveTypeController::class, 'destroy'])->name('leave-types.destroy');
            Route::get('/simulator', [\App\Http\Controllers\Admin\AttendancePolicySimulatorController::class, 'index'])->name('simulator.index');
            Route::get('/outside-punch-permissions', [\App\Http\Controllers\Admin\AttendanceOutsidePunchPermissionController::class, 'index'])->name('outside-punch-permissions.index');
            Route::post('/outside-punch-permissions', [\App\Http\Controllers\Admin\AttendanceOutsidePunchPermissionController::class, 'store'])->name('outside-punch-permissions.store');
            Route::put('/outside-punch-permissions/{permission}', [\App\Http\Controllers\Admin\AttendanceOutsidePunchPermissionController::class, 'update'])->name('outside-punch-permissions.update');
            Route::delete('/outside-punch-permissions/{permission}', [\App\Http\Controllers\Admin\AttendanceOutsidePunchPermissionController::class, 'destroy'])->name('outside-punch-permissions.destroy');
            Route::get('/leaves', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'leaves'])->name('leaves.index');
            Route::get('/regularizations', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'regularizations'])->name('regularizations.index');
            Route::get('/outside-punches', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'outsidePunches'])->name('outside-punches.index');
            Route::post('/outside-punches/{mapping}/toggle-request', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'toggleOutsidePunchRequest'])->name('outside-punches.toggle-request');
            Route::post('/outside-punches/{mapping}/toggle-direct-allow', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'toggleOutsidePunchDirectAllow'])->name('outside-punches.toggle-direct-allow');
            Route::post('/outside-punches/{mapping}/window', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'saveOutsidePunchWindow'])->name('outside-punches.window');
            Route::get('/overtimes', [\App\Http\Controllers\Hr\AttendanceOvertimeController::class, 'index'])->name('overtimes.index');
            Route::post('/leaves/{leaveRequest}/approve', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'approveLeave'])->name('leaves.approve');
            Route::post('/leaves/{leaveRequest}/reject', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'rejectLeave'])->name('leaves.reject');
            Route::post('/regularizations/{regularization}/approve', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'approveRegularization'])->name('regularizations.approve');
            Route::post('/regularizations/{regularization}/reject', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'rejectRegularization'])->name('regularizations.reject');
            Route::post('/outside-punches/{outsidePunchRequest}/approve', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'approveOutsidePunch'])->name('outside-punches.approve');
            Route::post('/outside-punches/{outsidePunchRequest}/reject', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'rejectOutsidePunch'])->name('outside-punches.reject');
            Route::post('/overtimes/{overtime}/approve', [\App\Http\Controllers\Hr\AttendanceOvertimeController::class, 'approve'])->name('overtimes.approve');
            Route::post('/overtimes/{overtime}/reject', [\App\Http\Controllers\Hr\AttendanceOvertimeController::class, 'reject'])->name('overtimes.reject');
        });

        Route::prefix('hr')->name('hr.')->group(function () {
            Route::get('/employees', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'index'])->name('employees.index');
            Route::get('/employees/create', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'create'])->name('employees.create');
            Route::post('/employees', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'store'])->name('employees.store');
            Route::get('/employees/{employee}', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'show'])->name('employees.show');
            Route::get('/employees/{employee}/edit', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'edit'])->name('employees.edit');
            Route::put('/employees/{employee}', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'update'])->name('employees.update');
            Route::post('/employees/{employee}/documents', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'storeDocument'])->name('employees.documents.store');
            Route::delete('/employees/{employee}/documents/{document}', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'destroyDocument'])->name('employees.documents.destroy');
            Route::post('/employees/{employee}/detail-link', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'generateDetailLink'])->name('employees.detail-link.generate');
            Route::post('/employees/{employee}/detail-link/{link}/revoke', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'revokeDetailLink'])->name('employees.detail-link.revoke');
            Route::post('/employees/{employee}/assets', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'storeAsset'])->name('employees.assets.store');
            Route::post('/employees/{employee}/assets/{asset}/status', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'updateAssetStatus'])->name('employees.assets.status');
            Route::post('/employees/{employee}/salary-revisions', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'storeSalaryRevision'])->name('employees.salary-revisions.store');
            Route::post('/employees/{employee}/exit-workflow', [\App\Http\Controllers\Admin\Hr\EmployeeController::class, 'updateExitWorkflow'])->name('employees.exit-workflow.update');
            Route::get('/document-center', [\App\Http\Controllers\Admin\Hr\DocumentCenterController::class, 'index'])->name('document-center.index');
            Route::get('/document-center/{document}/download', [\App\Http\Controllers\Admin\Hr\DocumentCenterController::class, 'download'])->name('document-center.download');
            Route::get('/salary-revisions', [\App\Http\Controllers\Admin\Hr\EmployeeSalaryRevisionController::class, 'index'])->name('salary-revisions.index');
            Route::get('/exit-cases', [\App\Http\Controllers\Admin\Hr\EmployeeExitWorkflowController::class, 'index'])->name('exit-workflows.index');
            Route::get('/incentives', [\App\Http\Controllers\Admin\Hr\EmployeeIncentiveController::class, 'index'])->name('incentives.index');
            Route::get('/salary-structures', [\App\Http\Controllers\Admin\Hr\SalaryStructureController::class, 'index'])->name('salary-structures.index');
            Route::post('/salary-structures', [\App\Http\Controllers\Admin\Hr\SalaryStructureController::class, 'store'])->name('salary-structures.store');
            Route::post('/salary-structures/employee-breakup', [\App\Http\Controllers\Admin\Hr\SalaryStructureController::class, 'storeEmployeeBreakup'])->name('salary-structures.employee-breakup.store');
            Route::put('/salary-structures/{salaryStructure}', [\App\Http\Controllers\Admin\Hr\SalaryStructureController::class, 'update'])->name('salary-structures.update');
            Route::get('/salary-profiles', [\App\Http\Controllers\Admin\Hr\SalaryProfileController::class, 'index'])->name('salary-profiles.index');
            Route::post('/salary-profiles', [\App\Http\Controllers\Admin\Hr\SalaryProfileController::class, 'store'])->name('salary-profiles.store');
            Route::get('/deduction-heads', [\App\Http\Controllers\Admin\Hr\DeductionHeadController::class, 'index'])->name('deduction-heads.index');
            Route::post('/deduction-heads', [\App\Http\Controllers\Admin\Hr\DeductionHeadController::class, 'store'])->name('deduction-heads.store');
            Route::get('/payslip-settings', [\App\Http\Controllers\Admin\Hr\PayslipSettingsController::class, 'index'])->name('payslip-settings.index');
            Route::post('/payslip-settings', [\App\Http\Controllers\Admin\Hr\PayslipSettingsController::class, 'update'])->name('payslip-settings.update');
            Route::get('/fraud-settings', [\App\Http\Controllers\Admin\Hr\FraudSettingsController::class, 'index'])->name('fraud-settings.index');
            Route::post('/fraud-settings/{policy}', [\App\Http\Controllers\Admin\Hr\FraudSettingsController::class, 'update'])->name('fraud-settings.update');
            Route::get('/reports', [\App\Http\Controllers\Admin\Hr\ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/export/attendance', [\App\Http\Controllers\Api\AttendanceReportExportController::class, 'attendance'])->name('reports.export.attendance');
            Route::get('/reports/export/payroll', [\App\Http\Controllers\Api\AttendanceReportExportController::class, 'payroll'])->name('reports.export.payroll');
            Route::get('/reports/export/suspicious', [\App\Http\Controllers\Api\AttendanceReportExportController::class, 'suspicious'])->name('reports.export.suspicious');
        });

        Route::prefix('expenses')->name('expenses.')->group(function () {
            Route::get('/dashboard', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'dashboard'])->name('dashboard');
            Route::get('/queue', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'queue'])->name('queue');
            Route::get('/monthly-report', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'monthlyReport'])->name('monthly-report');
            Route::get('/summary/print', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'printSummary'])->name('summary.print');
            Route::get('/summary/export', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'exportSummary'])->name('summary.export');
            Route::get('/entries', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'index'])->name('entries.index');
            Route::get('/entries/export', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'export'])->name('entries.export');
            Route::get('/entries/create', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'create'])->name('entries.create');
            Route::post('/entries', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'store'])->name('entries.store');
            Route::get('/entries/{entry}/attachment', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'downloadAttachment'])->name('entries.attachment.download');
            Route::get('/entries/{entry}/edit', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'edit'])->name('entries.edit');
            Route::put('/entries/{entry}', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'update'])->name('entries.update');
            Route::delete('/entries/{entry}', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'destroy'])->name('entries.destroy');
            Route::post('/entries/{entry}/approve', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'approve'])->name('entries.approve');
            Route::post('/entries/{entry}/reject', [\App\Http\Controllers\Expenses\ExpenseEntryController::class, 'reject'])->name('entries.reject');
            Route::get('/companies', [\App\Http\Controllers\Expenses\ExpenseCompanyController::class, 'index'])->name('companies.index');
            Route::post('/companies', [\App\Http\Controllers\Expenses\ExpenseCompanyController::class, 'store'])->name('companies.store');
            Route::put('/companies/{company}', [\App\Http\Controllers\Expenses\ExpenseCompanyController::class, 'update'])->name('companies.update');
            Route::get('/categories', [\App\Http\Controllers\Expenses\ExpenseCategoryController::class, 'index'])->name('categories.index');
            Route::post('/categories', [\App\Http\Controllers\Expenses\ExpenseCategoryController::class, 'store'])->name('categories.store');
            Route::put('/categories/{category}', [\App\Http\Controllers\Expenses\ExpenseCategoryController::class, 'update'])->name('categories.update');
            Route::get('/subcategories', [\App\Http\Controllers\Expenses\ExpenseSubcategoryController::class, 'index'])->name('subcategories.index');
            Route::post('/subcategories', [\App\Http\Controllers\Expenses\ExpenseSubcategoryController::class, 'store'])->name('subcategories.store');
            Route::put('/subcategories/{subcategory}', [\App\Http\Controllers\Expenses\ExpenseSubcategoryController::class, 'update'])->name('subcategories.update');
        });
        
    });
    
    // Target Management (Admin/CRM/Sales Head)
    Route::middleware(['role:admin,crm,sales_head'])->prefix('admin')->name('admin.')->group(function () {
        Route::post('targets/bulk-set', [\App\Http\Controllers\Admin\TargetController::class, 'bulkSet'])->name('targets.bulk-set');
        Route::post('targets/copy-previous', [\App\Http\Controllers\Admin\TargetController::class, 'copyPrevious'])->name('targets.copy-previous');
        Route::resource('targets', \App\Http\Controllers\Admin\TargetController::class);
        Route::get('/dead-leads', [\App\Http\Controllers\Admin\DeadLeadsController::class, 'index'])->name('dead-leads');
        Route::get('/other-leads', [\App\Http\Controllers\Admin\OtherLeadsController::class, 'index'])->middleware('role:admin,crm')->name('other-leads.index');
        Route::post('/other-leads/reassign', [\App\Http\Controllers\Admin\OtherLeadsController::class, 'reassign'])->middleware('role:admin,crm')->name('other-leads.reassign');
    });
    
    // CRM Automation Routes (CRM/Admin only)
    Route::middleware(['role:crm,admin'])->prefix('crm/automation')->name('crm.automation.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Crm\AutomationController::class, 'index'])->name('index');
        Route::get('/ivr-lead-distribution', [\App\Http\Controllers\Crm\IvrLeadAutomationController::class, 'index'])->name('ivr.index');
        Route::put('/ivr-lead-distribution', [\App\Http\Controllers\Crm\IvrLeadAutomationController::class, 'update'])->name('ivr.update');
        Route::get('/new-lead-sla', [\App\Http\Controllers\Crm\NewLeadSlaAutomationController::class, 'index'])->name('sla.index');
        Route::post('/new-lead-sla', [\App\Http\Controllers\Crm\NewLeadSlaAutomationController::class, 'store'])->name('sla.store');
        Route::put('/new-lead-sla/{config}', [\App\Http\Controllers\Crm\NewLeadSlaAutomationController::class, 'update'])->name('sla.update');
        Route::delete('/new-lead-sla/{config}', [\App\Http\Controllers\Crm\NewLeadSlaAutomationController::class, 'destroy'])->name('sla.destroy');
        Route::get('/leads/create', [\App\Http\Controllers\Crm\LeadController::class, 'create'])->name('leads.create');
        Route::get('/leads/check-duplicate', [\App\Http\Controllers\Crm\LeadController::class, 'checkDuplicate'])->name('leads.check-duplicate');
        Route::post('/leads', [\App\Http\Controllers\Crm\LeadController::class, 'store'])->name('leads.store');
        Route::get('/rules', [\App\Http\Controllers\Crm\AssignmentRuleController::class, 'index'])->name('rules');
        Route::post('/rules', [\App\Http\Controllers\Crm\AssignmentRuleController::class, 'store'])->name('rules.store');
        Route::delete('/rules/{rule}', [\App\Http\Controllers\Crm\AssignmentRuleController::class, 'destroy'])->name('rules.destroy');
        Route::get('/import', [\App\Http\Controllers\Crm\LeadImportController::class, 'showImportForm'])->name('import');
        Route::post('/import/csv', [\App\Http\Controllers\Crm\LeadImportController::class, 'importCsv'])->name('import.csv');
        Route::post('/import/csv/preview', [\App\Http\Controllers\Crm\LeadImportController::class, 'previewCsv'])->name('import.csv.preview');
    });

    // Leads Management
    Route::post('/leads/bulk-change-owner', [\App\Http\Controllers\LeadController::class, 'bulkChangeOwner'])
        ->middleware('role:admin,crm')
        ->name('leads.bulk-change-owner');
    Route::post('/leads/bulk-assign-hiring', [\App\Http\Controllers\LeadController::class, 'bulkAssignHiring'])
        ->middleware('role:admin,crm')
        ->name('leads.bulk-assign-hiring');
    Route::post('/leads/bulk-calling-tasks', [\App\Http\Controllers\LeadController::class, 'bulkCreateCallingTasks'])
        ->middleware('role:admin,crm')
        ->name('leads.bulk-calling-tasks');
    Route::post('/leads/export-selected', [\App\Http\Controllers\LeadController::class, 'exportSelected'])
        ->middleware('role:admin,crm')
        ->name('leads.export-selected');
    Route::delete('/leads/bulk-delete', [\App\Http\Controllers\LeadController::class, 'bulkDestroy'])
        ->middleware('role:admin,crm')
        ->name('leads.bulk-delete');
    Route::get('/leads/manual-check-duplicate', [\App\Http\Controllers\LeadController::class, 'checkDuplicate'])
        ->middleware('role:admin,crm')
        ->name('leads.manual-check-duplicate');
    Route::post('/leads/sources', [\App\Http\Controllers\LeadController::class, 'storeSource'])
        ->middleware('role:admin,crm')
        ->name('leads.sources.store');
    Route::get('/dashboard/lead-search', [\App\Http\Controllers\LeadController::class, 'dashboardQuickSearch'])
        ->middleware('role:admin,crm')
        ->name('dashboard.lead-search');
    Route::post('/leads/{lead}/reopen', \App\Http\Controllers\LeadReopenController::class)->middleware('role:admin')->name('leads.reopen');
    Route::get('/leads/{lead}/preview', [\App\Http\Controllers\LeadController::class, 'preview'])->middleware('role:admin')->name('leads.preview');
    Route::resource('leads', \App\Http\Controllers\LeadController::class);
    Route::get('/leads/{lead}/short-details', [\App\Http\Controllers\LeadController::class, 'shortDetails'])->name('leads.short-details');
    Route::post('/leads/{lead}/proposals', [\App\Http\Controllers\LeadProposalController::class, 'store'])->name('leads.proposals.store');
    Route::get('/leads/{lead}/proposals/{proposal}/analytics', [\App\Http\Controllers\LeadProposalController::class, 'analytics'])->name('leads.proposals.analytics');
    
    // Call Logs Routes
    Route::prefix('calls')->name('calls.')->group(function () {
        Route::get('/', [\App\Http\Controllers\CallLogController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\CallLogController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\CallLogController::class, 'store'])->name('store');
        Route::get('/{id}', [\App\Http\Controllers\CallLogController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [\App\Http\Controllers\CallLogController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\App\Http\Controllers\CallLogController::class, 'update'])->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\CallLogController::class, 'destroy'])->name('destroy');
        Route::get('/statistics', [\App\Http\Controllers\CallLogController::class, 'statistics'])->name('statistics');
        Route::get('/statistics/data', [\App\Http\Controllers\CallLogController::class, 'getStatistics'])->name('statistics.data');
        Route::get('/export/csv', [\App\Http\Controllers\CallLogController::class, 'exportCsv'])->name('export.csv');
    });
    
    // Export Routes (Admin, CRM, Sales Manager, Sales Head)
    Route::middleware(['role:admin,crm,sales_manager,sales_head', 'relief.block'])->prefix('export')->name('export.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ExportController::class, 'index'])->name('index');
        Route::get('/all-lead-preview', [\App\Http\Controllers\ExportController::class, 'previewAllLeadExport'])->name('all-lead-preview');
        Route::get('/reports/{reportKey}/preview', [\App\Http\Controllers\ExportController::class, 'previewReport'])->name('reports.preview');
        Route::post('/reports/{reportKey}/download', [\App\Http\Controllers\ExportController::class, 'downloadReport'])->name('reports.download');
        Route::post('/leads', [\App\Http\Controllers\ExportController::class, 'exportLeads'])->name('leads');
        Route::post('/prospects', [\App\Http\Controllers\ExportController::class, 'exportProspects'])->name('prospects');
        Route::post('/meetings', [\App\Http\Controllers\ExportController::class, 'exportMeetings'])->name('meetings');
        Route::post('/site-visits', [\App\Http\Controllers\ExportController::class, 'exportSiteVisits'])->name('site-visits');
        Route::post('/closed-leads', [\App\Http\Controllers\ExportController::class, 'exportClosedLeads'])->name('closed-leads');
        Route::post('/dead-leads', [\App\Http\Controllers\ExportController::class, 'exportDeadLeads'])->name('dead-leads');
        Route::post('/by-project', [\App\Http\Controllers\ExportController::class, 'exportByProject'])->name('by-project');
    });
    
    // Lead Import Routes (CRM and Admin only)
    Route::middleware(['role:crm,admin'])->prefix('lead-import')->name('lead-import.')->group(function () {
        // Dashboard
        Route::get('/', [\App\Http\Controllers\LeadImportController::class, 'index'])->name('index');
        
        // Google Sheets Config
        Route::get('/google-sheets/config', [\App\Http\Controllers\LeadImportController::class, 'getGoogleSheetsConfig'])->name('google-sheets.config');
        Route::post('/google-sheets/config', [\App\Http\Controllers\LeadImportController::class, 'saveGoogleSheetsConfig'])->name('google-sheets.config.save');
        Route::get('/google-sheets/configs', [\App\Http\Controllers\LeadImportController::class, 'getAllGoogleSheetsConfigs'])->name('google-sheets.configs');
        Route::delete('/google-sheets/config/{id}', [\App\Http\Controllers\LeadImportController::class, 'deleteGoogleSheetsConfig'])->name('google-sheets.config.delete');
        Route::post('/google-sheets/fetch-headers', [\App\Http\Controllers\LeadImportController::class, 'fetchSheetHeaders'])->name('google-sheets.fetch-headers');

        // Sync
        Route::post('/google-sheets/sync', [\App\Http\Controllers\LeadImportController::class, 'syncGoogleSheets'])->name('google-sheets.sync');
        
        // Simple Import
    Route::get('/simple', [\App\Http\Controllers\LeadImportController::class, 'showSimpleForm'])->name('simple');
    Route::get('/simple/sample-download', [\App\Http\Controllers\LeadImportController::class, 'downloadSimpleSample'])->name('simple.sample-download');
    Route::post('/simple/preview', [\App\Http\Controllers\LeadImportController::class, 'previewSimpleImport'])->name('simple.preview');
    Route::post('/simple/import', [\App\Http\Controllers\LeadImportController::class, 'importSimple'])->name('simple.import');
    Route::post('/simple/start', [\App\Http\Controllers\LeadImportController::class, 'startSimpleImport'])->name('simple.start');
    Route::post('/simple/process', [\App\Http\Controllers\LeadImportController::class, 'processSimpleImport'])->name('simple.process');

        // CSV Import (existing functionality moved here)
        Route::get('/csv', [\App\Http\Controllers\LeadImportController::class, 'showCsvForm'])->name('csv');
        Route::post('/csv', [\App\Http\Controllers\LeadImportController::class, 'importCsv'])->name('csv.import');
        Route::post('/csv/preview', [\App\Http\Controllers\LeadImportController::class, 'previewCsv'])->name('csv.preview');
        Route::get('/old-crm', [\App\Http\Controllers\LeadImportController::class, 'showOldCrmForm'])->name('old-crm');
        Route::post('/old-crm/analyze', [\App\Http\Controllers\LeadImportController::class, 'analyzeOldCrm'])->name('old-crm.analyze');
        Route::post('/old-crm/validate', [\App\Http\Controllers\LeadImportController::class, 'validateOldCrm'])->name('old-crm.validate');
        Route::post('/old-crm/import', [\App\Http\Controllers\LeadImportController::class, 'importOldCrm'])->name('old-crm.import');
        
        // History
        Route::get('/history', [\App\Http\Controllers\LeadImportController::class, 'history'])->name('history');
    });
    
});

// Lead Bank Request Routes (Managers can request; CRM/Admin/Lead Manager can monitor)
Route::middleware(['auth', 'role:crm,admin,lead_manager,sales_manager,senior_manager,assistant_sales_manager,telecaller'])->prefix('lead-bank')->name('lead-bank.')->group(function () {
    Route::get('/requests', [\App\Http\Controllers\LeadBankRequestController::class, 'index'])->name('requests.index');
    Route::post('/requests', [\App\Http\Controllers\LeadBankRequestController::class, 'store'])->name('requests.store');
    Route::post('/requests/preview', [\App\Http\Controllers\LeadBankRequestController::class, 'preview'])->name('requests.preview');
    Route::post('/requests/recall-expired', [\App\Http\Controllers\LeadBankRequestController::class, 'recallExpired'])->name('requests.recall-expired');
    Route::get('/requests/{leadBankRequest}/manual-candidates', [\App\Http\Controllers\LeadBankRequestController::class, 'manualCandidates'])->name('requests.manual-candidates');
    Route::post('/requests/{leadBankRequest}/manual-assign', [\App\Http\Controllers\LeadBankRequestController::class, 'manualAssign'])->name('requests.manual-assign');
    Route::post('/requests/{leadBankRequest}/approve', [\App\Http\Controllers\LeadBankRequestController::class, 'approve'])->name('requests.approve');
    Route::post('/requests/{leadBankRequest}/reject', [\App\Http\Controllers\LeadBankRequestController::class, 'reject'])->name('requests.reject');
    Route::post('/requests/{leadBankRequest}/cancel', [\App\Http\Controllers\LeadBankRequestController::class, 'cancel'])->name('requests.cancel');
    Route::post('/allocations/{allocation}/recall', [\App\Http\Controllers\LeadBankRequestController::class, 'recallAllocation'])->name('allocations.recall');
});

// Lead Bank Routes (CRM, Admin, and Lead Manager)
Route::middleware(['auth', 'role:crm,admin,lead_manager'])->prefix('lead-bank')->name('lead-bank.')->group(function () {
    Route::get('/', [\App\Http\Controllers\LeadBankController::class, 'index'])->name('index');
    Route::get('/folder/{scope}', [\App\Http\Controllers\LeadBankController::class, 'folder'])->whereIn('scope', ['all', 'unassigned'])->name('folder');
    Route::get('/folder/tag/{tag}', [\App\Http\Controllers\LeadBankController::class, 'tagFolder'])->name('folder.tag');
    Route::get('/analytics', \App\Http\Controllers\LeadBankAnalyticsController::class)->name('analytics');
    Route::post('/tags', [\App\Http\Controllers\LeadBankController::class, 'storeTag'])->name('tags.store');
    Route::put('/tags/{tag}', [\App\Http\Controllers\LeadBankController::class, 'updateTag'])->name('tags.update');
    Route::delete('/tags/{tag}', [\App\Http\Controllers\LeadBankController::class, 'destroyTag'])->name('tags.destroy');
    Route::post('/tags/merge', [\App\Http\Controllers\LeadBankController::class, 'mergeTags'])->name('tags.merge');
    Route::post('/bulk-tags', [\App\Http\Controllers\LeadBankController::class, 'bulkTags'])->name('bulk-tags');
    Route::get('/import', [\App\Http\Controllers\LeadBankImportController::class, 'index'])->name('import.index');
    Route::get('/import/sample-download', [\App\Http\Controllers\LeadBankImportController::class, 'downloadSample'])->name('import.sample-download');
    Route::post('/import', [\App\Http\Controllers\LeadBankImportController::class, 'upload'])->name('import.upload');
    Route::get('/import/{session}', [\App\Http\Controllers\LeadBankImportController::class, 'show'])->name('import.show');
    Route::post('/import/{session}/process-preview', [\App\Http\Controllers\LeadBankImportController::class, 'processPreview'])->name('import.process-preview');
    Route::post('/import/{session}/process-confirm', [\App\Http\Controllers\LeadBankImportController::class, 'processConfirm'])->name('import.process-confirm');
    Route::post('/import/{session}/mapping', [\App\Http\Controllers\LeadBankImportController::class, 'updateMapping'])->name('import.mapping');
    Route::post('/import/{session}/rows', [\App\Http\Controllers\LeadBankImportController::class, 'updateRows'])->name('import.rows');
    Route::post('/import/{session}/bulk-tag', [\App\Http\Controllers\LeadBankImportController::class, 'bulkTag'])->name('import.bulk-tag');
    Route::post('/import/{session}/confirm', [\App\Http\Controllers\LeadBankImportController::class, 'confirm'])->name('import.confirm');
});

Route::middleware(['auth', 'permission:calling_center.view', 'prevent.cache'])
    ->prefix('calling-center')
    ->name('calling-center.')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\CallingCenterController::class, 'index'])->name('index');
        Route::get('/queue', [\App\Http\Controllers\CallingCenterController::class, 'queue'])->middleware('permission:calling_center.agent_queue')->name('queue');
        Route::post('/preview', [\App\Http\Controllers\CallingCenterController::class, 'preview'])->middleware('permission:calling_center.create_campaign')->name('preview');
        Route::post('/campaigns', [\App\Http\Controllers\CallingCenterController::class, 'store'])->middleware('permission:calling_center.create_campaign')->name('store');
        Route::get('/campaigns/{campaign}', [\App\Http\Controllers\CallingCenterController::class, 'show'])->name('show');
        Route::post('/campaigns/{campaign}/start', [\App\Http\Controllers\CallingCenterController::class, 'start'])->middleware('permission:calling_center.create_campaign')->name('start');
        Route::post('/campaigns/{campaign}/pause', [\App\Http\Controllers\CallingCenterController::class, 'pause'])->name('pause');
        Route::post('/campaigns/{campaign}/cancel', [\App\Http\Controllers\CallingCenterController::class, 'cancel'])->middleware('permission:calling_center.create_campaign')->name('cancel');
        Route::get('/campaigns/{campaign}/export', [\App\Http\Controllers\CallingCenterController::class, 'export'])->middleware('permission:calling_center.export_reports')->name('export');
        Route::post('/items/{item}/outcome', [\App\Http\Controllers\CallingCenterController::class, 'submitOutcome'])->middleware('permission:calling_center.submit_outcome')->name('items.outcome');
        Route::post('/push', [\App\Http\Controllers\CallingCenterController::class, 'push'])->middleware('permission:calling_center.manual_push')->name('push');
        Route::post('/process', [\App\Http\Controllers\CallingCenterController::class, 'process'])->name('process');
    });

// Admin Impersonation (Session based)
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/impersonate/{user}', function (\App\Models\User $user) {
        if (auth()->id() === $user->id) {
            return back()->with('error', 'Aap khud ko impersonate nahi kar sakte!');
        }
        // Admin ka original ID save karo
        session(['impersonating_original_id' => auth()->id()]);
        // Us user ke roop mein login karo
        auth()->login($user);
        // Role ke hisaab se redirect
        $slug = $user->role->slug ?? $user->role ?? '';
        return match(true) {
            $slug === 'crm'                                          => redirect('/crm/dashboard'),
            $slug === 'lead_manager'                                 => redirect('/dashboard'),
            $slug === 'sales_head'                                   => redirect('/sales-head/dashboard'),
            in_array($slug, ['sales_manager','senior_manager',
                'assistant_sales_manager'])                          => redirect('/sales-manager/dashboard'),
            $slug === 'telecaller'                                   => redirect()->route('calling-center.queue'),
            $slug === 'sales_executive'                              => redirect('/telecaller/dashboard'),
            $slug === 'hr_manager'                                   => redirect('/dashboard'),
            $slug === 'junior_hr'                                    => redirect('/junior-hr/dashboard'),
            $slug === 'finance_manager'                              => redirect('/dashboard'),
            in_array($slug, ['marketing_manager', 'marketing_executive']) => redirect('/marketing/dashboard'),
            default                                                  => redirect('/dashboard'),
        };
    })->name('impersonate.start');

});

// Stop impersonation - admin middleware nahi chahiye kyunki current user admin nahi hota
Route::middleware(['auth'])->get('/impersonate/stop', function () {
    $originalId = session('impersonating_original_id');
    if ($originalId) {
        $admin = \App\Models\User::find($originalId);
        if ($admin) {
            auth()->login($admin);
            session()->forget('impersonating_original_id');
        }
    }
    return redirect('/users');
})->name('impersonate.stop');

// Lead Assignment Routes
Route::middleware(['auth', 'role:crm,admin'])->prefix('lead-assignment')->name('lead-assignment.')->group(function () {
    Route::get('/', [\App\Http\Controllers\LeadAssignmentController::class, 'index'])->name('index');
    Route::get('/unassigned', [\App\Http\Controllers\LeadAssignmentController::class, 'getUnassignedLeads'])->name('unassigned');
    Route::post('/assign', [\App\Http\Controllers\LeadAssignmentController::class, 'assignLeads'])->name('assign');
    Route::post('/delete', [\App\Http\Controllers\LeadAssignmentController::class, 'deleteLeads'])->name('delete');
    Route::get('/telecaller-stats', [\App\Http\Controllers\LeadAssignmentController::class, 'getTelecallerStats'])->name('telecaller-stats');
    Route::get('/calling-tasks', [\App\Http\Controllers\CrmBulkCallingTaskController::class, 'index'])->name('calling-tasks.index');
    Route::get('/calling-tasks/leads', [\App\Http\Controllers\CrmBulkCallingTaskController::class, 'leads'])->name('calling-tasks.leads');
    Route::post('/calling-tasks', [\App\Http\Controllers\CrmBulkCallingTaskController::class, 'store'])->name('calling-tasks.store');
    
    // Telecaller Limits
    Route::get('/telecaller-limits', [\App\Http\Controllers\TelecallerLimitController::class, 'index'])->name('telecaller-limits');
    Route::post('/telecaller-limits/save', [\App\Http\Controllers\TelecallerLimitController::class, 'saveDailyLimit'])->name('telecaller-limits.save');
    Route::get('/telecaller-limits/api', [\App\Http\Controllers\TelecallerLimitController::class, 'getDailyLimits'])->name('telecaller-limits.api');
    
    // Sheet Assignments
    Route::get('/sheet-assignments', [\App\Http\Controllers\SheetAssignmentController::class, 'index'])->name('sheet-assignments');
    Route::post('/sheet-assignments/assign', [\App\Http\Controllers\SheetAssignmentController::class, 'assignSheetToTelecaller'])->name('sheet-assignments.assign');
    Route::post('/sheet-assignments/config', [\App\Http\Controllers\SheetAssignmentController::class, 'updateSheetConfig'])->name('sheet-assignments.config');
    Route::post('/sheet-assignments/toggle-auto', [\App\Http\Controllers\SheetAssignmentController::class, 'toggleAutoAssign'])->name('sheet-assignments.toggle-auto');
    
    // Telecaller Status
    Route::get('/telecaller-status', [\App\Http\Controllers\TelecallerStatusController::class, 'index'])->name('telecaller-status');
    Route::get('/lead-off-users', [\App\Http\Controllers\TelecallerStatusController::class, 'index'])->name('lead-off-users');
    Route::post('/telecaller-status/update', [\App\Http\Controllers\TelecallerStatusController::class, 'updateStatus'])->name('telecaller-status.update');
    Route::get('/telecaller-status/api', [\App\Http\Controllers\TelecallerStatusController::class, 'getStatus'])->name('telecaller-status.api');
});

Route::middleware(['auth'])->prefix('execution-desk')->name('execution-desk.')->group(function () {
    Route::get('/', [\App\Http\Controllers\ExecutionDeskController::class, 'index'])->name('index');
    Route::get('/create', [\App\Http\Controllers\ExecutionDeskController::class, 'create'])->name('create');
    Route::post('/self-todos', [\App\Http\Controllers\SelfTodoController::class, 'store'])->name('self-todos.store');
    Route::put('/self-todos/{todo}', [\App\Http\Controllers\SelfTodoController::class, 'update'])->name('self-todos.update');
    Route::patch('/self-todos/{todo}/complete', [\App\Http\Controllers\SelfTodoController::class, 'toggleComplete'])->name('self-todos.complete');
    Route::delete('/self-todos/{todo}', [\App\Http\Controllers\SelfTodoController::class, 'destroy'])->name('self-todos.destroy');
    Route::post('/tasks', [\App\Http\Controllers\ExecutionTaskController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/{task}', [\App\Http\Controllers\ExecutionTaskController::class, 'show'])->name('tasks.show');
    Route::put('/tasks/{task}/status', [\App\Http\Controllers\ExecutionTaskController::class, 'updateStatus'])->name('tasks.status');
    Route::put('/tasks/{task}/reassign', [\App\Http\Controllers\ExecutionTaskController::class, 'reassign'])->name('tasks.reassign');
    Route::post('/tasks/{task}/notes', [\App\Http\Controllers\ExecutionTaskController::class, 'addNote'])->name('tasks.notes.store');
    Route::post('/tasks/{task}/attachments', [\App\Http\Controllers\ExecutionTaskAttachmentController::class, 'store'])->name('tasks.attachments.store');
    Route::get('/tasks/{task}/attachments/{attachment}/download', [\App\Http\Controllers\ExecutionTaskAttachmentController::class, 'download'])->name('tasks.attachments.download');
    Route::delete('/tasks/{task}/attachments/{attachment}', [\App\Http\Controllers\ExecutionTaskAttachmentController::class, 'destroy'])->name('tasks.attachments.destroy');
    Route::post('/tasks/{task}/checklists', [\App\Http\Controllers\ExecutionTaskChecklistController::class, 'store'])->name('tasks.checklists.store');
    Route::put('/tasks/{task}/checklists/{item}', [\App\Http\Controllers\ExecutionTaskChecklistController::class, 'update'])->name('tasks.checklists.update');
    Route::delete('/tasks/{task}/checklists/{item}', [\App\Http\Controllers\ExecutionTaskChecklistController::class, 'destroy'])->name('tasks.checklists.destroy');
    Route::post('/saved-views', [\App\Http\Controllers\ExecutionSavedViewController::class, 'store'])->name('saved-views.store');
    Route::delete('/saved-views/{savedView}', [\App\Http\Controllers\ExecutionSavedViewController::class, 'destroy'])->name('saved-views.destroy');
});

// Task Routes (public - no auth required)
Route::prefix('tasks')->name('tasks.')->middleware('auth')->group(function () {
    Route::get('/', [\App\Http\Controllers\TaskController::class, 'index'])->name('index');
    Route::get('/{task}', [\App\Http\Controllers\TaskController::class, 'show'])->name('show');
    Route::put('/{task}', [\App\Http\Controllers\TaskController::class, 'update'])->name('update');
    Route::delete('/{task}', [\App\Http\Controllers\TaskController::class, 'destroy'])->name('destroy');
    Route::post('/{task}/complete', [\App\Http\Controllers\TaskController::class, 'complete'])->name('complete');
    Route::post('/{task}/update-lead', [\App\Http\Controllers\TaskController::class, 'updateLeadAfterCall'])->name('update-lead');
    Route::post('/{task}/reschedule', [\App\Http\Controllers\TaskController::class, 'reschedule'])->name('reschedule');
    Route::post('/{task}/duplicate', [\App\Http\Controllers\TaskController::class, 'duplicate'])->name('duplicate');
    Route::post('/{task}/cancel', [\App\Http\Controllers\TaskController::class, 'cancel'])->name('cancel');
    Route::get('/{task}/activities', [\App\Http\Controllers\TaskController::class, 'activities'])->name('activities');
    
    // Attachments
    Route::post('/{task}/attachments', [\App\Http\Controllers\TaskController::class, 'uploadAttachment'])->name('attachments.upload');
    Route::delete('/{task}/attachments/{attachment}', [\App\Http\Controllers\TaskController::class, 'deleteAttachment'])->name('attachments.delete');
    Route::get('/{task}/attachments/{attachment}/download', [\App\Http\Controllers\TaskController::class, 'downloadAttachment'])->name('attachments.download');
});

// API routes for tasks
Route::prefix('api/tasks')->middleware('auth:sanctum')->group(function () {
    Route::put('/{task}/status', [\App\Http\Controllers\Api\TaskController::class, 'updateStatus']);
    Route::put('/{task}/reschedule', [\App\Http\Controllers\Api\TaskController::class, 'reschedule']);
    Route::get('/kanban', [\App\Http\Controllers\Api\TaskController::class, 'kanban']);
    Route::get('/calendar', [\App\Http\Controllers\Api\TaskController::class, 'calendar']);
    Route::post('/bulk-action', [\App\Http\Controllers\Api\TaskController::class, 'bulkAction']);
});

// Support Ticket Routes (all authenticated users)
Route::middleware(['auth'])->prefix('support')->name('support.')->group(function () {
    Route::get('/',         [\App\Http\Controllers\SupportTicketController::class, 'index'])->name('index');
    Route::get('/create',   [\App\Http\Controllers\SupportTicketController::class, 'create'])->name('create');
    Route::post('/',        [\App\Http\Controllers\SupportTicketController::class, 'store'])->name('store');
    Route::get('/{ticket}', [\App\Http\Controllers\SupportTicketController::class, 'show'])->name('show');
    Route::post('/{ticket}/reply', [\App\Http\Controllers\SupportTicketController::class, 'reply'])->name('reply');
});

// Knowledge Base Routes (all authenticated users)
Route::middleware(['auth'])->prefix('knowledge-base')->name('knowledge-base.')->group(function () {
    Route::get('/', [\App\Http\Controllers\KnowledgeBaseController::class, 'index'])->name('index');
    Route::get('/{item:slug}', [\App\Http\Controllers\KnowledgeBaseController::class, 'show'])->name('show');
    Route::post('/{item}/progress', [\App\Http\Controllers\KnowledgeBaseController::class, 'progress'])->name('progress');
});

// Admin / CRM Knowledge Base Management
Route::middleware(['auth', 'role:admin,crm'])->prefix('admin/knowledge-base')->name('admin.knowledge-base.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\KnowledgeBaseItemController::class, 'index'])->name('index');
    Route::get('/create', [\App\Http\Controllers\Admin\KnowledgeBaseItemController::class, 'create'])->name('create');
    Route::post('/', [\App\Http\Controllers\Admin\KnowledgeBaseItemController::class, 'store'])->name('store');
    Route::get('/{item}/edit', [\App\Http\Controllers\Admin\KnowledgeBaseItemController::class, 'edit'])->name('edit');
    Route::put('/{item}', [\App\Http\Controllers\Admin\KnowledgeBaseItemController::class, 'update'])->name('update');
    Route::delete('/{item}', [\App\Http\Controllers\Admin\KnowledgeBaseItemController::class, 'destroy'])->name('destroy');

    Route::get('/categories/manage', [\App\Http\Controllers\Admin\KnowledgeBaseCategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [\App\Http\Controllers\Admin\KnowledgeBaseCategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [\App\Http\Controllers\Admin\KnowledgeBaseCategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [\App\Http\Controllers\Admin\KnowledgeBaseCategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('/paths', [\App\Http\Controllers\Admin\KnowledgeBasePathController::class, 'index'])->name('paths.index');
    Route::get('/paths/create', [\App\Http\Controllers\Admin\KnowledgeBasePathController::class, 'create'])->name('paths.create');
    Route::post('/paths', [\App\Http\Controllers\Admin\KnowledgeBasePathController::class, 'store'])->name('paths.store');
    Route::get('/paths/{path}/edit', [\App\Http\Controllers\Admin\KnowledgeBasePathController::class, 'edit'])->name('paths.edit');
    Route::put('/paths/{path}', [\App\Http\Controllers\Admin\KnowledgeBasePathController::class, 'update'])->name('paths.update');
    Route::delete('/paths/{path}', [\App\Http\Controllers\Admin\KnowledgeBasePathController::class, 'destroy'])->name('paths.destroy');
});

Route::middleware(['auth', 'role:admin,crm,sales_manager,senior_manager,assistant_sales_manager'])
    ->prefix('admin/knowledge-base/reports')
    ->name('admin.knowledge-base.reports.')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\KnowledgeBaseReportController::class, 'index'])->name('index');
    });

// Admin Support Ticket Routes
Route::middleware(['auth', 'role:admin'])->prefix('admin/support')->name('admin.support.')->group(function () {
    Route::get('/',                         [\App\Http\Controllers\Admin\SupportController::class, 'index'])->name('index');
    Route::get('/{ticket}',                 [\App\Http\Controllers\Admin\SupportController::class, 'show'])->name('show');
    Route::post('/{ticket}/reply',          [\App\Http\Controllers\Admin\SupportController::class, 'reply'])->name('reply');
    Route::patch('/{ticket}/status',        [\App\Http\Controllers\Admin\SupportController::class, 'updateStatus'])->name('update-status');
    Route::delete('/{ticket}',              [\App\Http\Controllers\Admin\SupportController::class, 'destroy'])->name('destroy');
});

// WhatsApp Chat Routes
Route::middleware(['auth'])->prefix('chat')->name('chat.')->group(function () {
    Route::get('/', [\App\Http\Controllers\WhatsAppChatController::class, 'index'])->name('index');
    Route::get('/conversations', [\App\Http\Controllers\WhatsAppChatController::class, 'getConversations'])->name('conversations.index');
        Route::get('/leads', [\App\Http\Controllers\WhatsAppChatController::class, 'getLeads'])->name('leads.index');
    Route::post('/conversations', [\App\Http\Controllers\WhatsAppChatController::class, 'createConversation'])->name('conversations.create');
    Route::get('/conversations/{id}', [\App\Http\Controllers\WhatsAppChatController::class, 'getConversation'])->name('conversations.show');
    Route::post('/messages', [\App\Http\Controllers\WhatsAppChatController::class, 'sendMessage'])->name('messages.send');
    Route::post('/messages/template', [\App\Http\Controllers\WhatsAppChatController::class, 'sendTemplateMessage'])->name('messages.template');
    Route::get('/templates', [\App\Http\Controllers\WhatsAppChatController::class, 'getTemplates'])->name('templates.index');
    Route::get('/templates/{id}', [\App\Http\Controllers\WhatsAppChatController::class, 'getTemplate'])->name('templates.show');
    Route::post('/templates/sync', [\App\Http\Controllers\WhatsAppChatController::class, 'syncTemplates'])->name('templates.sync');
    Route::post('/conversations/{id}/sync-messages', [\App\Http\Controllers\WhatsAppChatController::class, 'syncMessages'])->name('conversations.sync-messages');
    Route::put('/conversations/{id}/read', [\App\Http\Controllers\WhatsAppChatController::class, 'markAsRead'])->name('conversations.read');
    Route::delete('/conversations/{id}', [\App\Http\Controllers\WhatsAppChatController::class, 'deleteConversation'])->name('conversations.delete');
});
