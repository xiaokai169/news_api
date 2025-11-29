# 🔧 宝塔面板环境变量修复指南

## 🚨 **问题诊断**

您遇到的环境变量问题：

```json
{
    "environment": {
        "APP_ENV": "not_set",
        "APP_DEBUG": "not_set",
        "CORS_ALLOW_ORIGIN": "not_set"
    },
    "issues": ["WARNING: CORS_ALLOW_ORIGIN environment variable not set"]
}
```

**根本原因**: 宝塔面板的 PHP-FPM 没有正确传递环境变量到 Symfony 应用。

---

## 🛠️ **解决方案**

### **方案 1: 已实施的代码级修复**

我已经更新了代码来处理环境变量缺失：

1. **更新了 [`ProductionCorsSubscriber.php`](src/EventSubscriber/ProductionCorsSubscriber.php)**

    - ✅ 自动检测环境变量缺失
    - ✅ 使用 `*` 作为回退值
    - ✅ 记录到系统日志

2. **创建了 [`env_fix_diagnosis.php`](public/env_fix_diagnosis.php)**
    - ✅ 诊断环境变量传递问题
    - ✅ 临时强制设置环境变量
    - ✅ 测试 CORS 功能

### **方案 2: 宝塔面板配置修复（推荐）**

#### **步骤 1: 检查 PHP-FPM 配置**

在宝塔面板中：

1. **软件商店** → **PHP-8.2** → **配置修改**
2. 查看 `www.conf` 文件
3. 确认以下配置：

```ini
; 环境变量传递
clear_env = no
env[APP_ENV] = prod
env[APP_DEBUG] = false
env[CORS_ALLOW_ORIGIN] = *
```

#### **步骤 2: 修改宝塔面板网站配置**

在宝塔面板中：

1. **网站** → **newsapi.arab-bee.com** → **设置**
2. **PHP 版本** → **配置文件**
3. 添加环境变量：

```ini
fastcgi_param APP_ENV prod;
fastcgi_param APP_DEBUG false;
fastcgi_param CORS_ALLOW_ORIGIN *;
```

#### **步骤 3: 创建 .env.local 文件**

在项目根目录创建 `.env.local`：

```bash
# 在宝塔面板文件管理中创建
APP_ENV=prod
APP_DEBUG=false
CORS_ALLOW_ORIGIN=*
```

---

## 🧪 **立即测试**

### **测试环境变量修复**

```bash
# 测试诊断脚本
curl "https://newsapi.arab-bee.com/env_fix_diagnosis.php"

# 测试 OPTIONS 请求
curl -X OPTIONS \
  -H "Origin: https://ops.arab-bee.com" \
  -H "Access-Control-Request-Method: GET" \
  -v "https://newsapi.arab-bee.com/env_fix_diagnosis.php"
```

### **测试实际 API**

```bash
# 测试官方 API
curl -X OPTIONS \
  -H "Origin: https://ops.arab-bee.com" \
  -H "Access-Control-Request-Method: GET" \
  -v "https://newsapi.arab-bee.com/official-api/news"
```

---

## 📊 **预期结果**

### **修复后的环境变量**

```json
{
    "environment": {
        "APP_ENV": "prod",
        "APP_DEBUG": "false",
        "CORS_ALLOW_ORIGIN": "*"
    },
    "issues": [],
    "summary": {
        "total_issues": 0,
        "critical_issues": 0,
        "warnings": 0
    }
}
```

### **CORS 响应头**

```http
HTTP/1.1 200 OK
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
Access-Control-Max-Age: 3600
```

---

## 🔧 **宝塔面板详细操作步骤**

### **方法 1: 通过宝塔面板 UI**

1. **登录宝塔面板**
2. **网站** → 找到 `newsapi.arab-bee.com`
3. **设置** → **PHP 版本** → **配置文件**
4. 在配置文件末尾添加：
    ```ini
    env[APP_ENV] = prod
    env[APP_DEBUG] = false
    env[CORS_ALLOW_ORIGIN] = *
    ```
5. **保存**并**重启 PHP-8.2**

### **方法 2: 通过文件管理**

1. **文件管理** → 进入 `/www/server/php/82/etc/php-fpm.d/`
2. 编辑 `www.conf` 文件
3. 找到 `clear_env = yes` 改为 `clear_env = no`
4. 添加环境变量：
    ```ini
    env[APP_ENV] = prod
    env[APP_DEBUG] = false
    env[CORS_ALLOW_ORIGIN] = *
    ```
5. **保存**并**重启 PHP-8.2**

### **方法 3: 通过 SSH（推荐）**

```bash
# SSH 到服务器
ssh root@your-server

# 编辑 PHP-FPM 配置
nano /www/server/php/82/etc/php-fpm.d/www.conf

# 修改以下配置
clear_env = no
env[APP_ENV] = prod
env[APP_DEBUG] = false
env[CORS_ALLOW_ORIGIN] = *

# 重启 PHP-FPM
systemctl restart php-fpm-82
```

---

## 🚨 **紧急备用方案**

如果上述方法都不行，在 `public/index.php` 入口强制设置：

```php
<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

// 🔧 强制设置环境变量
$_ENV['APP_ENV'] = 'prod';
$_ENV['APP_DEBUG'] = 'false';
$_ENV['CORS_ALLOW_ORIGIN'] = '*';

putenv('APP_ENV=prod');
putenv('APP_DEBUG=false');
putenv('CORS_ALLOW_ORIGIN=*');

// 🔧 CORS 处理
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

## 📝 **验证清单**

修复完成后确认：

-   [ ] 环境变量正确传递到 PHP
-   [ ] `env_fix_diagnosis.php` 显示正确的环境变量
-   [ ] OPTIONS 请求返回 200 状态码
-   [ ] CORS 头正确设置
-   [ ] 前端应用能正常调用 API
-   [ ] 浏览器控制台无 CORS 错误

---

## 📞 **技术支持**

### **诊断工具**

1. **环境变量诊断**: `https://newsapi.arab-bee.com/env_fix_diagnosis.php`
2. **CORS 测试**: `https://newsapi.arab-bee.com/baota_cors_test.php`
3. **综合测试**: `https://newsapi.arab-bee.com/cors_diagnostic_test.html`

### **日志监控**

```bash
# 查看系统日志
tail -f /www/wwwlogs/newsapi.arab-bee.com.error.log | grep "PROD CORS"

# 查看 PHP-FPM 日志
tail -f /www/server/php/82/var/log/php-fpm.log
```

---

**环境变量修复版本**: v1.0  
**修复时间**: 2025-11-29 15:29  
**适用环境**: 宝塔面板 + PHP 8.2 + Symfony 6.x  
**紧急程度**: 🚨 ENVIRONMENT CRITICAL
