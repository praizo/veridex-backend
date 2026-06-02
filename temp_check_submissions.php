<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$submissions = App\Models\NrsSubmission::where('invoice_id', 4)->get();
echo json_encode($submissions->toArray(), JSON_PRETTY_PRINT);
