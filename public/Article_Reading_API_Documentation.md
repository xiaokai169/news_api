# 文章阅读数量功能 API 文档

## 概述

本文档描述了文章阅读数量功能的完整 API 接口，包括阅读记录、统计查询、热门文章等功能。

## 数据库表结构

### 1. sys_news_article (文章表)

新增字段：

-   `view_count` (INT) - 阅读数量，默认值 0

### 2. article_read_logs (阅读记录表)

| 字段名           | 类型         | 说明                    |
| ---------------- | ------------ | ----------------------- |
| id               | INT          | 主键                    |
| article_id       | INT          | 文章 ID                 |
| user_id          | INT          | 用户 ID，0 表示匿名用户 |
| ip_address       | VARCHAR(45)  | IP 地址                 |
| user_agent       | VARCHAR(500) | 用户代理                |
| read_time        | DATETIME     | 阅读时间                |
| session_id       | VARCHAR(255) | 会话 ID                 |
| device_type      | VARCHAR(50)  | 设备类型                |
| referer          | VARCHAR(500) | 来源页面                |
| duration_seconds | INT          | 阅读时长（秒）          |
| is_completed     | TINYINT(1)   | 是否完成阅读            |
| create_at        | DATETIME     | 创建时间                |
| update_at        | DATETIME     | 更新时间                |

### 3. article_read_statistics (阅读统计表)

| 字段名               | 类型          | 说明               |
| -------------------- | ------------- | ------------------ |
| id                   | INT           | 主键               |
| article_id           | INT           | 文章 ID            |
| stat_date            | DATE          | 统计日期           |
| total_reads          | INT           | 总阅读次数         |
| unique_users         | INT           | 独立用户数         |
| anonymous_reads      | INT           | 匿名用户阅读次数   |
| registered_reads     | INT           | 注册用户阅读次数   |
| avg_duration_seconds | DECIMAL(10,2) | 平均阅读时长（秒） |
| completion_rate      | DECIMAL(5,2)  | 完成率（百分比）   |
| create_at            | DATETIME      | 创建时间           |
| update_at            | DATETIME      | 更新时间           |

## API 接口

### 1. 记录文章阅读

**接口地址：** `POST /official-api/article-read`

**请求参数：**

```json
{
    "articleId": 1, // 文章ID（必填）
    "userId": 1, // 用户ID，可选，0表示匿名用户
    "durationSeconds": 120, // 阅读时长（秒），可选
    "isCompleted": true, // 是否完成阅读，可选
    "deviceType": "mobile", // 设备类型，可选：desktop/mobile/tablet/unknown
    "sessionId": "abc123", // 会话ID，可选
    "referer": "https://example.com" // 来源页面，可选
}
```

**响应示例：**

```json
{
    "status": "200",
    "message": "阅读记录成功",
    "data": {
        "id": 1,
        "articleId": 1,
        "userId": 1,
        "durationSeconds": 120,
        "isCompleted": true,
        "deviceType": "mobile",
        "readTime": "2024-01-01T12:00:00+00:00"
    },
    "timestamp": 1704110400
}
```

### 2. 获取文章阅读统计

**接口地址：** `GET /official-api/article-read/statistics`

**查询参数：**

-   `articleId` (int) - 文章 ID，可选
-   `userId` (int) - 用户 ID，可选
-   `deviceType` (string) - 设备类型，可选
-   `statType` (string) - 统计类型：daily/weekly/monthly/overall，默认 daily
-   `readTimeFrom` (string) - 开始时间，格式：Y-m-d H:i:s
-   `readTimeTo` (string) - 结束时间，格式：Y-m-d H:i:s
-   `page` (int) - 页码，默认 1
-   `limit` (int) - 每页数量，默认 10

**响应示例：**

```json
{
    "status": "200",
    "message": "success",
    "data": [
        {
            "articleId": 1,
            "statDate": "2024-01-01",
            "totalReads": 100,
            "uniqueUsers": 80,
            "anonymousReads": 30,
            "registeredReads": 70,
            "avgDurationSeconds": 120.5,
            "completionRate": 85.0
        }
    ],
    "pagination": {
        "total": 1,
        "page": 1,
        "limit": 10,
        "pages": 1
    },
    "timestamp": 1704110400
}
```

### 3. 获取热门文章

