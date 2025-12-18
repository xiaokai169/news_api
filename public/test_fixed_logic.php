<?php

// 模拟MediaResourceProcessor的完整逻辑
class MockMediaResourceProcessorFixed
{
    private $logger;

    public function __construct()
    {
        $this->logger = new class {
            public function debug($message, $context = []) {
                echo "DEBUG: $message\n";
                if (!empty($context)) {
                    echo "  Context: " . json_encode($context, JSON_UNESCAPED_UNICODE) . "\n";
                }
            }

            public function info($message, $context = []) {
                echo "INFO: $message\n";
                if (!empty($context)) {
                    echo "  Context: " . json_encode($context, JSON_UNESCAPED_UNICODE) . "\n";
                }
            }
        };
    }

    /**
     * 替换内容中的URL（修复后的版本）
     */
    public function replaceUrlsInContent(string $content, array $urlMapping): string
    {
        $this->logger->info('开始替换内容中的URL', ['mapping_count' => count($urlMapping)]);

        foreach ($urlMapping as $oldUrl => $newUrl) {
            // 标准化URL（移除显式443端口等）
            $normalizedOldUrl = $this->normalizeUrl($oldUrl);
            $normalizedNewUrl = $this->normalizeUrl($newUrl);

            $this->logger->debug('替换URL', [
                'old_url' => $oldUrl,
                'new_url' => $newUrl,
                'normalized_old_url' => $normalizedOldUrl,
                'normalized_new_url' => $normalizedNewUrl
            ]);

            $replacements = 0;

            // 1. 替换img标签中的原始src属性（确保只匹配独立的src属性）
            $pattern = '/(<img[^>]+)(?<!data-)src=["\']' . preg_quote($oldUrl, '/') . '["\']([^>]*>)/i';
            $content = preg_replace($pattern, '$1src="' . $newUrl . '"$2', $content, -1, $count);
            $replacements += $count;
            echo "步骤1 - src属性替换: $count 次替换\n";

            // 2. 将data-src属性替换为src属性（移除懒加载机制）- 修复版本
            $pattern = '/(<img[^>]+)data-src=["\']' . preg_quote($oldUrl, '/') . '["\']([^>]*>)/i';
            $content = preg_replace_callback($pattern, function($matches) use ($newUrl, $oldUrl) {
                $imgTag = $matches[0];

                // 检查是否已经有src属性（确保不匹配data-src）
                if (!preg_match('/(?<!data-)\bsrc\s*=\s*["\'][^"\']*["\']/', $imgTag)) {
                    // 没有src属性，将data-src转换为src
                    $imgTag = preg_replace('/data-src=["\']' . preg_quote($oldUrl, '/') . '["\']/', 'src="' . $newUrl . '"', $imgTag);
                } else {
                    // 已经有src属性，只移除data-src
                    $imgTag = preg_replace('/\s+data-src=["\']' . preg_quote($oldUrl, '/') . '["\']/', '', $imgTag);
                }

                return $imgTag;
            }, $content, -1, $count);
            $replacements += $count;
            echo "步骤2 - data-src到src转换: 完成\n";

            // 4. 替换背景图片URL
            $pattern = '/background-image:\s*url\(["\']?' . preg_quote($oldUrl, '/') . '["\']?\)/i';
            $content = preg_replace($pattern, 'background-image: url("' . $newUrl . '")', $content, -1, $count);
            $replacements += $count;

            // 5. 替换background属性中的URL
            $pattern = '/background:\s*[^;]*url\(["\']?' . preg_quote($oldUrl, '/') . '["\']?\)/i';
            $content = preg_replace($pattern, 'background: url("' . $newUrl . '")', $content, -1, $count);
            $replacements += $count;

            // 6. 替换video标签中的src属性
            $pattern = '/(<video[^>]+)src=["\']' . preg_quote($oldUrl, '/') . '["\']([^>]*>)/i';
            $content = preg_replace($pattern, '$1src="' . $newUrl . '"$2', $content, -1, $count);
            $replacements += $count;

            // 7. 替换video标签中的poster属性
            $pattern = '/(<video[^>]+)poster=["\']' . preg_quote($oldUrl, '/') . '["\']([^>]*>)/i';
            $content = preg_replace($pattern, '$1poster="' . $newUrl . '"$2', $content, -1, $count);
            $replacements += $count;

            // 8. 替换source标签中的src属性
            $pattern = '/(<source[^>]+)src=["\']' . preg_quote($oldUrl, '/') . '["\']([^>]*>)/i';
            $content = preg_replace($pattern, '$1src="' . $newUrl . '"$2', $content, -1, $count);
            $replacements += $count;

            // 9. 处理标准化的URL替换（处理端口标准化后的URL）
            if ($normalizedOldUrl !== $oldUrl) {
                $pattern = '/(?<!data-)src=["\']' . preg_quote($normalizedOldUrl, '/') . '["\']/i';
                $content = preg_replace($pattern, 'src="' . $newUrl . '"', $content, -1, $count);
                $replacements += $count;

                // 将标准化的data-src替换为src
                $pattern = '/(<img[^>]+)data-src=["\']' . preg_quote($normalizedOldUrl, '/') . '["\']([^>]*>)/i';
                $content = preg_replace_callback($pattern, function($matches) use ($newUrl, $normalizedOldUrl) {
                    $imgTag = $matches[0];

                    // 检查是否已经有src属性（确保不匹配data-src）
                    if (!preg_match('/(?<!data-)\bsrc\s*=\s*["\'][^"\']*["\']/', $imgTag)) {
                        // 没有src属性，将data-src转换为src
                        $imgTag = preg_replace('/data-src=["\']' . preg_quote($normalizedOldUrl, '/') . '["\']/', 'src="' . $newUrl . '"', $imgTag);
                    } else {
                        // 已经有src属性，只移除data-src
                        $imgTag = preg_replace('/\s+data-src=["\']' . preg_quote($normalizedOldUrl, '/') . '["\']/', '', $imgTag);
                    }

                    return $imgTag;
                }, $content, -1, $count);
                $replacements += $count;
            }

            // 10. 处理URL编码的情况
            $encodedOldUrl = urlencode($oldUrl);
            if ($encodedOldUrl !== $oldUrl) {
                $pattern = '/(?<!data-)src=["\']' . preg_quote($encodedOldUrl, '/') . '["\']/i';
                $content = preg_replace($pattern, 'src="' . $newUrl . '"', $content, -1, $count);
                $replacements += $count;

                // 将URL编码的data-src替换为src
                $pattern = '/(<img[^>]+)data-src=["\']' . preg_quote($encodedOldUrl, '/') . '["\']([^>]*>)/i';
                $content = preg_replace_callback($pattern, function($matches) use ($newUrl, $encodedOldUrl) {
                    $imgTag = $matches[0];

                    // 检查是否已经有src属性（确保不匹配data-src）
                    if (!preg_match('/(?<!data-)\bsrc\s*=\s*["\'][^"\']*["\']/', $imgTag)) {
                        // 没有src属性，将data-src转换为src
                        $imgTag = preg_replace('/data-src=["\']' . preg_quote($encodedOldUrl, '/') . '["\']/', 'src="' . $newUrl . '"', $imgTag);
                    } else {
                        // 已经有src属性，只移除data-src
                        $imgTag = preg_replace('/\s+data-src=["\']' . preg_quote($encodedOldUrl, '/') . '["\']/', '', $imgTag);
                    }

                    return $imgTag;
                }, $content, -1, $count);
                $replacements += $count;
            }

            // 11. 最后的通用替换（作为兜底）
            $contentBefore = $content;
            $content = str_replace($oldUrl, $newUrl, $content);
            if ($contentBefore !== $content) {
                $replacements += substr_count($contentBefore, $oldUrl) - substr_count($content, $oldUrl);
            }

            $this->logger->debug('URL替换完成', [
                'old_url' => $oldUrl,
                'new_url' => $newUrl,
                'total_replacements' => $replacements
            ]);
        }

        // 12. 清理所有剩余的data-src属性，将它们转换为src属性（在所有URL映射处理完成后执行）
        $content = preg_replace_callback('/(<img[^>]+)data-src=["\']([^"\']*)["\']([^>]*>)/i', function($matches) use ($urlMapping) {
            $imgTag = $matches[0];
            $dataSrcValue = $matches[2];

            // 如果data-src为空，直接移除
            if (empty($dataSrcValue)) {
                return preg_replace('/\s*data-src=["\']["\']/', '', $imgTag);
            }

            // 检查是否已经有src属性（确保不匹配data-src）
            if (!preg_match('/(?<!data-)\bsrc\s*=\s*["\'][^"\']*["\']/', $imgTag)) {
                // 将data-src转换为src，如果有映射则使用映射后的URL
                $finalSrc = isset($urlMapping[$dataSrcValue]) ? $urlMapping[$dataSrcValue] : $dataSrcValue;
                $imgTag = preg_replace('/data-src=["\']' . preg_quote($dataSrcValue, '/') . '["\']/', 'src="' . $finalSrc . '"', $imgTag);
            } else {
                // 如果已经有src，则移除data-src
                $imgTag = preg_replace('/\s+data-src=["\'][^"\']*["\']/', '', $imgTag);
            }

            return $imgTag;
        }, $content);

        $this->logger->info('所有URL替换完成');
        return $content;
    }

