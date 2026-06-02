<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$org = App\Models\Organization::first();
if (!$org) {
    $org = new App\Models\Organization();
    $org->name = 'Test Org';
    $org->save();
}

$invoice = new App\Models\Invoice();
$invoice->organization_id = $org->id;
$invoice->invoice_number = 'INV00123';
$invoice->issue_date = now();
$invoice->status = App\Enums\InvoiceStatus::DRAFT;
$invoice->save();

echo "Generated IRN: " . $invoice->irn . "\n";
