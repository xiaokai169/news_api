# 生产环境配置部署指南

## 概述

本文档说明如何使用 `.env.prod` 配置文件在宝塔面板环境中部署 Symfony 项目。

## 快速部署步骤

### 1. 环境准备

```bash
# 确保PHP版本 >= 8.2
php -v

# 检查必需的PHP扩展
php -m | grep -E "(pdo_mysql|ctype|iconv|json|openssl|mbstring|tokenizer|xml)"
```

### 2. 配置文件部署

```bash
# 复制生产环境配置
cp .env.prod .env.local

# 设置文件权限
chmod 600 .env.local
```

### 3. 依赖安装

```bash
# 生产环境安装
composer install --no-dev --optimize-autoloader --no-progress --no-interaction
```

### 4. 缓存清理和预热

```bash
# 清理生产环境缓存
php bin/console cache:clear --env=prod --no-warmup

# 预热缓存
php bin/console cache:warmup --env=prod
```

### 5. 数据库迁移

```bash
# 执行数据库迁移
php bin/console doctrine:migrations:migrate --env=prod --no-interaction
```

## 当前配置说明

### 基础配置

-   **环境**: `prod`
-   **调试模式**: `false`
-   **域名**: `https://newsapi.arab-bee.com/`
-   **应用密钥**: `your_64_character_hex_secret_key_here_minimum_length` (✅ 已修复为安全占位符)

### 数据库配置

-   **主数据库**: `official_website`
-   **用户数据库**: `official_website_users` (✅ 已统一数据库名称)
-   **主数据库用户名**: `biz`
-   **用户数据库用户名**: `user_biz` (✅ 已分离用户权限)
-   **密码**: `your_secure_db_password_here` (✅ 已修复为安全占位符)
-   **服务器**: `127.0.0.1:3306`
-   **版本**: `MySQL 8.0`

### CORS 配置

-   **当前设置**: `https://newsapi.arab-bee.com,https://www.newsapi.arab-bee.com` (✅ 已修复为限制特定域名)
-   **安全状态**: 安全，仅允许授权域名访问

### JWT 配置

-   **密钥密码**: `your_secure_jwt_passphrase_here_minimum_32_characters` (✅ 已修复为安全占位符)
-   **Token 有效期**: 1 小时
-   **刷新 Token 有效期**: 7 天

### 增强安全配置 (✅ 新增)

-   **Session 安全**: Secure, HttpOnly, SameSite=Strict
-   **CSRF 保护**: 已启用
-   **请求频率限制**: 100 请求/小时
-   **安全头部**: X-Frame-Options, X-Content-Type-Options, XSS 保护
-   **内容安全策略**: 已配置
-   **数据库加密**: 已启用
-   **API 访问控制**: 已启用
-   **文件上传安全**: 已配置
-   **备份加密**: 已启用
-   **审计日志**: 已启用
-   **安全监控**: 已启用

## 安全建议

### 🚨 严重安全问题修复 - 必须立即执行

根据安全验证报告，发现了以下严重安全问题，需要立即修复：

#### 1. 硬编码敏感信息修复

**问题**: 发现硬编码的数据库密码和 JWT 密钥密码
**修复步骤**:

```bash
# 生成强数据库密码 (16位以上)
openssl rand -base64 16

# 生成新的APP_SECRET (64位十六进制)
php -r 'echo bin2hex(random_bytes(32));'

# 生成强JWT密钥密码 (32位以上)
openssl rand -base64 32

# 生成数据库加密密钥 (32字符)
openssl rand -base64 24

# 生成备份加密密钥 (32字符)
openssl rand -base64 24
```

**配置更新**:

-   数据库密码: 将 `your_secure_db_password_here` 替换为生成的强密码
-   用户数据库密码: 将 `your_secure_user_db_password_here` 替换为生成的强密码
-   JWT 密钥密码: 将 `your_secure_jwt_passphrase_here_minimum_32_characters` 替换为生成的强密码
-   APP_SECRET: 将 `your_64_character_hex_secret_key_here_minimum_length` 替换为生成的 64 位十六进制密钥

#### 2. CORS 安全配置修复

**问题**: CORS 配置允许所有域名访问 (`*`)
**修复**: 已限制为特定域名

```bash
# 已修复的配置
CORS_ALLOW_ORIGIN=https://newsapi.arab-bee.com,https://www.newsapi.arab-bee.com
```

#### 3. 数据库配置统一

**问题**: 数据库名称不一致
**修复**: 已统一为 `official_website` 和 `official_website_users`

#### 4. 增强安全配置

**新增安全配置项**:

-   Session 安全配置 (Secure, HttpOnly, SameSite)
-   CSRF 保护启用
-   请求频率限制
-   安全头部配置
-   内容安全策略 (CSP)
-   数据库加密
-   API 访问控制
-   文件上传安全
-   备份加密
-   审计日志
-   安全监控

### 1. 完整的安全检查清单

部署前必须完成以下安全检查：

#### 🔒 密码和密钥安全

-   [ ] 所有硬编码密码已替换为强密码
-   [ ] APP_SECRET 使用 64 位十六进制随机密钥
-   [ ] JWT_PASSPHRASE 使用 32 位以上强密码
-   [ ] 数据库密码强度验证 (16 位以上，包含大小写字母、数字、特殊字符)
-   [ ] 所有加密密钥已生成并配置

