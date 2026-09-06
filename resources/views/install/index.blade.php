<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Installation Wizard - {{ brand_name() }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .step-indicator {
            transition: all 0.3s ease;
        }
        .step-indicator.active {
            background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);
            color: white;
        }
        .step-indicator.completed {
            background: #10b981;
            color: white;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">
                <i class="fas fa-rocket text-[#063A1C] mr-2"></i>
                Installation Wizard
            </h1>
            <p class="text-gray-600">Welcome! Let's set up your CRM system in a few simple steps.</p>
        </div>

        <!-- Progress Steps -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center flex-1">
                    <div class="flex flex-col items-center flex-1">
                        <div id="step-1-indicator" class="step-indicator w-12 h-12 rounded-full flex items-center justify-center font-semibold bg-gray-200 text-gray-600">
                            1
                        </div>
                        <div class="mt-2 text-xs text-center text-gray-600">Requirements</div>
                    </div>
                    <div class="flex-1 h-1 mx-2 bg-gray-200" id="progress-1"></div>
                </div>
                <div class="flex items-center flex-1">
                    <div class="flex flex-col items-center flex-1">
                        <div id="step-2-indicator" class="step-indicator w-12 h-12 rounded-full flex items-center justify-center font-semibold bg-gray-200 text-gray-600">
                            2
                        </div>
                        <div class="mt-2 text-xs text-center text-gray-600">Database</div>
                    </div>
                    <div class="flex-1 h-1 mx-2 bg-gray-200" id="progress-2"></div>
                </div>
                <div class="flex items-center flex-1">
                    <div class="flex flex-col items-center flex-1">
                        <div id="step-3-indicator" class="step-indicator w-12 h-12 rounded-full flex items-center justify-center font-semibold bg-gray-200 text-gray-600">
                            3
                        </div>
                        <div class="mt-2 text-xs text-center text-gray-600">Admin User</div>
                    </div>
                    <div class="flex-1 h-1 mx-2 bg-gray-200" id="progress-3"></div>
                </div>
                <div class="flex items-center flex-1">
                    <div class="flex flex-col items-center flex-1">
                        <div id="step-4-indicator" class="step-indicator w-12 h-12 rounded-full flex items-center justify-center font-semibold bg-gray-200 text-gray-600">
                            4
                        </div>
                        <div class="mt-2 text-xs text-center text-gray-600">Install</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-8">
            <!-- Step 1: Requirements Check -->
            <div id="step-1" class="step-content">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">
                    <i class="fas fa-check-circle text-green-600 mr-2"></i>
                    System Requirements
                </h2>
                <p class="text-gray-600 mb-6">Checking if your server meets all requirements...</p>
                
                <div id="requirements-result" class="space-y-4">
                    <div class="flex items-center justify-center py-8">
                        <i class="fas fa-spinner fa-spin text-4xl text-[#063A1C]"></i>
                    </div>
                </div>
            </div>

            <!-- Step 2: Database Configuration -->
            <div id="step-2" class="step-content hidden">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">
                    <i class="fas fa-database text-blue-600 mr-2"></i>
                    Database Configuration
                </h2>
                <p class="text-gray-600 mb-6">Enter your database connection details:</p>
                
                <form id="database-form" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Database Host *</label>
                            <input type="text" name="host" value="127.0.0.1" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#063A1C] focus:border-[#063A1C]">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Database Port *</label>
                            <input type="number" name="port" value="3306" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#063A1C] focus:border-[#063A1C]">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Database Name *</label>
                        <input type="text" name="database" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#063A1C] focus:border-[#063A1C]"
                            placeholder="e.g., realtorcrm">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Database Username *</label>
                            <input type="text" name="username" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#063A1C] focus:border-[#063A1C]">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Database Password</label>
                            <input type="password" name="password"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#063A1C] focus:border-[#063A1C]">
                        </div>
                    </div>
                    <div id="db-test-result" class="hidden"></div>
                    <div class="flex justify-between">
                        <button type="button" onclick="goToStep(1)" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                            <i class="fas fa-arrow-left mr-2"></i> Back
                        </button>
                        <button type="button" onclick="testDatabase()" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i class="fas fa-plug mr-2"></i> Test Connection
                        </button>
                        <button type="button" onclick="goToStep(3)" id="db-next-btn" class="px-6 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] hidden">
                            Next <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Step 3: Admin User & App Settings -->
            <div id="step-3" class="step-content hidden">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">
                    <i class="fas fa-user-shield text-purple-600 mr-2"></i>
                    Admin User & Application Settings
                </h2>
                <p class="text-gray-600 mb-6">Create your admin account and configure application:</p>
                
                <form id="admin-form" class="space-y-4">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <h3 class="font-semibold text-blue-900 mb-2">Application Settings</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Application Name *</label>
                                <input type="text" name="app_name" value="{{ brand_name() }}" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#063A1C] focus:border-[#063A1C]">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Application URL *</label>
                                <input type="url" name="app_url" id="app_url" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#063A1C] focus:border-[#063A1C]"
                                    placeholder="https://yoursite.com or crm.bihtech.in">
                            </div>
                        </div>
                    </div>

                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                        <h3 class="font-semibold text-purple-900 mb-2">Admin Account</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Admin Name *</label>
                                <input type="text" name="admin_name" value="vivek" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#063A1C] focus:border-[#063A1C]"
                                    placeholder="Admin User">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Admin Email *</label>
                                <input type="email" name="admin_email" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#063A1C] focus:border-[#063A1C]"
                                    placeholder="admin@example.com">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Admin Password *</label>
                                <input type="password" name="admin_password" required minlength="8"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#063A1C] focus:border-[#063A1C]"
                                    placeholder="Minimum 8 characters">
                                <p class="text-xs text-gray-500 mt-1">Password must be at least 8 characters long</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-between">
                        <button type="button" onclick="goToStep(2)" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                            <i class="fas fa-arrow-left mr-2"></i> Back
                        </button>
                        <button type="button" onclick="goToStep(4)" class="px-6 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d]">
                            Next <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Step 4: Installation -->
            <div id="step-4" class="step-content hidden">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">
                    <i class="fas fa-cog fa-spin text-[#063A1C] mr-2"></i>
                    Installing...
                </h2>
                <p class="text-gray-600 mb-6">Please wait while we set up your system. This may take a few minutes.</p>
                
                <div id="install-progress" class="space-y-4">
                    <div class="bg-gray-100 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700">Initializing installation...</span>
                            <i class="fas fa-spinner fa-spin text-[#063A1C]"></i>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div id="progress-bar" class="bg-gradient-to-r from-[#063A1C] to-[#205A44] h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                    </div>
                    <div id="install-logs" class="bg-gray-50 rounded-lg p-4 max-h-64 overflow-y-auto">
                        <div class="text-sm text-gray-600">Waiting to start...</div>
                    </div>
                </div>

                <div id="install-success" class="hidden text-center py-8">
                    <i class="fas fa-check-circle text-green-600 text-6xl mb-4"></i>
                    <h3 class="text-2xl font-semibold text-gray-900 mb-2">Installation Complete!</h3>
                    <p class="text-gray-600 mb-6">Your CRM system has been successfully installed.</p>
                    <a href="/login" class="inline-block px-8 py-3 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] font-semibold">
                        <i class="fas fa-sign-in-alt mr-2"></i> Go to Login
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentStep = 1;
        let dbConfig = {};
        let requirementsMet = false;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            checkRequirements();
            
            // Auto-fix URL when user leaves the field
            const appUrlInput = document.getElementById('app_url');
            if (appUrlInput) {
                appUrlInput.addEventListener('blur', function() {
                    let url = this.value.trim();
                    if (url && !url.match(/^https?:\/\//i)) {
                        this.value = 'https://' + url;
                    }
                });
            }
        });

        // Step 1: Check Requirements
        async function checkRequirements() {
            try {
                const response = await fetch('/install/check-requirements', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                });

                // Check if response is OK
                if (!response.ok) {
                    let errorData;
                    try {
                        errorData = await response.json();
                    } catch (e) {
                        const text = await response.text();
                        throw new Error(`Server error (${response.status}): ${text.substring(0, 100)}`);
                    }
                    throw new Error(errorData.message || `Server error (${response.status})`);
                }

                const data = await response.json();
                displayRequirements(data);

                if (data.success) {
                    requirementsMet = true;
                    setTimeout(() => goToStep(2), 2000);
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('requirements-result').innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <p class="text-red-800">Error checking requirements: ${error.message}</p>
                    </div>
                `;
            }
        }

        function displayRequirements(data) {
            const resultDiv = document.getElementById('requirements-result');
            let html = '<div class="space-y-3">';

            // PHP Version
            html += `<div class="flex items-center justify-between p-3 rounded-lg ${data.requirements.php_version ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'}">
                <span class="font-medium">PHP Version (${data.php_version})</span>
                <i class="fas ${data.requirements.php_version ? 'fa-check text-green-600' : 'fa-times text-red-600'}"></i>
            </div>`;

            // Extensions
            Object.keys(data.requirements).forEach(key => {
                if (key !== 'php_version') {
                    const passed = data.requirements[key];
                    html += `<div class="flex items-center justify-between p-3 rounded-lg ${passed ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'}">
                        <span class="font-medium">${key.replace(/_/g, ' ').toUpperCase()}</span>
                        <i class="fas ${passed ? 'fa-check text-green-600' : 'fa-times text-red-600'}"></i>
                    </div>`;
                }
            });

            // Writable directories
            Object.keys(data.writable).forEach(key => {
                const passed = data.writable[key];
                html += `<div class="flex items-center justify-between p-3 rounded-lg ${passed ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'}">
                    <span class="font-medium">${key.replace(/_/g, ' ').toUpperCase()} (Writable)</span>
                    <i class="fas ${passed ? 'fa-check text-green-600' : 'fa-times text-red-600'}"></i>
                </div>`;
            });

            html += '</div>';

            if (!data.success) {
                html += `<div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p class="text-yellow-800"><i class="fas fa-exclamation-triangle mr-2"></i>Please fix the issues above before continuing.</p>
                </div>`;
            }

            resultDiv.innerHTML = html;
        }

        // Step 2: Test Database
        async function testDatabase() {
            const form = document.getElementById('database-form');
            const formData = new FormData(form);
            
            const dbTestResult = document.getElementById('db-test-result');
            dbTestResult.classList.remove('hidden');
            dbTestResult.innerHTML = '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4"><i class="fas fa-spinner fa-spin mr-2"></i>Testing connection...</div>';

            try {
                const response = await fetch('/install/test-database', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        host: formData.get('host'),
                        port: parseInt(formData.get('port')),
                        database: formData.get('database'),
                        username: formData.get('username'),
                        password: formData.get('password'),
                    }),
                });

                // Check if response is OK
                if (!response.ok) {
                    let errorData;
                    try {
                        errorData = await response.json();
                    } catch (e) {
                        const text = await response.text();
                        throw new Error(`Server error (${response.status}): ${text.substring(0, 100)}`);
                    }
                    throw new Error(errorData.message || `Server error (${response.status})`);
                }

                const data = await response.json();
                
                if (data.success) {
                    dbTestResult.innerHTML = '<div class="bg-green-50 border border-green-200 rounded-lg p-4"><i class="fas fa-check-circle text-green-600 mr-2"></i>Database connection successful!</div>';
                    document.getElementById('db-next-btn').classList.remove('hidden');
                    dbConfig = {
                        host: formData.get('host'),
                        port: formData.get('port'),
                        database: formData.get('database'),
                        username: formData.get('username'),
                        password: formData.get('password'),
                    };
                } else {
                    dbTestResult.innerHTML = `<div class="bg-red-50 border border-red-200 rounded-lg p-4"><i class="fas fa-times-circle text-red-600 mr-2"></i>${data.message}</div>`;
                }
            } catch (error) {
                dbTestResult.innerHTML = `<div class="bg-red-50 border border-red-200 rounded-lg p-4"><i class="fas fa-times-circle text-red-600 mr-2"></i>Error: ${error.message}</div>`;
            }
        }

        // Navigation
        function goToStep(step) {
            // Hide all steps
            document.querySelectorAll('.step-content').forEach(el => el.classList.add('hidden'));
            
            // Show current step
            document.getElementById(`step-${step}`).classList.remove('hidden');
            
            // Update indicators
            for (let i = 1; i <= 4; i++) {
                const indicator = document.getElementById(`step-${i}-indicator`);
                const progress = document.getElementById(`progress-${i}`);
                
                if (i < step) {
                    indicator.classList.remove('active', 'bg-gray-200', 'text-gray-600');
                    indicator.classList.add('completed');
                    if (progress) {
                        progress.classList.remove('bg-gray-200');
                        progress.classList.add('bg-green-500');
                    }
                } else if (i === step) {
                    indicator.classList.remove('completed', 'bg-gray-200', 'text-gray-600');
                    indicator.classList.add('active');
                    if (progress) {
                        progress.classList.remove('bg-green-500');
                        progress.classList.add('bg-gray-200');
                    }
                } else {
                    indicator.classList.remove('active', 'completed');
                    indicator.classList.add('bg-gray-200', 'text-gray-600');
                    if (progress) {
                        progress.classList.remove('bg-green-500');
                        progress.classList.add('bg-gray-200');
                    }
                }
            }

            currentStep = step;

            if (step === 4) {
                startInstallation();
            }
        }

        // Step 4: Start Installation
        async function startInstallation() {
            const logsDiv = document.getElementById('install-logs');
            const progressBar = document.getElementById('progress-bar');

            function addLog(message, type = 'info') {
                const icon = type === 'success' ? 'fa-check' : type === 'error' ? 'fa-times' : 'fa-info';
                const color = type === 'success' ? 'text-green-600' : type === 'error' ? 'text-red-600' : 'text-blue-600';
                logsDiv.innerHTML += `<div class="text-sm ${color} mb-1"><i class="fas ${icon} mr-2"></i>${message}</div>`;
                logsDiv.scrollTop = logsDiv.scrollHeight;
            }
            
            const adminForm = document.getElementById('admin-form');
            const adminFormData = new FormData(adminForm);
            
            // Validate that database config exists
            if (!dbConfig || !dbConfig.host || !dbConfig.database || !dbConfig.username) {
                addLog('Error: Database configuration is missing. Please go back and test database connection first.', 'error');
                return;
            }
            
            // Validate form data
            const appName = adminFormData.get('app_name');
            let appUrl = adminFormData.get('app_url');
            const adminName = adminFormData.get('admin_name');
            const adminEmail = adminFormData.get('admin_email');
            const adminPassword = adminFormData.get('admin_password');
            
            if (!appName || !appUrl || !adminName || !adminEmail || !adminPassword) {
                addLog('Error: Please fill all required fields in Admin User step.', 'error');
                return;
            }
            
            // Fix URL if it doesn't start with http:// or https://
            appUrl = appUrl.trim();
            if (appUrl && !appUrl.match(/^https?:\/\//i)) {
                appUrl = 'https://' + appUrl;
            }
            
            const installData = {
                app_name: appName,
                app_url: appUrl,
                admin_name: adminName,
                admin_email: adminEmail,
                admin_password: adminPassword,
                db_host: dbConfig.host,
                db_port: parseInt(dbConfig.port) || 3306,
                db_database: dbConfig.database,
                db_username: dbConfig.username,
                db_password: dbConfig.password || '',
            };

            addLog('Starting installation process...', 'info');
            progressBar.style.width = '10%';

            try {
                addLog('Creating .env file...', 'info');
                progressBar.style.width = '20%';

                addLog('Testing database connection...', 'info');
                progressBar.style.width = '30%';

                addLog('Generating application key...', 'info');
                progressBar.style.width = '40%';

                addLog('Running database migrations...', 'info');
                progressBar.style.width = '50%';

                addLog('Seeding database...', 'info');
                progressBar.style.width = '60%';

                addLog('Creating admin user...', 'info');
                progressBar.style.width = '70%';

                addLog('Finalizing installation...', 'info');
                progressBar.style.width = '80%';

                const response = await fetch('/install/install', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(installData),
                });

                // Check if response is OK
                if (!response.ok) {
                    // Try to parse as JSON, if fails, show error
                    let errorData;
                    try {
                        errorData = await response.json();
                    } catch (e) {
                        const text = await response.text();
                        throw new Error(`Server error (${response.status}): ${text.substring(0, 100)}`);
                    }
                    
                    // Handle validation errors (422)
                    if (response.status === 422 && errorData.errors) {
                        let errorMessage = errorData.message || 'Validation failed';
                        // Format validation errors
                        const errorList = [];
                        for (const field in errorData.errors) {
                            const fieldName = field.replace(/_/g, ' ').replace(/db /g, 'DB ').replace(/admin /g, 'Admin ');
                            errorList.push(`${fieldName}: ${errorData.errors[field].join(', ')}`);
                        }
                        if (errorList.length > 0) {
                            errorMessage += '\n' + errorList.join('\n');
                        }
                        throw new Error(errorMessage);
                    }
                    
                    throw new Error(errorData.message || `Server error (${response.status})`);
                }

                const data = await response.json();
                progressBar.style.width = '100%';

                if (data.success) {
                    addLog('Installation completed successfully!', 'success');
                    setTimeout(() => {
                        document.getElementById('install-progress').classList.add('hidden');
                        document.getElementById('install-success').classList.remove('hidden');
                    }, 1000);
                } else {
                    addLog(`Installation failed: ${data.message}`, 'error');
                }
            } catch (error) {
                addLog(`Error: ${error.message}`, 'error');
                progressBar.style.width = '100%';
            }
        }
    </script>
</body>
</html>
