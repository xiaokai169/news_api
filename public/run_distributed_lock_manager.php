<?php
/**
 * 分布式锁管理命令执行脚本
 */

// 设置环境变量
$_SERVER['APP_ENV'] = 'dev';
$_SERVER['APP_DEBUG'] = 'true';

require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Console\Application;

try {
    // 创建Symfony应用实例
    $kernel = new Kernel('dev', true);
    $kernel->boot();

    // 获取控制台应用
    $application = new Application($kernel);

    // 添加我们的命令
    $application->add(new \App\Command\DistributedLockManagerCommand(
        $kernel->getContainer()->get('doctrine.dbal.default_connection')
    ));

    // 解析命令行参数
    $argv = $_SERVER['argv'];
    array_shift($argv); // 移除脚本名

    if (empty($argv)) {
        echo "用法:\n";
        echo "  php run_distributed_lock_manager.php status                    # 查看锁状态\n";
        echo "  php run_distributed_lock_manager.php clean                     # 清理过期锁\n";
        echo "  php run_distributed_lock_manager.php clean --force             # 强制清理所有锁\n";
        echo "  php run_distributed_lock_manager.php release --lock-key=xxx   # 释放指定锁\n";
        echo "  php run_distributed_lock_manager.php create-table             # 创建锁表\n";
        exit(0);
    }

    // 运行命令
    $application->run();

    $kernel->shutdown();

} catch (\Exception $e) {
    echo "❌ 执行命令时发生错误: " . $e->getMessage() . "\n";
    echo "错误详情: " . $e->getTraceAsString() . "\n";
    exit(1);
}
