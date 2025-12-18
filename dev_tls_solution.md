# 开发环境 TLS 握手错误解决方案

## 问题分析

**错误**: `http: TLS handshake error from 127.0.0.1:39446: remote error: tls: unknown certificate`

**根本原因**: 开发环境中客户端尝试使用 HTTPS 连接到服务器，但服务器配置或客户端期望不匹配

## 开发环境解决方案（推荐）

### 方案 1: 使用 HTTP 而非 HTTPS（最简单）

**客户端修改**：

```go
// 将HTTPS改为HTTP
resp, err := http.Get("http://127.0.0.1:8000")  // 而不是 https://
```

```bash
# curl使用HTTP
curl http://127.0.0.1:8000  # 而不是 https://
```

### 方案 2: 禁用客户端 SSL 验证

**Go 客户端**：

```go
package main

import (
    "crypto/tls"
    "http"
    "log"
)

func main() {
    // 创建跳过证书验证的客户端
    tr := &http.Transport{
        TLSClientConfig: &tls.Config{InsecureSkipVerify: true},
    }

    client := &http.Client{Transport: tr}

    resp, err := client.Get("https://127.0.0.1:8000")
    if err != nil {
        log.Fatal(err)
    }
    defer resp.Body.Close()
}
```

**curl 命令**：

```bash
curl -k https://127.0.0.1:8000
# 或
curl --insecure https://127.0.0.1:8000
```

### 方案 3: 修改服务器配置，禁用 SSL

如果使用 Symfony 内置服务器：

```bash
# 使用HTTP启动服务器
php -S localhost:8000 -t public/

# 或者明确指定HTTP
php -S 127.0.0.1:8000 -t public/
```

如果使用 nginx，修改配置：

```nginx
server {
    listen 80;
    server_name localhost;
    root /www/wwwroot/official_website_backend/public;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 方案 4: 环境变量配置

检查 `.env` 文件中的配置：

```env
# 确保使用HTTP
DEFAULT_URI=http://127.0.0.1:8000

# 而不是
# DEFAULT_URI=https://127.0.0.1:8000
```

## Symfony 开发服务器最佳实践

### 启动 HTTP 服务器

```bash
# 进入项目目录
cd /www/wwwroot/official_website_backend

# 启动HTTP服务器（推荐）
php -S 127.0.0.1:8000 -t public/

# 或者使用localhost
php -S localhost:8000 -t public/
```

### 客户端连接

```bash
# 测试连接
curl http://127.0.0.1:8000

# 访问API
curl http://127.0.0.1:8000/api/doc
```

## 快速修复步骤

1. **检查当前服务器运行方式**：

    ```bash
    ps aux | grep php
    ```

2. **如果正在运行 HTTPS 服务器，停止并重启为 HTTP**：

    ```bash
    # 停止当前服务器
    pkill -f "php.*server"

    # 启动HTTP服务器
    php -S 127.0.0.1:8000 -t public/
    ```

3. **修改客户端代码使用 HTTP**：
    ```go
    // 将所有 https:// 改为 http://
    baseURL := "http://127.0.0.1:8000"
    ```

## 验证修复

运行测试命令：

```bash
# 应该返回页面内容
curl http://127.0.0.1:8000

# 检查响应头
curl -I http://127.0.0.1:8000
```

## 开发环境建议

-   **使用 HTTP**: 开发环境不需要 SSL，简化配置
-   **保留 SSL 配置**: 仅在需要测试 HTTPS 功能时启用
-   **文档化**: 在 README 中说明如何切换 HTTP/HTTPS 模式
-   **环境变量**: 使用环境变量控制协议选择

## 生产环境提醒

生产环境仍需要 SSL，届时可以：

1. 使用 Let's Encrypt 免费证书
2. 配置 nginx HTTPS
3. 强制 HTTPS 重定向

但在开发环境中，使用 HTTP 是最简单有效的解决方案。
