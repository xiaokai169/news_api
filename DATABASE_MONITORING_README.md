# 数据库监控系统

## 概述

本监控系统为生产环境设计，提供完整的数据库连接状态监控、性能指标收集、健康检查和配置验证功能。

## 🎯 主要功能

### 1. 连接可用性检查

-   实时监控所有数据库连接状态
-   检测连接中断和恢复
-   支持多数据库环境

### 2. 响应时间监控

-   精确测量查询响应时间
-   设置响应时间阈值告警
-   历史响应时间趋势分析

### 3. 错误率统计

-   跟踪数据库连接错误
-   计算错误率和可用性
-   错误日志记录和分析

### 4. 数据库连接池状态

-   监控活跃连接数
-   检查连接池使用率
-   预防连接池耗尽

### 5. 性能指标收集

-   每秒查询数 (QPS)
-   慢查询统计
-   缓冲池使用率
-   表锁等待情况

### 6. 日志记录

-   结构化日志输出
-   多级别日志记录
-   日志轮转和清理

### 7. 告警机制

-   多级别告警（INFO/WARNING/CRITICAL）
-   可配置告警阈值
-   多种通知方式支持

## 📁 文件结构

```
├── src/Service/
│   └── DatabaseMonitorService.php     # 核心监控服务
├── public/
│   ├── api_db_status.php               # API状态接口
│   ├── db_config_validator.php         # 配置验证工具
│   └── test_monitoring_system.php     # 系统测试页面
├── scripts/
│   └── database_monitor.sh             # Shell监控脚本
└── DATABASE_MONITORING_README.md       # 本文档
```

## 🚀 快速开始

### 1. 访问测试页面

```
http://your-domain.com/test_monitoring_system.php
```

这个页面会测试所有监控组件并提供详细的状态报告。

### 2. API 接口使用

#### 完整状态检查

```bash
curl "http://your-domain.com/api_db_status.php?token=db_monitor_2024_secure"
```

#### 健康检查

```bash
curl "http://your-domain.com/api_db_status.php?health&token=db_monitor_2024_secure"
```

#### 性能指标

```bash
curl "http://your-domain.com/api_db_status.php?metrics&token=db_monitor_2024_secure"
```

#### 历史统计

```bash
curl "http://your-domain.com/api_db_status.php?history=24&token=db_monitor_2024_secure"
```

#### 特定连接状态

```bash
curl "http://your-domain.com/api_db_status.php?connection=default&token=db_monitor_2024_secure"
```

### 3. 配置验证

```bash
# HTML格式
curl "http://your-domain.com/db_config_validator.php?token=db_config_validator_2024"

# JSON格式
curl "http://your-domain.com/db_config_validator.php?token=db_config_validator_2024&format=json"
```

## ⚙️ 定时任务设置

### Crontab 配置

```bash
# 编辑crontab
crontab -e

# 添加以下任务：

# 每5分钟检查一次数据库状态
*/5 * * * * /path/to/your/project/scripts/database_monitor.sh --quiet

# 每小时发送一次完整报告
0 * * * * /path/to/your/project/scripts/database_monitor.sh --alert-email

# 每天凌晨2点清理旧日志
0 2 * * * /path/to/your/project/scripts/database_monitor.sh --cleanup

# 每周日生成周报
0 8 * * 0 /path/to/your/project/scripts/database_monitor.sh --report
```

### 脚本参数说明

```bash
./database_monitor.sh [选项]

选项:
    --check-only     仅检查状态，不发送通知
    --verbose        详细输出模式
    --quiet          静默模式（适合crontab）
    --log-file FILE  指定日志文件路径
    --alert-email    发送告警邮件
    --timeout SEC    设置请求超时时间（默认30秒）
    --help           显示帮助信息
```

## 🔧 配置说明

### 环境变量配置

确保 `.env` 文件中包含正确的数据库配置：

```env
# 业务数据库
DATABASE_URL="mysql://official_website:password@127.0.0.1:3306/official_website?serverVersion=8.0&charset=utf8mb4"

# 用户数据库（注意：这里应该连接到正确的数据库）
USER_DATABASE_URL="mysql://official_website_user:password@127.0.0.1:3306/official_website_user?serverVersion=8.0&charset=utf8mb4"
```

### ⚠️ 重要配置问题修复

如果发现 `biz_redonly` 用户问题，请按以下步骤修复：

1. **识别问题**：

    ```bash
    curl "http://your-domain.com/db_config_validator.php?token=db_config_validator_2024&format=json"
    ```

