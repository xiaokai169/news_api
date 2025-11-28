<?php

// 简单的.env文件语法验证
$file = '.env.prod';
$content = file_get_contents($file);
$lines = explode("\n", $content);
$errors = [];
$success = true;

echo "🔍 正在验证 .env.prod 文件语法...\n\n";

foreach ($lines as $lineNum => $line) {
    $lineNum++;
    $line = trim($line);

    // 跳过空行和注释
    if (empty($line) || strpos($line, '#') === 0) {
        continue;
    }

    // 检查是否是有效的环境变量格式
    if (strpos($line, '=') === false) {
        $errors[] = "第 {$lineNum} 行: 无效的格式 - 缺少等号";
        $success = false;
        continue;
    }

    list($key, $value) = explode('=', $line, 2);
    $key = trim($key);
    $value = trim($value);

    // 检查键名格式
    if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $key)) {
        $errors[] = "第 {$lineNum} 行: 无效的键名格式 - {$key}";
        $success = false;
    }

    // 检查值的引号配对
    if ((substr($value, 0, 1) === '"' && substr($value, -1) !== '"') ||
        (substr($value, 0, 1) === "'" && substr($value, -1) !== "'")) {
        $errors[] = "第 {$lineNum} 行: 引号不配对 - {$value}";
        $success = false;
    }

    // 检查未引号包围的值中是否有特殊字符
    if (!empty($value) &&
        substr($value, 0, 1) !== '"' &&
        substr($value, 0, 1) !== "'" &&
        (strpos($value, ';') !== false || strpos($value, ' ') !== false)) {
        $errors[] = "第 {$lineNum} 行: 包含特殊字符但未加引号 - {$value}";
        $success = false;
    }
}

if ($success) {
    echo "✅ .env.prod 文件语法验证通过！\n";
    echo "✅ 所有环境变量格式正确\n";
    echo "✅ 引号使用正确\n";
    echo "✅ 特殊字符已正确处理\n";

    // 显示修复的关键行
    echo "\n🔧 修复的关键变量:\n";
    foreach ($lines as $lineNum => $line) {
        $lineNum++;
        if (strpos($line, 'X_XSS_PROTECTION=') === 0 ||
            strpos($line, 'STRICT_TRANSPORT_SECURITY=') === 0 ||
            strpos($line, 'CONTENT_SECURITY_POLICY=') === 0) {
            echo "✅ 第 {$lineNum} 行: {$line}\n";
        }
    }
} else {
    echo "❌ 发现语法错误:\n";
    foreach ($errors as $error) {
        echo "   - {$error}\n";
    }
}

echo "\n🎉 验证完成！\n";
