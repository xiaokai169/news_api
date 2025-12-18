<?php

// 简化的测试脚本，直接测试正则表达式逻辑

echo "=== 测试data-src到src转换功能 ===\n\n";

// 模拟replaceUrlsInContent方法中的核心逻辑
function testReplaceUrlsInContent($content, $urlMapping) {
    foreach ($urlMapping as $oldUrl => $newUrl) {
        // 1. 替换img标签中的src属性
        $pattern = '/(<img[^>]+)src=["\']' . preg_quote($oldUrl, '/') . '["\']([^>]*>)/i';
        $content = preg_replace($pattern, '$1src="' . $newUrl . '"$2', $content, -1, $count);

        // 2. 将data-src属性替换为src属性（移除懒加载机制）
        $pattern = '/(<img[^>]+)data-src=["\']' . preg_quote($oldUrl, '/') . '["\']([^>]*>)/i';
        $content = preg_replace($pattern, '$1src="' . $newUrl . '"$2', $content, -1, $count);

        // 3. 移除已替换的data-src属性，避免重复处理
        $pattern = '/(<img[^>]+)data-src=["\']' . preg_quote($oldUrl, '/') . '["\']([^>]*>)/i';
        $content = preg_replace_callback($pattern, function($matches) {
            $imgTag = $matches[0];
            // 移除已处理的data-src属性
            $imgTag = preg_replace('/\s+data-src=["\'][^"\']*["\']/', '', $imgTag);
            return $imgTag;
        }, $content);

    }

    // 12. 清理所有剩余的data-src属性，将它们转换为src属性（在所有URL映射处理完成后执行）
    $content = preg_replace_callback('/(<img[^>]+)data-src=["\']([^"\']*)["\']([^>]*>)/i', function($matches) use ($urlMapping) {
        $imgTag = $matches[0];
        $dataSrcValue = $matches[2];

        // 如果data-src为空，直接移除
        if (empty($dataSrcValue)) {
            return preg_replace('/\s*data-src=["\']["\']/', '', $imgTag);
        }

        // 检查是否已经有src属性
        if (!preg_match('/src=["\'][^"\']*["\']/', $imgTag)) {
            // 将data-src转换为src，如果有映射则使用映射后的URL
            $finalSrc = isset($urlMapping[$dataSrcValue]) ? $urlMapping[$dataSrcValue] : $dataSrcValue;
            $imgTag = preg_replace('/data-src=["\']' . preg_quote($dataSrcValue, '/') . '["\']/', 'src="' . $finalSrc . '"', $imgTag);
        } else {
            // 如果已经有src，则移除data-src
            $imgTag = preg_replace('/\s+data-src=["\'][^"\']*["\']/', '', $imgTag);
        }

        return $imgTag;
    }, $content);

    return $content;
}

// 测试用例
function runTests() {
    echo "测试1: 基本data-src转换\n";
    $content1 = '<img data-src="https://mmbiz.qpic.cn/test.jpg" alt="test">';
    $mapping1 = ['https://mmbiz.qpic.cn/test.jpg' => 'https://obs.myhuaweicloud.com/processed/image.jpg'];
    $result1 = testReplaceUrlsInContent($content1, $mapping1);
    echo "输入: $content1\n";
    echo "输出: $result1\n";
    echo "结果: " . (strpos($result1, 'src="https://obs.myhuaweicloud.com/processed/image.jpg"') !== false ? "✅ 通过" : "❌ 失败") . "\n\n";

    echo "测试2: 混合src和data-src属性\n";
    $content2 = '<img src="https://example.com/old.jpg" data-src="https://mmbiz.qpic.cn/test.jpg" alt="test">';
    $mapping2 = ['https://mmbiz.qpic.cn/test.jpg' => 'https://obs.myhuaweicloud.com/processed/image.jpg'];
    $result2 = testReplaceUrlsInContent($content2, $mapping2);
    echo "输入: $content2\n";
    echo "输出: $result2\n";

    $hasCorrectSrc = strpos($result2, 'src="https://obs.myhuaweicloud.com/processed/image.jpg"') !== false;
    $hasNoDataSrc = strpos($result2, 'data-src=') === false;
    echo "结果: " . ($hasCorrectSrc && $hasNoDataSrc ? "✅ 通过" : "❌ 失败") . "\n\n";

    echo "测试3: 多个data-src属性\n";
    $content3 = '<div>
        <img data-src="https://mmbiz.qpic.cn/image1.jpg" alt="image1">
        <img data-src="https://mmbiz.qpic.cn/image2.jpg" alt="image2">
    </div>';
    $mapping3 = [
        'https://mmbiz.qpic.cn/image1.jpg' => 'https://obs.myhuaweicloud.com/processed/image1.jpg',
        'https://mmbiz.qpic.cn/image2.jpg' => 'https://obs.myhuaweicloud.com/processed/image2.jpg'
    ];
    $result3 = testReplaceUrlsInContent($content3, $mapping3);
    echo "输入包含多个data-src的HTML\n";
    echo "输出: $result3\n";

    $hasImage1 = strpos($result3, 'src="https://obs.myhuaweicloud.com/processed/image1.jpg"') !== false;
    $hasImage2 = strpos($result3, 'src="https://obs.myhuaweicloud.com/processed/image2.jpg"') !== false;
    $hasNoDataSrc = strpos($result3, 'data-src=') === false;
    echo "结果: " . ($hasImage1 && $hasImage2 && $hasNoDataSrc ? "✅ 通过" : "❌ 失败") . "\n\n";

    echo "测试4: 只有data-src，没有对应的URL映射\n";
    $content4 = '<img data-src="https://mmbiz.qpic.cn/not-mapped.jpg" alt="test">';
    $mapping4 = ['https://mmbiz.qpic.cn/test.jpg' => 'https://obs.myhuaweicloud.com/processed/image.jpg'];
    $result4 = testReplaceUrlsInContent($content4, $mapping4);
    echo "输入: $content4\n";
    echo "输出: $result4\n";
    echo "结果: " . (strpos($result4, 'src="https://mmbiz.qpic.cn/not-mapped.jpg"') !== false ? "✅ 转换为src" : "❌ 未转换") . "\n\n";

    echo "测试5: 向后兼容性 - 原有src属性处理\n";
    $content5 = '<img src="https://example.com/image.jpg" alt="test">';
    $mapping5 = ['https://example.com/image.jpg' => 'https://obs.myhuaweicloud.com/processed/image.jpg'];
    $result5 = testReplaceUrlsInContent($content5, $mapping5);
    echo "输入: $content5\n";
    echo "输出: $result5\n";
    echo "结果: " . (strpos($result5, 'src="https://obs.myhuaweicloud.com/processed/image.jpg"') !== false ? "✅ 通过" : "❌ 失败") . "\n\n";

    echo "测试6: 边界情况 - 空data-src属性\n";
    $content6 = '<img data-src="" alt="test">';
    $mapping6 = [];
    $result6 = testReplaceUrlsInContent($content6, $mapping6);
    echo "输入: $content6\n";
    echo "输出: $result6\n";
    echo "结果: " . ($result6 === '<img alt="test">' ? "✅ 正确移除空data-src" : "❌ 处理不当") . "\n\n";
}

runTests();
echo "=== 测试完成 ===\n";
