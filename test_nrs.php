<?php

use App\Models\Invoice;
use App\Services\Nrs\NrsClient;
use App\Services\Nrs\NrsPayloadBuilder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$invoice = Invoice::latest()->first();
$client = app(NrsClient::class);
$builder = app(NrsPayloadBuilder::class);
$payload = $builder->buildFullInvoicePayload($invoice);
echo "Payload:\n" . json_encode($payload, JSON_PRETTY_PRINT) . "\n\n";

try {
    // Send Validate Request
    $response = $client->post('api/v1/invoice/sign', $payload);
    echo json_encode($response->json(), JSON_PRETTY_PRINT);
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
