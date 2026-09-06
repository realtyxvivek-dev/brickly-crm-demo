<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\SelfTodo;
use Illuminate\Console\Command;

class SendSelfTodoReminders extends Command
{
    protected $signature = 'notifications:self-todo-reminders';

    protected $description = 'Send due reminders for private self todos during the pilot';

    public function handle(): int
    {
        $now = now();

        $todos = SelfTodo::query()
            ->with('user')
            ->where('status', SelfTodo::STATUS_OPEN)
            ->whereNotNull('due_at')
            ->whereNull('reminder_sent_at')
            ->where('due_at', '<=', $now)
            ->get();

        foreach ($todos as $todo) {
            AppNotification::query()->create([
                'user_id' => $todo->user_id,
                'type' => AppNotification::TYPE_SELF_TODO_REMINDER,
                'title' => 'Self Todo due',
                'message' => $todo->title,
                'data' => [
                    'self_todo_id' => $todo->id,
                    'due_at' => optional($todo->due_at)->toIso8601String(),
                    'priority' => $todo->priority,
                ],
                'action_type' => AppNotification::ACTION_EXECUTION_DESK,
                'action_url' => route('execution-desk.index', ['tab' => 'self_todo']),
            ]);

            $todo->forceFill(['reminder_sent_at' => $now])->save();
        }

        $this->info('Self todo reminders processed: ' . $todos->count());

        return self::SUCCESS;
    }
}
