<?php
// 简单的语法检查脚本
$file = __DIR__ . '/test_wechat_access_token.php';

echo "检查文件: $file\n";

// 检查PHP语法
$output = [];
$return_var = 0;
exec("php -l " . escapeshellarg($file), $output, $return_var);

if ($return_var === 0) {
    echo "✅ PHP语法检查通过\n";
} else {
    echo "❌ PHP语法错误:\n";
    foreach ($output as $line) {
        echo "  $line\n";
    }
}

// 检查关键导入和类型
$content = file_get_contents($file);

echo "\n检查关键导入:\n";
if (strpos($content, 'use Psr\Log\LoggerInterface;') !== false) {
    echo "✅ LoggerInterface 已导入\n";
} else {
    echo "❌ LoggerInterface 未找到\n";
}

if (strpos($content, 'use Monolog\Logger;') !== false) {
    echo "✅ Monolog\Logger 已导入\n";
} else {
    echo "❌ Monolog\Logger 未找到\n";
}

echo "\n检查类型使用:\n";
if (strpos($content, 'private LoggerInterface $logger') !== false) {
    echo "✅ LoggerInterface 类型声明正确\n";
} else {
    echo "❌ LoggerInterface 类型声明未找到\n";
}

echo "\n语法检查完成。\n";
