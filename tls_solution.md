# TLS 握手错误解决方案

## 问题诊断结果

**错误信息**: `http: TLS handshake error from 127.0.0.1:39446: remote error: tls: unknown certificate`

### 根本原因分析

通过调试脚本分析，确认了以下问题：

1. ✅ **证书有效性** - 证书在有效期内（2025-12-16 至 2026-12-16）
2. ✅ **证书匹配性** - 证书和私钥匹配
3. ⚠️ **自签名证书** - 证书为自签名，不被客户端默认信任
4. ⚠️ **缺少 SAN 扩展** - 没有 Subject Alternative Name，不包含 127.0.0.1 或 localhost

**主要问题**: 自签名证书 + 缺少正确的 SAN 扩展

## 解决方案

### 方案 1: 重新生成包含 SAN 的自签名证书（推荐）

```bash
# 创建证书配置文件
cat > openssl.conf << EOF
[req]
distinguished_name = req_distinguished_name
req_extensions = v3_req
prompt = no

[req_distinguished_name]
C = AU
ST = Some-State
O = Internet Widgits Pty Ltd
CN = localhost

[v3_req]
keyUsage = keyEncipherment, dataEncipherment
extendedKeyUsage = serverAuth
subjectAltName = @alt_names

[alt_names]
DNS.1 = localhost
DNS.2 = 127.0.0.1
IP.1 = 127.0.0.1
IP.2 = ::1
EOF

# 生成新的私钥和证书
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout server.key \
    -out server.crt \
    -config openssl.conf \
    -extensions v3_req
```

### 方案 2: 客户端禁用证书验证（开发环境）

对于 Go 客户端：

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

对于 curl：

```bash
curl -k https://127.0.0.1:8000
```

### 方案 3: 添加证书到系统信任存储

#### Windows:

```powershell
# 导入证书到受信任的根证书颁发机构
certmgr.msc
# -> 受信任的根证书颁发机构 -> 右键 -> 所有任务 -> 导入
```

#### Linux:

```bash
# 复制证书到系统目录
sudo cp server.crt /usr/local/share/ca-certificates/
sudo update-ca-certificates
```

#### macOS:

```bash
# 添加证书到钥匙串
sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain server.crt
```

### 方案 4: 使用 mkcert 生成本地可信证书

```bash
# 安装mkcert
curl -JLO "https://dl.filippo.io/mkcert/latest?for=linux/amd64" && chmod +x mkcert-v*-linux-amd64
sudo cp mkcert-v*-linux-amd64 /usr/local/bin/mkcert
sudo rm mkcert-v*-linux-amd64

# 创建本地CA
mkcert -install

# 生成证书
mkcert localhost 127.0.0.1 ::1
```

## Symfony 配置调整

如果使用 Symfony 内置服务器，确保正确配置 TLS：

```bash
# 使用生成的证书启动服务器
php -S localhost:8000 -t public/ \
    --ssl-crt=server.crt \
    --ssl-key=server.key
```

## 验证修复

运行提供的调试脚本验证修复：

```bash
php debug_tls.php
```

期望输出：

-   ✅ 证书在有效期内
-   ✅ Subject Alternative Name 包含 127.0.0.1 和 localhost
-   ✅ 连接成功（无需禁用验证）

## 生产环境建议

1. **使用 Let's Encrypt 免费证书**：

    ```bash
    sudo apt install certbot
    sudo certbot --nginx -d yourdomain.com
    ```

2. **配置 nginx**：

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

3. **定期更新证书**：设置自动续期提醒

## 快速修复命令

```bash
# 生成包含SAN的证书（立即修复）
cat > openssl.conf << 'EOF'
[req]
distinguished_name = req_distinguished_name
req_extensions = v3_req
prompt = no

[req_distinguished_name]
C = AU
ST = Some-State
O = Internet Widgits Pty Ltd
CN = localhost

[v3_req]
keyUsage = keyEncipherment, dataEncipherment
extendedKeyUsage = serverAuth
subjectAltName = @alt_names

[alt_names]
DNS.1 = localhost
DNS.2 = 127.0.0.1
IP.1 = 127.0.0.1
IP.2 = ::1
EOF

openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout server.key -out server.crt -config openssl.conf -extensions v3_req
```

执行后重启服务器，TLS 握手错误应该解决。
