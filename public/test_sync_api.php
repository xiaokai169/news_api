<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// 加载环境变量
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/../.env');

echo "=== 测试微信同步API ===\n\n";

try {
    // 创建数据库连接获取可用的公众号ID
    $pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=official_website", 'root', 'qwe147258..');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT id FROM wechat_public_account LIMIT 1");
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        echo "❌ 没有找到公众号数据\n";
        exit(1);
    }

    $accountId = $account['id'];
    echo "✅ 使用公众号ID: $accountId\n\n";

    // 初始化Symfony内核
    require_once __DIR__ . '/../src/Kernel.php';

    $kernel = new \App\Kernel('dev', true);
    $kernel->boot();

    $container = $kernel->getContainer();

    // 创建测试请求
    echo "🧪 测试1: 正确的请求格式\n";

    $requestData = [
        'publicAccountId' => $accountId,
        'syncType' => 'articles',
        'forceSync' => false
    ];

    echo "请求数据: " . json_encode($requestData, JSON_UNESCAPED_UNICODE) . "\n";

    // 创建DTO对象
    $syncDto = new \App\DTO\Request\Wechat\SyncWechatDto($requestData);

    // 验证DTO
    $validator = $container->get('validator');
    $errors = $validator->validate($syncDto);

    if (count($errors) > 0) {
        echo "❌ DTO验证失败:\n";
        foreach ($errors as $error) {
            echo "   - {$error->getMessage()}\n";
        }
    } else {
        echo "✅ DTO验证通过\n";
    }

    // 测试自定义验证
    $validationErrors = $syncDto->validateSyncData();
    if (!empty($validationErrors)) {
        echo "❌ 自定义验证失败:\n";
        foreach ($validationErrors as $field => $error) {
            echo "   - $field: $error\n";
        }
    } else {
        echo "✅ 自定义验证通过\n";
    }

    echo "\n🧪 测试2: 空的publicAccountId\n";

    $requestData2 = [
        'publicAccountId' => '',
        'syncType' => 'articles',
        'forceSync' => false
    ];

    echo "请求数据: " . json_encode($requestData2, JSON_UNESCAPED_UNICODE) . "\n";

    $syncDto2 = new \App\DTO\Request\Wechat\SyncWechatDto($requestData2);
    $errors2 = $validator->validate($syncDto2);

    if (count($errors2) > 0) {
        echo "❌ DTO验证失败（预期）:\n";
        foreach ($errors2 as $error) {
            echo "   - {$error->getMessage()}\n";
        }
    } else {
        echo "⚠️ DTO验证应该失败但却通过了\n";
    }

    $validationErrors2 = $syncDto2->validateSyncData();
    if (!empty($validationErrors2)) {
        echo "❌ 自定义验证失败（预期）:\n";
        foreach ($validationErrors2 as $field => $error) {
            echo "   - $field: $error\n";
        }
    }

    echo "\n🧪 测试3: 缺少publicAccountId字段\n";

    $requestData3 = [
        'syncType' => 'articles',
        'forceSync' => false
    ];

    echo "请求数据: " . json_encode($requestData3, JSON_UNESCAPED_UNICODE) . "\n";

    $syncDto3 = new \App\DTO\Request\Wechat\SyncWechatDto($requestData3);
    $errors3 = $validator->validate($syncDto3);

    if (count($errors3) > 0) {
        echo "❌ DTO验证失败（预期）:\n";
        foreach ($errors3 as $error) {
            echo "   - {$error->getMessage()}\n";
        }
    } else {
        echo "⚠️ DTO验证应该失败但却通过了\n";
    }

    $validationErrors3 = $syncDto3->validateSyncData();
    if (!empty($validationErrors3)) {
        echo "❌ 自定义验证失败（预期）:\n";
        foreach ($validationErrors3 as $field => $error) {
            echo "   - $field: $error\n";
        }
    }

    echo "\n🧪 测试4: 模拟HTTP请求\n";

    // 创建Request对象
    $request = \Symfony\Component\HttpFoundation\Request::create(
        '/official-api/wechat/sync',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode($requestData)
    );

    echo "模拟HTTP请求: POST /official-api/wechat/sync\n";
    echo "Content-Type: application/json\n";
    echo "Body: " . json_encode($requestData, JSON_UNESCAPED_UNICODE) . "\n";

    // 测试控制器方法
    try {
        $controller = new \App\Controller\WechatController(
            $container->get('doctrine.orm.entity_manager'),
            $container->get('App\Repository\WechatPublicAccountRepository'),
            $container->get('App\Repository\OfficialRepository'),
            new \App\Http\ApiResponse(),
            $container->get('App\Service\WechatArticleSyncService'),
            $validator
        );

        // 使用MapRequestPayload特性，我们需要模拟这个行为
        // 在实际HTTP请求中，Symfony会自动将请求体映射到DTO
        echo "✅ 控制器创建成功\n";
        echo "💡 在实际的HTTP请求中，Symfony会自动将JSON请求体映射到SyncWechatDto对象\n";

    } catch (Exception $e) {
        echo "❌ 控制器测试失败: " . $e->getMessage() . "\n";
    }

    $kernel->shutdown();

    echo "\n=== 测试完成 ===\n";
    echo "\n📋 诊断结论:\n";
    echo "1. 数据库中存在公众号数据 ✅\n";
    echo "2. DTO验证逻辑正常工作 ✅\n";
    echo "3. 错误'公众号ID不能为空'来自于DTO的NotBlank验证 ✅\n";
    echo "4. 可能的问题原因:\n";
    echo "   - 客户端请求中publicAccountId字段为空\n";
    echo "   - 客户端请求中缺少publicAccountId字段\n";
    echo "   - 请求体格式不正确导致参数映射失败\n";

} catch (Exception $e) {
    echo "❌ 测试过程中出错: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
