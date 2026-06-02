<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $service = app(App\Services\Nrs\NrsInvoiceService::class);
    $invoice = App\Models\Invoice::latest()->first();
    
    if($invoice) {
        echo "Validating invoice ID: {$invoice->id} with IRN: {$invoice->irn}\n";
        $service->validate($invoice);
        echo "Success\n";
    } else {
        echo "No invoice found\n";
    }
} catch (\App\Exceptions\NrsApiException $e) {
    echo "NrsApiException: \n" . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "Exception: \n" . $e->getMessage() . "\n";
    if (method_exists($e, 'getResponse') && $e->getResponse()) {
        echo "Body: " . json_encode($e->getResponse()->json(), JSON_PRETTY_PRINT) . "\n";
    }
}
