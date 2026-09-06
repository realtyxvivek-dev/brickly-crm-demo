<?php

return [
    // Customer delivery is deliberately off until Admin enables it after a pilot.
    'customer_mail_enabled' => env('POST_SALES_CUSTOMER_MAIL_ENABLED', false),
    'test_recipient' => env('POST_SALES_TEST_RECIPIENT', 'realtyxvivek@gmail.com'),
    'reminder_days' => [7, 3, 0],
];
