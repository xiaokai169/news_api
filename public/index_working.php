<?php
// 绕过Symfony缓存系统的直接解决方案
require_once dirname(__DIR__).'/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

// 创建简单的路由
$routes = new RouteCollection();
$routes->add('welcome', new Route('/', [
    '_controller' => 'App\Controller\WelcomeController::index'
]));
$routes->add('api_doc', new Route('/api_doc', [
    '_controller' => 'App\Controller\WelcomeController::apiDoc'
]));

// 处理请求
$request = Request::createFromGlobals();
$context = new RequestContext();
$context->fromRequest($request);
$matcher = new UrlMatcher($routes, $context);

try {
    $parameters = $matcher->match($request->getPathInfo());

    // 直接调用控制器
    $controllerParts = explode('::', $parameters['_controller']);
    $controllerClass = $controllerParts[0];
    $method = $controllerParts[1];

    // 创建控制器实例
    $controller = new $controllerClass();
    $response = $controller->$method($request);

} catch (Exception $e) {
    // 如果路由不匹配，显示默认欢迎页面
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8"/>
    <title>官方网站后台</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
        h1 { color: #333; margin-bottom: 20px; }
        p { color: #666; line-height: 1.6; }
        .button { display: inline-block; background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin: 5px; }
        .button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="card">
        <h1>官方网站后台</h1>
        <p>系统已运行。接口文档请访问 <a class="button" href="/api_doc">/api_doc</a></p>
        <p>欢迎页面 <a class="button" href="/">/</a></p>
    </div>
</body>
</html>
HTML;
    $response = new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
}

$response->send();