**接口地址：** `GET /official-api/article-read/popular`

**查询参数：**

-   `limit` (int) - 返回数量，默认 10
-   `days` (int) - 统计天数，默认 7 天
-   `deviceType` (string) - 设备类型过滤，可选

**响应示例：**

```json
{
    "status": "200",
    "message": "success",
    "data": [
        {
            "articleId": 1,
            "title": "热门文章1",
            "totalReads": 500,
            "uniqueUsers": 400,
            "avgDurationSeconds": 180.0,
            "completionRate": 90.0
        }
    ],
    "timestamp": 1704110400
}
```

### 4. 获取用户阅读历史

**接口地址：** `GET /official-api/article-read/history`

**查询参数：**

-   `userId` (int) - 用户 ID，必填
-   `page` (int) - 页码，默认 1
-   `limit` (int) - 每页数量，默认 10
-   `deviceType` (string) - 设备类型过滤，可选

**响应示例：**

```json
{
    "status": "200",
    "message": "success",
    "data": {
        "items": [
            {
                "id": 1,
                "articleId": 1,
                "articleTitle": "文章标题",
                "readTime": "2024-01-01T12:00:00+00:00",
                "durationSeconds": 120,
                "isCompleted": true,
                "deviceType": "mobile"
            }
        ],
        "total": 1,
        "page": 1,
        "limit": 10,
        "pages": 1
    },
    "timestamp": 1704110400
}
```

## 增强的文章列表接口

原有的文章列表接口已增强，现在包含阅读数量相关字段：

**接口地址：** `GET /official-api/news`

**新增响应字段：**

```json
{
    "items": [
        {
            "id": 1,
            "name": "文章标题",
            // ... 其他原有字段
            "viewCount": 100, // 阅读数量
            "formattedViewCount": "100", // 格式化的阅读数量
            "readHeatLevel": "hot", // 阅读热度级别：cold/warm/hot/explosive
            "readHeatDescription": "热门", // 阅读热度描述
            "isPopular": true, // 是否热门文章
            "isExplosive": false // 是否爆款文章
        }
    ]
}
```

## 阅读热度级别说明

| 级别      | 阅读数量范围 | 描述 |
| --------- | ------------ | ---- |
| cold      | 0-49         | 冷门 |
| warm      | 50-199       | 温和 |
| hot       | 200-999      | 热门 |
| explosive | 1000+        | 爆款 |

## 错误响应格式

所有错误响应都遵循统一格式：

```json
{
    "status": "500",
    "message": "错误描述",
    "timestamp": 1704110400
}
```

## 常见错误码

-   `400` - 请求参数错误
-   `404` - 文章不存在
-   `500` - 服务器内部错误

## 使用示例

### JavaScript/Ajax 示例

```javascript
// 记录文章阅读
fetch("/official-api/article-read", {
    method: "POST",
    headers: {
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        articleId: 1,
        userId: 1,
        durationSeconds: 120,
        isCompleted: true,
        deviceType: "mobile",
    }),
})
    .then((response) => response.json())
    .then((data) => console.log(data));

// 获取热门文章
fetch("/official-api/article-read/popular?limit=5")
    .then((response) => response.json())
    .then((data) => console.log(data));
```

### PHP cURL 示例

```php
// 记录文章阅读
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8001/official-api/article-read');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'articleId' => 1,
    'userId' => 1,
    'durationSeconds' => 120,
    'isCompleted' => true
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$data = json_decode($response, true);
curl_close($ch);
```

## 性能优化建议

1. **缓存策略**：热门文章和统计数据建议使用 Redis 缓存，缓存时间 5-10 分钟
2. **异步处理**：阅读统计更新可以异步处理，避免影响用户阅读体验
3. **批量更新**：统计数据的更新可以使用批量操作，减少数据库压力
4. **索引优化**：确保相关字段都有适当的数据库索引

## 安全考虑

1. **防刷机制**：同一用户同一文章一天内只记录一次有效阅读
2. **IP 限制**：对异常 IP 进行访问频率限制
3. **数据验证**：严格验证所有输入参数
4. **权限控制**：用户历史查询只能查询自己的记录

## 更新日志

-   **2024-01-01** - 初始版本发布
-   **2024-01-02** - 添加设备类型检测
-   **2024-01-03** - 增加阅读热度级别功能
