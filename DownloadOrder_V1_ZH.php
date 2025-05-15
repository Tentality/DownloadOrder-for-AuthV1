<<?php

// 檢查命令列參數數量
if ($argc != 4) {
    echo "📌 用法: php DownloadOrder_V1_ZH.php <遊戲ID> <版本> <序列號>\n";
    exit(1);
}

$game_id = $argv[1];
$ver = $argv[2];
$serial = $argv[3];

// 驗證參數
$validate = [
    'game_id' => ['/^[A-Z0-9]{4}$/', "❌ 遊戲ID格式錯誤\n"],
    'ver' => ['/^\d+(\.\d+)?$/', "❌ 版本格式錯誤\n"],
    'serial' => ['/^[A-Z0-9]{11}$/', "❌ 序列號格式錯誤\n"]
];
foreach ($validate as $key => [$regex, $error]) {
    if (!preg_match($regex, $$key)) exit($error);
}

// 構建請求
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
    if ($result === false) throw new Exception("❌ 請求失敗");

    $decoded = gzuncompress(base64_decode($result));
    if ($decoded === false) throw new Exception("❌ 解壓失敗");

    parseResponse($decoded, $game_id, $ver, $serial);

} catch (Exception $e) {
    echo "\n🔴 錯誤: " . $e->getMessage() . "\n";
    exit(1);
}

function parseResponse($response, $game_id, $ver, $serial) {
    parse_str($response, $parsed);

    // 請求參數
    echo "======================================\n";
    echo "請求參數⬇️: \n";
    echo "遊戲ID=$game_id, 版本=$ver, 序列號=$serial\n";
    echo "======================================\n";

    // 狀態碼
    $status = isset($parsed['stat']) && $parsed['stat'] == 1 ? "請求成功✅" : "請求失敗❌";
    echo "狀態回報: $status\n";

    // 關聯序列號
    if (!empty($parsed['serial'])) {
        $serials = implode(', ', explode(',', $parsed['serial']));
        echo " 關聯序列號:\n";
        echo "      ➤ $serials\n";
    }

    // 下載連結
    if (!empty($parsed['uri'])) {
        $uris = array_filter(explode('|', $parsed['uri']));
        echo "======================================\n";
        echo " 下載連結:\n";
        foreach ($uris as $i => $uri) {
            echo "      ➤ " . ($i + 1) . ". $uri\n";
        }
    } else {
        echo "======================================\n";
        echo " 下載連結:\n      ➤ 暫無可用連結\n";
    }
    echo "======================================\n";
}