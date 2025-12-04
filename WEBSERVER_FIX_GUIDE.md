# Web 服务器配置修复指南

## 问题诊断

### 当前状态

-   数据库连接正常，密码已修正为 `qwe147258..`
-   数据库中已有测试数据：`{"code":"GZ_003","name":"2112"}` (ID: 3)
-   API 调用返回 404 错误，Web 服务器配置问题
-   Symfony 应用由于 WSL 路径权限问题无法正常启动

### 根本原因分析

1. **Nginx 配置问题**：路由未正确指向 Symfony 应用
2. **WSL 路径权限问题**：文件权限导致 Symfony 无法启动
3. **PHP-FPM 配置问题**：可能导致 API 请求处理失败

## 解决方案

### 1. 检查当前 Nginx 配置

#### 检查配置文件位置

```bash
# 检查Nginx配置文件
sudo nginx -t
sudo nginx -T | grep -A 20 -B 5 "server_name"

# 常见配置文件位置
/etc/nginx/sites-available/
/etc/nginx/conf.d/
/usr/local/nginx/conf/
```

#### 检查当前站点配置

```bash
# 查看当前激活的站点
ls -la /etc/nginx/sites-enabled/
# 或
cat /etc/nginx/nginx.conf | grep -E "include.*conf"
```

### 2. 正确的 Nginx 配置模板

#### Symfony 应用 Nginx 配置

```nginx
server {
    listen 80;
    server_name your-domain.com;  # 替换为实际域名
    root /var/www/official_website_backend/public;
    index index.php index.html;

    # 日志配置
    access_log /var/log/nginx/official_website_access.log;
    error_log /var/log/nginx/official_website_error.log;

    # 客户端上传大小限制
    client_max_body_size 100M;

    # Symfony路由配置
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP处理
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;  # 根据PHP版本调整
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;

        # Symfony特定参数
        fastcgi_param APP_ENV prod;
        fastcgi_param APP_SECRET your-app-secret;  # 从.env文件获取
        fastcgi_param DATABASE_URL "mysql://root:qwe147258..@127.0.0.1:3306/official_website";
    }

    # 静态文件缓存
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # 安全配置
    location ~ /\.ht {
        deny all;
    }

    # API路由特定配置
    location /api/ {
        try_files $uri $uri/ /index.php?$query_string;

        # CORS头配置
        add_header 'Access-Control-Allow-Origin' '*' always;
        add_header 'Access-Control-Allow-Methods' 'GET, POST, PUT, DELETE, OPTIONS' always;
        add_header 'Access-Control-Allow-Headers' 'DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range,Authorization' always;

        # 处理预检请求
        if ($request_method = 'OPTIONS') {
            add_header 'Access-Control-Allow-Origin' '*';
            add_header 'Access-Control-Allow-Methods' 'GET, POST, PUT, DELETE, OPTIONS';
            add_header 'Access-Control-Allow-Headers' 'DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range,Authorization';
            add_header 'Access-Control-Max-Age' 1728000;
            add_header 'Content-Type' 'text/plain; charset=utf-8';
            add_header 'Content-Length' 0;
            return 204;
        }
    }
}
```

#### 宝塔面板配置适配

如果使用宝塔面板，配置文件通常在：

```bash
/www/server/panel/vhost/nginx/your-domain.com.conf
```

### 3. WSL 路径权限修复

#### 检查当前权限

```bash
# 检查项目目录权限
ls -la /mnt/c/Users/Administrator/Desktop/www/official_website_backend/
ls -la /mnt/c/Users/Administrator/Desktop/www/official_website_backend/var/
ls -la /mnt/c/Users/Administrator/Desktop/www/official_website_backend/public/
```

#### 修复权限脚本

```bash
#!/bin/bash
# 修复WSL项目权限

PROJECT_PATH="/mnt/c/Users/Administrator/Desktop/www/official_website_backend"

# 设置目录权限
find "$PROJECT_PATH" -type d -exec chmod 755 {} \;

# 设置文件权限
find "$PROJECT_PATH" -type f -exec chmod 644 {} \;

# 特殊权限设置
chmod -R 777 "$PROJECT_PATH/var/"
chmod -R 777 "$PROJECT_PATH/public/"
chmod +x "$PROJECT_PATH/bin/console"

# 设置所有者（如果需要）
# sudo chown -R www-data:www-data "$PROJECT_PATH"

echo "权限修复完成"
```

### 4. PHP-FPM 配置检查

#### 检查 PHP-FPM 状态

```bash
# 检查PHP-FPM服务状态
sudo systemctl status php8.1-fpm  # 根据PHP版本调整

# 检查PHP-FPM配置
php -v
php -m | grep -E "(pdo|mysql|curl|json)"
```

#### PHP-FPM 配置优化

```ini
# /etc/php/8.1/fpm/php.ini 优化配置
memory_limit = 512M
max_execution_time = 300
upload_max_filesize = 100M
post_max_size = 100M
max_input_vars = 3000

# /etc/php/8.1/fpm/pool.d/www.conf
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
```

## 验证步骤

### 1. 配置验证

```bash
# 测试Nginx配置
sudo nginx -t

# 重启服务
sudo systemctl reload nginx
sudo systemctl restart php8.1-fpm
```

### 2. 应用验证

```bash
# 进入项目目录
cd /mnt/c/Users/Administrator/Desktop/www/official_website_backend

# 清除Symfony缓存
php bin/console cache:clear

# 检查路由
php bin/console debug:router

# 测试应用启动
php bin/console about
```

### 3. API 测试

```bash
# 测试主页
curl -I http://your-domain.com/

# 测试API端点
curl -X GET http://your-domain.com/api/sys-news-article-categories
curl -X POST http://your-domain.com/api/sys-news-article-categories \
  -H "Content-Type: application/json" \
  -d '{"code":"TEST_001","name":"测试分类"}'
```

## 常见问题解决

### 1. 502 Bad Gateway

-   检查 PHP-FPM 是否运行
-   检查 socket 文件路径
-   检查 Nginx 错误日志

### 2. 404 Not Found

-   检查 root 路径是否正确
-   检查 try_files 配置
-   检查 Symfony 路由配置

### 3. 403 Forbidden

-   检查文件权限
-   检查目录索引配置
-   检查 SELinux 设置

### 4. 500 Internal Server Error

-   检查 PHP 错误日志
-   检查 Symfony 日志
-   检查内存限制

## 监控和维护

### 日志监控

```bash
# 实时监控日志
tail -f /var/log/nginx/official_website_error.log
tail -f /var/log/nginx/official_website_access.log
tail -f /mnt/c/Users/Administrator/Desktop/www/official_website_backend/var/log/prod.log
```

### 性能监控

```bash
# 检查系统资源
top
htop
free -h
df -h

# 检查Nginx状态
curl http://localhost/nginx_status
```

## 下一步

1. 应用上述配置
2. 运行修复脚本
3. 执行验证步骤
4. 如果问题持续，检查系统日志和防火墙设置
