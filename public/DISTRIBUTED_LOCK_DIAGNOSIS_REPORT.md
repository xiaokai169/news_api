# 微信同步 API "同步任务正在进行中" 错误诊断报告

## 🔍 问题概述

**API 端点**: `https://newsapi.arab-bee.com/official-api/wechat/sync`
**错误信息**: `"同步任务正在进行中，请稍后再试"`
**HTTP 状态码**: `500`

## 🎯 问题根源分析

### 最可能的原因（已确认）：

1. **`distributed_locks`表不存在** ⭐⭐⭐⭐⭐

    - 这是导致错误的根本原因
    - [`DistributedLockService`](src/Service/DistributedLockService.php) 尝试操作不存在的表
    - SQL 执行失败但异常被静默处理

2. **异常处理掩盖了真实问题** ⭐⭐⭐⭐
    - [`DistributedLockService::acquireLock()`](src/Service/DistributedLockService.php:66-72) 中的 catch 块返回 false
    - 这个 false 被误认为是"锁已被其他进程持有"
    - 实际上应该是"表不存在"或"数据库错误"

### 详细问题流程：

```
API调用 → WechatController::sync()
    ↓
WechatArticleSyncService::syncArticles()
    ↓
DistributedLockService::acquireLock($lockKey, 1800)
    ↓
执行SQL: INSERT INTO distributed_locks...
    ↓
❌ 表不存在 → SQL执行失败
    ↓
异常被catch捕获 → return false
    ↓
被误认为"锁被占用" → "同步任务正在进行中"
```

## 📋 问题验证步骤

### 1. 代码分析确认

-   ✅ [`WechatArticleSyncService.php:58`](src/Service/WechatArticleSyncService.php:58) 调用锁服务
-   ✅ [`WechatArticleSyncService.php:59`](src/Service/WechatArticleSyncService.php:59) 返回错误消息
-   ✅ [`DistributedLockService.php:41-48`](src/Service/DistributedLockService.php:41-48) 执行 SQL 操作
-   ✅ [`DistributedLockService.php:66-72`](src/Service/DistributedLockService.php:66-72) 异常处理返回 false

### 2. 数据库表检查

-   ❌ `distributed_locks`表不存在
-   ❌ 没有相关的 migration 文件
-   ❌ 缺少表创建脚本

## 🛠️ 解决方案

### 立即解决方案

#### 1. 创建分布式锁表

```sql
CREATE TABLE `distributed_locks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lock_key` varchar(255) NOT NULL,
  `lock_id` varchar(255) NOT NULL,
  `expire_time` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_lock_key` (`lock_key`),
  KEY `idx_expire_time` (`expire_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 2. 执行修复脚本

```bash
curl http://127.0.0.1:8084/fix_distributed_lock.php
```

### 长期解决方案

#### 1. 添加 Doctrine 实体

-   ✅ 已创建 [`src/Entity/DistributedLock.php`](src/Entity/DistributedLock.php)
-   ✅ 已创建 [`src/Repository/DistributedLockRepository.php`](src/Repository/DistributedLockRepository.php)

#### 2. 创建 Migration 文件

-   ✅ 已创建 [`migrations/Version20251204075200.php`](migrations/Version20251204075200.php)

#### 3. 管理命令工具

-   ✅ 已创建 [`src/Command/DistributedLockManagerCommand.php`](src/Command/DistributedLockManagerCommand.php)
-   ✅ 已创建 [`public/run_distributed_lock_manager.php`](public/run_distributed_lock_manager.php)

#### 4. 改进错误处理

建议在 [`DistributedLockService`](src/Service/DistributedLockService.php) 中改进异常处理：

```php
} catch (\Exception $e) {
    $this->logger->error('获取分布式锁时发生错误', [
        'lock_key' => $lockKey,
        'error' => $e->getMessage()
    ]);

    // 区分表不存在和其他错误
    if (strpos($e->getMessage(), "Table") !== false && strpos($e->getMessage(), "doesn't exist") !== false) {
        throw new \RuntimeException('分布式锁表不存在，请运行数据库migration', 0, $e);
    }

    return false;
}
```

## 🔧 使用方法

### 查看锁状态

```bash
php public/run_distributed_lock_manager.php status
```

### 清理过期锁

```bash
php public/run_distributed_lock_manager.php clean
```

### 强制清理所有锁

```bash
php public/run_distributed_lock_manager.php clean --force
```

### 释放指定锁

```bash
php public/run_distributed_lock_manager.php release --lock-key=wechat_sync_gh_xxx
```

## ✅ 验证步骤

1. **执行修复脚本**:

    ```bash
    curl http://127.0.0.1:8084/fix_distributed_lock.php
    ```

2. **测试 API 调用**:

    ```bash
    curl -X POST "http://127.0.0.1:8084/official-api/wechat/sync" \
      -H "Content-Type: application/json" \
      -d '{"accountId":"gh_e4b07b2a992e6669","force":false}'
    ```

3. **检查锁状态**:
    ```bash
    php public/run_distributed_lock_manager.php status
    ```

## 📊 预期结果

修复后，API 应该：

-   ✅ 不再返回"同步任务正在进行中"错误
-   ✅ 正常执行同步逻辑
-   ✅ 正确处理并发请求
-   ✅ 提供准确的错误信息

## 🔄 监控建议

1. **定期检查锁状态**: 建议每天运行一次状态检查
2. **自动清理过期锁**: 可以设置 cron 任务定期清理
3. **监控表创建**: 在部署时确保 migration 已执行
4. **日志监控**: 监控分布式锁相关的错误日志

## 🎯 总结

这个问题是一个典型的"基础设施缺失"导致的错误：

-   **根本原因**: 缺少`distributed_locks`表
-   **触发条件**: 微信同步 API 调用分布式锁服务
-   **错误掩盖**: 异常处理机制返回了误导性的错误信息
-   **解决方案**: 创建缺失的数据库表并改进错误处理

通过执行提供的修复脚本，可以立即解决当前问题。长期来看，建议完善基础设施部署流程和错误处理机制。
