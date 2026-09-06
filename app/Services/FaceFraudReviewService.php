<?php

namespace App\Services;

use App\Models\AttendanceEvent;
use App\Models\AttendanceFaceReview;
use App\Models\AttendancePhoto;
use App\Models\AttendancePolicy;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class FaceFraudReviewService
{
    public function evaluatePunchIn(
        User $user,
        AttendancePolicy $policy,
        AttendanceRecord $record,
        AttendanceEvent $event,
        ?AttendancePhoto $photo
    ): array {
        if (!$this->supportsFraudReviewSchema()) {
            return [];
        }

        $flags = [];

        if ($policy->selfie_required && !$photo) {
            $flags[] = 'missing_selfie';
        }

        if ($photo && Schema::hasColumn('attendance_photos', 'file_hash') && $photo->file_hash) {
            $duplicateCount = AttendancePhoto::query()
                ->where('user_id', $user->id)
                ->where('file_hash', $photo->file_hash)
                ->where('id', '!=', $photo->id)
                ->count();

            if ($duplicateCount >= max(1, (int) $policy->duplicate_photo_threshold)) {
                $flags[] = 'duplicate_photo';
            }
        }

        $needsReview = $policy->face_review_required || !empty($flags);

        if (!$needsReview) {
            $record->update([
                'fraud_review_status' => 'clear',
                'fraud_payroll_blocked' => false,
                'fraud_review_reason' => null,
            ]);

            return [];
        }

        AttendanceFaceReview::updateOrCreate(
            ['attendance_record_id' => $record->id],
            [
                'attendance_event_id' => $event->id,
                'attendance_photo_id' => $photo?->id,
                'user_id' => $user->id,
                'status' => 'pending_review',
                'remarks' => empty($flags) ? 'Face review required by policy.' : implode(', ', $flags),
            ]
        );

        $record->update([
            'fraud_review_status' => 'pending_review',
            'fraud_payroll_blocked' => (bool) $policy->payroll_block_on_pending_face_review,
            'fraud_review_reason' => empty($flags) ? 'Face review required by policy.' : implode(', ', $flags),
        ]);

        return $flags;
    }

    public function review(AttendanceFaceReview $review, User $actor, string $status, ?string $remarks = null): AttendanceFaceReview
    {
        if (!$this->supportsFraudReviewSchema()) {
            return $review->fresh(['attendanceRecord', 'attendancePhoto', 'user']);
        }

        $review->update([
            'status' => $status,
            'remarks' => $remarks,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        if ($review->attendanceRecord) {
            $review->attendanceRecord->update([
                'fraud_review_status' => $status === 'accepted' ? 'accepted' : 'rejected',
                'fraud_payroll_blocked' => $status !== 'accepted',
                'fraud_review_reason' => $remarks,
            ]);
        }

        return $review->fresh(['attendanceRecord', 'attendancePhoto', 'user']);
    }

    private function supportsFraudReviewSchema(): bool
    {
        return Schema::hasTable('attendance_face_reviews')
            && Schema::hasColumn('attendance_records', 'fraud_review_status');
    }
}
