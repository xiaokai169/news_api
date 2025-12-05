# 微信同步"显示成功但数据库无值"问题系统性调试方案

## 问题概述

**现象**: API 返回同步成功，但数据库中没有相应的数据记录  
**环境**: 线上生产环境，本地无法复现  
**技术栈**: Symfony + Doctrine ORM + MySQL

## 可能原因分析

基于代码分析，最可能的原因包括：

### 1. 数据库层面问题 (高可能性)

-   **事务回滚**: 同步过程中发生异常，导致事务回滚
-   **权限问题**: 数据库用户缺少 INSERT/UPDATE 权限
-   **表结构问题**: 关键表缺失或字段不匹配
-   **连接问题**: 数据库连接中断或超时

### 2. 微信 API 层面问题 (中等可能性)

-   **数据格式**: 微信返回的数据格式与预期不符
-   **API 限制**: 微信 API 调用频率限制或权限问题
-   **数据解析**: API 响应数据解析失败

### 3. 应用逻辑问题 (中等可能性)

-   **分布式锁**: 锁机制阻止数据写入
-   **重复检查**: 去重逻辑过于严格，导致跳过所有数据
-   **异常处理**: 异常被捕获但未正确处理

### 4. 环境配置问题 (低可能性)

-   **环境差异**: 线上环境配置与本地不同
-   **缓存问题**: Doctrine 缓存导致数据不一致

## 系统性调试步骤

### 第一阶段：快速诊断 (5-10 分钟)

#### 1.1 运行综合调试脚本

```bash
# 基础检查
php public/comprehensive_sync_debug.php [account_id] 1

# 详细检查
php public/comprehensive_sync_debug.php [account_id] 2

# 完整调试
php public/comprehensive_sync_debug.php [account_id] 3
```

#### 1.2 检查关键指标

-   数据库连接状态
-   微信 API 连接状态
-   分布式锁状态
-   最近错误日志

### 第二阶段：深度分析 (15-30 分钟)

#### 2.1 数据库连接和权限验证

```bash
# 检查所有关键表
php public/database_connection_validator.php

# 检查特定表
php public/database_connection_validator.php official

# 测试数据库操作
php public/database_connection_validator.php official test
```

#### 2.2 日志分析

```bash
# 分析最近24小时日志
php public/sync_log_analyzer.php 24

# 分析最近6小时特定关键词
php public/sync_log_analyzer.php 6 "sync"

# 分析错误日志
php public/sync_log_analyzer.php 24 "ERROR"
```

#### 2.3 事务和错误处理检查

```bash
# 干运行测试
php public/transaction_debugger.php [account_id] dry_run

# 真实测试（谨慎使用）
php public/transaction_debugger.php [account_id] real_test

# 回滚测试
php public/transaction_debugger.php [account_id] rollback_test
```

### 第三阶段：针对性排查 (30-60 分钟)

#### 3.1 微信 API 专项检查

```bash
# 测试微信API连接
php public/test_wechat_direct.php [account_id]

# 检查access_token获取
curl -X GET "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=[APP_ID]&secret=[APP_SECRET]"
```

#### 3.2 数据一致性检查

```bash
# 检查数据完整性
php public/database_comprehensive_check.php

# 验证同步结果
mysql -u [user] -p -e "SELECT COUNT(*) FROM official WHERE create_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR);" [database]
```

## 关键文件和配置检查

### 1. 数据库配置检查

**文件**: [`config/packages/doctrine.yaml`](config/packages/doctrine.yaml)

```yaml
# 检查数据库连接配置
DATABASE_URL="mysql://user:pass@host:port/database?serverVersion=8.0&charset=utf8"
```

### 2. 日志配置检查

**文件**: [`config/packages/monolog.yaml`](config/packages/monolog.yaml)

```yaml
# 确保日志级别合适
monolog:
    handlers:
        wechat:
            level: info
            path: "%kernel.logs_dir%/wechat.log"
        database:
            level: debug
            path: "%kernel.logs_dir%/database.log"
```

### 3. 实体映射检查

**关键实体**:

-   [`src/Entity/Official.php`](src/Entity/Official.php) - 文章实体
-   [`src/Entity/WechatPublicAccount.php`](src/Entity/WechatPublicAccount.php) - 公众号实体

### 4. 服务层检查

**关键服务**:

-   [`src/Service/WechatArticleSyncService.php`](src/Service/WechatArticleSyncService.php) - 同步服务
-   [`src/Service/WechatApiService.php`](src/Service/WechatApiService.php) - API 服务
-   [`src/Service/DistributedLockService.php`](src/Service/DistributedLockService.php) - 锁服务

## 具体调试命令集

### 数据库相关命令

```bash
# 检查表结构
mysql -u [user] -p -e "DESCRIBE official;" [database]
mysql -u [user] -p -e "DESCRIBE wechat_public_account;" [database]
mysql -u [user] -p -e "DESCRIBE distributed_locks;" [database]

# 检查数据量
mysql -u [user] -p -e "SELECT COUNT(*) FROM official;" [database]
mysql -u [user] -p -e "SELECT COUNT(*) FROM wechat_public_account;" [database]

# 检查最近记录
mysql -u [user] -p -e "SELECT * FROM official ORDER BY create_at DESC LIMIT 10;" [database]

# 检查重复数据
mysql -u [user] -p -e "SELECT article_id, COUNT(*) FROM official WHERE article_id IS NOT NULL GROUP BY article_id HAVING COUNT(*) > 1;" [database]
```

### 日志相关命令

