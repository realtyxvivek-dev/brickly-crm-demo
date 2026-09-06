<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoogleSheetImportLog;
use App\Models\GoogleSheetsConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class GoogleSheetImportMonitorController extends Controller
{
    public function index()
    {
        $hasStateTable = Schema::hasTable('google_sheet_import_state');
        $hasLogsTable = Schema::hasTable('google_sheet_import_logs');

        $migrationWarning = null;
        if (!$hasStateTable || !$hasLogsTable) {
            $missing = [];
            if (!$hasStateTable) {
                $missing[] = 'google_sheet_import_state';
            }
            if (!$hasLogsTable) {
                $missing[] = 'google_sheet_import_logs';
            }

            $migrationWarning = 'Required import monitor tables are missing: ' . implode(', ', $missing) . '. Run: php artisan migrate --force';
        }

        $configs = GoogleSheetsConfig::with(['creator:id,name'])
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get();

        if ($hasStateTable && $hasLogsTable) {
            $configs->load([
                'importState',
                'importLogs' => function ($query) {
                    $query->latest('started_at')->limit(1);
                },
            ]);
        }

        $logs = $hasLogsTable
            ? GoogleSheetImportLog::with([
                'config:id,sheet_name,created_by',
                'config.creator:id,name',
            ])->latest('started_at')->limit(100)->get()
            : new Collection();

        return view('integrations.google-sheet-import-monitor', [
            'configs' => $configs,
            'logs' => $logs,
            'migrationWarning' => $migrationWarning,
        ]);
    }
}