    /**
     * 标准化URL
     */
    private function normalizeUrl(string $url): string
    {
        $parsedUrl = parse_url($url);
        if (!$parsedUrl || !isset($parsedUrl['scheme']) || !isset($parsedUrl['host'])) {
            return $url;
        }

        $scheme = $parsedUrl['scheme'];
        $host = $parsedUrl['host'];
        $port = $parsedUrl['port'] ?? null;
        $path = $parsedUrl['path'] ?? '';
        $query = $parsedUrl['query'] ?? '';
        $fragment = $parsedUrl['fragment'] ?? '';

        // 移除显式的443端口（HTTPS默认端口）
        if ($port === 443 && $scheme === 'https') {
            $port = null;
        }

        // 移除显式的80端口（HTTP默认端口）
        if ($port === 80 && $scheme === 'http') {
            $port = null;
        }

        // 重建URL
        $normalizedUrl = $scheme . '://' . $host;

        if ($port) {
            $normalizedUrl .= ':' . $port;
        }

        $normalizedUrl .= $path;

        if ($query) {
            $normalizedUrl .= '?' . $query;
        }

        if ($fragment) {
            $normalizedUrl .= '#' . $fragment;
        }

        return $normalizedUrl;
    }
}

// 测试用例
$processor = new MockMediaResourceProcessorFixed();