```bash
# 查看实时日志
tail -f var/log/wechat.log
tail -f var/log/database.log
tail -f var/log/prod.log

# 搜索特定错误
grep -n "ERROR" var/log/wechat.log | tail -20
grep -n "Exception" var/log/prod.log | tail -20
grep -n "rollback" var/log/database.log | tail -20

# 查看最近的同步日志
grep -n "sync" var/log/wechat.log | tail -20
```

### 系统状态检查

```bash
# 检查MySQL状态
mysql -u [user] -p -e "SHOW PROCESSLIST;" [database]
mysql -u [user] -p -e "SHOW STATUS LIKE 'Connections';" [database]

# 检查锁状态
mysql -u [user] -p -e "SELECT * FROM distributed_locks WHERE expire_time > NOW();" [database]

# 检查数据库大小
mysql -u [user] -p -e "SELECT table_name, ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)' FROM information_schema.tables WHERE table_schema = '[database]' ORDER BY (data_length + index_length) DESC;" [database]
```

## 常见问题解决方案

### 1. 事务回滚问题

**症状**: 同步显示成功但无数据  
**原因**: 异常导致事务回滚  
**解决方案**:

```php
// 检查 WechatArticleSyncService.php 中的事务处理
// 确保异常被正确捕获和记录
// 添加详细的调试日志
```

### 2. 权限问题

**症状**: 无错误但数据不写入  
**原因**: 数据库用户权限不足  
**解决方案**:

```sql
GRANT SELECT, INSERT, UPDATE, DELETE ON official_website.* TO 'app_user'@'%';
FLUSH PRIVILEGES;
```

### 3. 分布式锁问题

**症状**: 同步被阻止或跳过  
**原因**: 锁未正确释放  
**解决方案**:

```sql
-- 清理过期锁
DELETE FROM distributed_locks WHERE expire_time < NOW();

-- 检查活跃锁
SELECT * FROM distributed_locks WHERE expire_time > NOW();
```

### 4. 数据解析问题

**症状**: API 调用成功但数据为空  
**原因**: 微信 API 数据格式变化  
**解决方案**:

```php
// 检查 WechatApiService.php 中的数据解析逻辑
// 添加原始响应日志
// 验证数据结构
```

## 应急处理方案

### 方案 1: 手动数据修复

```bash
# 1. 备份当前数据
mysqldump -u [user] -p [database] > backup_$(date +%Y%m%d_%H%M%S).sql

# 2. 清理问题锁
mysql -u [user] -p -e "DELETE FROM distributed_locks WHERE lock_key LIKE 'wechat_sync_%';" [database]

# 3. 重新运行同步
php public/comprehensive_sync_debug.php [account_id] 3
```

### 方案 2: 绕过锁机制

```bash
# 临时禁用锁检查
php public/comprehensive_sync_debug.php [account_id] 3 --bypass-lock

# 或者修改代码临时禁用锁
# 在 WechatArticleSyncService::syncArticles() 中设置 $bypassLock = true
```

### 方案 3: 数据一致性修复

```bash
# 检查并修复重复数据
php public/database_comprehensive_check.php

# 重建索引（如果需要）
mysql -u [user] -p -e "ALTER TABLE official DROP INDEX idx_article_id; ALTER TABLE official ADD INDEX idx_article_id (article_id);" [database]
```

## 监控和预防措施

### 1. 实时监控脚本

```bash
# 创建监控脚本
cat > monitor_sync.sh << 'EOF'
#!/bin/bash
LOG_FILE="/var/log/sync_monitor.log"
DATE=$(date '+%Y-%m-%d %H:%M:%S')

# 检查数据库连接
if ! mysql -u [user] -p[password] -e "SELECT 1" [database] >/dev/null 2>&1; then
    echo "[$DATE] ERROR: Database connection failed" >> $LOG_FILE
fi

# 检查最近同步
RECENT_COUNT=$(mysql -u [user] -p[password] -e "SELECT COUNT(*) FROM official WHERE create_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR);" [database] -s -N)
if [ "$RECENT_COUNT" -eq 0 ]; then
    echo "[$DATE] WARNING: No recent sync data found" >> $LOG_FILE
fi

# 检查错误日志
ERROR_COUNT=$(grep -c "ERROR" /path/to/var/log/wechat.log 2>/dev/null || echo 0)
if [ "$ERROR_COUNT" -gt 0 ]; then
    echo "[$DATE] ERROR: Found $ERROR_COUNT errors in wechat.log" >> $LOG_FILE
fi
EOF

chmod +x monitor_sync.sh
# 添加到crontab每5分钟执行一次
*/5 * * * * /path/to/monitor_sync.sh
```

### 2. 日志轮转配置

```bash
# 配置logrotate
cat > /etc/logrotate.d/sync-app << 'EOF'
/path/to/var/log/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        systemctl reload apache2
    endscript
}
EOF
```

## 联系信息和升级路径

### 需要升级的情况

1. 调试脚本无法运行或权限不足
2. 数据库层面的严重问题
3. 微信 API 重大变更
4. 硬件或网络基础设施问题

### 收集信息清单

在请求升级支持时，请准备以下信息：

1. 完整的错误日志（最近 24 小时）
2. 调试脚本生成的报告文件
3. 数据库配置信息（脱敏后）
4. 微信 API 配置信息（脱敏后）
5. 问题发生的时间点和频率
6. 尝试过的解决方法

### 紧急联系方式

-   技术负责人: [联系方式]
-   运维团队: [联系方式]
-   项目经理: [联系方式]

---

**注意**: 本调试方案基于当前代码分析制定，在实际执行过程中可能需要根据具体情况进行调整。建议在测试环境先验证所有调试脚本，确保不会对生产环境造成影响。
