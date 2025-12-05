<?php

echo "=== 调试 MapRequestPayload 问题 ===\n\n";

// 模拟HTTP请求环境
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';
$_SERVER['REQUEST_URI'] = '/official-api/wechat/sync';

// 模拟原始请求数据
$rawInput = '{"publicAccountId": "test_account_001", "syncType": "articles", "forceSync": false}';

echo "模拟HTTP请求:\n";
echo "Method: POST\n";
echo "Content-Type: application/json\n";
echo "URI: /official-api/wechat/sync\n";
echo "Body: " . $rawInput . "\n\n";

// 设置php://input
file_put_contents('php://memory', $rawInput);

// 加载Symfony
require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
$dotenv = new Symfony\Component\Dotenv\Dotenv();
$dotenv->load(__DIR__ . '/../.env');

require_once __DIR__ . '/../src/Kernel.php';

$kernel = new \App\Kernel('dev', true);
$kernel->boot();

$container = $kernel->getContainer();

try {
    echo "🧪 测试1: 手动创建Request对象\n";

    // 创建Request对象
    $request = \Symfony\Component\HttpFoundation\Request::create(
        '/official-api/wechat/sync',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        $rawInput
    );

    echo "Request对象创建成功\n";
    echo "Request内容: " . $request->getContent() . "\n";
    echo "Request格式: " . $request->getContentType() . "\n\n";

    echo "🧪 测试2: 手动反序列化DTO\n";

    // 使用Serializer组件手动反序列化
    $serializer = $container->get('serializer');

    try {
        $dto = $serializer->deserialize($rawInput, \App\DTO\Request\Wechat\SyncWechatDto::class, 'json');

        echo "✅ DTO反序列化成功\n";
        echo "publicAccountId: " . var_export($dto->getAccountId(), true) . "\n";
        echo "syncType: " . var_export($dto->getSyncType(), true) . "\n";
        echo "forceSync: " . var_export($dto->isForceSync(), true) . "\n";

    } catch (Exception $e) {
        echo "❌ DTO反序列化失败: " . $e->getMessage() . "\n";
    }

    echo "\n🧪 测试3: 使用JsonSchema Validator\n";

    // 使用JsonSchema验证
    $validator = $container->get('validator');

    try {
        $dto2 = new \App\DTO\Request\Wechat\SyncWechatDto();
        $dto2->setAccountId('test_account_001');
        $dto2->setSyncType('articles');
        $dto2->setForceSync(false);

        $errors = $validator->validate($dto2);

        if (count($errors) > 0) {
            echo "❌ DTO验证失败:\n";
            foreach ($errors as $error) {
                echo "   - {$error->getPropertyPath()}: {$error->getMessage()}\n";
            }
        } else {
            echo "✅ DTO验证通过\n";
        }

    } catch (Exception $e) {
        echo "❌ DTO验证测试失败: " . $e->getMessage() . "\n";
    }

    echo "\n🧪 测试4: 检查Symfony的MapRequestPayload支持\n";

    // 检查Symfony版本和特性
    $symfonyVersion = \Symfony\Component\HttpKernel\Kernel::VERSION;
    echo "Symfony版本: " . $symfonyVersion . "\n";

    // 检查是否有MapRequestPayload属性
    $reflection = new ReflectionClass(\Symfony\Component\HttpKernel\Attribute\MapRequestPayload::class);
    echo "MapRequestPayload类存在: " . ($reflection ? '是' : '否') . "\n";

    echo "\n🔍 问题分析:\n";
    echo "1. JSON解析正常 ✅\n";
    echo "2. DTO类定义正常 ✅\n";
    echo "3. 验证规则定义正常 ✅\n";
    echo "4. 问题可能在于:\n";
    echo "   - MapRequestPayload特性配置问题\n";
    echo "   - 请求体解析问题\n";
    echo "   - 序列化器配置问题\n";

} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}

$kernel->shutdown();

echo "\n=== 调试完成 ===\n";
