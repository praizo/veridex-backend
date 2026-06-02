<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$org = App\Models\Organization::first();
$uuid = 'f0a48674-53bf-423e-a64b-248b4726dfdc';
$org->update(['nrs_business_id' => $uuid]);
echo "Updated nrs_business_id to: " . $uuid . "\n";
