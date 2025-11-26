<?php

// 自定义autoload_runtime.php - 绕过Symfony Dotenv组件

// 手动加载环境变量
$projectDir = dirname(__DIR__);
$envFile = $projectDir . '/.env';

// 手动加载.env文件
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // 移除值周围的引号
            if (preg_match('/^"([^"]*)"$/', $value, $matches)) {
                $value = $matches[1];
            } elseif (preg_match("/^'([^']*)'$/", $value, $matches)) {
                $value = $matches[1];
            }

            if (!array_key_exists($name, $_SERVER)) {
                $_SERVER[$name] = $value;
            }
            if (!array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
            }
        }
    }
}

// 设置默认环境变量
if (!isset($_SERVER['APP_ENV'])) {
    $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] ?? 'dev';
}
if (!isset($_SERVER['APP_DEBUG'])) {
    $_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] ?? '1';
}

// 加载autoloader
require_once $projectDir.'/vendor/autoload.php';

// 返回闭包函数，与Symfony标准runtime兼容
return function (array $context = []) {
    // 合并传入的上下文和环境变量
    $env = $context['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'dev';
    $debug = (bool) ($context['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? true);

    return new App\Kernel($env, $debug);
};
