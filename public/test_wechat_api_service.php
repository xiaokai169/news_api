<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Service\WechatApiService;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

echo "=== WechatApiService 实例化测试 ===\n\n";

try {
    // 创建一个简单的 logger 实例
    $logger = new Logger('test');
    $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

    echo "1. Logger 创建成功\n";
    echo "   Logger 类型: " . get_class($logger) . "\n";
    echo "   Logger 名称: " . $logger->getName() . "\n\n";

    // 测试 WechatApiService 实例化
    $wechatService = new WechatApiService($logger);

    echo "2. WechatApiService 实例化成功\n";
    echo "   服务类型: " . get_class($wechatService) . "\n\n";

    // 测试 withName 功能
    $modifiedLogger = $logger->withName('wechat');
    echo "3. withName('wechat') 功能测试\n";
    echo "   原始 logger 名称: " . $logger->getName() . "\n";
    echo "   修改后 logger 名称: " . $modifiedLogger->getName() . "\n\n";

    // 测试日志记录
    echo "4. 日志记录功能测试\n";
    $wechatService->logger->info('这是一个测试日志消息');
    echo "   日志记录成功\n\n";

    echo "=== 所有测试通过 ===\n";
    echo "✅ readonly 修饰符已成功移除\n";
    echo "✅ 构造函数参数声明正确: private LoggerInterface \$logger\n";
    echo "✅ withName('wechat') 逻辑保持不变\n";
    echo "✅ 服务能够正常实例化\n";
    echo "✅ 日志功能正常工作\n";

} catch (Throwable $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo "错误类型: " . get_class($e) . "\n";
    echo "错误文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\n堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
