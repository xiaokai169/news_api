# 日志系统使用指南

## 概述

本项目已配置完整的日志系统，支持在正式环境中查看和监控各种日志信息。

## 日志配置

### 配置文件位置

-   **主配置**: [`config/packages/monolog.yaml`](config/packages/monolog.yaml)
-   **日志目录**: `var/log/`

### 日志类型

| 日志类型      | 文件名            | 用途                                             | 级别    |
| ------------- | ----------------- | ------------------------------------------------ | ------- |
| 微信 API 日志 | `wechat.log`      | 记录微信 API 调用、access_token 获取、文章同步等 | INFO    |
| API 请求日志  | `api.log`         | 记录所有 API 请求和响应                          | INFO    |
| 数据库日志    | `database.log`    | 记录数据库查询、事务等操作                       | DEBUG   |
| 性能日志      | `performance.log` | 记录性能指标、响应时间等                         | INFO    |
| 错误日志      | `error.log`       | 记录所有错误和异常                               | ERROR   |
| 主日志        | `prod.log`        | 应用程序主日志文件                               | WARNING |

## 如何查看日志

### 方法 1: Web 界面查看

访问日志监控面板：

```
http://你的域名/public/logger_monitor.php
```

**功能特性：**

-   📊 日志文件总览
-   🔍 关键词搜索
-   📋 分类型查看
-   📈 显示行数控制
-   🔄 实时刷新

### 方法 2: 命令行查看

```bash
# 查看微信API日志
tail -f var/log/wechat.log

# 查看错误日志
tail -f var/log/error.log

# 查看最近100行API日志
tail -n 100 var/log/api.log

# 搜索特定关键词
grep "access_token" var/log/wechat.log
```

### 方法 3: 直接文件访问

日志文件位于 `var/log/` 目录下，可以直接下载查看：

-   `var/log/wechat.log` - 微信相关操作
-   `var/log/api.log` - API 请求记录
-   `var/log/error.log` - 错误信息
-   `var/log/database.log` - 数据库操作
-   `var/log/performance.log` - 性能监控

## 日志级别说明

| 级别    | 用途     | 示例                |
| ------- | -------- | ------------------- |
| DEBUG   | 调试信息 | 详细的 API 请求参数 |
| INFO    | 一般信息 | 操作成功记录        |
| WARNING | 警告信息 | 非致命错误          |
| ERROR   | 错误信息 | 异常和失败操作      |

## 微信 API 服务日志

[`WechatApiService`](src/Service/WechatApiService.php) 已配置专用日志通道，记录以下信息：

### 记录的操作

-   ✅ access_token 获取成功/失败
-   ✅ 文章列表获取操作
-   ✅ 文章详情获取操作
-   ✅ 草稿箱操作
-   ✅ 已发布消息获取
-   ✅ 网络错误和异常

### 日志示例

```
[2025-12-05T09:30:15.123] wechat.INFO: 获取access_token成功
[2025-12-05T09:30:16.456] wechat.INFO: 获取文章列表成功，数量: 20
[2025-12-05T09:30:17.789] wechat.ERROR: 获取access_token返回错误: invalid appid
```

## 生产环境配置

### 日志轮转

生产环境已配置日志轮转，防止日志文件过大：

-   主日志保留 30 天
-   专用日志保留 15 天
-   自动压缩旧日志文件

### 性能考虑

-   生产环境默认日志级别为 WARNING（除专用通道外）
-   微信 API 日志保持 INFO 级别以便监控
-   错误日志始终记录所有 ERROR 级别信息

## 故障排查

### 常见问题

1. **日志文件不存在**

    - 检查 `var/log/` 目录权限
    - 确保应用有写入权限

2. **日志无法写入**

    ```bash
    chmod 755 var/log/
    chown www-data:www-data var/log/
    ```

3. **日志文件过大**
    - 检查日志轮转配置
    - 调整日志级别

### 查看实时日志

```bash
# 监控微信API日志
tail -f var/log/wechat.log

# 同时监控多个日志
tail -f var/log/wechat.log var/log/error.log var/log/api.log
```

## 安全注意事项

1. **敏感信息保护**

    - 日志中不记录敏感的 API 密钥
    - 用户数据已脱敏处理

2. **访问控制**

    - 日志监控面板应设置访问权限
    - 建议限制 IP 访问范围

3. **定期清理**
    - 定期清理过期日志文件
    - 监控磁盘空间使用

## 开发环境调试

开发环境日志级别为 DEBUG，记录更详细的信息：

```yaml
# 开发环境配置
when@dev:
    # 显示详细调试信息
    level: debug
```

## 扩展日志功能

如需添加新的日志类型：

1. 在 [`config/packages/monolog.yaml`](config/packages/monolog.yaml) 中添加新通道
2. 在服务中注入专用 Logger
3. 在 [`public/logger_monitor.php`](public/logger_monitor.php) 中添加对应显示

### 示例：添加新的日志类型

```yaml
# 在 monolog.yaml 中添加
custom_log:
    type: stream
    path: "%kernel.logs_dir%/custom.log"
    level: info
    channels: ["custom"]
```

```php
// 在服务中使用
use Psr\Log\LoggerInterface;

class YourService
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
        $this->logger = $this->logger->withName('custom');
    }
}
```

## 联系支持

如遇到日志相关问题，请检查：

1. 日志文件权限
2. 磁盘空间
3. 配置文件语法
4. PHP 错误日志

更多技术支持请参考项目文档或联系开发团队。
