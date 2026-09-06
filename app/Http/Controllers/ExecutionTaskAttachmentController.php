<?php

namespace App\Http\Controllers;

use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAttachment;
use App\Services\ExecutionTaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExecutionTaskAttachmentController extends Controller
{
    public function __construct(protected ExecutionTaskService $executionTaskService)
    {
    }

    public function store(Request $request, ExecutionTask $task)
    {
        abort_unless($this->executionTaskService->userCanViewTask($request->user(), $task), 403);

        $validated = $request->validate([
            'file' => 'nullable|file|max:10240',
            'link_url' => 'nullable|url|max:2048',
            'link_title' => 'nullable|string|max:255',
        ]);

        if (empty($validated['file']) && blank($validated['link_url'] ?? null)) {
            return back()->withErrors([
                'file' => 'Upload a file or add a valid link.',
            ])->withInput();
        }

        $this->executionTaskService->storeAttachments(
            $task,
            $request->user(),
            !empty($validated['file']) ? [$validated['file']] : [],
            blank($validated['link_url'] ?? null)
                ? []
                : [[
                    'url' => $validated['link_url'],
                    'title' => $validated['link_title'] ?? null,
                ]]
        );

        return back()->with('success', 'Attachment saved.');
    }

    public function download(Request $request, ExecutionTask $task, ExecutionTaskAttachment $attachment)
    {
        abort_if($attachment->task_id !== $task->id, 404);
        abort_unless($this->executionTaskService->userCanViewTask($request->user(), $task), 403);

        if ($attachment->isLink()) {
            return redirect()->away((string) $attachment->link_url);
        }

        return Storage::disk('public')->download($attachment->file_path, $attachment->file_name);
    }

    public function destroy(Request $request, ExecutionTask $task, ExecutionTaskAttachment $attachment)
    {
        abort_if($attachment->task_id !== $task->id, 404);
        abort_unless($this->executionTaskService->userCanViewTask($request->user(), $task), 403);

        if (!$attachment->isLink() && Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $fileName = $attachment->display_name;
        $attachment->delete();

        $this->executionTaskService->logActivity($task, $request->user(), 'attachment_removed', $attachment->isLink() ? 'Link deleted' : 'Attachment deleted', [
            'attachment_kind' => $attachment->attachment_kind,
            'file_name' => $fileName,
        ]);

        return back()->with('success', $attachment->isLink() ? 'Link deleted.' : 'Attachment deleted.');
    }
}
