<?php
$json = json_decode(file_get_contents('E-Invoice Collection.postman_collection.json'), true);

function scanItem($item, $parentName = '') {
    if (isset($item['request'])) {
        $method = $item['request']['method'];
        $url = $item['request']['url']['raw'] ?? '';
        echo "[$method] $url ($parentName -> {$item['name']})\n";
    }
    if (isset($item['item'])) {
        foreach ($item['item'] as $subItem) {
            scanItem($subItem, $parentName ? "$parentName -> {$item['name']}" : $item['name']);
        }
    }
}

foreach ($json['item'] as $item) {
    scanItem($item);
}
