# 404 错误 JSON 响应修复文档

## 🔍 问题诊断

### 问题描述

API 接口返回 HTML 格式的 404 错误页面，而不是期望的 JSON 格式响应：

```html
<html>
    <head>
        <title>404 Not Found</title>
    </head>
    <body>
        <center><h1>404 Not Found</h1></center>
        <hr />
        <center>nginx</center>
    </body>
</html>
```

### 根本原因分析

经过系统诊断，确认了两个主要问题源：

1. **🎯 Nginx 配置问题** - Nginx 没有正确配置指向 Symfony 应用
2. **🎯 异常处理器路径判断不完整** - `ApiExceptionSubscriber`只处理部分 API 路径

## 🛠️ 解决方案

### 方案 1: Nginx 配置修复（推荐）

创建正确的 Nginx 站点配置文件 `nginx_site_config.conf`：

```nginx
# API路由处理 - 确保返回JSON而不是HTML
location ~ ^/(api|official-api|public-api) {
    try_files $uri $uri/ /index.php?$query_string;

    # 确保API请求返回JSON格式
    error_page 404 /api_404.json;
    error_page 500 502 503 504 /api_500.json;
}

# 处理API 404错误，返回JSON格式
location = /api_404.json {
    internal;
    add_header Content-Type application/json;
    return 404 '{"success": false, "message": "API endpoint not found", "error_code": 404}';
}
```

**部署步骤：**

1. 将配置文件复制到 `/etc/nginx/sites-available/official_website_backend`
2. 创建软链接：`sudo ln -s /etc/nginx/sites-available/official_website_backend /etc/nginx/sites-enabled/`
3. 测试配置：`sudo nginx -t`
4. 重启 Nginx：`sudo systemctl restart nginx`

### 方案 2: Symfony 异常处理器修复

修改 `src/EventSubscriber/ApiExceptionSubscriber.php`：

```php
// 🔍 判断是否为 API 请求（处理 /api、/official-api 和 /public-api 路径）
$path = $request->getPathInfo();
$isApiRequest = str_starts_with($path, '/api') ||
                str_starts_with($path, '/official-api') ||
                str_starts_with($path, '/public-api');
```

### 方案 3: Apache .htaccess 临时修复

修改 `public/.htaccess` 文件：

```apache
# API 404错误处理 - 对于API路径的无效请求，返回JSON格式的404
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} ^/(api|official-api|public-api) [NC]
RewriteRule ^(.*)$ api_404_handler.php [L]
```

### 方案 4: 独立 404 处理器

创建 `public/api_404_handler.php` 作为备用解决方案：

```php
<?php
header('Content-Type: application/json');
http_response_code(404);

$response = [
    'success' => false,
    'message' => 'API endpoint not found',
    'error_code' => 404,
    'data' => [
        'requested_path' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'timestamp' => date('c'),
        'available_api_prefixes' => [
            '/api' => 'General API endpoints',
            '/official-api' => 'Official application APIs',
            '/public-api' => 'Public access APIs'
        ]
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
```

## 🧪 验证测试

### 测试 API 端点

```bash
# 测试公共API
curl -v "http://localhost/public-api/articles?type=news"

# 测试官方API
curl -v "http://localhost/official-api/article-read/statistics"

# 测试不存在的API端点
curl -v "http://localhost/api/nonexistent"
```

### 期望的 JSON 响应格式

```json
{
    "success": false,
    "message": "API endpoint not found",
    "error_code": 404,
    "data": {
        "requested_path": "/api/nonexistent",
        "method": "GET",
        "timestamp": "2025-11-29T02:25:00+00:00",
        "available_api_prefixes": {
            "/api": "General API endpoints",
            "/official-api": "Official application APIs",
            "/public-api": "Public access APIs"
        }
    }
}
```

## 📋 部署检查清单

### 必需步骤

-   [ ] 修复 `ApiExceptionSubscriber.php` 中的 API 路径判断
-   [ ] 更新 Nginx 配置文件
-   [ ] 创建 API 404 处理器
-   [ ] 更新 `.htaccess` 重写规则（如果使用 Apache）

### 验证步骤

-   [ ] 测试所有 API 前缀路径 (`/api`, `/official-api`, `/public-api`)
-   [ ] 确认 404 错误返回 JSON 格式
-   [ ] 验证正常 API 请求仍然工作
-   [ ] 检查响应头包含正确的 `Content-Type: application/json`

### 监控建议

-   监控 API 404 错误率
-   记录无效 API 请求日志
-   定期检查 API 文档和实际路由的一致性

## 🚨 注意事项

1. **环境配置** - 确保生产环境中 `APP_DEBUG=false`
2. **缓存清理** - 修改配置后清理 Symfony 缓存：`php bin/console cache:clear`
3. **权限设置** - 确保 `public/` 目录有正确的写入权限
4. **日志监控** - 监控 Nginx 和 Symfony 错误日志

## 📞 故障排除

如果问题仍然存在：

1. 检查 Nginx 错误日志：`/var/log/nginx/error.log`
2. 检查 Symfony 日志：`var/log/prod.log`
3. 验证 PHP-FPM 状态：`systemctl status php8.3-fpm`
4. 测试 Symfony 路由：访问 `public/debug_routes.php`

---

**最后更新**: 2025-11-29  
**版本**: 1.0  
**状态**: 已完成
