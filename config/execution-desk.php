<?php

return [
    'contexts' => [
        'admin' => 'Admin',
        'hr' => 'HR',
        'finance' => 'Finance',
        'crm' => 'CRM',
        'sales' => 'Sales',
        'marketing' => 'Marketing',
        'operations' => 'Operations',
    ],
    'priorities' => ['low', 'medium', 'high', 'urgent'],
    'statuses' => [
        'open',
        'in_progress',
        'waiting',
        'completed',
        'closed',
        'reopened',
        'rejected',
    ],
    'open_statuses' => [
        'open',
        'in_progress',
        'waiting',
        'reopened',
    ],
    'elevated_roles' => [
        'admin',
        'hr_manager',
        'finance_manager',
        'lead_manager',
        'sales_manager',
        'senior_manager',
        'marketing_manager',
    ],
    'due_reminder_minutes' => 15,
    'overdue_repeat_minutes' => 60,
];
