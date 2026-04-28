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

$response = Http::withHeaders($client->getHeaders())
    ->post($client->resolveUrl('api/v1/invoice/validate'), $payload);

echo json_encode($response->json(), JSON_PRETTY_PRINT);
