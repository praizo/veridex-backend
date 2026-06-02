<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$invoice = App\Models\Invoice::latest()->first();
$serviceId = '0D2153BF';
$tinBranch = '99999999-0001';
$dateStamp = $invoice->issue_date->format('Ymd');
$irn = "{$tinBranch}-{$invoice->invoice_number}-{$serviceId}-{$dateStamp}";
$irn = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', $irn));
$invoice->irn = $irn;
$invoice->save();
echo "Updated IRN to: {$irn}\n";
