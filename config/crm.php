<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Danger: Delete All Leads password
    |--------------------------------------------------------------------------
    | Password required to confirm "Delete All Leads" in CRM dashboard.
    | Set CRM_DANGER_DELETE_ALL_LEADS_PASSWORD in .env to override; default 9559180196.
    */
    'danger_delete_all_leads_password' => env('CRM_DANGER_DELETE_ALL_LEADS_PASSWORD', '9559180196'),

    /*
    |--------------------------------------------------------------------------
    | Public landing page demo requests
    |--------------------------------------------------------------------------
    */
    'demo_request_email' => env('CRM_DEMO_REQUEST_EMAIL', 'realtyxvivek@gmail.com'),
];
