<?php

// 檢查命令列參數數量
if ($argc != 4) {
    echo "📌 用法: php DownloadOrder_V1_ZH.php <遊戲ID> <版本> <序列號>\n";
    exit(1);
}

$game_id = $argv[1];
$version = $argv[2];  
$serial = $argv[3];

// 參數驗證規則
$validation_rules = [
    'game_id' => [
        'regex' => '/^[A-Z0-9]{4}$/',
        'error' => "❌ 遊戲ID格式錯誤\n"
    ],
    'version' => [
        'regex' => '/^\d+(\.\d+)?$/',
        'error' => "❌ 版本格式錯誤\n"
    ],
    'serial' => [
        'regex' => '/^[A-Z0-9]{11}$/',
        'error' => "❌ 序列號格式錯誤\n"
    ]
];

// 執行參數驗證
foreach ($validation_rules as $param => $rule) {
    if (!preg_match($rule['regex'], $$param)) {
        exit($rule['error']);
    }
}

// 構建請求數據 (改用 http_build_query)
$request_data = http_build_query([
    'game_id' => $game_id,
    'ver' => $version,
    'serial' => $serial
]);

// 壓縮數據 (使用 zlib 格式)
$compressed_data = base64_encode(gzcompress($request_data));

// 配置 HTTP 請求
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
    
    // 檢查 HTTP 狀態碼
    if (!str_contains($http_response_header[0], '200')) {
        $status_line = $http_response_header[0] ?? 'Unknown HTTP Error';
        throw new Exception("HTTP 錯誤: " . explode(' ', $status_line, 3)[1]);
    }

    if ($result === false) {
        throw new Exception("❌ 請求失敗");
    }

    // Base64 解碼驗證
    $decoded_result = base64_decode($result, true);
    if ($decoded_result === false) {
        throw new Exception("❌ Base64 解碼失敗");
    }

    // 解壓縮數據
    $decoded_data = gzuncompress($decoded_result);
    if ($decoded_data === false) {
        throw new Exception("❌ 解壓失敗");
    }

    parse_response($decoded_data, $game_id, $version, $serial);

} catch (Exception $e) {
    echo "\n🔴 錯誤: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * 解析伺服器響應
 */
function parse_response($response, $game_id, $version, $serial) {
    parse_str($response, $parsed);

    // 自定義分隔線
    $divider = "▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅";
    
    // 輸出請求參數
    echo "$divider\n【請求參數】\n";
    echo str_pad("遊戲ID", 8, ' ') . ": $game_id\n";
    echo str_pad("版本", 8, ' ') . ": $version\n";
    echo str_pad("序列號", 8, ' ') . ": $serial\n$divider\n\n";

    // 狀態顯示
    $status_icon = $parsed['stat'] == 1 ? "✔" : "✘";
    echo "$status_icon 狀態 : " . ($parsed['stat'] == 1 ? "請求成功" : "請求失敗") . " (代碼 {$parsed['stat']})\n\n";

    // 關聯序列號
    if (!empty($parsed['serial'])) {
        echo "【關聯序列號】\n";
        foreach (explode(',', $parsed['serial']) as $s) {
            echo "‣ $s\n";
        }
        echo "\n";
    }

    // 下載連結
    $uris = !empty($parsed['uri']) ? array_values(array_filter(explode('|', $parsed['uri']))) : [];
    echo "【下載資源】\n";
    if (!empty($uris)) {
        foreach ($uris as $i => $uri) {
            $number = $i + 1; 
            echo "▸ {$number}. $uri\n"; 
        }
    } else {
        echo "⓪ 暫無可用連結\n";
    }
    echo $divider . "\n";
}
