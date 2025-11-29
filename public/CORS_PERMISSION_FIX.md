# 🔧 CORS 权限问题紧急修复指南

## 🚨 **问题诊断**

您遇到的错误：

```json
{
    "success": false,
    "message": "Warning: file_put_contents(/www/wwwroot/newsapi.arab-bee.com/src/EventSubscriber/../../public/cors_debug.log): Failed to open stream: Permission denied"
}
```

**根本原因**: [`CorsDebugSubscriber.php`](src/EventSubscriber/CorsDebugSubscriber.php) 尝试写入日志文件但 PHP 进程没有写入权限。

---

## 🛠️ **立即解决方案**

### **方案 1: 已实施的临时修复**

我已经创建了 [`ProductionCorsSubscriber.php`](src/EventSubscriber/ProductionCorsSubscriber.php) 并更新了 [`config/services.yaml`](config/services.yaml):

-   ✅ 禁用了有权限问题的 `CorsDebugSubscriber`
-   ✅ 启用了 `ProductionCorsSubscriber`（不写文件日志）
-   ✅ 保持了完整的 CORS 功能

### **方案 2: 宝塔面板权限修复（可选）**

如果您需要保留调试日志功能，在宝塔面板中修复权限：

#### **步骤 1: 设置目录权限**

```bash
# 在宝塔面板的终端中执行
chmod 755 /www/wwwroot/newsapi.arab-bee.com/public
chmod 644 /www/wwwroot/newsapi.arab-bee.com/public/cors_debug.log
chown www:www /www/wwwroot/newsapi.arab-bee.com/public/cors_debug.log
```

#### **步骤 2: 宝塔面板操作**

1. 进入 **文件管理**
2. 找到 `/www/wwwroot/newsapi.arab-bee.com/public/` 目录
3. 设置权限为 `755`
4. 如果存在 `cors_debug.log` 文件，设置权限为 `644`

---

## 🧪 **立即测试**

### **测试 CORS 功能是否正常**

```bash
# 测试 OPTIONS 预检请求
curl -X OPTIONS \
  -H "Origin: https://ops.arab-bee.com" \
  -H "Access-Control-Request-Method: GET" \
  -v "https://newsapi.arab-bee.com/official-api/news"

# 测试 GET 请求
curl -H "Origin: https://ops.arab-bee.com" \
  -v "https://newsapi.arab-bee.com/official-api/news"
```

### **测试宝塔专用脚本**

```bash
# 测试基础 CORS 脚本
curl -X OPTIONS \
  -H "Origin: https://ops.arab-bee.com" \
  -H "Access-Control-Request-Method: GET" \
  -v "https://newsapi.arab-bee.com/baota_cors_test.php"
```

---

## 📊 **预期结果**

修复后应该看到：

### **OPTIONS 请求响应头**

```http
HTTP/1.1 200 OK
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin, X-Custom-Header
Access-Control-Max-Age: 3600
Content-Type: application/json
```

### **GET 请求响应头**

```http
HTTP/1.1 200 OK
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin, X-Custom-Header
Content-Type: application/json
```

---

## 🔧 **服务重启**

在宝塔面板中执行：

1. **重启 PHP-8.2**

    - 软件商店 → PHP-8.2 → 重启

2. **清除 OPcache**

    - PHP-8.2 → 性能调整 → 清除 OPcache

3. **重启 Nginx**
    - 软件商店 → Nginx → 重启

---

## 📝 **日志监控**

现在日志会写入到系统日志而不是文件：

### **查看系统日志**

```bash
# 宝塔面板中查看
tail -f /www/wwwlogs/newsapi.arab-bee.com.error.log

# 或者查看系统日志
grep "PROD CORS" /var/log/syslog
```

### **关键日志标识**

```
[PROD CORS] Handling OPTIONS request for path: /official-api/news
[PROD CORS] Set CORS headers for path: /official-api/news, Status: 200
```

---

## 🚨 **如果仍然有问题**

### **紧急备用方案**

如果上述方案仍有问题，在 `public/index.php` 入口处添加：

```php
<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

// 🔧 紧急 CORS 修复
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
    http_response_code(200);
    exit;
}

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
}

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
```

---

## ✅ **验证清单**

修复完成后确认：

-   [ ] OPTIONS 请求返回 200 状态码
-   [ ] 响应包含正确的 CORS 头
-   [ ] 没有 Permission denied 错误
-   [ ] 前端应用能正常调用 API
-   [ ] 浏览器控制台无 CORS 错误

---

**权限修复版本**: v1.0  
**修复时间**: 2025-11-29 15:26  
**适用环境**: 宝塔面板 + PHP 8.2  
**紧急程度**: 🚨 IMMEDIATE FIX REQUIRED
