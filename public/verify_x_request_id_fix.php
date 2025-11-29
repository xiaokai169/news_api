<?php
/**
 * 简单的X-Request-Id修复验证脚本
 */

header('Content-Type: application/json; charset=utf-8');

// 验证nelmio_cors.yaml
$nelmioConfig = file_get_contents(__DIR__ . '/../config/packages/nelmio_cors.yaml');
$nelmioHasXRequestId = strpos($nelmioConfig, 'X-Request-Id') !== false;

// 验证ProductionCorsSubscriber
$prodSubscriber = file_get_contents(__DIR__ . '/../src/EventSubscriber/ProductionCorsSubscriber.php');
$prodHasXRequestId = strpos($prodSubscriber, 'X-Request-Id') !== false;

// 验证ForceCorsSubscriber
$forceSubscriber = file_get_contents(__DIR__ . '/../src/EventSubscriber/ForceCorsSubscriber.php');
$forceHasXRequestId = strpos($forceSubscriber, 'X-Request-Id') !== false;

$result = [
    'timestamp' => date('Y-m-d H:i:s'),
    'fix_verification' => [
        'nelmio_cors_yaml' => [
            'has_x_request_id' => $nelmioHasXRequestId,
            'status' => $nelmioHasXRequestId ? '✅ 已修复' : '❌ 未修复'
        ],
        'production_subscriber' => [
            'has_x_request_id' => $prodHasXRequestId,
            'status' => $prodHasXRequestId ? '✅ 已修复' : '❌ 未修复'
        ],
        'force_subscriber' => [
            'has_x_request_id' => $forceHasXRequestId,
            'status' => $forceHasXRequestId ? '✅ 已修复' : '❌ 未修复'
        ]
    ],
    'overall_status' => ($nelmioHasXRequestId && $prodHasXRequestId && $forceHasXRequestId) ? '✅ 修复成功' : '❌ 修复失败',
    'recommendations' => [
        '清理Symfony缓存: php bin/console cache:clear',
        '重启Web服务器',
        '测试OPTIONS请求'
    ]
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
