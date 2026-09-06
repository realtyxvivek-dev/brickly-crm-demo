<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class TaskViewPaginationTest extends TestCase
{
    public function test_task_header_and_scroll_state_support_all_view_data_types(): void
    {
        $source = file_get_contents(resource_path('views/tasks/index.blade.php'));
        preg_match('/@php.*?@endphp/s', $source, $setup);
        preg_match('/{{ \$taskCount }}.*?}}/', $source, $counter);
        preg_match('/document.body.dataset.hasMorePages = .*?;/', $source, $scroll);
        $this->assertNotEmpty($setup);
        $this->assertNotEmpty($counter);
        $this->assertNotEmpty($scroll);
        $template = $setup[0]."\n".$counter[0]."\n".$scroll[0];
        foreach ([
            [new Collection(), '0 tasks', 'false'],
            [new Collection([new \App\Models\Task()]), '1 task', 'false'],
            [new Collection([new \App\Models\Task(), new \App\Models\Task()]), '2 tasks', 'false'],
            [new LengthAwarePaginator([], 45, 20, 1), '45 tasks', 'true'],
            [new LengthAwarePaginator([], 45, 20, 3), '45 tasks', 'false'],
            [new LengthAwarePaginator([], 0, 20, 1), '0 tasks', 'false'],
        ] as [$tasks, $count, $more]) {
            $html = Blade::render($template, compact('tasks'));
            $this->assertStringContainsString($count, $html);
            $this->assertStringContainsString("hasMorePages = '$more'", $html);
        }
    }
}
