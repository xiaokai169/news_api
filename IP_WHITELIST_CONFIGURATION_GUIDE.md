# 微信公众平台 IP 白名单配置指南

## 问题分析

当前错误信息：

```
invalid ip 117.39.59.7 ipv6 ::ffff:117.39.59.7, not in whitelist
```

这表明微信 API 同时检测到了：

-   **IPv4 地址**: `117.39.59.7`
-   **IPv6 地址**: `::ffff:117.39.59.7`

## 解决方案

### 方法 1：在微信公众平台添加 IP 白名单

1. **登录微信公众平台**

    - 访问：https://mp.weixin.qq.com
    - 使用公众号管理员账号登录

2. **进入开发设置**

    - 左侧菜单 → 设置 → 公众号设置
    - 选择"功能设置"选项卡
    - 找到"IP 白名单"设置

3. **添加 IP 地址**

    - **必须同时添加以下两个 IP 地址**：
        ```
        117.39.59.7
        ::ffff:117.39.59.7
        ```
    - 或者如果支持 IPv6 格式，也可以尝试：
        ```
        117.39.59.7
        117.39.59.7/32
        ```

4. **保存并等待生效**
    - 保存设置后，可能需要等待 5-30 分钟才能生效

### 方法 2：检查网络配置（如果可能）

如果服务器支持，可以尝试：

1. **禁用 IPv6**（临时解决方案）

    ```bash
    # 临时禁用IPv6
    sysctl -w net.ipv6.conf.all.disable_ipv6=1
    sysctl -w net.ipv6.conf.default.disable_ipv6=1
    ```

2. **配置网络优先使用 IPv4**
    - 在 PHP 代码中强制使用 IPv4

### 方法 3：创建强制 IPv4 的测试脚本

创建一个只使用 IPv4 连接的测试脚本：

```php
<?php
// force_ipv4_test.php
require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;

$appId = 'wxbe2cd7c390d1b6f0';
$appSecret = 'bb001546...';

echo "强制使用IPv4测试微信API access_token 获取...\n";
echo "AppID: $appId\n";

$client = HttpClient::create([
```
