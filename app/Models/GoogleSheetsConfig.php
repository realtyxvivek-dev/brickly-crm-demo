<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class GoogleSheetsConfig extends Model
{
    use HasFactory;

    protected $table = 'google_sheets_config';

    protected $fillable = [
        'sheet_id',
        'sheet_name',
        'sheet_type',
        'selected_columns_json',
        'crm_status_columns_json',
        'api_endpoint_url',
        'api_key',
        'inbound_slug',
        'inbound_api_key',
        'refresh_token',
        'service_account_json_path',
        'range',
        'name_column',
        'phone_column',
        'notes_column',
        'status_column',
        'notes_column_sync',
        'last_sync_at',
        'last_synced_row',
        'auto_sync_enabled',
        'sync_interval_minutes',
        'assignment_rule_id',
        'automation_id',
        'linked_telecaller_id',
        'per_sheet_daily_limit',
        'is_active',
        'is_draft',
        'setup_completed_at',
        'completion_notification_sent',
        'created_by',
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
        'setup_completed_at' => 'datetime',
        'auto_sync_enabled' => 'boolean',
        'is_active' => 'boolean',
        'is_draft' => 'boolean',
        'completion_notification_sent' => 'boolean',
        'last_synced_row' => 'integer',
        'sync_interval_minutes' => 'integer',
        'crm_status_columns_json' => 'array',
        'selected_columns_json' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignmentRule(): BelongsTo
    {
        return $this->belongsTo(AssignmentRule::class);
    }


    public function linkedTelecaller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_telecaller_id');
    }

    public function sheetAssignmentConfig(): HasOne
    {
        return $this->hasOne(SheetAssignmentConfig::class, 'google_sheets_config_id');
    }

    public function leadAssignments(): HasMany
    {
        return $this->hasMany(LeadAssignment::class, 'sheet_config_id');
    }

    public function columnMappings(): HasMany
    {
        return $this->hasMany(GoogleSheetsColumnMapping::class, 'google_sheets_config_id')->orderBy('display_order');
    }

    public function importState(): HasOne
    {
        return $this->hasOne(GoogleSheetImportState::class, 'google_sheets_config_id');
    }

    public function importLogs(): HasMany
    {
        return $this->hasMany(GoogleSheetImportLog::class, 'google_sheets_config_id');
    }

    public function requestLogs(): HasMany
    {
        return $this->hasMany(GoogleSheetsRequestLog::class, 'google_sheets_config_id')->latest();
    }

    public static function generateInboundSlug(): string
    {
        return 'gsi_' . Str::lower(Str::random(32));
    }

    public static function generateInboundApiKey(): string
    {
        return 'gsk_' . Str::lower(Str::random(40));
    }

    public function ensureInboundCredentials(): bool
    {
        $updates = [];

        if (!$this->inbound_slug) {
            do {
                $slug = self::generateInboundSlug();
            } while (self::where('inbound_slug', $slug)->where('id', '!=', $this->id)->exists());

            $updates['inbound_slug'] = $slug;
        }

        if (!$this->inbound_api_key) {
            $updates['inbound_api_key'] = self::generateInboundApiKey();
        }

        if ($updates === []) {
            return false;
        }

        $this->forceFill($updates)->save();
        $this->refresh();

        return true;
    }

    public function rotateInboundApiKey(): string
    {
        $this->forceFill([
            'inbound_api_key' => self::generateInboundApiKey(),
        ])->save();
        $this->refresh();

        return (string) $this->inbound_api_key;
    }

    /**
     * Extract sheet ID from full Google Sheets URL
     */
    public static function extractSheetId($input): ?string
    {
        if (empty($input)) {
            return null;
        }

        // If it's already just an ID (alphanumeric with dashes/underscores)
        if (preg_match('/^[a-zA-Z0-9_-]+$/', $input)) {
            return $input;
        }

        // Extract from URL pattern: https://docs.google.com/spreadsheets/d/{SHEET_ID}/...
        if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9_-]+)/', $input, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Convert column letter to index (A=0, B=1, etc.)
     */
    public static function columnLetterToIndex(string $letter): int
    {
        $letter = strtoupper(trim($letter));
        $index = 0;
        $length = strlen($letter);
        
        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($letter[$i]) - ord('A') + 1);
        }
        
        return $index - 1;
    }
}
