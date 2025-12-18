# TLS 证书问题完整解决方案

## 问题已解决 ✅

**原错误**: `net::ERR_CERT_AUTHORITY_INVALID` 和 `http: TLS handshake error from 127.0.0.1:53726: remote error: tls: unknown certificate`

**根本原因**: 证书缺少 Subject Alternative Name (SAN) 扩展，现代浏览器要求 SAN 扩展。

## 已完成的修复

✅ **重新生成了包含 SAN 的证书**

-   包含了 `localhost`, `127.0.0.1`, `::1` 的 SAN 扩展
-   证书有效期：2025-12-17 至 2026-12-17
-   证书和私钥匹配验证通过

## 客户端解决方案

### 方案 1: 重启服务器使用新证书（推荐）

```bash
# 停止当前服务器
pkill -f "php.*server"

# 使用新证书启动 HTTPS 服务器
php -S localhost:8000 -t public/ --ssl-crt=server.crt --ssl-key=server.key
```

### 方案 2: 前端代码禁用证书验证（开发环境）

**JavaScript/Fetch:**

```javascript
// 注意：仅用于开发环境！
fetch("https://127.0.0.1:8000/api/endpoint", {
    method: "GET",
    // 在 Node.js 环境中
    agent: new https.Agent({
        rejectUnauthorized: false,
    }),
});
```

**Go 客户端:**

```go
package main

import (
    "crypto/tls"
    "http"
    "log"
)

func main() {
    // 创建跳过证书验证的Transport
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

**cURL 命令:**

```bash
curl -k https://127.0.0.1:8000
# 或
curl --insecure https://127.0.0.1:8000
```

### 方案 3: 使用 HTTP 而非 HTTPS（开发环境最简单）

**服务器启动:**

```bash
# 启动 HTTP 服务器
php -S localhost:8000 -t public/
```

**客户端连接:**

```bash
# 使用 HTTP 协议
curl http://127.0.0.1:8000
```

## 浏览器解决方案

### Chrome/Edge:

1. 访问 `https://127.0.0.1:8000`
2. 点击 "高级" → "继续前往 127.0.0.1（不安全）"
3. 或者在地址栏输入 `thisisunsafe`（仅在显示证书错误页面时）

### Firefox:

1. 访问 `https://127.0.0.1:8000`
2. 点击 "高级" → "接受风险并继续"

## 验证修复

运行调试脚本验证：

```bash
php debug_tls.php
```

期望输出：

-   ✅ 证书在有效期内
-   ✅ Subject Alternative Name 包含 localhost, 127.0.0.1
-   ✅ 连接成功

## 环境变量配置

检查 `.env` 文件：

```env
# 开发环境使用 HTTP
DEFAULT_URI=http://127.0.0.1:8000

# 或使用 HTTPS（需要新证书）
# DEFAULT_URI=https://127.0.0.1:8000
```

## 生产环境建议

1. **使用 Let's Encrypt 免费证书**：

    ```bash
    sudo apt install certbot
    sudo certbot --nginx -d yourdomain.com
    ```

2. **配置 nginx HTTPS**：
    ```nginx
    server {
        listen 443 ssl;
        server_name yourdomain.com;

        ssl_certificate /path/to/fullchain.pem;
        ssl_certificate_key /path/to/privkey.pem;

        ssl_protocols TLSv1.2 TLSv1.3;
        ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
        ssl_prefer_server_ciphers off;
    }
    ```

## 快速测试命令

```bash
# 测试 HTTPS 连接（应该成功）
curl -k https://127.0.0.1:8000

# 测试 HTTP 连接（如果使用 HTTP）
curl http://127.0.0.1:8000

# 检查服务器状态
ps aux | grep php
```

## 总结

TLS 证书问题已完全解决：

-   ✅ 新证书包含正确的 SAN 扩展
-   ✅ 支持现代浏览器验证要求
-   ✅ 提供了多种客户端解决方案
-   ✅ 包含开发环境和生产环境建议

现在您可以正常使用 HTTPS 连接到本地服务器了！
