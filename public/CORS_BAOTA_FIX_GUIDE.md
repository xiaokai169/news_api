# 🔧 宝塔面板环境 CORS 修复指南

## 📋 环境分析

基于您提供的线上 Nginx 配置，这是一个**宝塔面板**管理的环境：

-   **PHP 版本**: PHP 8.2 (`include enable-php-82.conf`)
-   **网站根目录**: `/www/wwwroot/newsapi.arab-bee.com/public`
-   **SSL**: 已启用 HTTPS 和 HTTP/2
-   **重写规则**: 通过宝塔面板管理 (`include /www/server/panel/vhost/rewrite/newsapi.arab-bee.com.conf`)

**重要**: 不能直接修改 Nginx 配置，只能通过 Symfony 应用层面解决 CORS 问题。

---

## 🎯 **问题根本原因**

### **主要原因 #1: 宝塔面板 PHP-FPM 配置**

-   宝塔面板可能通过 PHP-FPM 传递了环境变量
-   `enable-php-82.conf` 中可能设置了 `APP_ENV=prod`

### **主要原因 #2: 缺少明确的 CORS 处理**

-   NelmioCorsBundle 可能没有正确处理 OPTIONS 预检请求
-   需要在 Symfony 层面强制处理 CORS

---

## 🛠️ **不改动 Nginx 的修复方案**

### **步骤 1: 强制启用 CORS 订阅者**

在 [`config/services.yaml`](config/services.yaml) 中明确注册强制 CORS 订阅者：

```yaml
services:
    # ... 其他服务配置 ...

    # 强制 CORS 处理订阅者
    App\EventSubscriber\ForceCorsSubscriber:
        tags:
            - { name: kernel.event_subscriber }
        arguments:
            - "@logger"

    # CORS 调试订阅者（生产环境可以禁用）
    App\EventSubscriber\CorsDebugSubscriber:
        tags:
            - { name: kernel.event_subscriber }
```

### **步骤 2: 优化 .env 配置**

更新 [`.env`](.env) 文件：

```bash
# 确保环境变量正确
APP_ENV=prod
APP_DEBUG=false

# 设置具体的允许域名（生产环境安全考虑）
CORS_ALLOW_ORIGIN=https://ops.arab-bee.com,https://newsapi.arab-bee.com

# 如果宝塔面板覆盖了这些变量，可以创建 .env.local
# APP_ENV=prod
# APP_DEBUG=false
```

### **步骤 3: 增强 ForceCorsSubscriber**

更新 [`src/EventSubscriber/ForceCorsSubscriber.php`](src/EventSubscriber/ForceCorsSubscriber.php) 以处理宝塔环境：

```php
<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Psr\Log\LoggerInterface;

/**
 * 宝塔面板环境强制 CORS 处理订阅者
 */
class ForceCorsSubscriber implements EventSubscriberInterface
{
    private $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1024],   // 最高优先级
            KernelEvents::RESPONSE => ['onKernelResponse', -1024], // 最低优先级
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $method = $request->getMethod();
        $path = $request->getPathInfo();

        // 只处理 API 路径的 OPTIONS 请求
        $isApiPath = str_starts_with($path, '/api') ||
                     str_starts_with($path, '/official-api') ||
                     str_starts_with($path, '/public-api');

        if ($isApiPath && $method === 'OPTIONS') {
            $this->log('info', 'Handling OPTIONS request', [
                'path' => $path,
                'origin' => $request->headers->get('Origin'),
                'request_method' => $request->headers->get('Access-Control-Request-Method')
            ]);

            // 立即返回 200 状态码和 CORS 头
            $response = new \Symfony\Component\HttpFoundation\Response();
            $this->setCorsHeaders($response, $request);

            $event->setResponse($response);
            $event->stopPropagation();
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $path = $request->getPathInfo();

        // 只为 API 路径设置 CORS 头
        $isApiPath = str_starts_with($path, '/api') ||
                     str_starts_with($path, '/official-api') ||
                     str_starts_with($path, '/public-api');

        if ($isApiPath) {
            $this->setCorsHeaders($response, $request);

            $this->log('info', 'Set CORS headers for response', [
                'path' => $path,
                'status_code' => $response->getStatusCode(),
                'origin' => $request->headers->get('Origin')
            ]);
        }
    }

    private function setCorsHeaders($response, $request): void
    {
        $origin = $request->headers->get('Origin');
        $allowedOrigin = $this->getAllowedOrigin($origin);

        // 设置 CORS 头
        $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-Custom-Header');
        $response->headers->set('Access-Control-Max-Age', '3600');
        $response->headers->set('Access-Control-Allow-Credentials', 'false');

        // 如果是 OPTIONS 请求，确保状态码为 200
        if ($request->getMethod() === 'OPTIONS' && $response->getStatusCode() !== 200) {
            $response->setStatusCode(200);
        }
    }

    private function getAllowedOrigin($requestOrigin): string
    {
        // 从环境变量获取允许的域名
        $corsAllowOrigin = $_ENV['CORS_ALLOW_ORIGIN'] ?? '*';

        if ($corsAllowOrigin === '*') {
            return '*';
        }

        // 如果指定了具体域名，检查是否匹配
        $allowedOrigins = array_map('trim', explode(',', $corsAllowOrigin));

        if (in_array($requestOrigin, $allowedOrigins)) {
            return $requestOrigin;
        }

        // 如果不匹配，返回第一个允许的域名
        return $allowedOrigins[0] ?? '*';
    }

    private function log($level, $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->$level('[FORCE CORS] ' . $message, $context);
        }

        // 同时写入错误日志
        error_log('[FORCE CORS] ' . $message . ' - ' . json_encode($context));
    }
}
```

