<?php

require_once dirname(__DIR__)."/vendor/autoload_runtime.php";

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Router;
use Symfony\Component\HttpFoundation\JsonResponse;

// 创建内核实例
$kernel = new Kernel('prod', false);
$kernel->boot();

$container = $kernel->getContainer();
$router = $container->get('router');

echo "=== Symfony 路由调试信息 ===\n\n";

// 获取所有路由
$routes = $router->getRouteCollection();
echo "总路由数量: " . $routes->count() . "\n\n";

// 显示API相关路由
echo "=== API 路由列表 ===\n";
foreach ($routes as $name => $route) {
    $path = $route->getPath();
    if (str_contains($path, 'api') || str_contains($name, 'api')) {
        $methods = $route->getMethods() ?: ['ANY'];
        echo sprintf(
            "%-50s %-10s %s\n",
            $name,
            implode(',', $methods),
            $path
        );
    }
}

echo "\n=== 测试特定路由匹配 ===\n";

$testPaths = [
    '/public-api/articles',
    '/official-api/article-read/statistics',
    '/api/doc',
    '/public-api/news/123'
];

foreach ($testPaths as $testPath) {
    try {
        $request = Request::create($testPath);
        $matchInfo = $router->matchRequest($request);
        echo "✓ $testPath -> {$matchInfo['_route']}\n";
    } catch (\Exception $e) {
        echo "✗ $testPath -> 未匹配: " . $e->getMessage() . "\n";
    }
}

echo "\n=== 环境信息 ===\n";
echo "APP_ENV: " . ($_ENV['APP_ENV'] ?? 'not set') . "\n";
echo "APP_DEBUG: " . ($_ENV['APP_DEBUG'] ?? 'not set') . "\n";
echo "PHP版本: " . PHP_VERSION . "\n";
echo "Symfony版本: " . Kernel::VERSION . "\n";

$kernel->shutdown();