echo "=== 测试修复后的data-src到src转换逻辑 ===\n\n";

// 测试1: 基本data-src转换
echo "测试1: 基本data-src转换\n";
$input1 = '<img data-src="https://mmbiz.qpic.cn/test.jpg" alt="test">';
$urlMapping1 = [
    'https://mmbiz.qpic.cn/test.jpg' => 'https://obs.myhuaweicloud.com/processed/image.jpg'
];
$output1 = $processor->replaceUrlsInContent($input1, $urlMapping1);
echo "输入: $input1\n";
echo "输出: $output1\n";
$expected1 = '<img src="https://obs.myhuaweicloud.com/processed/image.jpg" alt="test">';
echo "预期: $expected1\n";
echo "结果: " . ($output1 === $expected1 ? "✅ 通过" : "❌ 失败") . "\n\n";

// 测试2: 混合src和data-src属性
echo "测试2: 混合src和data-src属性\n";
$input2 = '<img src="https://example.com/old.jpg" data-src="https://mmbiz.qpic.cn/test.jpg" alt="test">';
$urlMapping2 = [
    'https://mmbiz.qpic.cn/test.jpg' => 'https://obs.myhuaweicloud.com/processed/image.jpg'
];
$output2 = $processor->replaceUrlsInContent($input2, $urlMapping2);
echo "输入: $input2\n";
echo "输出: $output2\n";
$expected2 = '<img src="https://example.com/old.jpg" alt="test">';
echo "预期: $expected2\n";
echo "结果: " . ($output2 === $expected2 ? "✅ 通过" : "❌ 失败") . "\n\n";

