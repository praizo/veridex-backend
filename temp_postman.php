<?php
$json = json_decode(file_get_contents('E-Invoice Collection.postman_collection.json'), true);
foreach ($json['item'] as $folder) {
    if ($folder['name'] === 'Invoice') {
        foreach ($folder['item'] as $req) {
            if ($req['name'] === 'SignInvoice') {
                echo json_encode($req['request']['body'], JSON_PRETTY_PRINT);
            }
        }
    }
}
