# 🚀 立即 CORS 测试指南

## 🎯 **问题确认**

您说"响应头里什么都没有"，现在我已经在 **`public/index.php` 入口级别** 直接设置了 CORS 头，这应该能确保所有响应都包含 CORS 头。

---

## 🧪 **立即测试步骤**

### **步骤 1: 测试直接脚本**

```bash
# 测试绕过 Symfony 的直接脚本
curl -I "https://newsapi.arab-bee.com/direct_cors_test.php"

# 测试 OPTIONS 请求
curl -X OPTIONS -I \
  -H "Origin: https://ops.arab-bee.com" \
  -H "Access-Control-Request-Method: GET" \
  "https://newsapi.arab-bee.com/direct_cors_test.php"
```

### **步骤 2: 测试 index.php 级别的修复**

```bash
# 测试官方 API（现在应该有 CORS 头）
curl -I "https://newsapi.arab-bee.com/official-api/news"

# 测试 OPTIONS 请求
curl -X OPTIONS -I \
  -H "Origin: https://ops.arab-bee.com" \
  -H "Access-Control-Request-Method: GET" \
  "https://newsapi.arab-bee.com/official-api/news"
```

### **步骤 3: 浏览器测试**

访问: `https://newsapi.arab-bee.com/direct_cors_test.php`

---

## 📊 **预期结果**

### **直接脚本应该返回**:

```http
HTTP/1.1 200 OK
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
Access-Control-Max-Age: 3600
Content-Type: application/json
```

### **官方 API 应该返回**:

```http
HTTP/1.1 200 OK
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
Access-Control-Max-Age: 3600
Content-Type: application/json
```

---

## 🔧 **如果仍然没有 CORS 头**

### **可能性 1: 宝塔面板覆盖**

宝塔面板可能在更高层覆盖了响应头。检查：

1. **宝塔面板** → **网站** → **newsapi.arab-bee.com** → **设置**
2. **配置文件** → 查看是否有冲突的 header 设置
3. **伪静态** → 检查是否有 header 规则

### **可能性 2: PHP-FPM 配置**

检查 PHP-FPM 配置是否禁用了 header 函数：

```bash
# 检查 PHP 配置
php -i | grep disable_functions
```

### **可能性 3: 输出缓冲**

可能存在输出缓冲问题。在 `public/index.php` 中添加：

```php
// 在设置 header 之前清空缓冲
if (ob_get_level()) {
    ob_end_clean();
}

// 然后设置 header
header('Access-Control-Allow-Origin: *');
```

---

## 🚨 **紧急备用方案**

如果以上方法都不行，创建一个 `.htaccess` 文件：

```apache
# 在 public/.htaccess 中添加
<IfModule mod_headers.c>
    Header always set Access-Control-Allow-Origin "*"
    Header always set Access-Control-Allow-Methods "GET, POST, PUT, PATCH, DELETE, OPTIONS"
    Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With, Accept, Origin"
    Header always set Access-Control-Max-Age "3600"
</IfModule>
```

---

## 📋 **验证清单**

测试完成后确认：

-   [ ] `direct_cors_test.php` 返回 CORS 头
-   [ ] `official-api/news` 返回 CORS 头
-   [ ] OPTIONS 请求返回 200 状态码
-   [ ] 浏览器开发者工具能看到 CORS 头
-   [ ] 前端应用能正常调用 API

---

## 📞 **下一步**

1. **立即测试**: 使用上面的 curl 命令
2. **检查结果**: 查看响应头是否包含 CORS
3. **报告结果**: 告诉我测试结果

如果直接脚本有 CORS 头但官方 API 没有，说明 Symfony 层面有问题。
如果都没有 CORS 头，说明宝塔面板配置有问题。

---

**立即测试版本**: v1.0  
**测试时间**: 2025-11-29 15:37  
**修复级别**: 🚨 ENTRY LEVEL FIX  
**紧急程度**: 🔥 CRITICAL
