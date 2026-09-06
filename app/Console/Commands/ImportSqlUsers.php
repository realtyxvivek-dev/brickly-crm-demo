<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportSqlUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:sql-users {file : Path to SQL file} {--preview : Preview users with new roles without importing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import users from SQL file with role mapping';

    /**
     * Role mapping from old roles to new roles
     */
    protected $roleMapping = [
        'admin' => 'admin',
        'manager' => 'sales_manager', // Will be determined dynamically if user manager
        'sales_prospector' => 'sales_executive',
        'crm' => 'crm',
    ];
    
    /**
     * Store which managers have users (sales executives) under them
     * Key: old admin ID, Value: boolean
     */
    protected $managerHasUsers = [];
    
    /**
     * Store IDs of independent users who need Sales Head as manager
     */
    protected $independentUserIds = [];

    /**
     * Mapping of old user IDs to new user IDs
     */
    protected $userIdMapping = [];

    /**
     * Statistics
     */
    protected $stats = [
        'admins_imported' => 0,
        'users_imported' => 0,
        'errors' => 0,
        'skipped' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("SQL file not found: {$filePath}");
            return Command::FAILURE;
        }

        $isPreview = $this->option('preview');

        if ($isPreview) {
            $this->info("Extracting users with new roles from: {$filePath}");
            $this->newLine();
            return $this->extractUsersWithRoles($filePath);
        }

        $this->info("Starting SQL import from: {$filePath}");
        $this->newLine();

        try {
            // Read SQL file
            $sqlContent = file_get_contents($filePath);
            
            // First, determine which managers have users under them
            $this->info("Analyzing manager relationships...");
            $this->determineManagerHasUsers($sqlContent);
            
            // Parse and import admins table
            $this->info("Parsing admins table...");
            $admins = $this->parseInsertStatements($sqlContent, 'admins');
            $this->importAdmins($admins);

            // Parse and import users table
            $this->info("Parsing users table...");
            $users = $this->parseInsertStatements($sqlContent, 'users');
            $this->importUsers($users);

            // Update manager relationships
            $this->info("Updating manager relationships...");
            $this->updateManagerRelationships($sqlContent);
            
            // Assign Sales Head to independent users
            $this->assignSalesHeadToIndependentUsers();

            // Display summary
            $this->displaySummary();

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error during import: " . $e->getMessage());
            Log::error("SQL import error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Parse INSERT statements from SQL content
     */
    protected function parseInsertStatements(string $sqlContent, string $tableName): array
    {
        $records = [];
        
        // Pattern to match INSERT INTO statements
        $pattern = '/INSERT\s+INTO\s+[`"]?' . preg_quote($tableName, '/') . '[`"]?\s*\([^)]+\)\s*VALUES\s*(.+?);/is';
        
        if (preg_match($pattern, $sqlContent, $matches)) {
            $valuesString = $matches[1];
            
            // Split by ),( to get individual records
            $recordsStrings = preg_split('/\)\s*,\s*\(/', $valuesString);
            
            foreach ($recordsStrings as $recordString) {
                // Clean up the record string
                $recordString = trim($recordString, '()');
                
                // Parse values (handle SQL escaping)
                $values = $this->parseSqlValues($recordString);
                
                if (!empty($values)) {
                    $records[] = $values;
                }
            }
        }

        return $records;
    }

    /**
     * Parse SQL VALUES string into array
     */
    protected function parseSqlValues(string $valuesString): array
    {
        $values = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        $escaped = false;

        for ($i = 0; $i < strlen($valuesString); $i++) {
            $char = $valuesString[$i];

            if ($escaped) {
                $current .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                $current .= $char;
                continue;
            }

            if (($char === '"' || $char === "'") && !$inQuotes) {
                $inQuotes = true;
                $quoteChar = $char;
                $current .= $char;
            } elseif ($char === $quoteChar && $inQuotes) {
                $inQuotes = false;
                $quoteChar = null;
                $current .= $char;
            } elseif ($char === ',' && !$inQuotes) {
                $values[] = $this->cleanSqlValue(trim($current));
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if (!empty($current)) {
            $values[] = $this->cleanSqlValue(trim($current));
        }

        return $values;
    }

    /**
     * Clean SQL value (remove quotes, handle NULL)
     */
    protected function cleanSqlValue(string $value): ?string
    {
        $value = trim($value);
        
        if (strtoupper($value) === 'NULL') {
            return null;
        }

        // Remove surrounding quotes
        if (($value[0] === '"' && substr($value, -1) === '"') ||
            ($value[0] === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }

        // Unescape
        $value = str_replace(['\\"', "\\'", '\\\\'], ['"', "'", '\\'], $value);

        return $value;
    }

    /**
     * Import admins from parsed data
     */
    protected function importAdmins(array $admins): void
    {
        $bar = $this->output->createProgressBar(count($admins));
        $bar->start();

        foreach ($admins as $adminData) {
            try {
                // Expected columns: id, username, password_hash, role, manager_id, created_at, manager_username
                if (count($adminData) < 4) {
                    $this->stats['skipped']++;
                    $bar->advance();
                    continue;
                }

                $oldId = (int) $adminData[0];
                $username = $adminData[1] ?? null;
                $passwordHash = $adminData[2] ?? null;
                $role = $adminData[3] ?? null;
                $managerId = !empty($adminData[4]) ? (int) $adminData[4] : null;

                if (!$username || !$passwordHash || !$role) {
                    $this->stats['skipped']++;
                    $bar->advance();
                    continue;
                }

                // Check if user already exists
                $existingUser = User::where('email', $this->generateEmail($username))->first();
                if ($existingUser) {
                    $this->userIdMapping[$oldId] = $existingUser->id;
                    $this->stats['skipped']++;
                    $bar->advance();
                    continue;
                }

                // Map role
                $roleSlug = $this->getMappedRoleForImport($role, $oldId);
                $roleModel = Role::where('slug', $roleSlug)->first();

                if (!$roleModel) {
                    $this->error("Role not found: {$roleSlug}");
                    $this->stats['errors']++;
                    $bar->advance();
                    continue;
                }

                // Create user
                $user = User::create([
                    'name' => $username,
                    'email' => $this->generateEmail($username),
                    'password' => $this->preparePassword($passwordHash),
                    'role_id' => $roleModel->id,
                    'manager_id' => null, // Will be updated later
                    'is_active' => true,
                ]);

                // Store ID mapping
                $this->userIdMapping[$oldId] = $user->id;

                $this->stats['admins_imported']++;
                $bar->advance();

            } catch (\Exception $e) {
                $this->stats['errors']++;
                Log::error("Error importing admin: " . $e->getMessage(), ['data' => $adminData]);
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Import users from parsed data
     */
    protected function importUsers(array $users): void
    {
        $bar = $this->output->createProgressBar(count($users));
        $bar->start();

        foreach ($users as $userData) {
            try {
                // Expected columns: id, username, password_hash, pin, manager_id, is_independent, created_at, updated_at
                if (count($userData) < 3) {
                    $this->stats['skipped']++;
                    $bar->advance();
                    continue;
                }

                $oldId = (int) $userData[0];
                $username = $userData[1] ?? null;
                $passwordHash = $userData[2] ?? null;
                $managerId = !empty($userData[4]) ? (int) $userData[4] : null;
                $isIndependent = !empty($userData[5]) ? (int) $userData[5] : 0;

                if (!$username || !$passwordHash) {
                    $this->stats['skipped']++;
                    $bar->advance();
                    continue;
                }

                // Check if user already exists
                $existingUser = User::where('email', $this->generateEmail($username))->first();
                if ($existingUser) {
                    $this->userIdMapping[$oldId] = $existingUser->id;
                    $this->stats['skipped']++;
                    $bar->advance();
                    continue;
                }

                // Determine role based on manager and independence
                // If user is independent, they are Sales Executive (manager will be Sales Head)
                // If manager is Sales Head (Omkar = 2 or Alpish = 3), user is Sales Executive
                // All other users from users table are sales executives
                $roleSlug = 'sales_executive'; // Default
                
                if ($isIndependent === 1) {
                    $roleSlug = 'sales_executive';
                } elseif ($managerId === 2 || $managerId === 3) {
                    // Manager is Sales Head (Omkar or Alpish)
                    $roleSlug = 'sales_executive';
                }

                $roleModel = Role::where('slug', $roleSlug)->first();

                if (!$roleModel) {
                    $this->error("Role not found: {$roleSlug}");
                    $this->stats['errors']++;
                    $bar->advance();
                    continue;
                }

                // Create user
                $user = User::create([
                    'name' => $username,
                    'email' => $this->generateEmail($username),
                    'password' => $this->preparePassword($passwordHash),
                    'role_id' => $roleModel->id,
                    'manager_id' => null, // Will be updated later
                    'is_active' => true,
                ]);

                // Store ID mapping
                $this->userIdMapping[$oldId] = $user->id;
                
                // Store if user is independent for later manager assignment
                if ($isIndependent === 1) {
                    if (!isset($this->independentUserIds)) {
                        $this->independentUserIds = [];
                    }
                    $this->independentUserIds[] = $user->id;
                }

                $this->stats['users_imported']++;
                $bar->advance();

            } catch (\Exception $e) {
                $this->stats['errors']++;
                Log::error("Error importing user: " . $e->getMessage(), ['data' => $userData]);
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Update manager relationships using ID mapping
     */
    protected function updateManagerRelationships(string $sqlContent): void
    {
        // Get all users that need manager updates
        $users = User::whereNotNull('manager_id')->orWhereIn('id', array_values($this->userIdMapping))->get();
        
        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        // Parse admins again to get manager relationships
        $admins = $this->parseInsertStatements($sqlContent, 'admins');
        foreach ($admins as $adminData) {
            if (count($adminData) >= 5) {
                $oldId = (int) $adminData[0];
                $oldManagerId = !empty($adminData[4]) ? (int) $adminData[4] : null;

                if (isset($this->userIdMapping[$oldId]) && $oldManagerId && isset($this->userIdMapping[$oldManagerId])) {
                    $userId = $this->userIdMapping[$oldId];
                    $newManagerId = $this->userIdMapping[$oldManagerId];

                    User::where('id', $userId)->update(['manager_id' => $newManagerId]);
                    $bar->advance();
                }
            }
        }

        // Parse users again to get manager relationships
        $usersData = $this->parseInsertStatements($sqlContent, 'users');
        foreach ($usersData as $userData) {
            if (count($userData) >= 5) {
                $oldId = (int) $userData[0];
                $oldManagerId = !empty($userData[4]) ? (int) $userData[4] : null;

                if (isset($this->userIdMapping[$oldId]) && $oldManagerId && isset($this->userIdMapping[$oldManagerId])) {
                    $userId = $this->userIdMapping[$oldId];
                    $newManagerId = $this->userIdMapping[$oldManagerId];

                    User::where('id', $userId)->update(['manager_id' => $newManagerId]);
                    $bar->advance();
                }
            }
        }

        $bar->finish();
        $this->newLine();
    }
    
    /**
     * Assign Sales Head to independent users
     */
    protected function assignSalesHeadToIndependentUsers(): void
    {
        if (empty($this->independentUserIds)) {
            return;
        }
        
        $this->info("Assigning Sales Head to independent users...");
        
        // Find Sales Head (Senior Manager without users/sales executives under them)
        // Sales Head is a Senior Manager who doesn't have any sales executives assigned
        $salesHead = User::whereHas('role', function($q) {
            $q->where('slug', 'sales_manager');
        })
        ->whereNull('manager_id')
        ->whereDoesntHave('teamMembers', function($q) {
            $q->whereHas('role', function($r) {
                $r->where('slug', 'sales_executive');
            });
        })
        ->first();
        
        // If no Sales Head found, get any Senior Manager without manager
        if (!$salesHead) {
            $salesHead = User::whereHas('role', function($q) {
                $q->where('slug', 'sales_manager');
            })->whereNull('manager_id')->first();
        }
        
        if ($salesHead) {
            User::whereIn('id', $this->independentUserIds)
                ->update(['manager_id' => $salesHead->id]);
            $this->info("Assigned " . count($this->independentUserIds) . " independent users to Sales Head: " . $salesHead->name);
        } else {
            $this->warn("No Sales Head found. Independent users will remain without manager.");
        }
    }

    /**
     * Generate email from username
     */
    protected function generateEmail(string $username): string
    {
        $email = Str::slug($username) . '@imported.local';
        
        // Ensure uniqueness
        $counter = 1;
        while (User::where('email', $email)->exists()) {
            $email = Str::slug($username) . $counter . '@imported.local';
            $counter++;
        }

        return $email;
    }

    /**
     * Prepare password - hash if plain text
     */
    protected function preparePassword(string $passwordHash): string
    {
        // If password looks like it's already hashed (bcrypt format), return as is
        if (strlen($passwordHash) === 60 && preg_match('/^\$2[ayb]\$.{56}$/', $passwordHash)) {
            return $passwordHash;
        }

        // Otherwise, hash it
        return Hash::make($passwordHash);
    }

    /**
     * Extract and display users with new roles (preview mode)
     */
    protected function extractUsersWithRoles(string $filePath): int
    {
        try {
            $sqlContent = file_get_contents($filePath);
            
            // Parse admins and users
            $admins = $this->parseInsertStatements($sqlContent, 'admins');
            $users = $this->parseInsertStatements($sqlContent, 'users');
            
            // First, determine which managers have users under them
            $adminIds = [];
            foreach ($admins as $adminData) {
                if (count($adminData) >= 1) {
                    $adminIds[(int) $adminData[0]] = true;
                }
            }
            
            $managerHasUsers = [];
            foreach ($users as $userData) {
                if (count($userData) >= 5) {
                    $managerId = !empty($userData[4]) ? (int) $userData[4] : null;
                    if ($managerId && isset($adminIds[$managerId])) {
                        $managerHasUsers[$managerId] = true;
                    }
                }
            }
            
            $extractedUsers = [];
            
            // Process admins table
            $this->info("Extracting from admins table...");
            foreach ($admins as $adminData) {
                if (count($adminData) < 4) continue;
                
                $oldId = (int) $adminData[0];
                $username = $adminData[1] ?? null;
                $oldRole = $adminData[3] ?? null;
                $managerId = !empty($adminData[4]) ? (int) $adminData[4] : null;
                $managerUsername = $adminData[6] ?? null;
                
                if (!$username || !$oldRole) continue;
                
                // Determine new role based on mapping
                $newRole = $this->getMappedRoleForPreview($oldRole, $oldId, $managerHasUsers);
                
                $extractedUsers[] = [
                    'source' => 'admins',
                    'old_id' => $oldId,
                    'username' => $username,
                    'old_role' => $oldRole,
                    'new_role' => $newRole,
                    'manager_id' => $managerId,
                    'manager_username' => $managerUsername,
                ];
            }
            
            // Process users table
            $this->info("Extracting from users table...");
            foreach ($users as $userData) {
                if (count($userData) < 3) continue;
                
                $oldId = (int) $userData[0];
                $username = $userData[1] ?? null;
                $managerId = !empty($userData[4]) ? (int) $userData[4] : null;
                $isIndependent = !empty($userData[5]) ? (int) $userData[5] : 0;
                
                if (!$username) continue;
                
                // Determine role
                $newRole = 'sales_executive'; // Default
                if ($isIndependent === 1) {
                    $newRole = 'sales_executive';
                } elseif ($managerId === 2 || $managerId === 3) {
                    // Manager is Sales Head (Omkar or Alpish)
                    $newRole = 'sales_executive';
                }
                
                $extractedUsers[] = [
                    'source' => 'users',
                    'old_id' => $oldId,
                    'username' => $username,
                    'old_role' => 'sale prospector',
                    'new_role' => $newRole,
                    'manager_id' => $managerId,
                    'manager_username' => null,
                    'is_independent' => $isIndependent,
                ];
            }
            
            // Display results
            $this->newLine();
            $this->info('Extracted Users with New Roles:');
            $this->newLine();
            
            // Group by new role
            $groupedByRole = [];
            foreach ($extractedUsers as $user) {
                $role = $user['new_role'];
                if (!isset($groupedByRole[$role])) {
                    $groupedByRole[$role] = [];
                }
                $groupedByRole[$role][] = $user;
            }
            
            // Display summary
            $this->info('Summary by Role:');
            $summaryData = [];
            foreach ($groupedByRole as $role => $roleUsers) {
                $summaryData[] = [
                    'Role' => ucfirst(str_replace('_', ' ', $role)),
                    'Count' => count($roleUsers),
                ];
            }
            $this->table(['Role', 'Count'], $summaryData);
            $this->newLine();
            
            // Display detailed list
            $this->info('Detailed List:');
            $tableData = [];
            foreach ($extractedUsers as $user) {
                $tableData[] = [
                    'Source' => $user['source'],
                    'Username' => $user['username'],
                    'Old Role' => $user['old_role'],
                    'New Role' => ucfirst(str_replace('_', ' ', $user['new_role'])),
                    'Manager ID' => $user['manager_id'] ?? 'N/A',
                    'Manager Username' => $user['manager_username'] ?? 'N/A',
                ];
            }
            $this->table(
                ['Source', 'Username', 'Old Role', 'New Role', 'Manager ID', 'Manager Username'],
                $tableData
            );
            
            $this->newLine();
            $this->info("Total users extracted: " . count($extractedUsers));
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Error extracting users: " . $e->getMessage());
            Log::error("Extract error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
    
    /**
     * Get mapped role for admin entry (for preview)
     */
    protected function getMappedRoleForPreview(string $oldRole, int $oldId, array $managerHasUsers): string
    {
        // Direct mapping for known roles
        if (isset($this->roleMapping[$oldRole])) {
            $mappedRole = $this->roleMapping[$oldRole];
            
            // Special handling for managers - check if they have users under them
            if ($mappedRole === 'sales_manager' && $oldRole === 'manager') {
                // If this manager has users (telecallers) under them, they are user manager (sales_executive)
                if (isset($managerHasUsers[$oldId]) && $managerHasUsers[$oldId]) {
                    return 'sales_executive';
                }
                return 'sales_manager';
            }
            
            return $mappedRole;
        }
        
        // Default to sales_executive if role not found
        return 'sales_executive';
    }
    
    /**
     * Get mapped role for import (with dynamic manager check)
     */
    protected function getMappedRoleForImport(string $oldRole, int $oldId): string
    {
        if (!isset($this->roleMapping[$oldRole])) {
            return 'sales_executive';
        }
        
        $mappedRole = $this->roleMapping[$oldRole];
        
        // Special handling for managers
        if ($mappedRole === 'sales_manager' && $oldRole === 'manager') {
            // Check if this manager has users (sales executives) under them
            // If yes, they are user manager (sales_executive)
            if (isset($this->managerHasUsers[$oldId]) && $this->managerHasUsers[$oldId]) {
                return 'sales_executive';
            }
        }
        
        return $mappedRole;
    }
    
    /**
     * Determine which managers have users under them
     */
    protected function determineManagerHasUsers(string $sqlContent): void
    {
        $admins = $this->parseInsertStatements($sqlContent, 'admins');
        $users = $this->parseInsertStatements($sqlContent, 'users');
        
        // Create a map of admin IDs
        $adminIds = [];
        foreach ($admins as $adminData) {
            if (count($adminData) >= 1) {
                $adminIds[(int) $adminData[0]] = true;
            }
        }
        
        // Check which admins have users pointing to them
        foreach ($users as $userData) {
            if (count($userData) >= 5) {
                $managerId = !empty($userData[4]) ? (int) $userData[4] : null;
                if ($managerId && isset($adminIds[$managerId])) {
                    $this->managerHasUsers[$managerId] = true;
                }
            }
        }
    }

    /**
     * Display import summary
     */
    protected function displaySummary(): void
    {
        $this->newLine();
        $this->info('Import Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Admins Imported', $this->stats['admins_imported']],
                ['Users Imported', $this->stats['users_imported']],
                ['Skipped', $this->stats['skipped']],
                ['Errors', $this->stats['errors']],
                ['Total Imported', $this->stats['admins_imported'] + $this->stats['users_imported']],
            ]
        );
    }
}