2. **修复配置**：

    - 将 `USER_DATABASE_URL` 中的数据库名从 `biz` 改为 `official_website_user`
    - 将用户名从 `biz_redonly` 改为有适当权限的用户

3. **验证修复**：
    ```bash
    curl "http://your-domain.com/db_config_validator.php?token=db_config_validator_2024"
    ```

### 监控阈值配置

在 `DatabaseMonitorService.php` 中可以调整以下配置：

```php
private array $config = [
    'response_time_threshold' => 1000, // ms - 响应时间阈值
    'error_rate_threshold' => 5,       // % - 错误率阈值
    'connection_timeout' => 5,         // seconds - 连接超时
    'max_retry_attempts' => 3,        // 最大重试次数
    'alert_cooldown' => 300,          // seconds - 告警冷却时间
];
```

## 📊 监控指标说明

### 连接状态指标

-   **status**: connected/error/unknown
-   **response_time**: 响应时间（毫秒）
-   **database**: 数据库名称
-   **host**: 主机地址
-   **mysql_version**: MySQL 版本

### 性能指标

-   **queries_per_second**: 每秒查询数
-   **slow_queries**: 慢查询数量
-   **connections_used**: 当前连接数
-   **connections_max**: 最大连接数
-   **buffer_pool_usage**: 缓冲池使用率
-   **table_locks_waited**: 表锁等待次数

### 健康状态

-   **healthy**: 所有连接正常，指标在阈值内
-   **degraded**: 部分连接异常或指标超出阈值
-   **unhealthy**: 多个连接异常或严重性能问题
-   **error**: 检查过程中发生错误

## 🚨 告警处理

### 告警级别

-   **INFO**: 信息性告警，记录日志
-   **WARNING**: 警告级告警，需要关注
-   **CRITICAL**: 严重告警，需要立即处理

### 常见告警场景

1. **连接失败**: 数据库无法连接
2. **响应时间过长**: 超过配置阈值
3. **健康度过低**: 可用性低于 80%
4. **配置错误**: 检测到配置问题

### 告警通知方式

-   日志记录（默认）
-   邮件通知（需配置）
-   可扩展其他通知方式（短信、钉钉等）

## 🔍 故障排查

### 1. API 无法访问

```bash
# 检查文件权限
ls -la public/api_db_status.php

# 检查PHP错误日志
tail -f /var/log/php_errors.log

# 测试直接访问
curl -I http://your-domain.com/api_db_status.php
```

### 2. 监控脚本执行失败

```bash
# 检查脚本权限
chmod +x scripts/database_monitor.sh

# 检查依赖
which curl jq

# 手动执行测试
./scripts/database_monitor.sh --verbose
```

### 3. 数据库连接问题

```bash
# 使用配置验证工具
curl "http://your-domain.com/db_config_validator.php?token=db_config_validator_2024"

# 使用Symfony命令
php bin/console app:check-db-connection --detailed
```

## 📈 性能优化建议

### 1. 数据库层面

-   定期优化表结构
-   合理设置索引
-   监控慢查询日志
-   配置适当的缓存

### 2. 应用层面

-   使用连接池
-   实现查询缓存
-   优化 ORM 查询
-   监控内存使用

### 3. 监控层面

-   调整检查频率
-   优化告警阈值
-   定期清理日志
-   监控监控系统的性能

## 🔐 安全考虑

### 1. 访问控制

-   API 接口使用令牌认证
-   限制 IP 访问范围
-   生产环境禁用调试模式

### 2. 数据保护

-   敏感信息脱敏
-   日志文件权限控制
-   定期轮换访问令牌

### 3. 网络安全

-   使用 HTTPS 传输
-   防止 SQL 注入
-   输入验证和过滤

## 📝 维护指南

### 日常维护

1. **每日检查**：

    - 查看监控报告
    - 检查告警日志
    - 验证系统健康状态

2. **每周维护**：

    - 分析性能趋势
    - 清理旧日志文件
    - 更新监控配置

3. **每月检查**：
    - 审查告警规则
    - 优化监控阈值
    - 备份监控数据

### 升级指南

1. 备份当前配置
2. 测试新版本功能
3. 逐步部署更新
4. 验证监控功能
5. 更新文档

## 📞 支持和联系

如有问题或建议，请：

1. 查看本文档的故障排查部分
2. 检查日志文件获取详细信息
3. 使用测试页面诊断问题
4. 联系系统管理员

---

**版本**: 1.0.0  
**更新时间**: 2024-12-01  
**维护者**: Database Monitor Team
