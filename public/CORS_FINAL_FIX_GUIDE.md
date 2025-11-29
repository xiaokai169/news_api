# 🔧 CORS 跨域问题最终修复指南

## 📋 问题诊断总结

基于深入分析，我们识别出了以下**2 个根本原因**：

### 🎯 **主要原因 #1: 环境配置冲突**

-   **问题**: `.env` 文件设置 `APP_ENV=dev`，但 Nginx 配置强制设置 `APP_ENV=prod`
-   **影响**: Symfony 在不同环境下加载不同的 CORS 配置，导致配置混乱

### 🎯 **主要原因 #2: OPTIONS 预检请求处理失败**

-   **问题**: OPTIONS 请求可能被中间件拦截或处理不当
-   **影响**: 预检请求返回非 200 状态码，导致浏览器阻止实际请求

---

## 🛠️ **立即修复方案**

### **步骤 1: 统一环境配置**

#### 1.1 修复 `.env` 文件

```bash
# 编辑 .env 文件
APP_ENV=prod
APP_DEBUG=false

# 设置具体的允许域名（生产环境推荐）
CORS_ALLOW_ORIGIN=https://ops.arab-bee.com,https://newsapi.arab-bee.com
```

#### 1.2 更新 Nginx 配置

```nginx
# 在 nginx_site_config.conf 中注释掉环境变量覆盖
# fastcgi_param APP_ENV prod;
# fastcgi_param APP_DEBUG 0;

# 或者设置为与 .env 一致
fastcgi_param APP_ENV prod;
fastcgi_param APP_DEBUG false;
```

### **步骤 2: 优化 CORS 配置**

我们已经更新了 [`config/packages/nelmio_cors.yaml`](config/packages/nelmio_cors.yaml)：

```yaml
nelmio_cors:
    defaults:
        origin_regex: false # 避免正则匹配问题
        allow_origin: ["%env(CORS_ALLOW_ORIGIN)%"] # 使用环境变量
        allow_methods: ["GET", "OPTIONS", "POST", "PUT", "PATCH", "DELETE"]
        allow_headers:
            [
                "Content-Type",
                "Authorization",
                "X-Requested-With",
                "Accept",
                "Origin",
            ]
        expose_headers: ["Link", "X-Pagination"]
        max_age: 3600
        hosts: []
        allow_credentials: false
        forced_allow_origin_value: null
        skip_same_as_origin: true
    paths:
        "^/api/": ~
        "^/official-api/": ~
        "^/public-api/": ~
```

### **步骤 3: 启用强制 CORS 备用方案**

我们创建了 [`ForceCorsSubscriber`](src/EventSubscriber/ForceCorsSubscriber.php) 作为备用方案：

```php
// 自动处理 OPTIONS 预检请求
// 确保所有 API 响应都包含正确的 CORS 头
```

### **步骤 4: 清除缓存并重启服务**

```bash
# 清除 Symfony 缓存
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# 重启服务
sudo systemctl restart nginx
sudo systemctl restart php8.3-fpm
```

---

## 🧪 **验证修复效果**

### **测试工具**

1. **Bundle 配置诊断**: `https://newsapi.arab-bee.com/cors_bundle_diagnosis.php`
2. **OPTIONS 预检测试**: `https://newsapi.arab-bee.com/options_preflight_test.php`
3. **综合诊断页面**: `https://newsapi.arab-bee.com/cors_diagnostic_test.html`

### **手动测试命令**

```bash
# 1. 测试 OPTIONS 预检请求
curl -X OPTIONS \
  -H "Origin: https://ops.arab-bee.com" \
  -H "Access-Control-Request-Method: GET" \
  -v "https://newsapi.arab-bee.com/official-api/news"

# 2. 测试实际 GET 请求
curl -H "Origin: https://ops.arab-bee.com" \
  -v "https://newsapi.arab-bee.com/official-api/news"

# 3. 检查响应头
curl -I "https://newsapi.arab-bee.com/official-api/news"
```

