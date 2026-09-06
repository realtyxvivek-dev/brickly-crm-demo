<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PWA Installation Test - {{ brand_name() }}</title>
    <link rel="manifest" href="{{ asset('manifest.json') }}?v={{ filemtime(public_path('manifest.json')) }}">
    <link rel="apple-touch-icon" href="{{ asset('icon-192.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icon-192.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('icon-512.png') }}">
    <meta name="theme-color" content="#205A44">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #063A1C;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .test-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f9fafb;
            border-radius: 8px;
            border-left: 4px solid #205A44;
        }
        .test-section h2 {
            color: #063A1C;
            margin-bottom: 15px;
            font-size: 20px;
        }
        .test-item {
            display: flex;
            align-items: center;
            padding: 12px;
            margin-bottom: 8px;
            background: white;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }
        .test-item .icon {
            width: 24px;
            height: 24px;
            margin-right: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-weight: bold;
            font-size: 14px;
        }
        .test-item .icon.pass {
            background: #10b981;
            color: white;
        }
        .test-item .icon.fail {
            background: #ef4444;
            color: white;
        }
        .test-item .icon.warning {
            background: #f59e0b;
            color: white;
        }
        .test-item .label {
            flex: 1;
            font-weight: 500;
            color: #374151;
        }
        .test-item .value {
            color: #6b7280;
            font-size: 14px;
        }
        .test-item .details {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #6b7280;
        }
        .install-button {
            background: #205A44;
            color: white;
            border: none;
            padding: 14px 30px;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 20px;
            transition: all 0.3s;
        }
        .install-button:hover {
            background: #063A1C;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(32, 90, 68, 0.3);
        }
        .install-button:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }
        .error-box {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
            font-size: 14px;
        }
        .success-box {
            background: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
            font-size: 14px;
        }
        .code-block {
            background: #1f2937;
            color: #f9fafb;
            padding: 15px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 PWA Installation Test</h1>
        <p class="subtitle">Complete diagnostic tool to check why PWA installation is not working</p>

        <div class="test-section">
            <h2>1. Basic Requirements</h2>
            <div id="basicTests"></div>
        </div>

        <div class="test-section">
            <h2>2. Manifest File</h2>
            <div id="manifestTests"></div>
        </div>

        <div class="test-section">
            <h2>3. Service Worker</h2>
            <div id="serviceWorkerTests"></div>
        </div>

        <div class="test-section">
            <h2>4. Icons</h2>
            <div id="iconTests"></div>
        </div>

        <div class="test-section">
            <h2>5. Installation Status</h2>
            <div id="installationTests"></div>
        </div>

        <div class="test-section">
            <h2>6. Browser Events</h2>
            <div id="eventTests"></div>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button id="installBtn" class="install-button" onclick="testInstall()">
                <i class="fas fa-download"></i> Test Install
            </button>
            <button class="install-button" onclick="generateIcons()" style="background: #f59e0b;">
                <i class="fas fa-magic"></i> Generate Icons (Server)
            </button>
            <a href="/create-icon-save.html" class="install-button" style="background: #10b981; text-decoration: none; display: inline-block;">
                <i class="fas fa-save"></i> Create & Save Icons
            </a>
            <a href="/create-icon.html" class="install-button" style="background: #6366f1; text-decoration: none; display: inline-block;">
                <i class="fas fa-download"></i> Download Icons
            </a>
        </div>

        <div id="resultBox"></div>
    </div>

    <script>
        let deferredPrompt = null;
        let testResults = {
            basic: [],
            manifest: [],
            serviceWorker: [],
            icons: [],
            installation: [],
            events: []
        };

        // Test basic requirements
        function testBasic() {
            const tests = [
                {
                    label: 'HTTPS or localhost',
                    test: () => window.location.protocol === 'https:' || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1',
                    details: `Protocol: ${window.location.protocol}, Hostname: ${window.location.hostname}`
                },
                {
                    label: 'Service Worker Support',
                    test: () => 'serviceWorker' in navigator,
                    details: navigator.serviceWorker ? 'Supported' : 'Not supported'
                },
                {
                    label: 'Notification Support',
                    test: () => 'Notification' in window,
                    details: window.Notification ? 'Supported' : 'Not supported'
                },
                {
                    label: 'Manifest Link Present',
                    test: () => document.querySelector('link[rel="manifest"]') !== null,
                    details: document.querySelector('link[rel="manifest"]') ? 'Found' : 'Missing'
                }
            ];

            testResults.basic = tests.map(t => ({
                label: t.label,
                pass: t.test(),
                details: t.details
            }));
        }

        // Test manifest
        async function testManifest() {
            const manifestLink = document.querySelector('link[rel="manifest"]');
            if (!manifestLink) {
                testResults.manifest = [{
                    label: 'Manifest Link',
                    pass: false,
                    details: 'Manifest link not found in HTML'
                }];
                return;
            }

            try {
                const response = await fetch(manifestLink.href);
                if (!response.ok) {
                    testResults.manifest = [{
                        label: 'Manifest File',
                        pass: false,
                        details: `HTTP ${response.status}: ${response.statusText}`
                    }];
                    return;
                }

                const manifest = await response.json();
                const tests = [
                    {
                        label: 'Manifest File Accessible',
                        pass: true,
                        details: 'Manifest loaded successfully'
                    },
                    {
                        label: 'Name Present',
                        pass: !!manifest.name,
                        details: manifest.name || 'Missing'
                    },
                    {
                        label: 'Short Name Present',
                        pass: !!manifest.short_name,
                        details: manifest.short_name || 'Missing'
                    },
                    {
                        label: 'Start URL Present',
                        pass: !!manifest.start_url,
                        details: manifest.start_url || 'Missing'
                    },
                    {
                        label: 'Display Mode',
                        pass: !!manifest.display,
                        details: manifest.display || 'Missing (should be "standalone")'
                    },
                    {
                        label: 'Icons Array',
                        pass: Array.isArray(manifest.icons) && manifest.icons.length > 0,
                        details: manifest.icons ? `${manifest.icons.length} icon(s) defined` : 'Missing'
                    },
                    {
                        label: 'Icon Sizes (192x192 required)',
                        pass: manifest.icons && manifest.icons.some(icon => 
                            icon.sizes && (icon.sizes.includes('192') || icon.sizes === 'any' || icon.sizes.includes('192x192'))
                        ),
                        details: manifest.icons ? 
                            manifest.icons.map(i => i.sizes).join(', ') : 
                            'No icons'
                    },
                    {
                        label: 'Icon Sizes (512x512 required)',
                        pass: manifest.icons && manifest.icons.some(icon => 
                            icon.sizes && (icon.sizes.includes('512') || icon.sizes === 'any' || icon.sizes.includes('512x512'))
                        ),
                        details: manifest.icons ? 
                            manifest.icons.map(i => i.sizes).join(', ') : 
                            'No icons'
                    }
                ];

                testResults.manifest = tests;
            } catch (error) {
                testResults.manifest = [{
                    label: 'Manifest Load Error',
                    pass: false,
                    details: error.message
                }];
            }
        }

        // Test service worker
        async function testServiceWorker() {
            if (!('serviceWorker' in navigator)) {
                testResults.serviceWorker = [{
                    label: 'Service Worker Support',
                    pass: false,
                    details: 'Browser does not support service workers'
                }];
                return;
            }

            try {
                const registrations = await navigator.serviceWorker.getRegistrations();
                const tests = [
                    {
                        label: 'Service Worker Registered',
                        pass: registrations.length > 0,
                        details: `${registrations.length} service worker(s) registered`
                    }
                ];

                if (registrations.length > 0) {
                    const registration = registrations[0];
                    tests.push({
                        label: 'Service Worker Active',
                        pass: registration.active !== null,
                        details: registration.active ? 'Active' : 'Not active'
                    });
                    tests.push({
                        label: 'Service Worker Scope',
                        pass: true,
                        details: registration.scope
                    });
                } else {
                    tests.push({
                        label: 'Service Worker File',
                        pass: false,
                        details: 'No service worker registered. Check if /sw.js exists and is accessible'
                    });
                }

                testResults.serviceWorker = tests;
            } catch (error) {
                testResults.serviceWorker = [{
                    label: 'Service Worker Error',
                    pass: false,
                    details: error.message
                }];
            }
        }

        // Test icons
        async function testIcons() {
            const manifestLink = document.querySelector('link[rel="manifest"]');
            if (!manifestLink) {
                testResults.icons = [{
                    label: 'Manifest Required',
                    pass: false,
                    details: 'Cannot test icons without manifest'
                }];
                return;
            }

            try {
                const response = await fetch(manifestLink.href);
                const manifest = await response.json();
                const icons = manifest.icons || [];

                const iconTests = [];
                for (const icon of icons) {
                    try {
                        const iconResponse = await fetch(icon.src, { method: 'HEAD' });
                        iconTests.push({
                            label: `Icon: ${icon.src}`,
                            pass: iconResponse.ok,
                            details: iconResponse.ok ? 
                                `Status: ${iconResponse.status}, Size: ${icon.sizes || 'unknown'}` :
                                `Status: ${iconResponse.status} - File not found`
                        });
                    } catch (error) {
                        iconTests.push({
                            label: `Icon: ${icon.src}`,
                            pass: false,
                            details: `Error: ${error.message}`
                        });
                    }
                }

                if (iconTests.length === 0) {
                    iconTests.push({
                        label: 'Icons Defined',
                        pass: false,
                        details: 'No icons defined in manifest'
                    });
                }

                testResults.icons = iconTests;
            } catch (error) {
                testResults.icons = [{
                    label: 'Icon Test Error',
                    pass: false,
                    details: error.message
                }];
            }
        }

        // Test installation status
        function testInstallation() {
            const tests = [
                {
                    label: 'Already Installed (Standalone)',
                    pass: window.matchMedia('(display-mode: standalone)').matches,
                    details: window.matchMedia('(display-mode: standalone)').matches ? 
                        'App is running in standalone mode' : 
                        'Not in standalone mode'
                },
                {
                    label: 'iOS Standalone',
                    pass: window.navigator.standalone === true,
                    details: window.navigator.standalone === true ? 
                        'Installed on iOS' : 
                        'Not installed on iOS'
                },
                {
                    label: 'Deferred Prompt Available',
                    pass: deferredPrompt !== null,
                    details: deferredPrompt ? 
                        'beforeinstallprompt event captured' : 
                        'beforeinstallprompt event not fired yet'
                }
            ];

            testResults.installation = tests;
        }

        // Test events
        function testEvents() {
            const tests = [
                {
                    label: 'beforeinstallprompt Event',
                    pass: deferredPrompt !== null,
                    details: deferredPrompt ? 
                        'Event fired and captured' : 
                        'Event not fired (PWA requirements may not be met)'
                }
            ];

            testResults.events = tests;
        }

        // Render test results
        function renderTests(sectionId, tests) {
            const container = document.getElementById(sectionId);
            container.innerHTML = '';

            if (tests.length === 0) {
                container.innerHTML = '<div class="test-item"><div class="label">Loading...</div></div>';
                return;
            }

            tests.forEach(test => {
                const item = document.createElement('div');
                item.className = 'test-item';
                
                const iconClass = test.pass ? 'pass' : 'fail';
                const iconText = test.pass ? '✓' : '✗';
                
                item.innerHTML = `
                    <div class="icon ${iconClass}">${iconText}</div>
                    <div class="label">${test.label}</div>
                    <div class="value">${test.details}</div>
                `;
                
                container.appendChild(item);
            });
        }

        // Run all tests
        async function runAllTests() {
            testBasic();
            renderTests('basicTests', testResults.basic);

            await testManifest();
            renderTests('manifestTests', testResults.manifest);

            await testServiceWorker();
            renderTests('serviceWorkerTests', testResults.serviceWorker);

            await testIcons();
            renderTests('iconTests', testResults.icons);

            testInstallation();
            renderTests('installationTests', testResults.installation);

            testEvents();
            renderTests('eventTests', testResults.events);

            // Show summary
            showSummary();
        }

        // Show summary
        function showSummary() {
            const resultBox = document.getElementById('resultBox');
            const allTests = [
                ...testResults.basic,
                ...testResults.manifest,
                ...testResults.serviceWorker,
                ...testResults.icons,
                ...testResults.installation,
                ...testResults.events
            ];

            const passed = allTests.filter(t => t.pass).length;
            const total = allTests.length;
            const failed = allTests.filter(t => !t.pass);

            if (failed.length === 0) {
                resultBox.innerHTML = `
                    <div class="success-box">
                        <strong>✅ All Tests Passed!</strong><br>
                        PWA should be installable. If installation prompt doesn't appear, try:
                        <ul style="margin-top: 10px; margin-left: 20px;">
                            <li>Wait a few seconds for browser to detect PWA</li>
                            <li>Check browser menu for install option</li>
                            <li>Clear browser cache and reload</li>
                        </ul>
                    </div>
                `;
            } else {
                resultBox.innerHTML = `
                    <div class="error-box">
                        <strong>❌ ${failed.length} Test(s) Failed</strong><br>
                        <strong>Passed: ${passed}/${total}</strong><br><br>
                        <strong>Failed Tests:</strong>
                        <ul style="margin-top: 10px; margin-left: 20px;">
                            ${failed.map(t => `<li><strong>${t.label}:</strong> ${t.details}</li>`).join('')}
                        </ul>
                    </div>
                `;
            }
        }

        // Test install function
        function testInstall() {
            const btn = document.getElementById('installBtn');
            btn.disabled = true;
            btn.textContent = 'Testing...';

            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        alert('✅ Installation accepted!');
                    } else {
                        alert('❌ Installation dismissed');
                    }
                    deferredPrompt = null;
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-download"></i> Test Install';
                    runAllTests();
                });
            } else {
                alert('Install prompt not available. Check the test results above to see what\'s missing.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-download"></i> Test Install';
            }
        }

        // Listen for beforeinstallprompt
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            console.log('✅ beforeinstallprompt event fired!');
            runAllTests();
        });

        // Listen for appinstalled
        window.addEventListener('appinstalled', () => {
            console.log('✅ PWA was installed!');
            runAllTests();
        });

        // Run tests on load
        window.addEventListener('load', () => {
            // Register service worker
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js')
                    .then(() => {
                        console.log('Service Worker registered');
                        runAllTests();
                    })
                    .catch(error => {
                        console.error('Service Worker registration failed:', error);
                        runAllTests();
                    });
            } else {
                runAllTests();
            }
        });

        // Generate icons server-side
        async function generateIcons() {
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';

            try {
                const response = await fetch('/generate-icons-server');
                const data = await response.json();
                
                if (data.success) {
                    alert('✅ Icons generated successfully!\n\nPlease refresh this page to verify.');
                    location.reload();
                } else {
                    alert('❌ Failed to generate icons.\n\n' + data.message + '\n\nPlease use /create-icon.html instead.');
                }
            } catch (error) {
                alert('❌ Error: ' + error.message + '\n\nPlease use /create-icon.html to generate icons manually.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        // Re-run tests every 5 seconds to catch changes
        setInterval(() => {
            testInstallation();
            testEvents();
            renderTests('installationTests', testResults.installation);
            renderTests('eventTests', testResults.events);
            showSummary();
        }, 5000);
    </script>
</body>
</html>
