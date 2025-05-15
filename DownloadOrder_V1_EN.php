<?php

// Check command-line arguments
if ($argc != 4) {
    echo "📌 Usage: php DownloadOrder_V1_EN.php <GameID> <Version> <Serial>\n";
    exit(1);
}

$game_id = $argv[1];
$ver = $argv[2];
$serial = $argv[3];

// Validate parameters
$validate = [
    'game_id' => ['/^[A-Z0-9]{4}$/', "❌ Invalid Game ID format\n"],
    'ver' => ['/^\d+(\.\d+)?$/', "❌ Invalid Version format\n"],
    'serial' => ['/^[A-Z0-9]{11}$/', "❌ Invalid Serial format\n"]
];
foreach ($validate as $key => [$regex, $error]) {
    if (!preg_match($regex, $$key)) exit($error);
}

// Build request payload
$request_data = "game_id=$game_id&ver=$ver&serial=$serial";
$compressed_data = base64_encode(gzdeflate($request_data, -1, ZLIB_ENCODING_DEFLATE));
$options = [
    'http' => [
        'header' => "Pragma: DFI\r\nUser-Agent: ALL.Net\r\nContent-Type: application/octet-stream\r\nContent-Length: " . strlen($compressed_data),
        'method' => 'POST',
        'content' => $compressed_data
    ]
];

try {
    $result = file_get_contents('http://naominet.jp/sys/servlet/DownloadOrder', false, stream_context_create($options));
    if ($result === false) throw new Exception("❌ Request failed");

    $decoded = gzuncompress(base64_decode($result));
    if ($decoded === false) throw new Exception("❌ Decompression failed");

    parseResponse($decoded, $game_id, $ver, $serial);

} catch (Exception $e) {
    echo "\n🔴 Error: " . $e->getMessage() . "\n";
    exit(1);
}

function parseResponse($response, $game_id, $ver, $serial) {
    parse_str($response, $parsed);

    // Request parameters
    echo "======================================\n";
    echo "Request Parameters⬇️: \n";
    echo "Game ID=$game_id, Version=$ver, Serial=$serial\n";
    echo "======================================\n";

    // Status
    $status = isset($parsed['stat']) && $parsed['stat'] == 1 ? "Request Successful✅" : "Request Failed❌";
    echo "Status Report: $status\n";

    // Related serial numbers
    if (!empty($parsed['serial'])) {
        $serials = implode(', ', explode(',', $parsed['serial']));
        echo " Related Serial Numbers:\n";
        echo "      ➤ $serials\n";
    }

    // Download links
    if (!empty($parsed['uri'])) {
        $uris = array_filter(explode('|', $parsed['uri']));
        echo "======================================\n";
        echo " Download Links:\n";
        foreach ($uris as $i => $uri) {
            echo "      ➤ " . ($i + 1) . ". $uri\n";
        }
    } else {
        echo "======================================\n";
        echo " Download Links:\n      ➤ No available links\n";
    }
    echo "======================================\n";
}