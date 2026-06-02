<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$i = App\Models\Invoice::latest()->first();
if ($i) {
    echo "Invoice ID: " . $i->id . "\n";
    echo "Invoice Number: " . $i->invoice_number . "\n";
    echo "Org ID: " . $i->organization_id . "\n";
    echo "Org Name: " . $i->organization->name . "\n";
    echo "Org Business ID: " . $i->organization->nrs_business_id . "\n";
    echo "Org TIN: " . $i->organization->tin . "\n";
} else {
    echo "No invoice found.\n";
}

$orgs = App\Models\Organization::all();
foreach ($orgs as $org) {
    echo "Org ID: {$org->id}, Name: {$org->name}, TIN: {$org->tin}, NRS Business ID: {$org->nrs_business_id}\n";
}
