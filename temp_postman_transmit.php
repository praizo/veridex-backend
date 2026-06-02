<?php
$json = json_decode(file_get_contents('E-Invoice Collection.postman_collection.json'), true);
foreach ($json['item'] as $folder) {
    if ($folder['name'] === 'Transmitting') {
        foreach ($folder['item'] as $req) {
            if ($req['name'] === 'Transmit') {
                echo "Request URL: " . json_encode($req['request']['url']) . "\n";
                echo "Body: " . json_encode($req['request']['body'] ?? 'No Body', JSON_PRETTY_PRINT) . "\n";
            }
        }
    }
}
