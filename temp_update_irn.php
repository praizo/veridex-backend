<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$invoice = App\Models\Invoice::latest()->first();
$newIrn = str_replace('310355', rand(100000, 999999), $invoice->irn);
$invoice->irn = $newIrn;
$invoice->save();

echo "Updated IRN to: " . $invoice->irn . "\n";
