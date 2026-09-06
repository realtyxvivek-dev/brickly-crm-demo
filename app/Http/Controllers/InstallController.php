<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use App\Services\InstallationChecker;

class InstallController extends Controller
{
    /**
     * Show installation wizard
     */
    public function index()
    {
        try {
            // If already installed (lock file or DB fallback), redirect to home
            if (InstallationChecker::isInstalled()) {
                return redirect('/');
            }

            return view('install.index');
        } catch (\Throwable $e) {
            Log::error('Install page error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return simple error message
            return response('<h1>Installation Error</h1><p>Error: ' . htmlspecialchars($e->getMessage()) . '</p><p>Please check server logs: storage/logs/laravel.log</p>', 500);
        }
    }

    /**
     * Check system requirements
     */
    public function checkRequirements()
    {
        $requirements = [
            'php_version' => version_compare(PHP_VERSION, '8.1.0', '>='),
            'pdo' => extension_loaded('pdo'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'mbstring' => extension_loaded('mbstring'),
            'openssl' => extension_loaded('openssl'),
            'tokenizer' => extension_loaded('tokenizer'),
            'json' => extension_loaded('json'),
            'curl' => extension_loaded('curl'),
            'fileinfo' => extension_loaded('fileinfo'),
            'gd' => extension_loaded('gd'),
        ];

        $writable = [
            'storage' => is_writable(storage_path()),
            'bootstrap_cache' => is_writable(base_path('bootstrap/cache')),
        ];

        $allRequirementsMet = !in_array(false, $requirements) && !in_array(false, $writable);

        return response()->json([
            'success' => $allRequirementsMet,
            'requirements' => $requirements,
            'writable' => $writable,
            'php_version' => PHP_VERSION,
        ]);
    }

    /**
     * Test database connection
     */
    public function testDatabase(Request $request)
    {
        try {
            $request->validate([
                'host' => 'required|string',
                'port' => 'required|integer',
                'database' => 'required|string',
                'username' => 'required|string',
                'password' => 'nullable|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            // Test connection with provided credentials
            config([
                'database.connections.mysql.host' => $request->host,
                'database.connections.mysql.port' => $request->port,
                'database.connections.mysql.database' => $request->database,
                'database.connections.mysql.username' => $request->username,
                'database.connections.mysql.password' => $request->password ?? '',
            ]);

            DB::purge('mysql');
            DB::connection('mysql')->getPdo();

            return response()->json([
                'success' => true,
                'message' => 'Database connection successful!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Complete installation process
     */
    public function install(Request $request)
    {
        // If already installed, return error
        if (InstallationChecker::isInstalled()) {
            return response()->json([
                'success' => false,
                'message' => 'System is already installed.',
            ], 400);
        }

        try {
            $request->validate([
                'app_name' => 'required|string|max:255',
                'app_url' => 'required|url',
                'db_host' => 'required|string',
                'db_port' => 'required|integer',
                'db_database' => 'required|string',
                'db_username' => 'required|string',
                'db_password' => 'nullable|string',
                'admin_name' => 'required|string|max:255',
                'admin_email' => 'required|email|max:255',
                'admin_password' => 'required|string|min:8',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            // Step 1: Create .env file
            $this->createEnvFile($request);

            // Step 2: Test database connection
            config([
                'database.connections.mysql.host' => $request->db_host,
                'database.connections.mysql.port' => $request->db_port,
                'database.connections.mysql.database' => $request->db_database,
                'database.connections.mysql.username' => $request->db_username,
                'database.connections.mysql.password' => $request->db_password ?? '',
            ]);

            DB::purge('mysql');
            DB::connection('mysql')->getPdo();

            // Step 3: Clear all caches before proceeding (ignore permission errors)
            try {
                Artisan::call('config:clear');
            } catch (\Exception $e) {
                Log::warning('Config clear warning', ['error' => $e->getMessage()]);
            }
            
            try {
                Artisan::call('cache:clear');
            } catch (\Exception $e) {
                // Cache clear may fail due to permissions, but continue
                Log::warning('Cache clear warning', ['error' => $e->getMessage()]);
            }
            
            try {
                Artisan::call('route:clear');
            } catch (\Exception $e) {
                Log::warning('Route clear warning', ['error' => $e->getMessage()]);
            }
            
            try {
                Artisan::call('view:clear');
            } catch (\Exception $e) {
                Log::warning('View clear warning', ['error' => $e->getMessage()]);
            }

            // Step 4: Generate app key
            try {
                Artisan::call('key:generate', ['--force' => true]);
            } catch (\Exception $e) {
                Log::error('Key generation failed', ['error' => $e->getMessage()]);
                throw new \Exception('Failed to generate application key: ' . $e->getMessage());
            }

            // Step 5: Run migrations
            try {
                Artisan::call('migrate', ['--force' => true]);
            } catch (\Exception $e) {
                Log::error('Migration failed', ['error' => $e->getMessage()]);
                throw new \Exception('Migration failed: ' . $e->getMessage());
            }

            // Step 6: Seed database
            try {
                Artisan::call('db:seed', ['--force' => true]);
            } catch (\Exception $e) {
                Log::error('Seeding failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                throw new \Exception('Database seeding failed: ' . $e->getMessage());
            }

            // Step 7: Create admin user
            $adminRole = Role::where('slug', 'admin')->first();
            if (!$adminRole) {
                throw new \Exception('Admin role not found. Please run seeders first.');
            }

            $admin = User::create([
                'name' => $request->admin_name,
                'email' => $request->admin_email,
                'password' => Hash::make($request->admin_password),
                'role_id' => $adminRole->id,
                'is_active' => true,
            ]);

            // Step 8: Create installation lock file
            File::put(storage_path('app/installed.lock'), now()->toDateTimeString());

            // Step 9: Clear and cache config (skip route cache to avoid route conflict)
            Artisan::call('config:cache');
            // Skip route:cache to avoid route conflict with duplicate users.index
            // Artisan::call('route:cache');
            Artisan::call('view:cache');

            Log::info('Installation completed successfully', [
                'admin_email' => $request->admin_email,
                'app_url' => $request->app_url,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Installation completed successfully!',
                'admin_email' => $request->admin_email,
            ]);
        } catch (\Exception $e) {
            Log::error('Installation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Installation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create .env file from request data
     */
    private function createEnvFile(Request $request)
    {
        $envContent = "APP_NAME=\"{$request->app_name}\"\n";
        $envContent .= "APP_ENV=production\n";
        $envContent .= "APP_KEY=\n";
        $envContent .= "APP_DEBUG=false\n";
        $envContent .= "APP_URL={$request->app_url}\n\n";
        
        $envContent .= "LOG_CHANNEL=stack\n";
        $envContent .= "LOG_DEPRECATIONS_CHANNEL=null\n";
        $envContent .= "LOG_LEVEL=error\n\n";
        
        $envContent .= "DB_CONNECTION=mysql\n";
        $envContent .= "DB_HOST={$request->db_host}\n";
        $envContent .= "DB_PORT={$request->db_port}\n";
        $envContent .= "DB_DATABASE={$request->db_database}\n";
        $envContent .= "DB_USERNAME={$request->db_username}\n";
        $envContent .= "DB_PASSWORD=" . ($request->db_password ?? '') . "\n\n";
        
        $envContent .= "BROADCAST_DRIVER=pusher\n";
        $envContent .= "CACHE_DRIVER=file\n";
        $envContent .= "FILESYSTEM_DISK=local\n";
        $envContent .= "QUEUE_CONNECTION=sync\n";
        $envContent .= "SESSION_DRIVER=file\n";
        $envContent .= "SESSION_LIFETIME=120\n\n";
        
        $envContent .= "REDIS_HOST=127.0.0.1\n";
        $envContent .= "REDIS_PASSWORD=null\n";
        $envContent .= "REDIS_PORT=6379\n\n";
        
        $envContent .= "PUSHER_APP_ID=\n";
        $envContent .= "PUSHER_APP_KEY=\n";
        $envContent .= "PUSHER_APP_SECRET=\n";
        $envContent .= "PUSHER_HOST=\n";
        $envContent .= "PUSHER_PORT=443\n";
        $envContent .= "PUSHER_SCHEME=https\n";
        $envContent .= "PUSHER_APP_CLUSTER=mt1\n\n";
        
        $envContent .= "MAIL_MAILER=smtp\n";
        $envContent .= "MAIL_HOST=mailpit\n";
        $envContent .= "MAIL_PORT=1025\n";
        $envContent .= "MAIL_USERNAME=null\n";
        $envContent .= "MAIL_PASSWORD=null\n";
        $envContent .= "MAIL_ENCRYPTION=null\n";
        $envContent .= "MAIL_FROM_ADDRESS=\"hello@example.com\"\n";
        $envContent .= "MAIL_FROM_NAME=\"{$request->app_name}\"\n";

        File::put(base_path('.env'), $envContent);
    }
}