#### 🌐 网络安全

-   [ ] CORS 配置限制为授权域名
-   [ ] SSL/TLS 证书已配置
-   [ ] 安全头部已启用
-   [ ] 内容安全策略已配置

#### 🛡️ 应用安全

-   [ ] Session 安全配置正确
-   [ ] CSRF 保护已启用
-   [ ] 请求频率限制已配置
-   [ ] 文件上传安全限制已设置
-   [ ] 数据库加密已启用

#### 📊 监控和审计

-   [ ] 审计日志已启用
-   [ ] 安全监控已配置
-   [ ] 错误报告已设置
-   [ ] 备份加密已启用

### 2. JWT 密钥生成

```bash
# 生成新的JWT密钥对
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout

# 设置文件权限
chmod 600 config/jwt/private.pem
chmod 644 config/jwt/public.pem
```

### 3. 安全验证命令

```bash
# 检查Symfony安全状态
php bin/console security:check

# 验证安全头部
curl -I https://yourdomain.com -k | grep -E "(Strict-Transport-Security|X-Frame-Options|X-Content-Type-Options)"

# 测试CORS配置
curl -H "Origin: https://unauthorized-domain.com" -H "Access-Control-Request-Method: GET" -X OPTIONS https://yourdomain.com/api
```

## 宝塔面板配置

### 1. PHP 设置

-   PHP 版本: 8.2+
-   必需扩展: pdo_mysql, ctype, iconv, json, openssl, mbstring, tokenizer, xml
-   OPcache: 启用，内存 128MB

### 2. 网站设置

-   域名: `newsapi.arab-bee.com`
-   根目录: `/www/wwwroot/newsapi.arab-bee.com`
-   SSL 证书: 启用 HTTPS
-   伪静态: 选择 Symfony 规则

### 3. Nginx 配置

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ ^/index\.php(/|$) {
    fastcgi_pass unix:/tmp/php-cgi-82.sock;
    fastcgi_split_path_info ^(.+\.php)(/.*)$;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param HTTPS on;
    internal;
}
```

## 监控和维护

### 1. 日志监控

```bash
# 查看应用日志
tail -f var/log/prod.log

# 查看Nginx日志
tail -f /www/server/nginx/logs/access.log
```

### 2. 性能监控

```bash
# 检查PHP-FPM状态
php -v

# 检查数据库连接
php bin/console doctrine:database:import --env=prod
```

### 3. 定期维护

-   每周检查日志文件大小
-   每月更新依赖包
-   每季度轮换密钥
-   定期备份数据库

## 故障排除

### 常见问题

1. **500 错误**: 检查文件权限和 PHP 扩展
2. **数据库连接失败**: 验证数据库配置和密码
3. **CORS 错误**: 检查 CORS_ALLOW_ORIGIN 设置
4. **JWT 认证失败**: 验证密钥文件和密码
5. **安全头部错误**: 检查 Nginx 配置中的安全头部设置
6. **CSRF 错误**: 确保表单包含 CSRF token
7. **请求频率限制**: 检查 RATE_LIMIT 配置
8. **文件上传失败**: 验证文件类型和大小限制

### 安全相关故障排除

#### 密码和密钥问题

```bash
# 验证APP_SECRET格式
php -r "echo strlen(bin2hex(random_bytes(32))) . PHP_EOL;"  # 应该输出64

# 检查JWT密钥文件
ls -la config/jwt/
openssl rsa -in config/jwt/private.pem -check
```

#### CORS 问题调试

```bash
# 测试CORS配置
curl -H "Origin: https://newsapi.arab-bee.com" \
     -H "Access-Control-Request-Method: GET" \
     -X OPTIONS https://newsapi.arab-bee.com/api

# 测试未授权域名
curl -H "Origin: https://malicious-site.com" \
     -H "Access-Control-Request-Method: GET" \
     -X OPTIONS https://newsapi.arab-bee.com/api
```

#### 安全头部验证

```bash
# 检查安全头部
curl -I https://newsapi.arab-bee.com | grep -E "(X-Frame-Options|X-Content-Type-Options|Strict-Transport-Security)"
```

### 调试命令

```bash
# 检查环境配置
php bin/console about --env=prod

# 验证路由
php bin/console debug:router --env=prod

# 检查服务
php bin/console debug:container --env=prod

# 检查安全配置
php bin/console debug:config security --env=prod

# 验证数据库连接
php bin/console doctrine:database:import --env=prod

# 检查JWT配置
php bin/console debug:config lexik_jwt_authentication --env=prod
```

### 安全监控命令

```bash
# 检查应用日志中的安全事件
grep -i "security\|csrf\|unauthorized" var/log/prod.log

# 监控访问日志中的异常请求
tail -f /www/server/nginx/logs/access.log | grep -E "(401|403|429)"

# 检查文件完整性
find . -type f -name "*.php" -exec md5sum {} \; > checksums.txt
```

## 联系支持

如遇问题，请检查：

1. 服务器日志
2. 应用日志
3. PHP 错误日志
4. Nginx 访问日志

---

**注意**: 生产环境部署前请务必完成所有安全配置项！
