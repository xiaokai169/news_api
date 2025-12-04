<?php

echo "=== 调试请求体解析 ===\n\n";

// 模拟原始请求数据
$rawInput = '{"publicAccountId": "test_account_001", "syncType": "articles", "forceSync": false}';

echo "原始请求体: " . $rawInput . "\n";
echo "Content-Type: application/json\n\n";

// 测试JSON解析
$parsedData = json_decode($rawInput, true);

if ($parsedData === null) {
    echo "❌ JSON解析失败: " . json_last_error_msg() . "\n";
} else {
    echo "✅ JSON解析成功\n";
    echo "解析后的数据:\n";
    print_r($parsedData);

    echo "\n📋 检查publicAccountId字段:\n";
    if (isset($parsedData['publicAccountId'])) {
        echo "✅ publicAccountId存在: " . var_export($parsedData['publicAccountId'], true) . "\n";
        echo "   类型: " . gettype($parsedData['publicAccountId']) . "\n";
        echo "   是否为空: " . (empty($parsedData['publicAccountId']) ? '是' : '否') . "\n";
        echo "   长度: " . strlen($parsedData['publicAccountId']) . "\n";
    } else {
        echo "❌ publicAccountId字段不存在\n";
    }

    echo "\n🧪 测试DTO创建:\n";

    // 加载Symfony
    require_once __DIR__ . '/../vendor/autoload.php';

    $dotenv = new Symfony\Component\Dotenv\Dotenv();
    $dotenv->load(__DIR__ . '/../.env');

    require_once __DIR__ . '/../src/Kernel.php';

    $kernel = new \App\Kernel('dev', true);
    $kernel->boot();

    $container = $kernel->getContainer();

    try {
        // 创建DTO
        $dto = new \App\DTO\Request\Wechat\SyncWechatDto($parsedData);

        echo "✅ DTO创建成功\n";
        echo "DTO中的publicAccountId: " . var_export($dto->getAccountId(), true) . "\n";

        // 验证DTO
        $validator = $container->get('validator');
        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            echo "❌ DTO验证失败:\n";
            foreach ($errors as $error) {
                echo "   - {$error->getPropertyPath()}: {$error->getMessage()}\n";
            }
        } else {
            echo "✅ DTO验证通过\n";
        }

        // 自定义验证
        $customErrors = $dto->validateSyncData();
        if (!empty($customErrors)) {
            echo "❌ 自定义验证失败:\n";
            foreach ($customErrors as $field => $error) {
                echo "   - $field: $error\n";
            }
        } else {
            echo "✅ 自定义验证通过\n";
        }

    } catch (Exception $e) {
        echo "❌ DTO测试失败: " . $e->getMessage() . "\n";
    }

    $kernel->shutdown();
}

echo "\n=== 调试完成 ===\n";
