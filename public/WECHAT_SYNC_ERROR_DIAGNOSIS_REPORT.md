# 微信同步错误诊断报告

## 错误现象

```
[error] 获取分布式锁时发生错误
[error] 获取access_token返回错误: invalid ip 1.92.157.200 ipv6 ::ffff:1.92.157.200, not in whitelist rid: 69314eae-54edb1ba-15ae265a
[error] 释放分布式锁时发生错误
[ERROR] 获取access_token失败
[error] CLI同步任务失败: 获取access_token失败
```

## 根本原因分析

### 主要问题：IP 白名单配置缺失

**最直接的原因**：服务器 IP 地址 `1.92.157.200` 未在微信公众号的 IP 白名单中配置。

**错误信息解析**：

-   `invalid ip 1.92.157.200` - 明确指出 IP 地址无效
-   `ipv6 ::ffff:1.92.157.200` - IPv6 映射地址
-   `not in whitelist` - 不在白名单中

### 次要问题：分布式锁异常

由于 access_token 获取失败，导致后续的分布式锁操作也出现异常，形成连锁反应。

## 解决方案

### 第一步：配置 IP 白名单（必须）

1. **登录微信公众平台**

    - 访问：https://mp.weixin.qq.com
    - 使用管理员账号登录

2. **进入 IP 白名单配置**

    - 导航：开发 -> 基本配置 -> IP 白名单

3. **添加服务器 IP**

    - 添加：`1.92.157.200`
    - 如有多个服务器 IP，请全部添加

4. **保存并等待生效**
    - 保存配置后，等待 5-10 分钟生效

### 第二步：清理分布式锁（可选）

如果 IP 白名单配置后仍有锁问题，执行以下命令清理过期锁：

```bash
curl http://127.0.0.1:8084/cleanup_expired_locks.php
```

### 第三步：验证修复

1. **测试 access_token 获取**

    ```bash
    curl http://127.0.0.1:8084/quick_diagnosis.php
    ```

2. **测试同步功能**
    ```bash
    curl -X POST "http://127.0.0.1:8084/official-api/wechat/sync" \
         -H "Content-Type: application/json" \
         -d "{\"accountId\":\"gh_e4b07b2a992e6669\",\"force\":false}"
    ```

## 技术细节

### 为什么会出现这个问题？

1. **微信安全机制**：微信公众号 API 要求服务器 IP 必须在白名单中，这是微信的安全防护机制

2. **服务器迁移或变更**：可能是服务器 IP 发生了变化，但未及时更新微信配置
3. **新环境部署**：在新环境部署时忘记配置 IP 白名单

### 分布式锁错误的连锁反应

1. access_token 获取失败 → 同步服务异常
2. 同步服务异常 → 分布式锁获取失败
3. 锁操作失败 → 释放锁时也报错

### 代码分析

从 `src/Service/WechatApiService.php` 的 `getAccessToken()` 方法可以看到：

```php
if (isset($result['errcode']) && $result['errcode'] !== 0) {
    $this->logger->error('获取access_token返回错误: ' . $result['errmsg']);
    return null;
}
```

这直接导致了后续的同步流程失败。

## 预防措施

1. **IP 白名单管理**

    - 定期检查服务器 IP 是否变更
    - 在部署新环境时优先配置 IP 白名单
    - 保留多个备用 IP 地址

2. **监控和告警**

    - 监控 access_token 获取成功率
    - 设置 IP 白名单相关错误的告警

3. **部署检查清单**
    - 包含 IP 白名单配置的部署检查项

## 总结

**根本原因**：服务器 IP `1.92.157.200` 未在微信公众号 IP 白名单中

**解决方案**：

1. 登录微信公众平台，添加 IP 到白名单
2. 等待 5-10 分钟生效
3. 重新测试同步功能

**优先级**：高 - 这是阻塞性问题，必须先解决 IP 白名单问题
