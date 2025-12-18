<?php

// 逐步调试MediaResourceProcessor的逻辑
class DebugStepByStep
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
        };
    }

    public function debugProcess(string $content, array $urlMapping): string
    {
        echo "=== 逐步调试开始 ===\n";
        echo "初始内容: $content\n\n";

        foreach ($urlMapping as $oldUrl => $newUrl) {
            echo "--- 处理URL映射: $oldUrl -> $newUrl ---\n";

            $replacements = 0;

            // 步骤1: 替换img标签中的原始src属性（确保只匹配独立的src属性）
            echo "步骤1: 替换src属性\n";
            $pattern = '/(<img[^>]+)(?<!data-)src=["\']' . preg_quote($oldUrl, '/') . '["\']([^>]*>)/i';
            $content = preg_replace($pattern, '$1src="' . $newUrl . '"$2', $content, -1, $count);
            $replacements += $count;
            echo "  替换次数: $count\n";
            echo "  内容: $content\n";

            // 步骤2: 将data-src属性替换为src属性
            echo "步骤2: 替换data-src属性\n";
            $pattern = '/(<img[^>]+)data-src=["\']' . preg_quote($oldUrl, '/') . '["\']([^>]*>)/i';
            $content = preg_replace_callback($pattern, function($matches) use ($newUrl) {
                $imgTag = $matches[0];

                echo "    处理img标签: $imgTag\n";

                // 检查是否已经有src属性（确保不匹配data-src）
                $hasSrc = preg_match('/(?<!data-)\bsrc\s*=\s*["\'][^"\']*["\']/', $imgTag);
                echo "    是否已有src属性: " . ($hasSrc ? "是" : "否") . "\n";

                if (!$hasSrc) {
                    // 没有src属性，将data-src转换为src
                    $newTag = preg_replace('/data-src=["\'][^"\']*["\']/', 'src="' . $newUrl . '"', $imgTag);
                    echo "    转换后: $newTag\n";
                    return $newTag;
                } else {
                    // 已经有src属性，只移除data-src
                    $newTag = preg_replace('/\s+data-src=["\'][^"\']*["\']/', '', $imgTag);
                    echo "    移除data-src后: $newTag\n";
                    return $newTag;
                }
            }, $content, -1, $count);
            $replacements += $count;
            echo "  替换次数: $count\n";
            echo "  内容: $content\n";

            echo "步骤2完成后的内容: $content\n\n";
        }

        // 步骤12: 清理所有剩余的data-src属性
        echo "--- 步骤12: 清理剩余data-src属性 ---\n";
        $content = preg_replace_callback('/(<img[^>]+)data-src=["\']([^"\']*)["\']([^>]*>)/i', function($matches) use ($urlMapping) {
            $imgTag = $matches[0];
            $dataSrcValue = $matches[2];

            echo "  处理剩余data-src: $imgTag\n";
            echo "  data-src值: $dataSrcValue\n";

            // 如果data-src为空，直接移除
            if (empty($dataSrcValue)) {
                $result = preg_replace('/\s*data-src=["\']["\']/', '', $imgTag);
                echo "  空data-src，移除后: $result\n";
                return $result;
            }

            // 检查是否已经有src属性（确保不匹配data-src）
            $hasSrc = preg_match('/(?<!data-)\bsrc\s*=\s*["\'][^"\']*["\']/', $imgTag);
            echo "  是否已有src属性: " . ($hasSrc ? "是" : "否") . "\n";

            if (!$hasSrc) {
                // 将data-src转换为src，如果有映射则使用映射后的URL
                $finalSrc = isset($urlMapping[$dataSrcValue]) ? $urlMapping[$dataSrcValue] : $dataSrcValue;
                $result = preg_replace('/data-src=["\']' . preg_quote($dataSrcValue, '/') . '["\']/', 'src="' . $finalSrc . '"', $imgTag);
                echo "  转换为src后: $result\n";
                return $result;
            } else {
                // 如果已经有src，则移除data-src
                $result = preg_replace('/\s+data-src=["\'][^"\']*["\']/', '', $imgTag);
                echo "  已有src，移除data-src后: $result\n";
                return $result;
            }
        }, $content);

        echo "最终内容: $content\n";
        echo "=== 逐步调试结束 ===\n";

        return $content;
    }
}

// 测试
$debugger = new DebugStepByStep();

// 测试1: 基本data-src转换
echo "测试1: 基本data-src转换\n";
$input1 = '<img data-src="https://mmbiz.qpic.cn/test.jpg" alt="test">';
$urlMapping1 = [
    'https://mmbiz.qpic.cn/test.jpg' => 'https://obs.myhuaweicloud.com/processed/image.jpg'
];
$result1 = $debugger->debugProcess($input1, $urlMapping1);
echo "结果: $result1\n";
echo "预期: <img src=\"https://obs.myhuaweicloud.com/processed/image.jpg\" alt=\"test\">\n";
echo "是否正确: " . ($result1 === '<img src="https://obs.myhuaweicloud.com/processed/image.jpg" alt="test">' ? "✅" : "❌") . "\n\n";
