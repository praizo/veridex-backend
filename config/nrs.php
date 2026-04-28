<?php

return [
    'base_url' => env('NRS_BASE_URL', 'https://api.einvoice.firs.gov.ng'),
    'api_key' => env('NRS_API_KEY'),
    'api_secret' => env('NRS_API_SECRET'),
    'environment' => env('NRS_ENVIRONMENT', 'sandbox'),
];
