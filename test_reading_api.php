<?php

// 测试文章阅读数量API的脚本

$baseUrl = 'http://localhost:8001';

echo "=== 测试文章阅读数量API ===\n\n";

// 1. 测试记录文章阅读
echo "1. 测试记录文章阅读...\n";
$readData = [
    'articleId' => 1,
    'userId' => 1,
    'durationSeconds' => 120,
    'isCompleted' => true,
    'deviceType' => 'mobile'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/official-api/article-read');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($readData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response: " . $response . "\n\n";

// 2. 测试获取文章阅读统计
echo "2. 测试获取文章阅读统计...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/official-api/article-read/statistics?articleId=1&statType=daily');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response: " . $response . "\n\n";

// 3. 测试获取热门文章
echo "3. 测试获取热门文章...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/official-api/article-read/popular?limit=5');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response: " . $response . "\n\n";

// 4. 测试获取文章列表（包含阅读数量）
echo "4. 测试获取文章列表（包含阅读数量）...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/official-api/news?page=1&limit=3');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response: " . $response . "\n\n";

echo "=== 测试完成 ===\n";
