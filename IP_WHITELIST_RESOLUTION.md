# 微信 IP 白名单问题解决方案

## 问题诊断结果

### 原始错误

```
{"errcode":40164,"errmsg":"invalid ip 117.39.59.7 ipv6 ::ffff:117.39.59.7, not in whitelist rid: 691d5b03-245c4618-0056bcfe"}
```

### 问题分析

1. **错误类型**: 微信 API IP 白名单错误 (errcode: 40164)
2. **错误 IP**: 117.39.59.7
3. **问题原因**: 服务器出站 IP 未添加到微信公众平台白名单

### 服务器信息

-   **内网 IP**: 172.30.210.223
-   **公网出站 IP**: 117.39.59.7
-   **服务器时间**: 2025-11-19 14:23:27

## 解决方案

### 已实施的解决方案

✅ **IP 白名单已配置正确**

通过运行同步命令验证：

```bash
php bin/console app:wechat:sync gh_01119904cc650f0c --force --bypass-lock
```

**结果**: 同步命令成功执行，没有 IP 白名单错误

### 当前状态

-   ✅ IP 白名单问题已解决
-   ✅ 可以正常连接到微信 API
-   ⚠️ 永久素材库中没有找到文章

## 后续步骤

### 1. 确认文章来源

由于永久素材库中没有文章，需要确认：

-   公众号是否有已发布的文章
-   文章是否在草稿箱中
-   是否需要使用其他微信 API 获取文章

### 2. 测试其他文章获取方式

可以尝试：

-   检查草稿箱文章
-   使用其他微信 API 接口
-   确认公众号的发布状态

### 3. 监控和验证

-   定期检查 IP 白名单配置
-   监控网络出口 IP 变化
-   建立 IP 变更预警机制

## 预防措施

### 长期解决方案

1. **固定 IP**: 使用云服务的弹性 IP 功能
2. **代理服务器**: 使用固定 IP 的代理服务器
3. **自动检测**: 实现 IP 自动检测和更新机制

### 配置检查清单

-   [x] 微信公众平台 IP 白名单配置
-   [x] 服务器网络配置
-   [x] 公众号 AppID 和 AppSecret 配置
-   [ ] 文章获取 API 验证

## 技术细节

### 验证方法

```bash
# 测试微信API连接
php bin/console app:wechat:sync <account-id> --force --bypass-lock

# 检查服务器IP
hostname -I
curl -s ifconfig.me
```

### 相关文件

-   `src/Service/WechatApiService.php` - 微信 API 服务
-   `src/Service/WechatArticleSyncService.php` - 文章同步服务
-   `src/Command/WechatSyncCommand.php` - 同步命令

---

**问题状态**: ✅ 已解决  
**解决时间**: 2025-11-19 14:23  
**验证方法**: 同步命令成功执行无 IP 白名单错误
