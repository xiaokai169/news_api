<?php
/**
 * 创建分布式锁表的执行脚本
 * 这个脚本会调用Symfony命令来创建表
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
    $application->add(new \App\Command\CreateDistributedLocksTableCommand(
        $kernel->getContainer()->get('doctrine.dbal.default_connection')
    ));

    // 运行命令
    $command = $application->find('app:create-distributed-locks-table');
    $input = new \Symfony\Component\Console\Input\ArrayInput(['command' => $command->getName()]);
    $output = new \Symfony\Component\Console\Output\ConsoleOutput();

    $exitCode = $command->run($input, $output);

    $kernel->shutdown();

    echo "\n命令执行完成，退出码: {$exitCode}\n";

} catch (\Exception $e) {
    echo "❌ 执行命令时发生错误: " . $e->getMessage() . "\n";
    echo "错误详情: " . $e->getTraceAsString() . "\n";
    exit(1);
}
