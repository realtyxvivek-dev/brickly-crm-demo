<?php

return [
    'pdf' => [
        'binary' => env('WKHTMLTOPDF_BINARY'),
        'fallback_paths' => [
            'C:\\easytime\\zkeco_dlls\\wkhtmltopdf.exe',
            'C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
            'C:\\Program Files (x86)\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
        ],
        'timeout' => (int) env('WKHTMLTOPDF_TIMEOUT', 60),
    ],
];
