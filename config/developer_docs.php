<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Developer API Docs Access Key
    |--------------------------------------------------------------------------
    | The documentation page is only accessible at:
    | /developer/docs/{access_key}
    | Set a unique key in .env (DEVELOPER_DOCS_ACCESS_KEY) and share only that
    | URL with your Flutter/mobile developer. Anyone without the key gets 404.
    */
    'access_key' => env('DEVELOPER_DOCS_ACCESS_KEY', 'api-docs-' . substr(md5(config('app.key')), 0, 24)),
];
