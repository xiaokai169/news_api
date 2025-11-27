# 公共 API 接口文档

## 概述

本文档描述了为前端提供的公共 API 接口，这些接口不需要登录验证即可访问。

## 基础信息

-   **基础 URL**: `/public-api`
-   **认证方式**: 无需认证
-   **数据格式**: JSON
-   **字符编码**: UTF-8

## 接口列表

### 1. 获取文章列表

**接口地址**: `GET /public-api/articles`

**功能描述**: 获取新闻或公众号文章列表，支持分页

**请求参数**:

| 参数名 | 类型    | 必填 | 默认值 | 说明                                      |
| ------ | ------- | ---- | ------ | ----------------------------------------- |
| type   | string  | 是   | -      | 文章类型：news（新闻）或 wechat（公众号） |
| limit  | integer | 否   | 20     | 每页数量，最大 100                        |
| page   | integer | 否   | 1      | 页码，从 1 开始                           |

**请求示例**:

```
GET /public-api/articles?type=news&limit=10&page=1
GET /public-api/articles?type=wechat&limit=5&page=2
```

**响应示例**:

```json
{
    "success": true,
    "data": {
        "items": [
            {
                "id": 1,
                "title": "文章标题",
                "cover": "封面图片URL",
                "summary": "文章摘要",
                "releaseTime": "2023-12-01 10:00:00",
                "category": {
                    "id": 1,
                    "name": "分类名称"
                },
                "isRecommend": true,
                "perfect": "完美描述",
                "createTime": "2023-12-01 09:00:00"
            }
        ],
        "total": 100,
        "page": 1,
        "limit": 10,
        "pages": 10,
        "type": "news"
    }
}
```

### 2. 获取新闻文章详情

**接口地址**: `GET /public-api/news/{id}`

**功能描述**: 获取指定 ID 的新闻文章详情

**路径参数**:

| 参数名 | 类型    | 必填 | 说明    |
| ------ | ------- | ---- | ------- |
| id     | integer | 是   | 文章 ID |

**请求示例**:

```
GET /public-api/news/123
```

**响应示例**:

```json
{
    "success": true,
    "data": {
        "id": 123,
        "title": "新闻文章标题",
        "cover": "封面图片URL",
        "content": "文章完整内容",
        "releaseTime": "2023-12-01 10:00:00",
        "originalUrl": "原文链接",
        "category": {
            "id": 1,
            "name": "分类名称"
        },
        "isRecommend": true,
        "perfect": "完美描述",
        "createTime": "2023-12-01 09:00:00",
        "updateTime": "2023-12-01 11:00:00"
    }
}
```

### 3. 获取公众号文章详情

**接口地址**: `GET /public-api/wechat/{id}`

**功能描述**: 获取指定 ID 的公众号文章详情

**路径参数**:

| 参数名 | 类型    | 必填 | 说明    |
| ------ | ------- | ---- | ------- |
| id     | integer | 是   | 文章 ID |

**请求示例**:

```
GET /public-api/wechat/456
```

**响应示例**:

```json
{
    "success": true,
    "data": {
        "id": 456,
        "title": "公众号文章标题",
        "content": "文章内容",
        "releaseTime": "发布时间字符串",
        "originalUrl": "原文链接",
        "articleId": "微信文章ID",
        "createTime": "2023-12-01 09:00:00",
        "updateTime": "2023-12-01 10:00:00",
        "category": {
            "id": 1,
            "name": "分类名称"
        }
    }
}
```

## 错误响应

所有接口在出错时都会返回统一的错误格式：

```json
{
    "success": false,
    "error": "错误描述信息"
}
```

### 常见错误码

| HTTP 状态码 | 说明           |
| ----------- | -------------- |
| 400         | 请求参数错误   |
| 404         | 资源不存在     |
| 500         | 服务器内部错误 |

## 数据过滤规则

### 新闻文章

-   只返回状态为"已发布"(STATUS_ACTIVE)的文章
-   排除已删除的文章
-   按创建时间倒序排列

### 公众号文章

-   只返回状态为 1 的文章
-   按创建时间倒序排列

## 安全说明

1. **无需认证**: 所有公共接口都不需要登录验证
2. **数据过滤**: 只返回适合公开显示的数据
3. **参数验证**: 所有输入参数都经过严格验证
4. **错误处理**: 统一的错误响应格式

## 技术实现

### 控制器

-   `PublicController`: 处理所有公共 API 请求
-   路由前缀: `/public-api`

### 数据访问层

-   `SysNewsArticleRepository::findActivePublicArticles()`: 获取公共新闻文章
-   `SysNewsArticleRepository::countActivePublicArticles()`: 统计公共新闻文章数量
-   `OfficialRepository::findActivePublicArticles()`: 获取公共公众号文章
-   `OfficialRepository::countActivePublicArticles()`: 统计公共公众号文章数量

### 调试日志

所有接口都包含调试日志，便于开发和维护：

```
[DEBUG] PublicController::getArticles - 访问公共文章列表接口
[DEBUG] PublicController::getNewsDetail - 访问公共新闻详情接口，ID: 123
[DEBUG] PublicController::getWechatDetail - 访问公共微信文章详情接口，ID: 456
```

## 使用示例

### JavaScript/Ajax 示例

```javascript
// 获取新闻列表
fetch("/public-api/articles?type=news&limit=10")
    .then((response) => response.json())
    .then((data) => {
        if (data.success) {
            console.log("新闻列表:", data.data.items);
        }
    })
    .catch((error) => console.error("请求失败:", error));

// 获取新闻详情
fetch("/public-api/news/123")
    .then((response) => response.json())
    .then((data) => {
        if (data.success) {
            console.log("新闻详情:", data.data);
        }
    });
```

### PHP cURL 示例

```php
// 获取公众号文章列表
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, '/public-api/articles?type=wechat&limit=5');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$data = json_decode($response, true);

if ($data['success']) {
    foreach ($data['data']['items'] as $article) {
        echo "标题: " . $article['title'] . "\n";
    }
}
```
