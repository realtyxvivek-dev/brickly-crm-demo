<?php

namespace App\Services;

use App\Models\AttendancePhoto;
use App\Models\CallLog;
use App\Models\MediaTrashItem;
use App\Models\Meeting;
use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageManagerService
{
    public function activeItems(array $filters = []): Collection
    {
        $trashedKeys = $this->trashedActiveKeys();

        return collect()
            ->merge($this->attendancePhotoItems($trashedKeys))
            ->merge($this->meetingPhotoItems($trashedKeys))
            ->merge($this->siteVisitPhotoItems($trashedKeys))
            ->merge($this->callRecordingItems($trashedKeys))
            ->filter(fn (array $item) => $this->matchesFilters($item, $filters))
            ->sortByDesc(fn (array $item) => $item['created_at']?->timestamp ?? 0)
            ->values();
    }

    public function trashItems(array $filters = []): Collection
    {
        return MediaTrashItem::with('trashedBy')
            ->whereNotNull('trashed_at')
            ->whereNull('restored_at')
            ->whereNull('permanently_deleted_at')
            ->latest('trashed_at')
            ->get()
            ->filter(function (MediaTrashItem $trash) use ($filters) {
                if (($filters['type'] ?? '') && $filters['type'] !== 'all' && $trash->media_type !== $filters['type']) {
                    return false;
                }
                if (($filters['module'] ?? '') && $filters['module'] !== 'all' && $trash->module !== $filters['module']) {
                    return false;
                }
                return true;
            })
            ->values();
    }

    public function overview(array $filters = []): array
    {
        $items = $this->activeItems($filters);
        $trash = $this->trashItems($filters);

        return [
            'total_size' => $items->sum('file_size'),
            'photos_size' => $items->where('media_type', 'photo')->sum('file_size'),
            'recordings_size' => $items->where('media_type', 'recording')->sum('file_size'),
            'trash_size' => $trash->sum('file_size'),
            'photos_count' => $items->where('media_type', 'photo')->count(),
            'recordings_count' => $items->where('media_type', 'recording')->count(),
            'trash_count' => $trash->count(),
            'old_files_count' => $items->filter(fn (array $item) => $item['created_at']?->lt(now()->subDays(90)))->count(),
        ];
    }

    public function trashMedia(string $key, User $actor): ?MediaTrashItem
    {
        $item = $this->activeItems()->firstWhere('key', $key);
        if (!$item || $item['is_protected'] || !$item['can_trash']) {
            return null;
        }

        return MediaTrashItem::create([
            'source_type' => $item['source_type'],
            'source_id' => $item['source_id'],
            'source_field' => $item['source_field'],
            'module' => $item['module'],
            'media_type' => $item['media_type'],
            'disk' => $item['disk'],
            'file_path' => $item['file_path'],
            'file_url' => $item['url'],
            'file_name' => $item['file_name'],
            'mime_type' => $item['mime_type'],
            'file_size' => $item['file_size'],
            'is_protected' => $item['is_protected'],
            'protected_reason' => $item['protected_reason'],
            'trashed_by' => $actor->id,
            'trashed_at' => now(),
            'delete_after' => now()->addDays(7),
            'meta' => [
                'label' => $item['label'],
                'owner' => $item['owner_name'],
                'related' => $item['related_label'],
            ],
        ]);
    }

    public function restore(MediaTrashItem $trash, User $actor): void
    {
        $trash->update([
            'restored_at' => now(),
            'restored_by' => $actor->id,
        ]);
    }

    public function permanentlyDelete(MediaTrashItem $trash, User $actor): bool
    {
        if ($trash->is_protected || $trash->permanently_deleted_at || $trash->restored_at) {
            return false;
        }

        $this->detachReference($trash);

        if ($trash->disk === 'public' && Storage::disk('public')->exists($trash->file_path)) {
            Storage::disk('public')->delete($trash->file_path);
        }

        $trash->update([
            'permanently_deleted_at' => now(),
            'permanently_deleted_by' => $actor->id,
        ]);

        return true;
    }

    public function formatBytes(?int $bytes): string
    {
        if (!$bytes) {
            return '--';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return number_format($value, $unit === 0 ? 0 : 1) . ' ' . $units[$unit];
    }

    private function attendancePhotoItems(Collection $trashedKeys): Collection
    {
        return AttendancePhoto::with('user')
            ->latest('captured_at')
            ->limit(1000)
            ->get()
            ->map(function (AttendancePhoto $photo) {
                $path = $this->normalizePublicPath($photo->file_path);
                return $this->makeItem([
                    'source_type' => AttendancePhoto::class,
                    'source_id' => $photo->id,
                    'source_field' => 'file_path',
                    'module' => 'attendance',
                    'media_type' => 'photo',
                    'file_path' => $path,
                    'url' => Storage::disk('public')->url($path),
                    'file_name' => basename($path),
                    'mime_type' => $photo->mime_type,
                    'file_size' => $photo->compressed_size ?: $photo->file_size ?: $this->publicFileSize($path),
                    'owner_name' => $photo->user?->name,
                    'related_label' => 'Attendance photo',
                    'created_at' => $photo->captured_at ?: $photo->created_at,
                    'is_protected' => false,
                    'protected_reason' => null,
                    'label' => 'Attendance photo #' . $photo->id,
                    'can_trash' => true,
                ]);
            })
            ->reject(fn (array $item) => $trashedKeys->contains($this->trashKey($item)));
    }

    private function meetingPhotoItems(Collection $trashedKeys): Collection
    {
        return Meeting::withoutGlobalScopes()
            ->with(['lead:id,name,phone', 'assignedTo:id,name', 'creator:id,name'])
            ->latest('updated_at')
            ->limit(1000)
            ->get()
            ->flatMap(fn (Meeting $meeting) => collect([
                ...$this->jsonPhotoItems($meeting, 'photos', 'meeting', false),
                ...$this->jsonPhotoItems($meeting, 'completion_proof_photos', 'meeting', false),
            ]))
            ->reject(fn (array $item) => $trashedKeys->contains($this->trashKey($item)));
    }

    private function siteVisitPhotoItems(Collection $trashedKeys): Collection
    {
        return SiteVisit::withoutGlobalScopes()
            ->with(['lead:id,name,phone', 'assignedTo:id,name', 'creator:id,name'])
            ->latest('updated_at')
            ->limit(1000)
            ->get()
            ->flatMap(fn (SiteVisit $visit) => collect([
                ...$this->jsonPhotoItems($visit, 'photos', 'site_visit', false),
                ...$this->jsonPhotoItems($visit, 'completion_proof_photos', 'site_visit', false),
                ...$this->jsonPhotoItems($visit, 'closer_request_proof_photos', 'site_visit', true, 'Closing proof is protected'),
            ]))
            ->reject(fn (array $item) => $trashedKeys->contains($this->trashKey($item)));
    }

    private function callRecordingItems(Collection $trashedKeys): Collection
    {
        return CallLog::with(['lead:id,name,phone', 'user:id,name', 'telecaller:id,name'])
            ->whereNotNull('recording_url')
            ->latest('start_time')
            ->limit(1000)
            ->get()
            ->map(function (CallLog $call) {
                $path = $this->recordingPathFromUrl((string) $call->recording_url);
                $isLocal = $path !== null;

                return $this->makeItem([
                    'source_type' => CallLog::class,
                    'source_id' => $call->id,
                    'source_field' => 'recording_url',
                    'module' => 'call',
                    'media_type' => 'recording',
                    'file_path' => $path ?: (string) $call->recording_url,
                    'url' => route('calls.recording', $call),
                    'file_name' => $path ? basename($path) : 'External recording',
                    'mime_type' => null,
                    'file_size' => $path ? $this->publicFileSize($path) : null,
                    'owner_name' => $call->user?->name ?: $call->telecaller?->name,
                    'related_label' => $call->lead?->name ?: $call->phone_number,
                    'created_at' => $call->start_time ?: $call->created_at,
                    'is_protected' => !$isLocal,
                    'protected_reason' => $isLocal ? null : 'External recording link cannot be deleted from CRM storage',
                    'label' => 'Call recording #' . $call->id,
                    'can_trash' => $isLocal,
                ]);
            })
            ->reject(fn (array $item) => $trashedKeys->contains($this->trashKey($item)));
    }

    private function jsonPhotoItems(Model $model, string $field, string $module, bool $protected, ?string $reason = null): array
    {
        return collect((array) $model->{$field})
            ->filter()
            ->map(function (string $rawPath) use ($model, $field, $module, $protected, $reason) {
                $path = $this->normalizePublicPath($rawPath);
                $lead = method_exists($model, 'lead') ? $model->lead : null;
                $owner = $model->assignedTo?->name ?: $model->creator?->name;

                return $this->makeItem([
                    'source_type' => $model::class,
                    'source_id' => $model->id,
                    'source_field' => $field,
                    'module' => $module,
                    'media_type' => 'photo',
                    'file_path' => $path,
                    'url' => Storage::disk('public')->url($path),
                    'file_name' => basename($path),
                    'mime_type' => null,
                    'file_size' => $this->publicFileSize($path),
                    'owner_name' => $owner,
                    'related_label' => $lead?->name ?: ($model->customer_name ?? class_basename($model) . ' #' . $model->id),
                    'created_at' => $model->updated_at ?: $model->created_at,
                    'is_protected' => $protected,
                    'protected_reason' => $reason,
                    'label' => Str::headline($module . ' ' . $field),
                    'can_trash' => !$protected,
                ]);
            })
            ->values()
            ->all();
    }

    private function makeItem(array $data): array
    {
        $data['disk'] = $data['disk'] ?? 'public';
        $data['key'] = hash('sha256', $data['source_type'] . '|' . $data['source_id'] . '|' . $data['source_field'] . '|' . $data['file_path']);
        return $data;
    }

    private function matchesFilters(array $item, array $filters): bool
    {
        if (($filters['type'] ?? 'all') !== 'all' && $item['media_type'] !== $filters['type']) {
            return false;
        }
        if (($filters['module'] ?? 'all') !== 'all' && $item['module'] !== $filters['module']) {
            return false;
        }
        if (($filters['search'] ?? '') !== '') {
            $haystack = strtolower(implode(' ', [
                $item['owner_name'],
                $item['related_label'],
                $item['file_name'],
                $item['label'],
            ]));
            if (!str_contains($haystack, strtolower($filters['search']))) {
                return false;
            }
        }
        if (($filters['from_date'] ?? '') && $item['created_at']?->lt(\Carbon\Carbon::parse($filters['from_date'])->startOfDay())) {
            return false;
        }
        if (($filters['to_date'] ?? '') && $item['created_at']?->gt(\Carbon\Carbon::parse($filters['to_date'])->endOfDay())) {
            return false;
        }
        return true;
    }

    private function trashedActiveKeys(): Collection
    {
        return MediaTrashItem::query()
            ->whereNotNull('trashed_at')
            ->whereNull('restored_at')
            ->whereNull('permanently_deleted_at')
            ->get()
            ->map(fn (MediaTrashItem $trash) => $this->trashKey([
                'source_type' => $trash->source_type,
                'source_id' => $trash->source_id,
                'source_field' => $trash->source_field,
                'file_path' => $trash->file_path,
            ]));
    }

    private function trashKey(array $item): string
    {
        return $item['source_type'] . '|' . $item['source_id'] . '|' . $item['source_field'] . '|' . $item['file_path'];
    }

    private function detachReference(MediaTrashItem $trash): void
    {
        $model = $trash->source_type;
        if (!class_exists($model)) {
            return;
        }

        /** @var Model|null $record */
        $record = $model::withoutGlobalScopes()->find($trash->source_id);
        if (!$record) {
            return;
        }

        if ($record instanceof AttendancePhoto) {
            $record->delete();
            return;
        }

        if ($record instanceof CallLog) {
            $record->recording_url = null;
            $record->save();
            return;
        }

        if ($trash->source_field) {
            $current = collect((array) $record->{$trash->source_field})
                ->reject(fn ($path) => $this->normalizePublicPath((string) $path) === $trash->file_path)
                ->values()
                ->all();
            $record->{$trash->source_field} = $current;
            $record->save();
        }
    }

    private function normalizePublicPath(string $path): string
    {
        $path = trim($path);
        $path = preg_replace('#^https?://[^/]+/storage/#i', '', $path);
        $path = preg_replace('#^/?storage/#i', '', $path);
        $path = preg_replace('#^/?public/#i', '', $path);
        return ltrim((string) $path, '/');
    }

    private function recordingPathFromUrl(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        if (!str_contains($path, '/storage/')) {
            return null;
        }

        return $this->normalizePublicPath(Str::after($path, '/storage/'));
    }

    private function publicFileSize(?string $path): ?int
    {
        if (!$path || !Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->size($path);
    }
}