### **预期结果**

✅ **OPTIONS 请求**: 返回 200 状态码，包含正确的 CORS 头  
✅ **GET 请求**: 返回 200 状态码，包含 `Access-Control-Allow-Origin` 头  
✅ **浏览器控制台**: 无 CORS 错误  
✅ **前端应用**: 能正常调用 API

---

## 🔍 **调试日志监控**

### **关键日志位置**

1. **PHP 错误日志**: `/var/log/php8.3-fpm.log` 或 `var/log/prod.log`
2. **Nginx 错误日志**: `/var/log/nginx/error.log`
3. **CORS 调试日志**: `public/cors_debug.log`（如果启用调试模式）

### **关键日志标识符**

查找以下日志来确认修复效果：

```
[CORS DEBUG] ENVIRONMENT CHECK
[CORS DEBUG] OPTIONS REQUEST DETECTED
[CORS DEBUG] RESPONSE HEADERS
[FORCE CORS] Handling OPTIONS request
[FORCE CORS] Set CORS headers
```

---

## 🚨 **如果问题仍然存在**

### **紧急回滚方案**

1. **临时禁用强制 CORS**:

    ```php
    // 在 config/services.yaml 中注释掉 ForceCorsSubscriber
    # App\EventSubscriber\ForceCorsSubscriber:
    #     tags:
    #         - { name: kernel.event_subscriber }
    ```

2. **检查 Nginx 配置冲突**:

    ```nginx
    # 确保没有重复的 CORS 头设置
    # add_header Access-Control-Allow-Origin "*";  # 注释掉
    ```

3. **验证环境变量**:
    ```bash
    php -r "echo 'APP_ENV: ' . getenv('APP_ENV') . PHP_EOL;"
    php -r "echo 'CORS_ALLOW_ORIGIN: ' . getenv('CORS_ALLOW_ORIGIN') . PHP_EOL;"
    ```

### **进一步诊断**

如果问题仍然存在，请访问以下诊断工具：

1. **完整系统诊断**: `https://newsapi.arab-bee.com/cors_system_diagnosis.php`
2. **Bundle 配置检查**: `https://newsapi.arab-bee.com/cors_bundle_diagnosis.php`
3. **综合测试页面**: `https://newsapi.arab-bee.com/cors_diagnostic_test.html`

---

## 📊 **成功指标**

修复成功的标准：

-   ✅ OPTIONS 请求返回 200 状态码
-   ✅ `Access-Control-Allow-Origin` 头存在且正确
-   ✅ `Access-Control-Allow-Methods` 和 `Access-Control-Allow-Headers` 正确设置
-   ✅ 前端应用能正常调用 API
-   ✅ 浏览器控制台无 CORS 错误
-   ✅ 生产环境稳定运行

---

## 🔒 **生产安全注意事项**

### **⚠️ 重要提醒**

1. **不要在生产环境启用 `APP_DEBUG=true`**
2. **限制诊断工具的访问权限**（建议仅内网访问）
3. **定期检查 CORS 配置**，避免过度开放
4. **监控错误日志**，及时发现异常

### **🛡️ 安全建议**

```yaml
# 生产环境推荐配置
CORS_ALLOW_ORIGIN=https://ops.arab-bee.com,https://newsapi.arab-bee.com
# 而不是 CORS_ALLOW_ORIGIN=*
```

---

## 📞 **技术支持**

如果按照本指南操作后问题仍然存在：

1. **检查所有日志文件**
2. **运行完整的诊断工具**
3. **确认 Nginx 和 PHP-FPM 服务状态**
4. **验证环境变量传递**

---

**修复指南版本**: v1.0  
**最后更新**: 2025-11-29  
**适用环境**: Symfony 6.x + Nginx + PHP 8.3  
**紧急程度**: 🚨 PRODUCTION FIX
