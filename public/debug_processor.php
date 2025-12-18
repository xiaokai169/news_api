<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Service\MediaResourceProcessor;

// 创建处理器实例
$processor = new MediaResourceProcessor();

// 测试基本data-src转换
echo "=== 调试MediaResourceProcessor data-src转换 ===\n\n";

// 测试1: 基本data-src转换
$testHtml1 = '<img data-src="https://mmbiz.qpic.cn/test.jpg" alt="test">';
echo "测试1: 基本data-src转换\n";
echo "输入: $testHtml1\n";

$result1 = $processor->processMediaResources($testHtml1, MediaResourceProcessor::TYPE_WECHAT_ARTICLE);
echo "输出: $result1\n";

$expected1 = '<img src="https://obs.myhuaweicloud.com/processed/image.jpg" alt="test">';
echo "期望: $expected1\n";

$success1 = strpos($result1, 'src="https://obs.myhuaweicloud.com/processed/image.jpg"') !== false;
echo "结果: " . ($success1 ? "✅ 通过" : "❌ 失败") . "\n\n";

// 测试2: 检查是否有src属性被保留
$testHtml2 = '<img src="https://example.com/existing.jpg" data-src="https://mmbiz.qpic.cn/test.jpg" alt="test">';
echo "测试2: 混合src和data-src属性\n";
echo "输入: $testHtml2\n";

$result2 = $processor->processMediaResources($testHtml2, MediaResourceProcessor::TYPE_WECHAT_ARTICLE);
echo "输出: $result2\n";

$hasOriginalSrc = strpos($result2, 'src="https://example.com/existing.jpg"') !== false;
$hasNoDataSrc = strpos($result2, 'data-src=') === false;
echo "结果: " . ($hasOriginalSrc && $hasNoDataSrc ? "✅ 通过" : "❌ 失败") . "\n\n";

// 测试3: 只有src，没有data-src
$testHtml3 = '<img src="https://example.com/image.jpg" alt="test">';
echo "测试3: 向后兼容性 - 只有src属性\n";
echo "输入: $testHtml3\n";

$result3 = $processor->processMediaResources($testHtml3, MediaResourceProcessor::TYPE_WECHAT_ARTICLE);
echo "输出: $result3\n";

$hasProcessedSrc = strpos($result3, 'src="https://obs.myhuaweicloud.com/processed/image.jpg"') !== false;
echo "结果: " . ($hasProcessedSrc ? "✅ 通过" : "❌ 失败") . "\n\n";

// 测试4: 空data-src
$testHtml4 = '<img data-src="" alt="test">';
echo "测试4: 空data-src属性\n";
echo "输入: $testHtml4\n";

$result4 = $processor->processMediaResources($testHtml4, MediaResourceProcessor::TYPE_WECHAT_ARTICLE);
echo "输出: $result4\n";

$hasNoDataSrc4 = strpos($result4, 'data-src=') === false;
echo "结果: " . ($hasNoDataSrc4 ? "✅ 通过" : "❌ 失败") . "\n\n";

echo "=== 调试完成 ===\n";
