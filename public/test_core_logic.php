<?php

echo "=== 测试核心data-src到src转换逻辑 ===\n\n";

// 模拟replaceUrlsInContent方法中的核心逻辑
function testReplaceUrlsInContent($content, $urlMapping) {
    foreach ($urlMapping as $oldUrl => $newUrl) {
        echo "处理URL映射: $oldUrl -> $newUrl\n";

        // 1. 替换img标签中的原始src属性
        $pattern = '/(<img[^>]+)src=["\']' . preg_quote($oldUrl, '/') . '["\']([^>]*>)/i';
        $content = preg_replace($pattern, '$1src="' . $newUrl . '"$2', $content, -1, $count);
        echo "步骤1 - src属性替换: $count 次替换\n";

        // 2. 将data-src属性替换为src属性（移除懒加载机制）
        $pattern = '/(<img[^>]+)data-src=["\']' . preg_quote($oldUrl, '/') . '["\']([^>]*>)/i';
        $content = preg_replace_callback($pattern, function($matches) use ($newUrl) {
            $imgTag = $matches[0];

            // 检查是否已经有src属性
            if (!preg_match('/\bsrc\s*=\s*["\'][^"\']*["\']/', $imgTag)) {
                // 没有src属性，将data-src转换为src
                $imgTag = preg_replace('/data-src=["\'][^"\']*["\']/', 'src="' . $newUrl . '"', $imgTag);
            } else {
                // 已经有src属性，只移除data-src
                $imgTag = preg_replace('/\s+data-src=["\'][^"\']*["\']/', '', $imgTag);
            }

            return $imgTag;
        }, $content);
        echo "步骤2 - data-src到src替换: 完成\n";
    }

    // 12. 清理所有剩余的data-src属性，将它们转换为src属性（在所有URL映射处理完成后执行）
    echo "执行通用清理步骤...\n";
    $content = preg_replace_callback('/(<img[^>]+)data-src=["\']([^"\']*)["\']([^>]*>)/i', function($matches) use ($urlMapping) {
        $imgTag = $matches[0];
        $dataSrcValue = $matches[2];

        echo "通用清理处理data-src: '$dataSrcValue'\n";

        // 如果data-src为空，直接移除
        if (empty($dataSrcValue)) {
            echo "  -> 空data-src，直接移除\n";
            return preg_replace('/\s*data-src=["\']["\']/', '', $imgTag);
        }

        // 检查是否已经有src属性（在当前img标签中）
        if (!preg_match('/\bsrc\s*=\s*["\'][^"\']*["\']/', $imgTag)) {
            // 将data-src转换为src，如果有映射则使用映射后的URL
            $finalSrc = isset($urlMapping[$dataSrcValue]) ? $urlMapping[$dataSrcValue] : $dataSrcValue;
            echo "  -> 转换为src: $finalSrc\n";
            $imgTag = preg_replace('/data-src=["\']' . preg_quote($dataSrcValue, '/') . '["\']/', 'src="' . $finalSrc . '"', $imgTag);
        } else {
            // 如果已经有src，则移除data-src
            echo "  -> 已有src，移除data-src\n";
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

    echo "输入: $content1\n";
    $result1 = testReplaceUrlsInContent($content1, $mapping1);
    echo "输出: $result1\n";

    $hasCorrectSrc = strpos($result1, 'src="https://obs.myhuaweicloud.com/processed/image.jpg"') !== false;
    $hasNoDataSrc = strpos($result1, 'data-src=') === false;
    echo "结果: " . ($hasCorrectSrc && $hasNoDataSrc ? "✅ 通过" : "❌ 失败") . "\n\n";

    echo "测试2: 混合src和data-src属性\n";
    $content2 = '<img src="https://example.com/old.jpg" data-src="https://mmbiz.qpic.cn/test.jpg" alt="test">';
    $mapping2 = ['https://mmbiz.qpic.cn/test.jpg' => 'https://obs.myhuaweicloud.com/processed/image.jpg'];

    echo "输入: $content2\n";
    $result2 = testReplaceUrlsInContent($content2, $mapping2);
    echo "输出: $result2\n";

    $hasOriginalSrc = strpos($result2, 'src="https://example.com/old.jpg"') !== false;
    $hasNoDataSrc = strpos($result2, 'data-src=') === false;
    echo "结果: " . ($hasOriginalSrc && $hasNoDataSrc ? "✅ 通过" : "❌ 失败") . "\n\n";

    echo "测试3: 多个data-src属性\n";
    $content3 = '<div>
        <img data-src="https://mmbiz.qpic.cn/image1.jpg" alt="image1">
        <img data-src="https://mmbiz.qpic.cn/image2.jpg" alt="image2">
    </div>';
    $mapping3 = [
        'https://mmbiz.qpic.cn/image1.jpg' => 'https://obs.myhuaweicloud.com/processed/image1.jpg',
        'https://mmbiz.qpic.cn/image2.jpg' => 'https://obs.myhuaweicloud.com/processed/image2.jpg'
    ];

    echo "输入包含多个data-src的HTML\n";
    $result3 = testReplaceUrlsInContent($content3, $mapping3);
    echo "输出: $result3\n";

    $hasImage1 = strpos($result3, 'src="https://obs.myhuaweicloud.com/processed/image1.jpg"') !== false;
    $hasImage2 = strpos($result3, 'src="https://obs.myhuaweicloud.com/processed/image2.jpg"') !== false;
    $hasNoDataSrc = strpos($result3, 'data-src=') === false;
    echo "结果: " . ($hasImage1 && $hasImage2 && $hasNoDataSrc ? "✅ 通过" : "❌ 失败") . "\n\n";

    echo "测试4: 只有data-src，没有对应的URL映射\n";
    $content4 = '<img data-src="https://mmbiz.qpic.cn/not-mapped.jpg" alt="test">';
    $mapping4 = [];

    echo "输入: $content4\n";
    $result4 = testReplaceUrlsInContent($content4, $mapping4);
    echo "输出: $result4\n";

    // 应该保持原始URL，但转换为src属性
    $hasOriginalSrc = strpos($result4, 'src="https://mmbiz.qpic.cn/not-mapped.jpg"') !== false;
    $hasNoDataSrc = strpos($result4, 'data-src=') === false;
    echo "结果: " . ($hasOriginalSrc && $hasNoDataSrc ? "✅ 通过" : "❌ 失败") . "\n\n";

    echo "测试5: 向后兼容性 - 只有src属性\n";
    $content5 = '<img src="https://example.com/image.jpg" alt="test">';
    $mapping5 = ['https://example.com/image.jpg' => 'https://obs.myhuaweicloud.com/processed/image.jpg'];

    echo "输入: $content5\n";
    $result5 = testReplaceUrlsInContent($content5, $mapping5);
    echo "输出: $result5\n";

    $hasCorrectSrc = strpos($result5, 'src="https://obs.myhuaweicloud.com/processed/image.jpg"') !== false;
    $hasNoDataSrc = strpos($result5, 'data-src=') === false;
    echo "结果: " . ($hasCorrectSrc && $hasNoDataSrc ? "✅ 通过" : "❌ 失败") . "\n\n";

    echo "测试6: 边界情况 - 空data-src属性\n";
    $content6 = '<img data-src="" alt="test">';
    $mapping6 = [];

    echo "输入: $content6\n";
    $result6 = testReplaceUrlsInContent($content6, $mapping6);
    echo "输出: $result6\n";

    $hasNoDataSrc = strpos($result6, 'data-src=') === false;
    echo "结果: " . ($hasNoDataSrc ? "✅ 正确移除空data-src" : "❌ 失败") . "\n\n";
}

runTests();
echo "=== 测试完成 ===\n";
