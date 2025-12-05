<?php

echo "=== 最近的错误日志检查 ===<br>\n";
echo "检查时间: " . date('Y-m-d H:i:s') . "<br>\n";

$logFile = __DIR__ . '/../var/log/dev.log';

if (!file_exists($logFile)) {
    echo "❌ 日志文件不存在: {$logFile}<br>\n";
    exit;
}

$fileSize = filesize($logFile);
echo "📊 日志文件大小: " . round($fileSize / 1024 / 1024, 2) . " MB<br>\n";

// 读取最后1000行
$lines = [];
$handle = fopen($logFile, 'r');
if ($handle) {
    // 移动到文件末尾
    fseek($handle, -10240, SEEK_END); // 读取最后10KB
    while (!feof($handle)) {
        $line = fgets($handle);
        if ($line !== false) {
            $lines[] = trim($line);
        }
    }
    fclose($handle);
}

echo "📊 读取了 " . count($lines) . " 行日志<br>\n";

// 分析错误
$recentErrors = [];
$recentWarnings = [];
$wechatErrors = [];
$apiErrors = [];

foreach ($lines as $line) {
    // 检查ERROR级别日志
    if (strpos($line, 'ERROR') !== false) {
        $recentErrors[] = $line;
    }

    // 检查WARNING级别日志
    if (strpos($line, 'WARNING') !== false) {
        $recentWarnings[] = $line;
    }

    // 检查微信相关错误
    if (strpos($line, 'wechat') !== false && (strpos($line, 'ERROR') !== false || strpos($line, '失败') !== false)) {
        $wechatErrors[] = $line;
    }

    // 检查API相关错误
    if (strpos($line, 'API') !== false && strpos($line, 'ERROR') !== false) {
        $apiErrors[] = $line;
    }
}

echo "<h2>错误统计</h2>\n";
echo "- ERROR级别: " . count($recentErrors) . " 条<br>\n";
echo "- WARNING级别: " . count($recentWarnings) . " 条<br>\n";
echo "- 微信相关错误: " . count($wechatErrors) . " 条<br>\n";
echo "- API相关错误: " . count($apiErrors) . " 条<br>\n";

// 显示最近的错误
if (!empty($recentErrors)) {
    echo "<h2>最近的ERROR日志 (最新10条)</h2>\n";
    $displayErrors = array_slice(array_reverse($recentErrors), 0, 10);
    foreach ($displayErrors as $error) {
        $error = htmlspecialchars($error);
        echo "❌ {$error}<br>\n";
    }
}

if (!empty($wechatErrors)) {
    echo "<h2>微信相关错误 (最新5条)</h2>\n";
    $displayWechatErrors = array_slice(array_reverse($wechatErrors), 0, 5);
    foreach ($displayWechatErrors as $error) {
        $error = htmlspecialchars($error);
        echo "🔴 {$error}<br>\n";
    }
}

if (!empty($apiErrors)) {
    echo "<h2>API相关错误 (最新5条)</h2>\n";
    $displayApiErrors = array_slice(array_reverse($apiErrors), 0, 5);
    foreach ($displayApiErrors as $error) {
        $error = htmlspecialchars($error);
        echo "🔴 {$error}<br>\n";
    }
}

// 检查分布式锁相关
$lockErrors = [];
foreach ($lines as $line) {
    if (strpos($line, 'lock') !== false && strpos($line, 'ERROR') !== false) {
        $lockErrors[] = $line;
    }
}

if (!empty($lockErrors)) {
    echo "<h2>分布式锁相关错误 (最新3条)</h2>\n";
    $displayLockErrors = array_slice(array_reverse($lockErrors), 0, 3);
    foreach ($displayLockErrors as $error) {
        $error = htmlspecialchars($error);
        echo "🔒 {$error}<br>\n";
    }
}

echo "<br>=== 检查完成 ===<br>\n";
