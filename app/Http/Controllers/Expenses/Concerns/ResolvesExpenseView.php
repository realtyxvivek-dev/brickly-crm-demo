<?php

namespace App\Http\Controllers\Expenses\Concerns;

trait ResolvesExpenseView
{
    protected function expenseView(string $page): string
    {
        return request()->routeIs('finance-manager.*')
            ? 'finance-manager.expenses.' . $page
            : 'admin.expenses.' . $page;
    }

    protected function expenseRoute(string $suffix, mixed $parameters = []): string
    {
        $prefix = request()->routeIs('finance-manager.*') ? 'finance-manager.expenses.' : 'admin.expenses.';

        return route($prefix . $suffix, $parameters);
    }
}
