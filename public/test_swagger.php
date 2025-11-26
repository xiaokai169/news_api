<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

echo "=== Swagger UI 配置测试 ===\n\n";

try {
    // 初始化 Symfony 内核
    $kernel = new Kernel('dev', true);
    $kernel->boot();

    $container = $kernel->getContainer();

    echo "✓ Symfony 内核启动成功\n";

    // 检查 NelmioApiDocBundle 是否已注册
    if ($container->has('nelmio_api_doc.generator')) {
        echo "✓ NelmioApiDocBundle 服务已注册\n";
    } else {
        echo "✗ NelmioApiDocBundle 服务未找到\n";
    }

    // 检查路由器
    if ($container->has('router')) {
        echo "✓ 路由器服务已注册\n";
        $router = $container->get('router');

        try {
            $routeCollection = $router->getRouteCollection();
            $apiDocRoutes = [];

            foreach ($routeCollection as $name => $route) {
                if (strpos($name, 'nelmio_api_doc') !== false || strpos($route->getPath(), '/api/doc') !== false) {
                    $apiDocRoutes[] = [
                        'name' => $name,
                        'path' => $route->getPath(),
                        'methods' => $route->getMethods()
                    ];
                }
            }

            if (!empty($apiDocRoutes)) {
                echo "✓ 找到 API 文档相关路由:\n";
                foreach ($apiDocRoutes as $route) {
                    echo "  - {$route['name']}: {$route['path']} [" . implode(', ', $route['methods']) . "]\n";
                }
            } else {
                echo "✗ 未找到 API 文档相关路由\n";
            }
        } catch (Exception $e) {
            echo "✗ 获取路由集合失败: " . $e->getMessage() . "\n";
        }
    } else {
        echo "✗ 路由器服务未找到\n";
    }

    // 检查控制器扫描
    $controllerPaths = [
        __DIR__ . '/../src/Controller/NewsController.php',
        __DIR__ . '/../src/Controller/TestController.php',
        __DIR__ . '/../src/Controller/SecurityController.php'
    ];

    echo "\n=== 控制器文件检查 ===\n";
    foreach ($controllerPaths as $controllerPath) {
        if (file_exists($controllerPath)) {
            echo "✓ " . basename($controllerPath) . " 存在\n";
        } else {
            echo "✗ " . basename($controllerPath) . " 不存在\n";
        }
    }

    // 检查配置文件
    $configFiles = [
        __DIR__ . '/../config/packages/nelmio_api_doc.yaml',
        __DIR__ . '/../config/routes/nelmio_api_doc.yaml',
        __DIR__ . '/../config/routes.yaml'
    ];

    echo "\n=== 配置文件检查 ===\n";
    foreach ($configFiles as $configFile) {
        if (file_exists($configFile)) {
            echo "✓ " . basename($configFile) . " 存在\n";
        } else {
            echo "✗ " . basename($configFile) . " 不存在\n";
        }
    }

    echo "\n=== 测试完成 ===\n";
    echo "Swagger UI 应该可以通过以下地址访问:\n";
    echo "- http://localhost:8001/api/doc (Swagger UI)\n";
    echo "- http://localhost:8001/api/doc.json (OpenAPI JSON)\n";

} catch (Exception $e) {
    echo "✗ 测试过程中发生错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
