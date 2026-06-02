<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$client = app(App\Services\Nrs\NrsClient::class);

try {
    $response = $client->get('api/v1/entity');
    echo "Entities response:\n";
    echo json_encode($response->json(), JSON_PRETTY_PRINT) . "\n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
