<?php
// 修复WSL文件系统权限问题的Symfony入口文件
use App\Kernel;

// 设置自定义缓存目录，避免WSL权限问题
$cacheDir = dirname(__DIR__) . '/var/cache_temp';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}

// 设置环境变量来使用临时缓存目录
putenv('SYMFONY_CACHE_DIR=' . $cacheDir);
putenv('APP_ENV=dev');
putenv('APP_DEBUG=1');

// 加载环境变量
require_once dirname(__DIR__).'/vendor/autoload.php';

// 创建并运行应用
return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