// 测试3: 多个data-src属性
echo "测试3: 多个data-src属性\n";
$input3 = '<div>
        <img data-src="https://mmbiz.qpic.cn/image1.jpg" alt="image1">
        <img data-src="https://mmbiz.qpic.cn/image2.jpg" alt="image2">
    </div>';
$urlMapping3 = [
    'https://mmbiz.qpic.cn/image1.jpg' => 'https://obs.myhuaweicloud.com/processed/image1.jpg',
    'https://mmbiz.qpic.cn/image2.jpg' => 'https://obs.myhuaweicloud.com/processed/image2.jpg'
];
$output3 = $processor->replaceUrlsInContent($input3, $urlMapping3);
echo "输入包含多个data-src的HTML\n";
echo "输出: $output3\n";
$expected3 = '<div>
        <img src="https://obs.myhuaweicloud.com/processed/image1.jpg" alt="image1">
        <img src="https://obs.myhuaweicloud.com/processed/image2.jpg" alt="image2">
    </div>';
echo "预期: $expected3\n";
echo "结果: " . ($output3 === $expected3 ? "✅ 通过" : "❌ 失败") . "\n\n";

// 测试4: 只有data-src，没有对应的URL映射
echo "测试4: 只有data-src，没有对应的URL映射\n";
$input4 = '<img data-src="https://mmbiz.qpic.cn/not-mapped.jpg" alt="test">';
$urlMapping4 = [];
$output4 = $processor->replaceUrlsInContent($input4, $urlMapping4);
echo "输入: $input4\n";
echo "输出: $output4\n";
$expected4 = '<img src="https://mmbiz.qpic.cn/not-mapped.jpg" alt="test">';
echo "预期: $expected4\n";
echo "结果: " . ($output4 === $expected4 ? "✅ 通过" : "❌ 失败") . "\n\n";

// 测试5: 向后兼容性 - 只有src属性
echo "测试5: 向后兼容性 - 只有src属性\n";
$input5 = '<img src="https://example.com/image.jpg" alt="test">';
$urlMapping5 = [
    'https://example.com/image.jpg' => 'https://obs.myhuaweicloud.com/processed/image.jpg'
];
$output5 = $processor->replaceUrlsInContent($input5, $urlMapping5);
echo "输入: $input5\n";
echo "输出: $output5\n";
$expected5 = '<img src="https://obs.myhuaweicloud.com/processed/image.jpg" alt="test">';
echo "预期: $expected5\n";
echo "结果: " . ($output5 === $expected5 ? "✅ 通过" : "❌ 失败") . "\n\n";

// 测试6: 边界情况 - 空data-src属性
echo "测试6: 边界情况 - 空data-src属性\n";
$input6 = '<img data-src="" alt="test">';
$urlMapping6 = [];
$output6 = $processor->replaceUrlsInContent($input6, $urlMapping6);
echo "输入: $input6\n";
echo "输出: $output6\n";
$expected6 = '<img alt="test">';
echo "预期: $expected6\n";
echo "结果: " . ($output6 === $expected6 ? "✅ 正确移除空data-src" : "❌ 失败") . "\n\n";

echo "=== 测试完成 ===\n";
