<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$org = App\Models\Organization::first();
$org->update([
    'street_name' => '32, owonikoko street',
    'city_name' => 'Gwarikpa',
    'postal_zone' => '023401',
    'country_code' => 'NG',
    'telephone' => '+23480254099000',
]);
echo "Updated Organization address:\n";
echo "  street_name: {$org->street_name}\n";
echo "  city_name: {$org->city_name}\n";
echo "  postal_zone: {$org->postal_zone}\n";
echo "  country_code: {$org->country_code}\n";
echo "  telephone: {$org->telephone}\n";