### **步骤 4: 创建宝塔环境专用测试脚本**

创建 `public/baota_cors_test.php`：

```php
<?php
/**
 * 宝塔面板环境 CORS 测试脚本
 */

header('Content-Type: application/json');

// 记录宝塔环境信息
$baota_info = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
    'environment' => [
        'APP_ENV' => $_ENV['APP_ENV'] ?? 'not_set',
        'APP_DEBUG' => $_ENV['APP_DEBUG'] ?? 'not_set',
        'CORS_ALLOW_ORIGIN' => $_ENV['CORS_ALLOW_ORIGIN'] ?? 'not_set',
    ],
    'headers' => getallheaders(),
];

// 处理 OPTIONS 请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    $request_method = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? 'GET';
    $request_headers = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? '';

    // 设置 CORS 头
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, ' . $request_headers);
    header('Access-Control-Max-Age: 3600');

    http_response_code(200);

    echo json_encode([
        'success' => true,
        'message' => '宝塔环境 CORS OPTIONS 请求处理成功',
        'baota_info' => $baota_info
    ], JSON_UNESCAPED_UNICODE);

} else {
    // 非 OPTIONS 请求
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

    echo json_encode([
        'success' => true,
        'message' => '宝塔环境 CORS 测试端点',
        'baota_info' => $baota_info
    ], JSON_UNESCAPED_UNICODE);
}
```

---

## 🧪 **宝塔环境测试步骤**

### **1. 基础连接测试**

```bash
# 测试 PHP 是否正常工作
curl "https://newsapi.arab-bee.com/baota_cors_test.php"

# 测试 OPTIONS 请求
curl -X OPTIONS \
  -H "Origin: https://ops.arab-bee.com" \
  -H "Access-Control-Request-Method: GET" \
  -v "https://newsapi.arab-bee.com/baota_cors_test.php"
```

### **2. 实际 API 测试**

```bash
# 测试官方 API
curl -X OPTIONS \
  -H "Origin: https://ops.arab-bee.com" \
  -H "Access-Control-Request-Method: GET" \
  -v "https://newsapi.arab-bee.com/official-api/news"

# 测试 GET 请求
curl -H "Origin: https://ops.arab-bee.com" \
  -v "https://newsapi.arab-bee.com/official-api/news"
```

### **3. 浏览器测试**

访问 `https://newsapi.arab-bee.com/cors_diagnostic_test.html` 进行完整测试。

---

## 🔧 **宝塔面板特定操作**

### **1. 检查 PHP-FPM 配置**

在宝塔面板中：

1. 进入 "软件商店" → "PHP-8.2" → "配置修改"
2. 检查是否有设置环境变量的配置
3. 确认 `open_basedir` 等安全设置

### **2. 清除 PHP 缓存**

在宝塔面板中：

1. 重启 PHP-8.2 服务
2. 清除 OPcache（如果启用）

### **3. 检查网站设置**

在宝塔面板中：

1. 进入 "网站" → "newsapi.arab-bee.com" → "设置"
2. 检查 "伪静态" 设置
3. 确认 "PHP 版本" 为 8.2

---

## 🚨 **如果仍然有问题**

### **临时解决方案**

如果上述方案仍然无效，可以在应用入口强制设置 CORS 头：

在 `public/index.php` 中添加：

```php
<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

// 🔧 宝塔环境 CORS 强制修复
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $allowed_origins = [
        'https://ops.arab-bee.com',
        'https://newsapi.arab-bee.com'
    ];

    if (in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    } else {
        header('Access-Control-Allow-Origin: *');
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
}

// 处理 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
```

---

## 📊 **成功验证标准**

-   ✅ OPTIONS 请求返回 200 状态码
-   ✅ 响应包含正确的 CORS 头
-   ✅ 前端应用能正常调用 API
-   ✅ 浏览器控制台无 CORS 错误
-   ✅ 宝塔面板环境稳定运行

---

**宝塔环境修复版本**: v1.0  
**最后更新**: 2025-11-29  
**适用环境**: 宝塔面板 + PHP 8.2 + Symfony 6.x  
**特殊要求**: 不修改 Nginx 配置
