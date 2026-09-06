<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class VisitImportService
{
    protected $duplicateService;
    protected $userMapping = [];
    protected $userMappingFile;

    public function __construct(DuplicateDetectionService $duplicateService)
    {
        $this->duplicateService = $duplicateService;
        $this->userMappingFile = storage_path('app/visit_import_user_mapping.json');
    }

    /**
     * Import visits from SQL file
     */
    public function importFromSql(string $sqlFilePath): array
    {
        $stats = [
            'total' => 0,
            'imported' => 0,
            'skipped' => 0,
            'errors' => [],
            'users_found' => 0,
            'users_not_found' => 0,
            'leads_created' => 0,
            'leads_found' => 0,
            'photos_imported' => 0,
        ];

        // Load user mapping if exists
        $this->loadUserMapping();

        // Parse SQL file
        $visits = $this->parseSqlFile($sqlFilePath);
        $photos = $this->parsePhotosFromSql($sqlFilePath);
        $verifications = $this->parseVerificationsFromSql($sqlFilePath);

        $stats['total'] = count($visits);

        DB::beginTransaction();
        try {
            foreach ($visits as $visitData) {
                try {
                    $result = $this->importVisit($visitData, $photos, $verifications);
                    
                    if ($result['success']) {
                        $stats['imported']++;
                        $stats['users_found'] += $result['users_found'];
                        $stats['users_not_found'] += $result['users_not_found'];
                        $stats['leads_created'] += $result['lead_created'] ? 1 : 0;
                        $stats['leads_found'] += $result['lead_found'] ? 1 : 0;
                        $stats['photos_imported'] += $result['photos_count'];
                    } else {
                        $stats['skipped']++;
                        $stats['errors'][] = $result['error'];
                    }
                } catch (\Exception $e) {
                    $stats['skipped']++;
                    $stats['errors'][] = "Visit ID {$visitData['id']}: " . $e->getMessage();
                    Log::error("Visit import error for ID {$visitData['id']}: " . $e->getMessage());
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        // Save user mapping
        $this->saveUserMapping();

        return $stats;
    }

    /**
     * Import a single visit
     */
    protected function importVisit(array $visitData, array $photos, array $verifications): array
    {
        $result = [
            'success' => false,
            'users_found' => 0,
            'users_not_found' => 0,
            'lead_created' => false,
            'lead_found' => false,
            'photos_count' => 0,
            'error' => null,
        ];

        // Map employee name to user
        $employeeUserId = null;
        if (!empty($visitData['employee_name'])) {
            $employeeUserId = $this->findOrMapUser($visitData['employee_name'], 'employee');
            if ($employeeUserId) {
                $result['users_found']++;
            } else {
                $result['users_not_found']++;
            }
        }

        // Map assigned manager to user
        $managerUserId = null;
        if (!empty($visitData['assigned_manager'])) {
            $managerUserId = $this->findOrMapUser($visitData['assigned_manager'], 'manager');
            if ($managerUserId) {
                $result['users_found']++;
            } else {
                $result['users_not_found']++;
            }
        }

        // Find or create lead
        $leadResult = $this->findOrCreateLead($visitData);
        if (!$leadResult['lead']) {
            $result['error'] = "Could not create/find lead for phone: {$visitData['phone']}";
            return $result;
        }

        $lead = $leadResult['lead'];
        if ($leadResult['created']) {
            $result['lead_created'] = true;
        } else {
            $result['lead_found'] = true;
        }

        // Map visit type
        $leadType = $this->mapVisitType($visitData['visit_type'] ?? 'new');

        // Map budget range
        $budgetRange = $this->mapBudgetRange($visitData['budget'] ?? null);

        // Map lead status
        $leadStatus = $this->mapLeadStatus($visitData['lead_status'] ?? 'Follow-up');
        $isDead = in_array($visitData['lead_status'], ['Lost', 'Dead']);
        $closerStatus = ($visitData['lead_status'] === 'Closed') ? 'verified' : null;

        // Combine notes
        $notes = $this->combineNotes([
            $visitData['notes'] ?? null,
            $visitData['employee_remark'] ?? null,
            $visitData['manager_remark'] ?? null,
        ]);

        // Parse visited_at date
        $visitedAt = !empty($visitData['visited_at']) 
            ? \Carbon\Carbon::parse($visitData['visited_at']) 
            : now();

        // Parse updated_at from old SQL, fallback to visited_at
        $updatedAt = !empty($visitData['updated_at']) 
            ? \Carbon\Carbon::parse($visitData['updated_at']) 
            : $visitedAt;

        // Create site visit
        $siteVisitData = [
            'lead_id' => $lead->id,
            'created_by' => $employeeUserId ?? $managerUserId ?? 1, // Fallback to admin
            'assigned_to' => $employeeUserId,
            'customer_name' => $visitData['customer_name'] ?? null,
            'phone' => $visitData['phone'] ?? null,
            'occupation' => $visitData['occupation'] ?? null,
            'employee' => $visitData['employee_name'] ?? null,
            'project' => $visitData['project_name'] ?? null,
            'date_of_visit' => $visitedAt->format('Y-m-d'),
            'scheduled_at' => $visitedAt,
            'completed_at' => $visitedAt,
            'status' => 'completed',
            'lead_type' => $leadType,
            'property_type' => $visitData['selection_interest'] ?? null,
            'payment_mode' => $visitData['payment_mode'] ?? null,
            'budget_range' => $budgetRange,
            'tentative_period' => $visitData['tentative_period'] ?? null,
            'team_leader' => $visitData['assigned_manager'] ?? null,
            'visit_notes' => $notes,
            'verification_status' => 'verified',
            'verified_at' => $visitedAt,
            'verified_by' => $managerUserId ?? $employeeUserId ?? 1,
            'is_dead' => $isDead,
            'dead_reason' => $isDead ? ($visitData['manager_remark'] ?? 'Imported as dead') : null,
            'closer_status' => $closerStatus,
            'closer_verified_at' => $closerStatus === 'verified' ? $visitedAt : null,
            'closer_verified_by' => $closerStatus === 'verified' ? ($managerUserId ?? 1) : null,
        ];

        // Import photos
        $visitId = isset($visitData['id']) ? (int)$visitData['id'] : 0;
        $visitPhotos = $photos[$visitId] ?? [];
        if (!empty($visitPhotos)) {
            $photoPaths = $this->importPhotos($visitPhotos, $visitId);
            $siteVisitData['photos'] = $photoPaths;
            $result['photos_count'] = count($photoPaths);
        }

        // Create site visit with explicit timestamps from old SQL
        $siteVisit = SiteVisit::make($siteVisitData);
        $siteVisit->created_at = $visitedAt;
        $siteVisit->updated_at = $updatedAt;
        $siteVisit->save();

        // Update lead status
        if ($leadStatus) {
            $lead->update(['status' => $leadStatus]);
        }

        if ($isDead && !$lead->is_dead) {
            $lead->markAsDead(
                $managerUserId ?? $employeeUserId ?? 1,
                $visitData['manager_remark'] ?? 'Imported as dead',
                'site_visit'
            );
        }

        $result['success'] = true;
        return $result;
    }

    /**
     * Find or map user by name
     */
    protected function findOrMapUser(string $name, string $type): ?int
    {
        // Check if already mapped
        $key = strtolower(trim($name));
        if (isset($this->userMapping[$key])) {
            return $this->userMapping[$key];
        }

        // Search for user by name (case-insensitive)
        $user = User::whereRaw('LOWER(name) = ?', [strtolower(trim($name))])
            ->where('is_active', true)
            ->first();

        if ($user) {
            $this->userMapping[$key] = $user->id;
            return $user->id;
        }

        // Store for manual mapping
        $this->userMapping[$key] = null;
        return null;
    }

    /**
     * Find or create lead by phone
     */
    protected function findOrCreateLead(array $visitData): array
    {
        if (empty($visitData['phone'])) {
            return ['lead' => null, 'created' => false];
        }

        $phone = $this->duplicateService->sanitizePhone($visitData['phone']);
        
        if (!$this->duplicateService->isValidPhone($phone)) {
            return ['lead' => null, 'created' => false];
        }

        // Find existing lead
        $lead = $this->duplicateService->findExistingLeadByPhone($phone);

        if (!$lead) {
            // Create new lead
            $lead = Lead::create([
                'name' => $visitData['customer_name'] ?? 'Unknown',
                'phone' => $phone,
                'source' => 'other', // Use 'other' as imported_visit is not in enum
                'status' => 'new',
                'property_type' => $this->mapPropertyType($visitData['selection_interest'] ?? null),
                'budget' => $visitData['budget'] ?? null,
                'notes' => $visitData['notes'] ?? null,
                'created_by' => 1, // Admin
            ]);
            return ['lead' => $lead, 'created' => true];
        }

        app(LeadReenquiryService::class)->markGenericReenquiry($lead, 'other', 1);

        return ['lead' => $lead, 'created' => false];
    }

    /**
     * Map visit type
     */
    protected function mapVisitType(?string $type): string
    {
        $mapping = [
            'new' => 'New Visit',
            'revisited' => 'Revisited',
            'meeting' => 'Meeting',
        ];

        return $mapping[strtolower($type ?? 'new')] ?? 'New Visit';
    }

    /**
     * Map property type from old format to new format
     */
    protected function mapPropertyType(?string $type): ?string
    {
        if (empty($type)) {
            return null;
        }

        $mapping = [
            'plot/villa' => 'villa',
            'flat' => 'apartment',
            'commercial' => 'commercial',
            'just exploring' => 'other',
        ];

        $normalized = strtolower(trim($type));
        return $mapping[$normalized] ?? 'other';
    }

    /**
     * Map budget range
     */
    protected function mapBudgetRange(?string $budget): ?string
    {
        if (empty($budget)) {
            return null;
        }

        // Clean budget string
        $budget = trim($budget);
        
        // Map common budget formats
        if (stripos($budget, '50 lac') !== false && stripos($budget, '1 cr') !== false) {
            return '50 Lac – 1 Cr';
        }
        if (stripos($budget, '1 cr') !== false && stripos($budget, '2 cr') !== false) {
            return '1 Cr – 2 Cr';
        }
        if (stripos($budget, '2 cr') !== false && stripos($budget, '3 cr') !== false) {
            return '2 Cr – 3 Cr';
        }
        if (stripos($budget, 'above 3 cr') !== false || stripos($budget, '3 cr') !== false) {
            return 'Above 3 Cr';
        }
        if (stripos($budget, 'under 50') !== false || stripos($budget, 'below 50') !== false) {
            return 'Under 50 Lac';
        }

        // If it matches enum values, return as is
        $validRanges = [
            'Under 50 Lac',
            '50 Lac – 1 Cr',
            '1 Cr – 2 Cr',
            '2 Cr – 3 Cr',
            'Above 3 Cr',
        ];

        if (in_array($budget, $validRanges)) {
            return $budget;
        }

        return null;
    }

    /**
     * Map lead status
     */
    protected function mapLeadStatus(?string $status): ?string
    {
        $mapping = [
            'Follow-up' => 'connected', // Use 'connected' instead of 'contacted'
            'Lost' => 'dead',
            'Dead' => 'dead',
            'Closed' => 'closed',
        ];

        return $mapping[$status ?? 'Follow-up'] ?? 'connected';
    }

    /**
     * Combine notes
     */
    protected function combineNotes(array $notes): ?string
    {
        $combined = array_filter($notes, function($note) {
            return !empty(trim($note ?? ''));
        });

        if (empty($combined)) {
            return null;
        }

        return implode("\n\n", $combined);
    }

    /**
     * Import photos
     */
    protected function importPhotos(array $photoPaths, int $visitId): array
    {
        $importedPaths = [];

        foreach ($photoPaths as $oldPath) {
            try {
                // Try to copy photo if it exists in old location
                // For now, just store the path - photos may need manual copying
                $newPath = 'site-visits/imported_' . $visitId . '_' . basename($oldPath);
                
                // If old path exists in public/uploads, try to copy
                $oldFullPath = public_path($oldPath);
                if (file_exists($oldFullPath)) {
                    $content = file_get_contents($oldFullPath);
                    Storage::disk('public')->put($newPath, $content);
                    $importedPaths[] = $newPath;
                } else {
                    // Store original path for reference
                    $importedPaths[] = $oldPath;
                }
            } catch (\Exception $e) {
                Log::warning("Could not import photo: {$oldPath} - " . $e->getMessage());
            }
        }

        return $importedPaths;
    }

    /**
     * Parse SQL file to extract visits
     */
    protected function parseSqlFile(string $filePath): array
    {
        $visits = [];
        $content = file_get_contents($filePath);
        
        // Extract INSERT INTO visits statements - match the full VALUES section
        preg_match_all(
            '/INSERT INTO `visits`[^)]+\)\s*VALUES\s*(.+?);/s',
            $content,
            $matches
        );

        foreach ($matches[1] as $valuesString) {
            $parsedVisits = $this->parseInsertValues($valuesString, [
                'id', 'customer_name', 'occupation', 'phone', 'project_name',
                'visit_type', 'selection_interest', 'payment_mode', 'budget',
                'tentative_period', 'notes', 'employee_remark', 'assigned_manager',
                'lead_status', 'manager_remark', 'employee_name', 'visited_at',
                'created_at', 'updated_at', 'is_new_assignment', 'assignment_transfer_date',
                'trashed_at', 'transferred_at'
            ]);

            foreach ($parsedVisits as $visit) {
                if ($visit && !empty($visit['customer_name'])) {
                    $visits[] = $visit;
                }
            }
        }

        return $visits;
    }

    /**
     * Parse photos from SQL file
     */
    protected function parsePhotosFromSql(string $filePath): array
    {
        $photos = [];
        $content = file_get_contents($filePath);
        
        preg_match_all(
            '/INSERT INTO `visit_photos`[^)]+\)\s*VALUES\s*(.+?);/s',
            $content,
            $matches
        );

        foreach ($matches[1] as $valuesString) {
            $parsedPhotos = $this->parseInsertValues($valuesString, [
                'id', 'visit_id', 'photo_path', 'created_at'
            ]);

            foreach ($parsedPhotos as $photo) {
                if ($photo && isset($photo['visit_id'])) {
                    if (!isset($photos[$photo['visit_id']])) {
                        $photos[$photo['visit_id']] = [];
                    }
                    $photos[$photo['visit_id']][] = $photo['photo_path'];
                }
            }
        }

        return $photos;
    }

    /**
     * Parse verifications from SQL file
     */
    protected function parseVerificationsFromSql(string $filePath): array
    {
        $verifications = [];
        $content = file_get_contents($filePath);
        
        preg_match_all(
            '/INSERT INTO `visit_verifications`[^)]+\)\s*VALUES\s*(.+?);/s',
            $content,
            $matches
        );

        foreach ($matches[1] as $valuesString) {
            $parsedVerifications = $this->parseInsertValues($valuesString, [
                'id', 'visit_id', 'verification_status', 'verified_by',
                'verified_by_type', 'verified_at', 'created_at'
            ]);

            foreach ($parsedVerifications as $verification) {
                if ($verification && isset($verification['visit_id'])) {
                    $verifications[$verification['visit_id']] = $verification;
                }
            }
        }

        return $verifications;
    }

    /**
     * Parse INSERT VALUES string
     */
    protected function parseInsertValues(string $valuesString, array $fields): array
    {
        // Handle multiple rows - split by ),( pattern
        $rows = preg_split('/\)\s*,\s*\(/', $valuesString);
        $result = [];

        foreach ($rows as $row) {
            $row = trim($row, '()');
            if (empty($row)) {
                continue;
            }
            
            $values = $this->parseSqlValues($row);
            
            if (count($values) === count($fields)) {
                $data = array_combine($fields, $values);
                $result[] = $data;
            } elseif (count($values) > 0) {
                // Log mismatch but continue
                Log::warning("Field count mismatch in SQL parsing", [
                    'expected' => count($fields),
                    'got' => count($values),
                    'fields' => $fields
                ]);
            }
        }

        return $result;
    }

    /**
     * Parse SQL values (handle quotes, NULL, etc.)
     */
    protected function parseSqlValues(string $row): array
    {
        $values = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        
        for ($i = 0; $i < strlen($row); $i++) {
            $char = $row[$i];
            
            if (($char === '"' || $char === "'") && ($i === 0 || $row[$i-1] !== '\\')) {
                if (!$inQuotes) {
                    $inQuotes = true;
                    $quoteChar = $char;
                } elseif ($char === $quoteChar) {
                    $inQuotes = false;
                    $quoteChar = null;
                } else {
                    $current .= $char;
                }
            } elseif ($char === ',' && !$inQuotes) {
                $values[] = $this->cleanSqlValue(trim($current));
                $current = '';
            } else {
                $current .= $char;
            }
        }
        
        if (!empty(trim($current))) {
            $values[] = $this->cleanSqlValue(trim($current));
        }
        
        return $values;
    }

    /**
     * Clean SQL value
     */
    protected function cleanSqlValue(string $value): ?string
    {
        $value = trim($value);
        
        if (strtoupper($value) === 'NULL') {
            return null;
        }
        
        // Remove quotes
        $value = trim($value, '"\'');
        
        // Unescape
        $value = str_replace(['\\"', "\\'", '\\\\'], ['"', "'", '\\'], $value);
        
        return $value === '' ? null : $value;
    }

    /**
     * Load user mapping from file
     */
    protected function loadUserMapping(): void
    {
        if (file_exists($this->userMappingFile)) {
            $this->userMapping = json_decode(file_get_contents($this->userMappingFile), true) ?? [];
        }
    }

    /**
     * Save user mapping to file
     */
    protected function saveUserMapping(): void
    {
        $unmapped = [];
        foreach ($this->userMapping as $name => $userId) {
            if ($userId === null) {
                $unmapped[$name] = null;
            }
        }

        if (!empty($unmapped)) {
            file_put_contents($this->userMappingFile, json_encode($unmapped, JSON_PRETTY_PRINT));
        }
    }
}
