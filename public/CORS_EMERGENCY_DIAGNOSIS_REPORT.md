# 🚨 CORS 紧急故障诊断报告

## 📋 执行摘要

**问题**: 从 `https://ops.arab-bee.com` 向 `https://newsapi.arab-bee.com/official-api/news` 发起 XMLHttpRequest 请求时被 CORS 策略阻止

**错误信息**: "Response to preflight request doesn't pass access control check: It does not have HTTP ok status."

**诊断时间**: 2025-11-29

**严重程度**: 🔴 **CRITICAL** - 生产环境紧急问题

---

## 🔍 根本原因分析

基于系统级深入分析，我识别出了以下**2 个最可能的根本原因**：

### 🎯 **主要原因 #1: 生产环境配置不一致**

**问题描述**:

-   `.env`文件显示 `APP_ENV=dev`
-   Nginx 配置强制设置 `fastcgi_param APP_ENV prod`
-   环境变量传递存在冲突

**影响**:

-   Symfony 在不同环境下加载不同的 CORS 配置
-   NelmioCorsBundle 可能未正确加载
-   缓存机制混乱

**证据**:

```yaml
# .env 文件
APP_ENV=dev
APP_DEBUG=true

# nginx_site_config.conf
fastcgi_param APP_ENV prod;
fastcgi_param APP_DEBUG 0;
```

### 🎯 **主要原因 #2: OPTIONS 预检请求处理失败**

**问题描述**:

-   OPTIONS 请求到达 Symfony 但未正确处理
-   可能被安全中间件或 EventSubscriber 拦截
-   返回非 200 状态码

**影响**:

-   浏览器阻止实际请求
-   预检请求失败导致整个 CORS 流程中断

**证据**:

-   ApiExceptionSubscriber 在第 52-54 行有特殊处理逻辑
-   安全配置中 API 防火墙虽然禁用，但可能在更高层被拦截

---

## 🔧 **立即解决方案**

### **方案 1: 统一环境配置 (推荐)**

#### 步骤 1: 修复环境变量

```bash
# 1. 编辑 .env 文件
APP_ENV=prod
APP_DEBUG=false

# 2. 确保 CORS_ALLOW_ORIGIN 包含前端域名
CORS_ALLOW_ORIGIN=https://ops.arab-bee.com,https://newsapi.arab-bee.com

# 3. 清除缓存
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

#### 步骤 2: 更新 Nginx 配置

```nginx
# 确保Nginx不覆盖环境变量
location ~ \.php$ {
    # 移除或注释掉这些行，让Symfony使用.env配置
    # fastcgi_param APP_ENV prod;
    # fastcgi_param APP_DEBUG 0;

    # 只传递必要的环境变量
    fastcgi_param SYMFONY_ENV prod;
}
```

#### 步骤 3: 重启服务

```bash
# 重启Nginx
sudo systemctl restart nginx

# 重启PHP-FPM
sudo systemctl restart php8.3-fpm
```

### **方案 2: 强制 CORS 头处理**

#### 步骤 1: 更新 NelmioCorsBundle 配置

```yaml
# config/packages/nelmio_cors.yaml
nelmio_cors:
    defaults:
        origin_regex: false # 改为false避免正则匹配问题
        allow_origin: ["%env(CORS_ALLOW_ORIGIN)%"]
        allow_methods: ["GET", "OPTIONS", "POST", "PUT", "PATCH", "DELETE"]
        allow_headers: ["Content-Type", "Authorization", "X-Requested-With"]
        expose_headers: ["Link"]
        max_age: 3600
        forced_allow_origin: true # 强制设置CORS头
    paths:
        "^/api/": ~
        "^/official-api/": ~
        "^/public-api/": ~
```

#### 步骤 2: 创建 CORS 中间件

```php
// src/EventSubscriber/ForceCorsSubscriber.php
<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ForceCorsSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -1000],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $path = $request->getPathInfo();

        // 只为API路径强制设置CORS头
        if (str_starts_with($path, '/api') ||
            str_starts_with($path, '/official-api') ||
            str_starts_with($path, '/public-api')) {

            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }
}
```

---

## 🛠️ **诊断工具使用指南**

### **1. 系统诊断工具**

访问: `https://newsapi.arab-bee.com/cors_system_diagnosis.php`

