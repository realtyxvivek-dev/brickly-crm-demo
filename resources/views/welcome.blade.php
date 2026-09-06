<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Real Estate CRM - Flow Documentation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#205A44">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ brand_name() }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}?v={{ filemtime(public_path('manifest.json')) }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    
    <!-- Define installPWA function early, before body loads -->
    <script>
        // Global variables for PWA
        var deferredPrompt = null;
        var installButton = null;

        // Define function in global scope immediately - MUST be available for onclick
        function installPWA() {
            console.log('✅ Install button clicked - installPWA() called');
            
            if (!installButton) {
                installButton = document.getElementById('installButton');
            }

            if (deferredPrompt) {
                console.log('Showing install prompt...');
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('✅ User accepted the install prompt');
                    } else {
                        console.log('❌ User dismissed the install prompt');
                    }
                    deferredPrompt = null;
                }).catch((error) => {
                    console.error('Error showing install prompt:', error);
                });
            } else {
                console.log('⚠️ Deferred prompt not available');
                if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
                    alert('App is already installed!');
                    return;
                }
                
                const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                if (isIOS) {
                    alert('To install this app on iOS:\n1. Tap the Share button (square with arrow)\n2. Tap "Add to Home Screen"');
                } else {
                    const manifestLink = document.querySelector('link[rel="manifest"]');
                    console.log('PWA Requirements Check:');
                    console.log('- Service Worker:', 'serviceWorker' in navigator);
                    console.log('- Manifest:', manifestLink ? 'Found' : 'Not found');
                    
                    if (manifestLink) {
                        fetch(manifestLink.href)
                            .then(response => response.json())
                            .then(manifest => {
                                console.log('Manifest icons:', manifest.icons);
                                if (!manifest.icons || manifest.icons.length < 2) {
                                    console.error('❌ Manifest needs proper icons (192x192 and 512x512)');
                                }
                            })
                            .catch(error => console.error('Error loading manifest:', error));
                    }
                    
                    // Check icons only once, not on every click
                    if (!window.iconsChecked) {
                        window.iconsChecked = true;
                        fetch('/icon-192.png', { method: 'HEAD' })
                            .then(response => {
                                if (response.ok) {
                                    console.log('✅ Icon files exist');
                                } else {
                                    console.warn('⚠️ Icon files (192x192 and 512x512) are missing');
                                    console.log('💡 To create icons: Visit http://localhost:8008/create-icon.html');
                                    console.log('💡 After creating icons, refresh the page');
                                }
                            })
                            .catch(() => {});
                    }
                    
                    console.log('💡 Install prompt will appear automatically when PWA requirements are met.');
                    console.log('💡 Alternative: Use browser menu → Install');
                }
            }
        }

        // Make it available on window object too
        window.installPWA = installPWA;
    </script>
    <script>
        window.crmInstallConfig = {
            androidAppUrl: @json(\App\Models\SystemSettings::get('mobile_app_update_apk_url', url('/downloads/base-crm-latest.apk'))),
            pwaInstallUrl: @json(url('/install-app'))
        };

        (function () {
            function isStandaloneApp() {
                return window.matchMedia('(display-mode: standalone)').matches
                    || window.navigator.standalone === true;
            }

            function detectInstallPlatform() {
                const ua = navigator.userAgent || navigator.vendor || window.opera || '';
                const isAndroid = /Android/i.test(ua);
                const isIOS = /iPad|iPhone|iPod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

                if (isAndroid) {
                    return 'android';
                }

                if (isIOS) {
                    return 'ios';
                }

                if (!/Mobi|Android|iPhone|iPad|iPod/i.test(ua)) {
                    return 'desktop';
                }

                return null;
            }

            function getDeferredPrompt() {
                if (typeof deferredPrompt !== 'undefined' && deferredPrompt) {
                    return deferredPrompt;
                }

                return window.deferredPrompt || null;
            }

            function clearDeferredPrompt() {
                if (typeof deferredPrompt !== 'undefined') {
                    deferredPrompt = null;
                }
                window.deferredPrompt = null;
            }

            function promptPwaOrOpenGuide() {
                if (isStandaloneApp()) {
                    alert('App already installed hai.');
                    return;
                }

                const promptEvent = getDeferredPrompt();
                if (promptEvent) {
                    promptEvent.prompt();
                    promptEvent.userChoice.finally(clearDeferredPrompt);
                    return;
                }

                window.location.href = window.crmInstallConfig.pwaInstallUrl;
            }

            function showInstallChoice() {
                const modal = document.getElementById('installChoiceModal');
                if (modal) {
                    modal.classList.add('is-open');
                    document.body.classList.add('install-choice-open');
                }
            }

            function hideInstallChoice() {
                const modal = document.getElementById('installChoiceModal');
                if (modal) {
                    modal.classList.remove('is-open');
                    document.body.classList.remove('install-choice-open');
                }
            }

            window.hideInstallChoice = hideInstallChoice;
            window.chooseInstallPlatform = function (platform) {
                hideInstallChoice();

                if (platform === 'android') {
                    const androidUrl = window.crmInstallConfig.androidAppUrl;
                    if (androidUrl) {
                        window.location.href = androidUrl;
                        return;
                    }
                }

                promptPwaOrOpenGuide();
            };

            window.installPWA = function () {
                const platform = detectInstallPlatform();
                if (platform) {
                    window.chooseInstallPlatform(platform);
                    return;
                }

                showInstallChoice();
            };

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    hideInstallChoice();
                }
            });
        })();
    </script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            background: linear-gradient(135deg, #205A44 0%, #063A1C 100%); 
            min-height: 100vh; 
            padding: 20px;
        }
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-section {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            text-align: center;
            width: 100%;
            max-width: 500px;
        }
        .button-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
        }
        .login-section h1 {
            color: #063A1C;
            font-size: 36px;
            margin-bottom: 10px;
        }
        .login-section p {
            color: #6b7280;
            font-size: 16px;
            margin-bottom: 30px;
        }
        .btn {
            display: inline-block;
            padding: 14px 40px;
            background: #205A44;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background: #063A1C;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .btn-secondary {
            background: #6b7280;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
        .btn-install {
            background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
        }
        .btn-install:hover {
            background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);
        }
        .btn-install i {
            font-size: 18px;
        }
        .btn-install.hidden {
            display: none;
        }

        .install-choice-open {
            overflow: hidden;
        }
        .install-choice-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(6, 58, 28, 0.72);
            backdrop-filter: blur(8px);
        }
        .install-choice-overlay.is-open {
            display: flex;
        }
        .install-choice-card {
            width: min(460px, 100%);
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.35);
            padding: 26px;
            position: relative;
            text-align: left;
        }
        .install-choice-card h3 {
            color: #063A1C;
            font-size: 26px;
            margin-bottom: 8px;
        }
        .install-choice-card p {
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 18px;
        }
        .install-choice-close {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 36px;
            height: 36px;
            border: 0;
            border-radius: 999px;
            background: #f1f5f9;
            color: #0f172a;
            cursor: pointer;
            font-size: 18px;
        }
        .install-choice-actions {
            display: grid;
            gap: 10px;
        }
        .install-choice-option {
            border: 1px solid #dbe7df;
            border-radius: 14px;
            background: #f8fbf8;
            padding: 14px 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #063A1C;
            font-weight: 700;
            text-align: left;
        }
        .install-choice-option:hover {
            border-color: #205A44;
            background: #edf8f1;
        }
        .install-choice-option i {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #205A44;
            color: #ffffff;
        }
        .install-choice-option small {
            display: block;
            color: #64748b;
            font-weight: 500;
            margin-top: 3px;
        }
        
        /* Documentation Section */
        .doc-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }
        .doc-header {
            background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .doc-header h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        .doc-header p {
            opacity: 0.9;
            font-size: 14px;
        }
        .doc-tabs {
            display: flex;
            background: #f3f4f6;
            border-bottom: 2px solid #e5e7eb;
            overflow-x: auto;
        }
        .doc-tab {
            flex: 1;
            padding: 15px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #6b7280;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
            white-space: nowrap;
        }
        .doc-tab:hover {
            background: #e5e7eb;
            color: #063A1C;
        }
        .doc-tab.active {
            background: white;
            color: #205A44;
            border-bottom-color: #205A44;
        }
        .doc-content {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }
        .flow-content {
            display: none;
        }
        .flow-content.active {
            display: block;
        }
        .flow-title {
            font-size: 20px;
            font-weight: 700;
            color: #063A1C;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
        }
        .step-card {
            background: #f9fafb;
            border-left: 4px solid #205A44;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .step-card:hover {
            background: #f3f4f6;
            transform: translateX(5px);
        }
        .step-card.expanded {
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .step-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background: #205A44;
            color: white;
            border-radius: 50%;
            font-weight: 700;
            font-size: 14px;
            margin-right: 12px;
        }
        .step-title {
            flex: 1;
            font-weight: 600;
            color: #063A1C;
            font-size: 16px;
        }
        .step-icon {
            color: #6b7280;
            transition: transform 0.3s;
        }
        .step-card.expanded .step-icon {
            transform: rotate(180deg);
        }
        .step-details {
            display: none;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
        }
        .step-card.expanded .step-details {
            display: block;
        }
        .step-info {
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        .step-info strong {
            color: #063A1C;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }
        .badge-scheduled {
            background: #dbeafe;
            color: #1e40af;
        }
        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .badge-verified {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-dead {
            background: #fee2e2;
            color: #991b1b;
        }
        .role-badge {
            background: #e0e7ff;
            color: #3730a3;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            margin-left: 5px;
        }
        .flow-diagram {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 2px dashed #d1d5db;
        }
        .flow-diagram-title {
            font-weight: 700;
            color: #063A1C;
            margin-bottom: 15px;
            text-align: center;
        }
        .flow-box {
            display: inline-block;
            padding: 10px 15px;
            margin: 5px;
            background: white;
            border: 2px solid #205A44;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #063A1C;
        }
        .flow-arrow {
            display: inline-block;
            color: #205A44;
            font-size: 20px;
            margin: 0 5px;
        }
        .highlight-box {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px;
            border-radius: 6px;
            margin: 10px 0;
        }
        .highlight-box strong {
            color: #92400e;
        }
        @media (max-width: 1024px) {
            .main-container {
                grid-template-columns: 1fr;
            }
            .doc-section {
                max-height: none;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Login Section -->
        <div class="login-section">
            <h1>Real Estate CRM</h1>
            <p>Scalable CRM System for Real Estate Companies</p>
            <div class="button-group">
                <!-- PWA Install Button (Left Side) -->
                <button id="installButton" class="btn btn-install" type="button">
                    <i class="fas fa-download"></i>
                    Install App
                </button>
                @auth
                    <a href="/dashboard" class="btn">Go to Dashboard</a>
                @else
                    <a href="/login" class="btn">Login</a>
                @endauth
            </div>
        </div>

        <div id="installChoiceModal" class="install-choice-overlay" onclick="if (event.target === this) hideInstallChoice()">
            <div class="install-choice-card" role="dialog" aria-modal="true" aria-labelledby="installChoiceTitle">
                <button type="button" class="install-choice-close" onclick="hideInstallChoice()" aria-label="Close install options">
                    <i class="fas fa-times"></i>
                </button>
                <h3 id="installChoiceTitle">Install App</h3>
                <p>Device auto detect nahi hua. Apna device select karo, uske hisaab se correct install flow open hoga.</p>
                <div class="install-choice-actions">
                    <button type="button" class="install-choice-option" onclick="chooseInstallPlatform('android')">
                        <i class="fab fa-android"></i>
                        <span>Android App <small>Latest APK download/open hoga.</small></span>
                    </button>
                    <button type="button" class="install-choice-option" onclick="chooseInstallPlatform('ios')">
                        <i class="fab fa-apple"></i>
                        <span>iPhone / iPad <small>PWA install guide open hoga.</small></span>
                    </button>
                    <button type="button" class="install-choice-option" onclick="chooseInstallPlatform('desktop')">
                        <i class="fas fa-desktop"></i>
                        <span>Desktop <small>PWA install prompt ya guide open hoga.</small></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Documentation Section (Hidden) -->
        <div class="doc-section" style="display: none;">
            <div class="doc-header">
                <h2><i class="fas fa-book mr-2"></i>Complete Flow Documentation</h2>
                <p>Understand how leads move through the system</p>
            </div>
            
            <div class="doc-tabs">
                <button class="doc-tab active" onclick="switchFlow('flow1')">
                    <i class="fas fa-user-tie mr-2"></i>Sales Executive → Closer
                </button>
                <button class="doc-tab" onclick="switchFlow('flow2')">
                    <i class="fas fa-user-shield mr-2"></i>Manager's Lead → Closer
                </button>
                <button class="doc-tab" onclick="switchFlow('flow3')">
                    <i class="fas fa-skull mr-2"></i>Dead Lead Management
                </button>
            </div>

            <div class="doc-content">
                <!-- Flow 1: Sales Executive Prospect to Closer -->
                <div id="flow1" class="flow-content active">
                    <h3 class="flow-title">Flow 1: Sales Executive Prospect to Closer</h3>
                    
                    <div class="flow-diagram">
                        <div class="flow-diagram-title">Visual Flow</div>
                        <div style="text-align: center; line-height: 2;">
                            <span class="flow-box">CRM/Admin</span>
                            <span class="flow-arrow">→</span>
                            <span class="flow-box">Sales Executive</span>
                            <span class="flow-arrow">→</span>
                            <span class="flow-box">Prospect</span>
                            <span class="flow-arrow">→</span>
                            <span class="flow-box">Manager Verify</span>
                            <span class="flow-arrow">→</span>
                            <span class="flow-box">Meeting</span>
                            <span class="flow-arrow">→</span>
                            <span class="flow-box">Site Visit</span>
                            <span class="flow-arrow">→</span>
                            <span class="flow-box">Closer</span>
                            <span class="flow-arrow">→</span>
                            <span class="flow-box">Closed Won</span>
                        </div>
                    </div>

                    <div class="step-card" onclick="toggleStep(this)">
                        <div class="step-header">
                            <div style="display: flex; align-items: center;">
                                <span class="step-number">1</span>
                                <span class="step-title">CRM/Admin Assigns Leads to Sales Executive</span>
                            </div>
                            <i class="fas fa-chevron-down step-icon"></i>
                        </div>
                        <div class="step-details">
                            <div class="step-info">
                                <strong>Who:</strong> <span class="role-badge">CRM</span> <span class="role-badge">Admin</span><br>
                                <strong>Action:</strong> Assign leads from the system to sales executives for calling.<br>
                                <strong>Result:</strong> Sales Executive receives leads in their dashboard.
                            </div>
                        </div>
                    </div>

                    <div class="step-card" onclick="toggleStep(this)">
                        <div class="step-header">
                            <div style="display: flex; align-items: center;">
                                <span class="step-number">2</span>
                                <span class="step-title">Sales Executive Calls and Extracts Prospect</span>
                            </div>
                            <i class="fas fa-chevron-down step-icon"></i>
                        </div>
                        <div class="step-details">
                            <div class="step-info">
                                <strong>Who:</strong> <span class="role-badge">Sales Executive</span><br>
                                <strong>Action:</strong> Sales Executive calls the lead, collects information, and creates a prospect if the lead is interested.<br>
                                <strong>Result:</strong> Prospect is created with customer details and marked as "Pending Verification".
                            </div>
                        </div>
                    </div>

                    <div class="step-card" onclick="toggleStep(this)">
                        <div class="step-header">
                            <div style="display: flex; align-items: center;">
                                <span class="step-number">3</span>
                                <span class="step-title">Prospect Sent to Manager for Verification</span>
                            </div>
                            <i class="fas fa-chevron-down step-icon"></i>
                        </div>
                        <div class="step-details">
                            <div class="step-info">
                                <strong>Who:</strong> <span class="role-badge">System</span><br>
                                <strong>Action:</strong> Prospect automatically appears in the Manager's verification panel.<br>
                                <strong>Result:</strong> Manager can see all pending prospects from their team members.
                            </div>
                        </div>
                    </div>

                    <div class="step-card" onclick="toggleStep(this)">
                        <div class="step-header">
                            <div style="display: flex; align-items: center;">
                                <span class="step-number">4</span>
                                <span class="step-title">Manager Verifies/Rejects Prospect</span>
                            </div>
                            <i class="fas fa-chevron-down step-icon"></i>
                        </div>
                        <div class="step-details">
                            <div class="step-info">
                                <strong>Who:</strong> <span class="role-badge">Senior Manager</span><br>
                                <strong>Action:</strong> Manager reviews the prospect and either verifies or rejects it. Only the sales executive's direct manager can verify.<br>
                                <strong>Result:</strong> 
                                <span class="badge badge-verified">Verified</span> - Prospect moves forward
                                <span class="badge badge-dead">Rejected</span> - Prospect is rejected
                            </div>
                            <div class="highlight-box">
                                <strong>Important:</strong> CRM/Admin can only VIEW prospects, they cannot verify them. Only the sales executive's manager can verify.
                            </div>
                        </div>
                    </div>

                    <div class="step-card" onclick="toggleStep(this)">
                        <div class="step-header">
                            <div style="display: flex; align-items: center;">
                                <span class="step-number">5</span>
                                <span class="step-title">Meeting Scheduled <span class="badge badge-scheduled">No Verification</span></span>
                            </div>
                            <i class="fas fa-chevron-down step-icon"></i>
                        </div>
                        <div class="step-details">
                            <div class="step-info">
                                <strong>Who:</strong> <span class="role-badge">Senior Manager</span><br>
                                <strong>Action:</strong> Manager schedules a meeting with the verified prospect. Meeting can be created from prospect or manager can create their own meeting.<br>
                                <strong>Result:</strong> Meeting is scheduled with date, time, and customer details. Status: <span class="badge badge-scheduled">Scheduled</span>
                            </div>
                            <div class="highlight-box">
                                <strong>Note:</strong> At this stage, NO verification is required. Meeting is just scheduled.
                            </div>
                        </div>
                    </div>

                    <div class="step-card" onclick="toggleStep(this)">
                        <div class="step-header">
                            <div style="display: flex; align-items: center;">
                                <span class="step-number">6</span>
                                <span class="step-title">Manager Completes Meeting <span class="badge badge-pending">Photo Required</span></span>
                            </div>
                            <i class="fas fa-chevron-down step-icon"></i>
                        </div>
                        <div class="step-details">
                            <div class="step-info">
                                <strong>Who:</strong> <span class="role-badge">Senior Manager</span><br>
                                <strong>Action:</strong> Manager marks meeting as completed and MUST upload proof photos (at least 1 photo required).<br>
                                <strong>Result:</strong> Meeting status changes to <span class="badge badge-pending">Completed</span> and moves to verification queue.
                            </div>
                            <div class="highlight-box">
                                <strong>Required:</strong> Proof photos must be uploaded. Max 5MB per image. Multiple photos can be uploaded.
                            </div>
                        </div>
                    </div>

                    <div class="step-card" onclick="toggleStep(this)">
                        <div class="step-header">
                            <div style="display: flex; align-items: center;">
                                <span class="step-number">7</span>
                                <span class="step-title">Meeting Pending CRM Verification</span>
                            </div>
                            <i class="fas fa-chevron-down step-icon"></i>
                        </div>
                        <div class="step-details">
                            <div class="step-info">
                                <strong>Who:</strong> <span class="role-badge">CRM</span> <span class="role-badge">Admin</span><br>
                                <strong>Action:</strong> Meeting appears in CRM/Admin verification panel for review.<br>
                                <strong>Result:</strong> Meeting shows as <span class="badge badge-pending">Pending Verification</span>
                            </div>
                        </div>
                    </div>

                    <div class="step-card" onclick="toggleStep(this)">
                        <div class="step-header">
                            <div style="display: flex; align-items: center;">
                                <span class="step-number">8</span>
                                <span class="step-title">CRM Verifies Meeting <span class="badge badge-verified">Achievement Count</span></span>
                            </div>
                            <i class="fas fa-chevron-down step-icon"></i>
                        </div>
                        <div class="step-details">
                            <div class="step-info">
                                <strong>Who:</strong> <span class="role-badge">CRM</span> <span class="role-badge">Admin</span><br>
                                <strong>Action:</strong> CRM/Admin reviews the meeting and proof photos, then verifies it.<br>
                                <strong>Result:</strong> Meeting status: <span class="badge badge-verified">Verified</span><br>
                                <strong>Achievement:</strong> This counts as 1 Meeting Achievement for the Manager. (e.g., 2/10 meetings completed)
                            </div>
                        </div>
                    </div>

                    <div class="step-card" onclick="toggleStep(this)">
                        <div class="step-header">
                            <div style="display: flex; align-items: center;">
                                <span class="step-number">9</span>
                                <span class="step-title">Site Visit Scheduled <span class="badge badge-scheduled">No Verification</span></span>
                            </div>
                            <i class="fas fa-chevron-down step-icon"></i>
                        </div>
                        <div class="step-details">
                            <div class="step-info">
                                <strong>Who:</strong> <span class="role-badge">Senior Manager</span><br>
                                <strong>Action:</strong> After meeting is verified, manager schedules a site visit. Can convert meeting to site visit or create new site visit.<br>
                                <strong>Result:</strong> Site visit is scheduled. Status: <span class="badge badge-scheduled">Scheduled</span>
                            </div>
                            <div class="highlight-box">
                                <strong>Note:</strong> At this stage, NO verification is required. Site visit is just scheduled.
                            </div>
                        </div>
                    </div>

                    <div class="step-card" onclick="toggleStep(this)">
                        <div class="step-header">
                            <div style="display: flex; align-items: center;">
                                <span class="step-number">10</span>
                                <span class="step-title">Manager Completes Site Visit <span class="badge badge-pending">Photo Required</span></span>
                            </div>
                            <i class="fas fa-chevron-down step-icon"></i>
                        </div>
                        <div class="step-details">
                            <div class="step-info">
                                <strong>Who:</strong> <span class="role-badge">Senior Manager</span><br>
                                <strong>Action:</strong> Manager marks site visit as completed and MUST upload proof photos (at least 1 photo required).<br>
                                <strong>Result:</strong> Site visit status: <span class="badge badge-pending">Completed</span> and moves to verification queue.
                            </div>
                            <div class="highlight-box">
                                <strong>Required:</strong> Proof photos must be uploaded. Max 5MB per image.
                            </div>
                        </div>
                    </div>

                    <div class="step-card" onclick="toggleStep(this)">
                        <div class="step-header">
                            <div style="display: flex; align-items: center;">
                                <span class="step-number">11</span>
                                <span class="step-title">Site Visit Pending CRM Verification</span>
                            </div>
                            <i class="fas fa-chevron-down step-icon"></i>
                        </div>
                        <div class="step-details">
                            <div class="step-info">
                                <strong>Who:</strong> <span class="role-badge">CRM</span> <span class="role-badge">Admin</span><br>
                                <strong>Action:</strong> Site visit appears in CRM/Admin verification panel.<br>
                                <strong>Result:</strong> Site visit shows as <span class="badge badge-pending">Pending Verification</span>
                            </div>
                        </div>
                    </div>

                    <div class="step-card" onclick="toggleStep(this)">
                        <div class="step-header">
                            <div style="display: flex; align-items: center;">
                                <span class="step-number">12</span>
                                <span class="step-title">CRM Verifies Site Visit <span class="badge badge-verified">Achievement Count</span></span>
                            </div>
                            <i class="fas fa-chevron-down step-icon"></i>
                        </div>
                        <div class="step-details">
                            <div class="step-info">
                                <strong>Who:</strong> <span class="role-badge">CRM</span> <span class="role-badge">Admin</span><br>
                                <strong>Action:</strong> CRM/Admin reviews site visit and proof photos, then verifies it.<br>
                                <strong>Result:</strong> Site visit status: <span class="badge badge-verified">Verified</span><br>
                                <strong>Achievement:</strong> This counts as 1 Site Visit Achievement for the Manager. (e.g., 2/10 site visits completed)
                            </div>
                        </div>
                    </div>

                    <div class="step-card" onclick="toggleStep(this)">
                        <div class="step-header">
                            <div style="display: flex; align-items: center;">
                                <span class="step-number">13</span>
                                <span class="step-title">Manager Requests Closer <span class="badge badge-pending">Photo Required</span></span>
                            </div>
                            <i class="fas fa-chevron-down step-icon"></i>
                        </div>
                        <div class="step-details">
                            <div class="step-info">
                                <strong>Who:</strong> <span class="role-badge">Senior Manager</span><br>
                                <strong>Action:</strong> After site visit is verified, manager can request for closer. MUST upload proof photos.<br>
                                <strong>Result:</strong> Closer request is submitted. Status: <span class="badge badge-pending">Pending Verification</span>
                            </div>
                            <div class="highlight-box">
                                <strong>Required:</strong> Proof photos must be uploaded for closer request. This is a MANUAL conversion, not automatic.
                            </div>
                        </div>
                    </div>

                    <div class="step-card" onclick="toggleStep(this)">
                        <div class="step-header">
                            <div style="display: flex; align-items: center;">
                                <span class="step-number">14</span>
                                <span class="step-title">Closer Pending CRM Verification</span>
                            </div>
                            <i class="fas fa-chevron-down step-icon"></i>
                        </div>
                        <div class="step-details">
                            <div class="step-info">
                                <strong>Who:</strong> <span class="role-badge">CRM</span> <span class="role-badge">Admin</span><br>
                                <strong>Action:</strong> Closer request appears in CRM/Admin verification panel.<br>
                                <strong>Result:</strong> Closer shows as <span class="badge badge-pending">Pending Verification</span>
                            </div>
                        </div>
                    </div>

                    <div class="step-card" onclick="toggleStep(this)">
                        <div class="step-header">
                            <div style="display: flex; align-items: center;">
                                <span class="step-number">15</span>
                                <span class="step-title">CRM Verifies Closer <span class="badge badge-verified">Closer Achievement Count</span></span>
                            </div>
                            <i class="fas fa-chevron-down step-icon"></i>
                        </div>
                        <div class="step-details">
                            <div class="step-info">
                                <strong>Who:</strong> <span class="role-badge">CRM</span> <span class="role-badge">Admin</span><br>
                                <strong>Action:</strong> CRM/Admin reviews closer request and proof photos, then verifies it.<br>
                                <strong>Result:</strong> Closer status: <span class="badge badge-verified">Verified</span><br>
                                <strong>Achievement:</strong> This counts as 1 Closer Achievement for the Manager. (e.g., 1/5 closers completed)<br>
                                <strong>Lead Status:</strong> Automatically updated to <strong>Closed Won</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Flow 2: Manager's Own Lead to Closer -->
                <div id="flow2" class="flow-content">
                    <h3 class="flow-title">Flow 2: Manager's Own Lead to Closer</h3>
                    
                    <div class="flow-diagram">
                        <div class="flow-diagram-title">Visual Flow</div>
                        <div style="text-align: center; line-height: 2;">
                            <span class="flow-box">Manager</span>
                            <span class="flow-arrow">→</span>
                            <span class="flow-box">Create Lead</span>
                            <span class="flow-arrow">→</span>
                            <span class="flow-box">Meeting</span>
                            <span class="flow-arrow">→</span>
                            <span class="flow-box">Site Visit</span>
                            <span class="flow-arrow">→</span>
                            <span class="flow-box">Closer</span>
                            <span class="flow-arrow">→</span>
                            <span class="flow-box">Closed Won</span>
                        </div>
                    </div>

                    <div class="step-card" onclick="toggleStep(this)">
                        <div class="step-header">
                            <div style="display: flex; align-items: center;">
                                <span class="step-number">1</span>
                                <span class="step-title">Manager Creates Own Lead</span>
                            </div>
                            <i class="fas fa-chevron-down step-icon"></i>
                        </div>
                        <div class="step-details">
                            <div class="step-info">
                                <strong>Who:</strong> <span class="role-badge">Senior Manager</span><br>
                                <strong>Action:</strong> Manager can create their own lead directly without going through sales executive prospect flow.<br>
                                <strong>Result:</strong> Lead is created and appears in manager's leads list.
                            </div>
                            <div class="highlight-box">
                                <strong>Difference:</strong> This flow skips the sales executive prospect and manager verification steps. Manager goes directly to scheduling meeting.
                            </div>
                        </div>
                    </div>

                    <div class="step-card" onclick="toggleStep(this)">
                        <div class="step-header">
                            <div style="display: flex; align-items: center;">
                                <span class="step-number">2</span>
                                <span class="step-title">Meeting Scheduled <span class="badge badge-scheduled">No Verification</span></span>
                            </div>
                            <i class="fas fa-chevron-down step-icon"></i>
                        </div>
                        <div class="step-details">
                            <div class="step-info">
                                <strong>Who:</strong> <span class="role-badge">Senior Manager</span><br>
                                <strong>Action:</strong> Manager schedules meeting with the lead they created.<br>
                                <strong>Result:</strong> Meeting is scheduled. Status: <span class="badge badge-scheduled">Scheduled</span>
                            </div>
                            <div class="highlight-box">
                                <strong>Note:</strong> From this point, the flow is IDENTICAL to Flow 1 (Steps 6-15). Meeting completion, site visit, and closer follow the same process.
                            </div>
                        </div>
                    </div>

                    <div class="step-card" onclick="toggleStep(this)">
                        <div class="step-header">
                            <div style="display: flex; align-items: center;">
                                <span class="step-number">3-8</span>
                                <span class="step-title">Same as Flow 1 Steps 6-12</span>
                            </div>
                            <i class="fas fa-chevron-down step-icon"></i>
                        </div>
                        <div class="step-details">
                            <div class="step-info">
                                <strong>Steps:</strong><br>
                                • Complete Meeting (with photos) → CRM Verifies → Meeting Achievement<br>
                                • Schedule Site Visit → Complete Site Visit (with photos) → CRM Verifies → Site Visit Achievement<br>
                                • Request Closer (with photos) → CRM Verifies → Closer Achievement → Lead: Closed Won
                            </div>
                            <div class="highlight-box">
                                <strong>Important:</strong> All verification steps, photo requirements, and achievement counting work exactly the same as Flow 1.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Flow 3: Dead Lead Management -->
                <div id="flow3" class="flow-content">
                    <h3 class="flow-title">Flow 3: Dead Lead Management</h3>
                    
                    <div class="flow-diagram">
                        <div class="flow-diagram-title">Visual Flow</div>
                        <div style="text-align: center; line-height: 2;">
                            <span class="flow-box">Any Stage</span>
                            <span class="flow-arrow">→</span>
                            <span class="flow-box">Mark as Dead</span>
                            <span class="flow-arrow">→</span>
                            <span class="flow-box">Hidden from Manager</span>
                            <span class="flow-arrow">→</span>
                            <span class="flow-box">Admin/CRM Trash</span>
                        </div>
                    </div>

                    <div class="step-card" onclick="toggleStep(this)">
                        <div class="step-header">
                            <div style="display: flex; align-items: center;">
                                <span class="step-number">1</span>
                                <span class="step-title">Dead Lead Option Available at Any Stage</span>
                            </div>
                            <i class="fas fa-chevron-down step-icon"></i>
                        </div>
                        <div class="step-details">
                            <div class="step-info">
                                <strong>When:</strong> At ANY stage (Meeting, Site Visit, or Closer Request)<br>
                                <strong>Who:</strong> <span class="role-badge">Senior Manager</span><br>
                                <strong>Action:</strong> Manager can mark a lead as dead if it's no longer viable.<br>
                                <strong>Stages:</strong>
                                <ul style="margin-left: 20px; margin-top: 10px;">
                                    <li>During Meeting (scheduled or completed)</li>
                                    <li>During Site Visit (scheduled or completed)</li>
                                    <li>During Closer Request (pending verification)</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="step-card" onclick="toggleStep(this)">
                        <div class="step-header">
                            <div style="display: flex; align-items: center;">
                                <span class="step-number">2</span>
                                <span class="step-title">Mark as Dead - Reason Required</span>
                            </div>
                            <i class="fas fa-chevron-down step-icon"></i>
                        </div>
                        <div class="step-details">
                            <div class="step-info">
                                <strong>Action:</strong> Manager clicks "Mark as Dead" button and MUST provide a reason/remark.<br>
                                <strong>Required:</strong> Reason field is mandatory (cannot be empty).<br>
                                <strong>Result:</strong> 
                                <ul style="margin-left: 20px; margin-top: 10px;">
                                    <li>Lead is marked as <span class="badge badge-dead">Dead</span></li>
                                    <li>Dead reason is saved</li>
                                    <li>Stage at which it was marked dead is recorded</li>
                                    <li>Timestamp and user who marked it dead is saved</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="step-card" onclick="toggleStep(this)">
                        <div class="step-header">
                            <div style="display: flex; align-items: center;">
                                <span class="step-number">3</span>
                                <span class="step-title">Dead Lead Hidden from Manager</span>
                            </div>
                            <i class="fas fa-chevron-down step-icon"></i>
                        </div>
                        <div class="step-details">
                            <div class="step-info">
                                <strong>Who:</strong> <span class="role-badge">Senior Manager</span><br>
                                <strong>Result:</strong> Once marked as dead, the lead/meeting/site visit:
                                <ul style="margin-left: 20px; margin-top: 10px;">
                                    <li>Disappears from manager's dashboard</li>
                                    <li>Does not appear in manager's meetings list</li>
                                    <li>Does not appear in manager's site visits list</li>
                                    <li>Cannot be accessed by manager anymore</li>
                                </ul>
                            </div>
                            <div class="highlight-box">
                                <strong>Important:</strong> Manager cannot see or access dead leads. They are completely hidden from manager's view.
                            </div>
                        </div>
                    </div>

                    <div class="step-card" onclick="toggleStep(this)">
                        <div class="step-header">
                            <div style="display: flex; align-items: center;">
                                <span class="step-number">4</span>
                                <span class="step-title">Dead Lead Visible in Admin/CRM Trash</span>
                            </div>
                            <i class="fas fa-chevron-down step-icon"></i>
                        </div>
                        <div class="step-details">
                            <div class="step-info">
                                <strong>Who:</strong> <span class="role-badge">CRM</span> <span class="role-badge">Admin</span><br>
                                <strong>Location:</strong> Admin/CRM panel → "Dead Leads / Trash" section<br>
                                <strong>View:</strong> Admin/CRM can see:
                                <ul style="margin-left: 20px; margin-top: 10px;">
                                    <li>All dead leads</li>
                                    <li>All dead meetings</li>
                                    <li>All dead site visits</li>
                                    <li>Dead reason</li>
                                    <li>Stage at which it was marked dead</li>
                                    <li>Who marked it dead and when</li>
                                </ul>
                            </div>
                            <div class="highlight-box">
                                <strong>Purpose:</strong> This allows Admin/CRM to review dead leads, analyze reasons, and maintain records.
                            </div>
                        </div>
                    </div>

                    <div class="step-card" onclick="toggleStep(this)">
                        <div class="step-header">
                            <div style="display: flex; align-items: center;">
                                <span class="step-number">5</span>
                                <span class="step-title">Dead Lead Impact</span>
                            </div>
                            <i class="fas fa-chevron-down step-icon"></i>
                        </div>
                        <div class="step-details">
                            <div class="step-info">
                                <strong>Impact:</strong>
                                <ul style="margin-left: 20px; margin-top: 10px;">
                                    <li>Dead leads do NOT count towards achievements</li>
                                    <li>Dead leads do NOT appear in manager's reports</li>
                                    <li>Dead leads are permanently marked (cannot be undone by manager)</li>
                                    <li>Lead status is updated to "Closed Lost"</li>
                                </ul>
                            </div>
                            <div class="highlight-box">
                                <strong>Note:</strong> Once a lead is marked as dead, it cannot be reactivated by the manager. Only Admin/CRM can view it in trash.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variables already defined in head section - DO NOT redeclare
        // Use global variables: deferredPrompt and installButton from head section

        // Function already defined in head - just ensure it's accessible
        if (typeof window.installPWA === 'undefined') {
            // Fallback if head script didn't load
            window.installPWA = function() {
            console.log('✅ Install button clicked - installPWA() called');
            
            if (!installButton) {
                installButton = document.getElementById('installButton');
            }

            if (deferredPrompt) {
                console.log('Showing install prompt...');
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('✅ User accepted the install prompt');
                    } else {
                        console.log('❌ User dismissed the install prompt');
                    }
                    deferredPrompt = null;
                }).catch((error) => {
                    console.error('Error showing install prompt:', error);
                });
            } else {
                console.log('⚠️ Deferred prompt not available');
                if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
                    alert('App is already installed!');
                    return;
                }
                
                const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                if (isIOS) {
                    alert('To install this app on iOS:\n1. Tap the Share button (square with arrow)\n2. Tap "Add to Home Screen"');
                } else {
                    const manifestLink = document.querySelector('link[rel="manifest"]');
                    console.log('PWA Requirements Check:');
                    console.log('- Service Worker:', 'serviceWorker' in navigator);
                    console.log('- Manifest:', manifestLink ? 'Found' : 'Not found');
                    
                    if (manifestLink) {
                        fetch(manifestLink.href)
                            .then(response => response.json())
                            .then(manifest => {
                                console.log('Manifest icons:', manifest.icons);
                                if (!manifest.icons || manifest.icons.length < 2) {
                                    console.error('❌ Manifest needs proper icons (192x192 and 512x512)');
                                }
                            })
                            .catch(error => console.error('Error loading manifest:', error));
                    }
                    
                    // Check icons only once, not on every click
                    if (!window.iconsChecked) {
                        window.iconsChecked = true;
                        fetch('/icon-192.png', { method: 'HEAD' })
                            .then(response => {
                                if (response.ok) {
                                    console.log('✅ Icon files exist');
                                } else {
                                    console.warn('⚠️ Icon files (192x192 and 512x512) are missing');
                                    console.log('💡 To create icons: Visit http://localhost:8008/create-icon.html');
                                    console.log('💡 After creating icons, refresh the page');
                                }
                            })
                            .catch(() => {});
                    }
                    
                    // Don't show alert - just log to console
                    console.log('💡 Install prompt will appear automatically when PWA requirements are met.');
                    console.log('💡 Alternative: Use browser menu → Install (Chrome/Edge show install icon in address bar)');
                }
            }
            };
        }

        // Function already defined in head section, no need to redefine

        function switchFlow(flowId) {
            // Hide all flows
            document.querySelectorAll('.flow-content').forEach(flow => {
                flow.classList.remove('active');
            });
            
            // Remove active from all tabs
            document.querySelectorAll('.doc-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected flow
            document.getElementById(flowId).classList.add('active');
            
            // Add active to clicked tab
            event.target.closest('.doc-tab').classList.add('active');
            
            // Scroll to top of content
            document.querySelector('.doc-content').scrollTop = 0;
        }

        function toggleStep(card) {
            card.classList.toggle('expanded');
        }

        // Expand first step by default
        document.addEventListener('DOMContentLoaded', function() {
            const firstStep = document.querySelector('.step-card');
            if (firstStep) {
                firstStep.classList.add('expanded');
            }
        });

        // PWA Install Button Logic - variables and function already defined in head section
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Use global variables from head section
            if (typeof installButton === 'undefined' || installButton === null) {
                installButton = document.getElementById('installButton');
            }
            
            if (installButton) {
                // Also add event listener as backup (onclick should work, but this is backup)
                installButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Button clicked via event listener (backup)');
                    if (typeof window.installPWA === 'function') {
                        window.installPWA();
                    } else if (typeof installPWA === 'function') {
                        installPWA();
                    } else {
                        console.error('installPWA function not found!');
                    }
                });
                console.log('✅ Install button found and event listener added');
                console.log('Function available:', typeof installPWA, typeof window.installPWA);
            } else {
                console.error('❌ Install button not found!');
            }
        });

        // Listen for the beforeinstallprompt event - use global deferredPrompt from head
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('✅ beforeinstallprompt event fired - PWA is installable!');
            // Prevent the mini-infobar from appearing on mobile
            e.preventDefault();
            // Stash the event so it can be triggered later - use global variable
            if (typeof deferredPrompt !== 'undefined') {
                deferredPrompt = e;
            } else {
                window.deferredPrompt = e;
            }
            console.log('Deferred prompt stored');
            
            // Show button if hidden
            if (installButton) {
                installButton.style.display = 'inline-block';
            } else if (typeof installButton !== 'undefined') {
                installButton = document.getElementById('installButton');
                if (installButton) {
                    installButton.style.display = 'inline-block';
                }
            }
        });

        // Log if beforeinstallprompt doesn't fire (for debugging)
        setTimeout(() => {
            if (!deferredPrompt) {
                console.warn('⚠️ beforeinstallprompt event did not fire. Possible reasons:');
                console.warn('1. App may already be installed');
                console.warn('2. PWA requirements not fully met (check manifest, service worker, icons)');
                console.warn('3. Browser may not support PWA installation');
                console.warn('4. Site may need to be accessed via HTTPS (localhost is OK)');
                
                // Check manifest icons
                const manifestLink = document.querySelector('link[rel="manifest"]');
                if (manifestLink) {
                    fetch(manifestLink.href)
                        .then(response => response.json())
                        .then(manifest => {
                            console.log('Manifest icons:', manifest.icons);
                            if (!manifest.icons || manifest.icons.length < 2) {
                                console.error('❌ Manifest needs at least 2 icons (192x192 and 512x512)');
                            }
                        })
                        .catch(error => {
                            console.error('Error loading manifest:', error);
                        });
                }
            }
        }, 5000);

        // Track installation
        window.addEventListener('appinstalled', () => {
            console.log('PWA was installed');
            deferredPrompt = null;
        });

        // Register Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                // Unregister old service workers first
                navigator.serviceWorker.getRegistrations().then((registrations) => {
                    for (let registration of registrations) {
                        registration.unregister();
                    }
                    // Register new service worker
                    return navigator.serviceWorker.register('/sw.js?v=' + Date.now());
                })
                .then((registration) => {
                    console.log('Service Worker registered successfully:', registration.scope);
                    // Check for updates
                    registration.update();
                })
                .catch((error) => {
                    console.error('Service Worker registration failed:', error);
                });
            });
        }

        // Debug: Log PWA installability
        window.addEventListener('load', () => {
            setTimeout(() => {
                console.log('PWA Installability Check:');
                console.log('- Service Worker:', 'serviceWorker' in navigator);
                console.log('- Manifest:', document.querySelector('link[rel="manifest"]') ? 'Found' : 'Not found');
                console.log('- Standalone mode:', window.matchMedia('(display-mode: standalone)').matches);
                console.log('- Deferred prompt:', deferredPrompt ? 'Available' : 'Not available');
                console.log('- Install function:', typeof window.installPWA);
                console.log('- Install button:', installButton ? 'Found' : 'Not found');
            }, 2000);
        });
    </script>
</body>
</html>
