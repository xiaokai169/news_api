# CORS x-request-id 头部问题解决方案

## 问题描述

```
Access to XMLHttpRequest at 'https://newsapi.arab-bee.com/official-api/news?page=1&size=10&name=&status=&categoryCode=&isRecommend=' from origin 'https://ops.arab-bee.com' has been blocked by CORS policy: Request header field x-request-id is not allowed by Access-Control-Allow-Headers in preflight response.
```

## 根本原因分析

### 主要问题

1. **ForceCorsSubscriber 缺少 x-request-id 头部支持**
    - 位置：`src/EventSubscriber/ForceCorsSubscriber.php` 第 74 行
    - 原配置：`'Content-Type, Authorization, X-Requested-With, Accept, Origin'`
    - 缺少：`x-request-id` 和 `X-Request-ID`

### 次要问题

2. **CORS 处理器配置不一致**
    - NelmioCorsBundle 使用 `allow_headers: ['*']`
    - ForceCorsSubscriber 使用具体头部列表
    - 可能导致响应头冲突

## 解决方案

### 1. 修复 ForceCorsSubscriber（已完成）

```php
// 修改前：
$response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');

// 修改后：
$response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, x-request-id, X-Request-ID');
```

### 2. 部署步骤

#### 步骤 1：清除 Symfony 缓存

```bash
# 生产环境
php bin/console cache:clear --env=prod

# 开发环境
php bin/console cache:clear --env=dev
```

#### 步骤 2：验证 CORS 配置

访问诊断脚本：

```
https://newsapi.arab-bee.com/cors_x_request_id_diagnosis.php
```

#### 步骤 3：测试预检请求

使用 curl 测试 OPTIONS 请求：

```bash
curl -X OPTIONS \
  -H "Origin: https://ops.arab-bee.com" \
  -H "Access-Control-Request-Method: GET" \
  -H "Access-Control-Request-Headers: content-type, x-request-id" \
  -v \
  "https://newsapi.arab-bee.com/official-api/news"
```

### 3. 验证要点

#### 预检请求应该返回：

```
Access-Control-Allow-Origin: https://ops.arab-bee.com
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin, x-request-id, X-Request-ID
Access-Control-Max-Age: 3600
```

#### 日志应该显示：

```
[FORCE CORS] 包含x-request-id: 是
[FORCE CORS] Allow-Headers包含x-request-id: 是
```

## 诊断工具

### 1. 基础诊断

-   `public/cors_x_request_id_diagnosis.php` - 专门诊断 x-request-id 问题
-   `public/cors_preflight_validation.php` - 预检请求验证工具

### 2. 系统级诊断

-   `public/cors_system_diagnosis.php` - 全面 CORS 系统诊断

### 3. 实时日志监控

```bash
# 监控Symfony日志
tail -f var/log/prod.log | grep "FORCE CORS"

# 监控系统日志
tail -f /var/log/php_errors.log | grep "FORCE CORS"
```

## 如果问题仍然存在

### 检查清单

1. ✅ ForceCorsSubscriber 已修复
2. ✅ Symfony 缓存已清除
3. ❓ Nginx 配置是否冲突
4. ❓ 生产环境变量是否正确
5. ❓ 是否有其他 CORS 处理器覆盖

### 进一步排查

1. 检查 Nginx 配置中的 CORS 设置
2. 验证环境变量 `CORS_ALLOW_ORIGIN`
3. 确认只有一个 CORS 处理器在运行
4. 检查是否有 CDN 或负载均衡器添加 CORS 头

## 预防措施

1. 统一 CORS 配置管理
2. 定期检查预检请求
3. 监控 CORS 错误日志
4. 建立 CORS 测试用例

## 联系信息

如果问题持续存在，请提供：

1. 完整的错误日志
2. OPTIONS 请求的响应头
3. 生产环境的 Nginx 配置
4. Symfony 环境变量配置
