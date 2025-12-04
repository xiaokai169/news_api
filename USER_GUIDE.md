# 用户使用指南

## 概述

本指南将帮助您快速诊断和修复 Symfony API 应用中的问题，包括数据库连接、Web 服务器配置和 API 响应等问题。

## 问题诊断流程

### 快速诊断步骤

1. **检查当前状态**

    ```bash
    # 运行快速状态检查
    php public/quick_db_test.php
    ```

2. **运行完整诊断**

    ```bash
    # 运行端到端测试
    php tests/end_to_end_test.php --verbose
    ```

3. **查看具体错误**
    ```bash
    # 查看详细错误信息
    php public/test_controller_with_logging.php
    ```

## 修复方案

### 1. 数据库问题修复

#### 密码错误修复

如果遇到数据库密码错误，请按以下步骤操作：

1. **验证当前密码**

    ```bash
    php public/db_connection_checker.php
    ```

2. **更新数据库配置**
   编辑 `.env` 文件，确保密码正确：

    ```
    DATABASE_URL="mysql://root:qwe147258..@127.0.0.1:3306/official_website"
    ```

3. **测试连接**
    ```bash
    php public/database_connection_test.php
    ```

#### 数据库结构修复

```bash
# 重新创建数据库结构
php public/init_database.php

# 验证数据库结构
php tests/database_validation.php --verbose
```

### 2. Symfony 应用修复

#### 自动修复（推荐）

```bash
# Linux/Mac用户
./scripts/symfony_fix_scripts.sh full

# Windows用户
scripts\symfony_fix_windows.bat full
```

#### 手动修复步骤

1. **清除缓存**

    ```bash
    php bin/console cache:clear --env=prod
    php bin/console cache:warmup --env=prod
    ```

2. **修复权限**

    ```bash
    # Linux/Mac
    chmod -R 777 var/
    chmod +x bin/console

    # Windows（以管理员身份运行）
    icacls var /grant Everyone:(OI)(CI)F /T
    ```

3. **验证应用**
    ```bash
    php bin/console about
    php bin/console debug:router
    ```

### 3. Web 服务器配置修复

#### Nginx 配置修复

1. **检查当前配置**

    ```bash
    sudo nginx -t
    sudo nginx -T
    ```

2. **应用修复配置**
   参考 [`WEBSERVER_FIX_GUIDE.md`](WEBSERVER_FIX_GUIDE.md) 中的配置模板

3. **重启服务**
    ```bash
    sudo systemctl reload nginx
    sudo systemctl restart php8.1-fpm
    ```

#### 宝塔面板配置

1. **检查站点配置**

    - 登录宝塔面板
    - 找到对应站点
    - 检查配置文件是否包含 Symfony 路由规则

2. **修复路径权限**
    ```bash
    # 在宝塔面板中设置
    # 网站目录：/www/wwwroot/your-domain.com/public
    # 运行目录：/public
    # PHP版本：8.1或更高
    ```

## API 使用指南

### 基础 API 调用

#### 获取所有分类

```bash
curl -X GET http://your-domain.com/api/sys-news-article-categories
```

#### 创建新分类

```bash
curl -X POST http://your-domain.com/api/sys-news-article-categories \
  -H "Content-Type: application/json" \
  -d '{"code":"TEST_001","name":"测试分类"}'
```

#### 更新分类

```bash
curl -X PUT http://your-domain.com/api/sys-news-article-categories/1 \
  -H "Content-Type: application/json" \
  -d '{"name":"更新后的分类名"}'
```

#### 删除分类

```bash
curl -X DELETE http://your-domain.com/api/sys-news-article-categories/1
```

### API 测试工具

#### 使用内置测试脚本

```bash
# 基础API测试
php tests/api_endpoints_test.php --url=http://your-domain.com --verbose

# 完整集成测试
php tests/end_to_end_test.php --url=http://your-domain.com --verbose
```

#### 使用浏览器测试

访问以下 URL 进行测试：

-   `http://your-domain.com/api/sys-news-article-categories` - 获取所有分类
-   `http://your-domain.com/api/doc` - API 文档（如果已配置）

## 故障排除

### 常见问题及解决方案

#### 1. 404 Not Found 错误

**症状**：访问 API 返回 404 错误

**解决方案**：

```bash
# 1. 检查Nginx配置
sudo nginx -t

# 2. 检查Symfony路由
php bin/console debug:router

# 3. 验证控制器文件
ls -la src/Controller/SysNewsArticleCategoryController.php
```

