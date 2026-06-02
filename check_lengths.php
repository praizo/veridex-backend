<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$org = App\Models\Organization::latest()->first();
echo "Organization TIN: " . $org->tin . " (Length: " . strlen($org->tin) . ")\n";

$invoice = App\Models\Invoice::latest()->first();
echo "Invoice IRN: " . $invoice->irn . " (Length: " . strlen($invoice->irn) . ")\n";
echo "Invoice Number: " . $invoice->invoice_number . " (Length: " . strlen($invoice->invoice_number) . ")\n";
