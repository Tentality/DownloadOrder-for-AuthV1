<?php

// Check command line arguments count
if ($argc != 4) {
    echo "📌 Usage: php DownloadOrder_V1_EN.php <GameID> <Version> <SerialNumber>\n";
    exit(1);
}

$game_id = $argv[1];
$version = $argv[2];
$serial = $argv[3];

// Validation rules
$validation_rules = [
    'game_id' => [
        'regex' => '/^[A-Z0-9]{4}$/',
        'error' => "❌ Invalid Game ID format\n"
    ],
    'version' => [
        'regex' => '/^\d+(\.\d+)?$/',
        'error' => "❌ Invalid Version format\n"
    ],
    'serial' => [
        'regex' => '/^[A-Z0-9]{11}$/',
        'error' => "❌ Invalid Serial Number format\n"
    ]
];

// Validate parameters
foreach ($validation_rules as $param => $rule) {
    if (!preg_match($rule['regex'], $$param)) exit($rule['error']);
}

// Build request data
$request_data = http_build_query([
    'game_id' => $game_id,
    'ver' => $version,
    'serial' => $serial
]);

// Compress data
$compressed_data = base64_encode(gzcompress($request_data));

// Configure HTTP request
$options = [
    'http' => [
        'header' => implode("\r\n", [
            'Pragma: DFI',
            'User-Agent: ALL.Net',
            'Content-Type: application/octet-stream',
            'Content-Length: ' . strlen($compressed_data)
        ]),
        'method' => 'POST',
        'content' => $compressed_data,
        'ignore_errors' => true
    ]
];

try {
    $context = stream_context_create($options);
    $result = @file_get_contents('http://naominet.jp/sys/servlet/DownloadOrder', false, $context);

    // Check HTTP status
    if (!str_contains($http_response_header[0], '200')) {
        $status_line = $http_response_header[0] ?? 'Unknown HTTP Error';
        throw new Exception("HTTP Error: " . explode(' ', $status_line, 3)[1]);
    }

    if ($result === false) throw new Exception("❌ Request failed");

    // Base64 decode
    $decoded_result = base64_decode($result, true);
    if ($decoded_result === false) throw new Exception("❌ Base64 decode failed");

    // Decompress
    $decoded_data = gzuncompress($decoded_result);
    if ($decoded_data === false) throw new Exception("❌ Decompression failed");

    parse_response($decoded_data, $game_id, $version, $serial);

} catch (Exception $e) {
    echo "\n🔴 Error: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Parse server response
 */
function parse_response($response, $game_id, $version, $serial) {
    parse_str($response, $parsed);

    $divider = "▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅";
    
    // Request Parameters
    echo "$divider\n【Request Parameters】\n";
    echo str_pad("GameID", 8, ' ') . ": $game_id\n";
    echo str_pad("Version", 8, ' ') . ": $version\n";
    echo str_pad("Serial", 8, ' ') . ": $serial\n$divider\n\n";

    // Status
    $status_icon = $parsed['stat'] == 1 ? "✔" : "✘";
    echo "$status_icon Status : " . ($parsed['stat'] == 1 ? "Success" : "Failed") . " (Code {$parsed['stat']})\n\n";

    // Linked Serials
    if (!empty($parsed['serial'])) {
        echo "【Linked Serials】\n";
        foreach (explode(',', $parsed['serial']) as $s) {
            echo "‣ $s\n";
        }
        echo "\n";
    }

    // Download Links
    $uris = !empty($parsed['uri']) ? array_values(array_filter(explode('|', $parsed['uri']))) : [];
    echo "【Download Links】\n";
    if (!empty($uris)) {
        foreach ($uris as $i => $uri) {
            $number = $i + 1;
            echo "▸ {$number}. $uri\n";
        }
    } else {
        echo "▸ No available links\n";
    }
    echo $divider . "\n";
}
