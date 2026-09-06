<?php

$token = 'a4086d20bb2946078d3c6e72bddeed41';

if (($_GET['token'] ?? '') !== $token) {
    http_response_code(404);
    exit('Not found');
}

$root = dirname(__DIR__);
$deleted = 0;

foreach (glob($root . '/storage/framework/views/*.php') ?: [] as $file) {
    if (is_file($file) && @unlink($file)) {
        $deleted++;
    }
}

@unlink(__FILE__);

header('Content-Type: application/json');
echo json_encode(['ok' => true, 'views_deleted' => $deleted]);
