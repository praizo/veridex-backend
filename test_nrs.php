<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$invoice = App\Models\Invoice::latest()->first();
$client = app(App\Services\Nrs\NrsClient::class);
$builder = app(App\Services\Nrs\NrsPayloadBuilder::class);
$payload = $builder->buildFullInvoicePayload($invoice);

$response = Illuminate\Support\Facades\Http::withHeaders($client->getHeaders())
    ->post($client->resolveUrl('api/v1/invoice/validate'), $payload);

echo json_encode($response->json(), JSON_PRETTY_PRINT);
