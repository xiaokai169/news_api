# 微信已发布消息同步功能指南

## 概述

根据微信官方文档 [获取已发布消息列表](https://developers.weixin.qq.com/doc/subscription/api/public/api_freepublish_batchget.html)，我们实现了新的已发布消息同步功能。这个功能与之前的素材库同步功能不同，专门用于获取已经发布到公众号的文章。

## 主要区别

### 1. API 接口不同

-   **素材库同步**: 使用 `/material/batchget_material` 接口
-   **已发布消息同步**: 使用 `/freepublish/batchget` 接口

### 2. 数据来源不同

-   **素材库**: 永久素材库中的文章（包括草稿和已发布）
-   **已发布消息**: 仅包含已经发布到公众号的文章

### 3. 数据结构不同

-   **素材库**: 包含 `media_id` 和 `update_time`
-   **已发布消息**: 包含 `article_id` 和 `publish_time`

## 使用方法

### 1. 在代码中使用

```php
// 获取同步服务
$syncService = $container->get('App\Service\WechatArticleSyncService');

// 同步已发布消息
$result = $syncService->syncPublishedArticles(
    $accountId,      // 公众号账户ID
    $forceSync,      // 是否强制同步（默认false）
    $bypassLock,     // 是否绕过锁检查（默认false）
    $beginDate,      // 开始日期（时间戳，默认0）
    $endDate         // 结束日期（时间戳，默认0）
);
```

### 2. 参数说明

-   **accountId**: 公众号账户的唯一标识符
-   **forceSync**:
    -   `true`: 强制同步，即使文章已存在也会更新
    -   `false`: 仅同步新文章，已存在的文章跳过
-   **bypassLock**:
    -   `true`: 绕过分布式锁检查
    -   `false`: 使用分布式锁防止并发同步
-   **beginDate**: 开始日期的时间戳（例如：strtotime('2024-01-01')）
-   **endDate**: 结束日期的时间戳（例如：strtotime('2024-12-31')）

### 3. 返回值

```php
[
    'success' => true, // 是否成功
    'message' => '同步完成消息',
    'stats' => [
        'total' => 100,   // 总计文章数
        'created' => 10,  // 新增文章数
        'updated' => 5,   // 更新文章数
        'skipped' => 85,  // 跳过文章数
        'failed' => 0     // 失败文章数
    ],
    'errors' => [] // 错误信息数组
]
```

## 使用示例

### 示例 1：基本同步

```php
$result = $syncService->syncPublishedArticles('account_123');
```

### 示例 2：强制同步并指定日期范围

```php
$beginDate = strtotime('2024-01-01');
$endDate = strtotime('2024-12-31');
$result = $syncService->syncPublishedArticles('account_123', true, false, $beginDate, $endDate);
```

### 示例 3：绕过锁检查（用于调试）

```php
$result = $syncService->syncPublishedArticles('account_123', false, true);
```

## 测试方法

1. 运行测试脚本：

```bash
php test_published_articles_sync.php
```

2. 确保在测试脚本中替换正确的公众号账户 ID：

```php
$accountId = 'your_wechat_account_id_here'; // 替换为实际的公众号ID
```

## 注意事项

1. **权限要求**: 需要公众号的相应 API 权限
2. **频率限制**: 微信 API 有调用频率限制，请合理控制同步频率
3. **IP 白名单**: 确保服务器 IP 在微信公众平台的 IP 白名单中
4. **错误处理**: 同步过程中会记录详细的错误日志，便于排查问题
5. **分布式锁**: 默认启用分布式锁，防止并发同步导致数据重复

## 故障排除

### 常见问题

1. **获取 access_token 失败**

    - 检查公众号的 AppID 和 AppSecret 是否正确
    - 确认公众号是否已授权相关 API 权限

2. **API 调用返回错误**

    - 检查错误码和错误信息
    - 确认 IP 地址是否在白名单中
    - 检查 API 调用频率是否超限

3. **同步过程中断**
    - 检查网络连接
    - 查看服务器日志获取详细错误信息
    - 确认分布式锁是否正常工作

### 日志查看

同步过程中的详细日志可以在以下位置查看：

-   Symfony 应用日志文件
-   系统日志
-   控制台输出（如果启用调试模式）

## 相关文件

-   `src/Service/WechatApiService.php` - 微信 API 服务
-   `src/Service/WechatArticleSyncService.php` - 文章同步服务
-   `test_published_articles_sync.php` - 测试脚本