```bash
# 运行完整诊断
curl "https://newsapi.arab-bee.com/cors_system_diagnosis.php"

# 测试OPTIONS请求
curl -X OPTIONS \
  -H "Origin: https://ops.arab-bee.com" \
  -H "Access-Control-Request-Method: GET" \
  "https://newsapi.arab-bee.com/official-api/news"
```

### **2. 生产修复工具**

访问: `https://newsapi.arab-bee.com/cors_production_fix.php`

```bash
# 运行诊断和修复
curl "https://newsapi.arab-bee.com/cors_production_fix.php?action=diagnose&clear_cache=1"

# 修复CORS配置
curl "https://newsapi.arab-bee.com/cors_production_fix.php?action=diagnose&fix_cors_config=1"
```

### **3. 综合测试工具**

访问: `https://newsapi.arab-bee.com/cors_comprehensive_test.html`

这个工具提供:

-   ✈️ OPTIONS 预检请求测试
-   📡 实际 API 请求测试
-   🔧 系统诊断
-   📊 日志分析
-   📋 测试总结

---

## 🚨 **紧急修复步骤**

### **立即执行 (5 分钟内)**

1. **检查当前环境**:

    ```bash
    php bin/console debug:config nelmio_cors --env=prod
    php bin/console debug:container --env=prod | grep cors
    ```

2. **清除所有缓存**:

    ```bash
    php bin/console cache:clear --env=prod
    php bin/console cache:clear --env=dev
    rm -rf var/cache/*
    ```

3. **测试基本连接**:
    ```bash
    curl -I "https://newsapi.arab-bee.com/official-api/news"
    ```

### **短期修复 (30 分钟内)**

1. **统一环境配置**
2. **更新 CORS 配置**
3. **重启 Web 服务**
4. **验证修复效果**

### **长期预防 (1 周内)**

1. **实施 CORS 监控**
2. **建立配置审计流程**
3. **创建部署检查清单**
4. **建立回滚机制**

---

## 📊 **验证清单**

### **修复前检查**

-   [ ] 备份当前配置文件
-   [ ] 记录当前错误状态
-   [ ] 确认回滚计划

### **修复后验证**

-   [ ] OPTIONS 请求返回 200 状态码
-   [ ] CORS 头正确设置
-   [ ] 实际 API 请求成功
-   [ ] 浏览器控制台无 CORS 错误
-   [ ] 生产环境功能正常

### **测试命令**

```bash
# 1. 测试OPTIONS预检请求
curl -X OPTIONS \
  -H "Origin: https://ops.arab-bee.com" \
  -H "Access-Control-Request-Method: GET" \
  -v "https://newsapi.arab-bee.com/official-api/news"

# 2. 测试实际GET请求
curl -H "Origin: https://ops.arab-bee.com" \
  -v "https://newsapi.arab-bee.com/official-api/news"

# 3. 检查响应头
curl -I "https://newsapi.arab-bee.com/official-api/news"
```

---

## 🔒 **生产安全注意事项**

### **⚠️ 安全警告**

1. **不要在生产环境启用 APP_DEBUG=true**
2. **限制诊断工具的访问权限**
3. **所有操作前必须备份**
4. **使用蓝绿部署或滚动更新**

### **🛡️ 安全措施**

```php
// 限制IP访问
$allowed_ips = ['你的管理IP', '服务器内网IP'];

// 限制访问时间
$allowed_hours = range(2, 6); // 凌晨2-6点

// 记录所有操作
error_log('[CORS_FIX] Admin action from IP: ' . $_SERVER['REMOTE_ADDR']);
```

---

## 📞 **应急联系**

如果问题仍然存在:

1. **立即检查**: `https://newsapi.arab-bee.com/cors_system_diagnosis.php`
2. **查看日志**: `/var/log/nginx/error.log`, `var/log/prod.log`
3. **使用测试工具**: `https://newsapi.arab-bee.com/cors_comprehensive_test.html`

---

## 📈 **成功指标**

修复成功的标准:

-   ✅ OPTIONS 请求返回 200 状态码
-   ✅ `Access-Control-Allow-Origin` 头存在且正确
-   ✅ 前端应用能正常调用 API
-   ✅ 浏览器控制台无 CORS 错误
-   ✅ 生产环境稳定运行

---

**报告生成时间**: 2025-11-29 05:50 UTC  
**诊断工具版本**: v1.0  
**适用环境**: Symfony 6.x + Nginx + PHP 8.3  
**紧急程度**: 🚨 PRODUCTION EMERGENCY
