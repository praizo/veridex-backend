<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$log = App\Models\NrsApiLog::latest()->first();
if ($log) {
    echo "Status Code: {$log->status_code}\n";
    echo "Request Payload:\n";
    echo json_encode($log->request_payload, JSON_PRETTY_PRINT) . "\n\n";
    echo "Response Body:\n";
    echo json_encode($log->response_body, JSON_PRETTY_PRINT);
} else {
    echo "No log found in NrsApiLogs.";
}
