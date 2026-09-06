<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Task;
use Illuminate\Support\Collection;

class LeadDisplayStatusResolver
{
    public function apply(Collection $leads): void
    {
        if ($leads->isEmpty()) {
            return;
        }

        $leadIds = $leads->pluck('id')->filter()->values();
        if ($leadIds->isEmpty()) {
            return;
        }

        $latestTasksByLead = Task::query()
            ->whereIn('lead_id', $leadIds)
            ->where('type', 'phone_call')
            ->orderByDesc('id')
            ->get()
            ->groupBy('lead_id');

        $boundaries = app(LeadReopenService::class)->boundaries($leadIds->all());
        foreach ($leads as $lead) {
            if (!$lead instanceof Lead) {
                continue;
            }

            $lead->setAttribute('display_status', $this->resolve($lead, $latestTasksByLead->get($lead->id, collect()), $boundaries[$lead->id] ?? []));
        }
    }

    public function label(Lead $lead): string
    {
        $status = (string) ($lead->getAttribute('display_status') ?? $this->resolve($lead));

        return ucwords(str_replace('_', ' ', $status));
    }

    public function resolve(Lead $lead, ?Collection $tasks = null, ?array $boundary = null): string
    {
        $displayStatus = (string) ($lead->status ?? 'new');

        if ($displayStatus !== 'new') {
            return $displayStatus;
        }

        if ((int) ($lead->cnp_count ?? 0) > 0) {
            return 'cnp';
        }

        if (!empty($lead->next_followup_at)) {
            return 'follow_up';
        }

        $tasks = $tasks ?: $this->leadPhoneCallTasks($lead);
        $boundary ??= app(LeadReopenService::class)->boundary((int) $lead->id);
        if ($boundary) $tasks = $tasks->filter(fn ($task) => $task->id > ($boundary['tasks'] ?? 0));
        $latestRelevantTask = $tasks->first(function (Task $task) use ($lead) {
            $taskText = $this->taskText($task);

            $isCnpRetryTask = str_contains($taskText, 'cnp retry task created')
                || str_contains($taskText, 'cnp rescheduled')
                || str_contains($taskText, 'previous call not picked');

            if ($isCnpRetryTask) {
                return true;
            }

            $taskCategory = $this->determineAsmTaskCategory($task, $lead);

            return ($task->outcome === 'cnp' && $taskCategory === 'fresh_lead')
                || ($task->outcome === 'follow_up' && $taskCategory === 'fresh_lead');
        });

        if ($latestRelevantTask) {
            return $latestRelevantTask->outcome === 'follow_up' ? 'follow_up' : 'cnp';
        }

        return $displayStatus;
    }

    private function leadPhoneCallTasks(Lead $lead): Collection
    {
        if ($lead->relationLoaded('tasks')) {
            return $lead->tasks
                ->where('type', 'phone_call')
                ->sortByDesc('id')
                ->values();
        }

        return Task::query()
            ->where('lead_id', $lead->id)
            ->where('type', 'phone_call')
            ->orderByDesc('id')
            ->get();
    }

    private function determineAsmTaskCategory(Task $task, Lead $lead): string
    {
        $prospects = $lead->relationLoaded('prospects') ? $lead->prospects : collect();
        $prospect = $prospects->sortByDesc('created_at')->first();
        $hasPendingProspect = $prospect && in_array($prospect->verification_status ?? '', ['pending', 'pending_verification'], true);
        if ($prospect && $prospect->id <= (app(LeadReopenService::class)->boundary($lead->id)['prospects'] ?? 0)) $hasPendingProspect = false;

        $taskText = $this->taskText($task);
        $isFollowUpTask = str_contains($taskText, 'follow-up call')
            || str_contains($taskText, 'follow up call')
            || str_contains($taskText, 'follow-up scheduled');
        $isCnpRetryTask = str_contains($taskText, 'cnp retry task created')
            || str_contains($taskText, 'cnp rescheduled')
            || str_contains($taskText, 'previous call not picked');
        $isCloserTask = str_contains($taskText, 'closer');
        $isSiteVisitTask = $task->site_visit_id !== null
            || str_contains($taskText, 'site visit')
            || str_contains($taskText, 'site-visit');
        $isMeetingTask = $task->meeting_id !== null
            || str_contains($taskText, 'meeting id')
            || str_contains($taskText, 'pre-meeting')
            || (str_contains($taskText, 'meeting') && !$isSiteVisitTask);
        $isProspectTask = !$isFollowUpTask && !$isCnpRetryTask && $hasPendingProspect;
        $isFreshLeadTask = !$isFollowUpTask
            && !$isCnpRetryTask
            && !$isCloserTask
            && !$isSiteVisitTask
            && !$isMeetingTask
            && !$isProspectTask;

        if ($isFreshLeadTask) {
            return 'fresh_lead';
        }
        if ($isFollowUpTask) {
            return 'follow_up';
        }
        if ($isCloserTask) {
            return 'closer';
        }
        if ($isSiteVisitTask) {
            return 'site_visit';
        }
        if ($isMeetingTask) {
            return 'meeting';
        }
        if ($isProspectTask) {
            return 'prospect';
        }

        return 'other';
    }

    private function taskText(Task $task): string
    {
        return strtolower(trim(
            ($task->title ?? '') . ' ' .
            ($task->description ?? '') . ' ' .
            ($task->notes ?? '')
        ));
    }
}