#### 2. 500 Internal Server Error

**症状**：服务器内部错误

**解决方案**：

```bash
# 1. 查看错误日志
tail -f var/log/prod.log
tail -f /var/log/nginx/error.log

# 2. 检查权限
./scripts/symfony_fix_scripts.sh perms

# 3. 检查PHP错误
php -l public/index.php
```

#### 3. 数据库连接失败

**症状**：无法连接到数据库

**解决方案**：

```bash
# 1. 验证数据库服务
sudo systemctl status mysql

# 2. 测试连接
php public/db_connection_checker.php

# 3. 检查配置
cat .env | grep DATABASE_URL
```

#### 4. CORS 错误

**症状**：跨域请求被阻止

**解决方案**：

```bash
# 1. 测试CORS配置
php public/cors_comprehensive_test.html

# 2. 检查Nginx CORS头
curl -I -X OPTIONS http://your-domain.com/api/sys-news-article-categories
```

### 日志分析

#### 查看应用日志

```bash
# 生产环境日志
tail -f var/log/prod.log

# 开发环境日志
tail -f var/log/dev.log

# PHP错误日志
tail -f /var/log/php8.1-fpm.log
```

#### 查看 Web 服务器日志

```bash
# Nginx访问日志
tail -f /var/log/nginx/access.log

# Nginx错误日志
tail -f /var/log/nginx/error.log
```

## 性能优化

### 数据库优化

1. **添加索引**

    ```sql
    ALTER TABLE sys_news_article_category ADD UNIQUE INDEX idx_code (code);
    ALTER TABLE sys_news_article ADD INDEX idx_category_id (category_id);
    ```

2. **查询优化**
    ```bash
    # 分析查询性能
    php public/comprehensive_db_diagnosis.php
    ```

### 应用优化

1. **启用 OPcache**

    ```ini
    ; php.ini
    opcache.enable=1
    opcache.memory_consumption=128
    opcache.max_accelerated_files=4000
    ```

2. **配置缓存**
    ```bash
    php bin/console cache:pool:clear cache.app
    php bin/console cache:pool:clear cache.system
    ```

## 监控和维护

### 健康检查脚本

创建定期健康检查：

```bash
# 添加到crontab
*/5 * * * * /path/to/scripts/health_monitor.sh

# 手动运行
./scripts/health_monitor.sh
```

### 备份策略

1. **数据库备份**

    ```bash
    mysqldump -u root -p official_website > backup_$(date +%Y%m%d).sql
    ```

2. **代码备份**
    ```bash
    git add .
    git commit -m "Backup $(date)"
    git push origin main
    ```

## 安全建议

### 1. 环境配置

-   生产环境设置 `APP_ENV=prod`
-   使用强密码的 `APP_SECRET`
-   定期更新依赖包

### 2. 访问控制

-   配置防火墙规则
-   限制 API 访问频率
-   使用 HTTPS 协议

### 3. 数据保护

-   定期备份数据
-   加密敏感信息
-   审计访问日志

## 联系支持

如果问题仍然存在，请提供以下信息：

1. **系统信息**

    ```bash
    php -v
    nginx -v
    mysql --version
    ```

2. **错误日志**

    ```bash
    # 最近的错误信息
    tail -50 var/log/prod.log
    tail -50 /var/log/nginx/error.log
    ```

3. **测试结果**

    ```bash
    # 运行诊断并保存结果
    php tests/end_to_end_test.php > diagnostic_result.txt 2>&1
    ```

4. **配置文件**
    - `.env`（隐藏密码信息）
    - Nginx 配置文件
    - PHP 配置文件

## 附录

### 有用的命令

```bash
# 快速重启所有服务
sudo systemctl restart nginx php8.1-fpm mysql

# 清除所有缓存
php bin/console cache:clear --env=prod
sudo rm -rf var/cache/*

# 检查系统资源
df -h
free -h
top
```

### 配置文件位置

-   **Symfony 应用**: `/var/www/official_website_backend/`
-   **Nginx 配置**: `/etc/nginx/sites-available/`
-   **PHP 配置**: `/etc/php/8.1/fpm/`
-   **日志文件**: `/var/log/`

### 版本兼容性

-   **PHP**: 8.1+
-   **Symfony**: 6.x
-   **MySQL**: 8.0+
-   **Nginx**: 1.18+

---

**注意**：本指南基于当前的系统状态编写。如果系统配置发生变化，请相应调整修复步骤。
