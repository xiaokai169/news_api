# 文章管理系统完整指南

基于需求文档实现的 SysNewsArticle 实体增删改查完整逻辑系统。

## 系统架构

### 核心组件

1. **实体类** (`src/Entity/SysNewsArticle.php`)

    - 完整的字段定义和验证约束
    - 预约发布状态自动判定逻辑
    - 业务状态管理方法
    - 格式化时间显示方法

2. **仓库类** (`src/Repository/SysNewsArticleRepository.php`)

    - 多条件查询支持
    - 定时发布文章查询
    - 批量状态更新
    - 统计功能

3. **控制器类** (`src/Controller/NewsController.php`)

    - 完整的 CRUD API 接口
    - 数据验证和业务逻辑
    - OpenAPI 文档支持

4. **发布服务** (`src/Service/NewsPublishService.php`)

    - 定时发布任务执行
    - 延迟发布检测
    - 发布统计信息
    - 手动强制发布

5. **命令行工具** (`src/Command/NewsPublishCommand.php`)

    - 定时任务执行
    - 发布统计查看
    - 延迟发布检查
    - 手动强制发布

6. **分布式锁服务** (`src/Service/DistributedLockService.php`)
    - 并发控制
    - 防止重复执行
    - 锁管理

## 安装和配置

### 1. 数据库迁移

```bash
# 执行数据库迁移
php bin/console doctrine:migrations:migrate
```

这将创建：

-   `sys_news_article` 表（文章主表）
-   `distributed_locks` 表（分布式锁表）

### 2. 定时任务配置

#### Linux/Unix 系统 (crontab)

```bash
# 编辑crontab
crontab -e

# 添加以下行（每分钟执行一次）
* * * * * cd /path/to/your/project && php bin/console app:news:publish >> /var/log/news-publish.log 2>&1

# 或者使用完整路径
* * * * * /usr/bin/php /path/to/your/project/bin/console app:news:publish >> /var/log/news-publish.log 2>&1
```

#### Windows 系统 (任务计划程序)

1. 打开"任务计划程序"
2. 创建基本任务
3. 设置触发器为"每天"，开始时间设为当前时间
4. 操作设置为"启动程序"
5. 程序/脚本：`C:\path\to\php.exe`
6. 参数：`C:\path\to\project\bin\console app:news:publish`
7. 开始于：`C:\path\to\project`

## API 接口文档

### 基础信息

-   **Base URL**: `http://your-domain/api`
-   **Content-Type**: `application/json`

### 1. 创建文章

**POST** `/news`

**请求体**:

```json
{
    "name": "文章标题",
    "cover": "封面图片URL",
    "content": "文章内容",
    "category": 1,
    "perfect": "摘要内容",
    "releaseTime": "2025-11-17 12:00:00",
    "originalUrl": "原文链接",
    "isRecommend": false,
    "merchantId": 1,
    "userId": 1
}
```

**字段说明**:

-   `name`: 文章标题（必填，最大 10 字符）
-   `cover`: 封面图片 URL（必填）
-   `content`: 文章内容（必填，最大 255 字符）
-   `category`: 分类 ID（必填，必须存在）
-   `perfect`: 摘要内容（可选，最大 255 字符）
-   `releaseTime`: 发布时间（可选，格式：Y-m-d H:i:s）
-   `originalUrl`: 原文链接（可选）
-   `isRecommend`: 是否推荐（可选，默认 false）
-   `merchantId`: 商户 ID（可选，默认 0）
-   `userId`: 用户 ID（可选，默认 0）

**发布状态逻辑**:

-   如果 `releaseTime` 为空：立即发布（status=1）
-   如果 `releaseTime` 为未来时间：等待发布（status=2）
-   如果 `releaseTime` 为过去时间：立即发布（status=1）

### 2. 查询文章列表

**GET** `/news`

**查询参数**:

-   `page`: 页码（默认 1）
-   `limit`: 每页数量（默认 20）
-   `merchantId`: 商户 ID
-   `userId`: 用户 ID
-   `status`: 状态（1=激活，2=非激活）
-   `isRecommend`: 是否推荐（true/false）
-   `category`: 分类 ID
-   `keyword`: 关键词搜索（标题或内容）
-   `publishStatus`: 发布状态（published=已发布，scheduled=待发布）
-   `startTime`: 开始时间（创建时间）
-   `endTime`: 结束时间（创建时间）
-   `sort`: 排序字段（createTime, updateTime, releaseTime, id）
-   `order`: 排序方向（asc, desc）

**示例**:

```
GET /news?page=1&limit=10&status=1&isRecommend=true&keyword=测试&sort=createTime&order=desc
```

### 3. 获取文章详情

**GET** `/news/{id}`

### 4. 更新文章

**PUT** `/news/{id}`

**请求体**:

```json
{
    "name": "更新后的标题",
    "cover": "更新后的封面",
    "content": "更新后的内容",
    "category": 2,
    "perfect": "更新后的摘要",
    "releaseTime": "2025-11-18 10:00:00",
    "originalUrl": "更新后的原文链接",
    "isRecommend": true,
    "status": 1
}
```

**发布时间更新特殊逻辑**:

-   从无到有：设置未来时间 → 状态改为非激活
-   从未来到更早：提前到当前或过去时间 → 状态改为激活
-   从有到无：删除发布时间 → 状态改为激活，releaseTime 设为当前时间

### 5. 删除文章

**DELETE** `/news/{id}`

执行逻辑删除，将状态改为 3（删除状态）

### 6. 恢复文章

**POST** `/news/{id}/restore`

恢复已删除的文章，根据 releaseTime 重新计算发布状态

### 7. 设置文章状态

**PUT** `/news/{id}/status`

**请求体**:

```json
{
    "status": 1
}
```

## 命令行工具使用

### 1. 执行发布任务

```bash
# 常规执行（每分钟执行一次）
php bin/console app:news:publish

# 强制执行（忽略分布式锁）
php bin/console app:news:publish --force

# 查看发布统计
php bin/console app:news:publish --stats

# 检查延迟发布的文章
php bin/console app:news:publish --check-delayed

# 手动强制发布指定文章
php bin/console app:news:publish --article-id=123
```

### 2. 命令输出示例

**常规执行**:

```
执行常规发布任务

 [OK] 发布任务执行成功，共发布 2 篇文章

发布的文章ID: 45, 78
```

**查看统计**:

```
文章发布统计信息

┌─────────────────┬──────┐
│ 统计项          │ 数量 │
├─────────────────┼──────┤
│ 待发布文章      │ 5    │
│ 已发布文章      │ 120  │
│ 延迟发布文章    │ 2    │
│ 今日发布成功    │ 15   │
│ 今日发布失败    │ 0    │
│ 发布成功率      │ 100% │
└─────────────────┴──────┘

 [!] 发现 2 篇延迟发布的文章，建议检查系统状态
```

## 业务逻辑详解

### 状态管理

-   **STATUS_ACTIVE (1)**: 激活状态，文章在前台显示
-   **STATUS_INACTIVE (2)**: 非激活状态，文章等待定时发布
-   **STATUS_DELETED (3)**: 删除状态，逻辑删除

### 预约发布流程

1. **创建文章时**:

    - 如果设置未来发布时间：状态设为非激活（2）
    - 如果发布时间为空或过去时间：状态设为激活（1）

2. **定时任务执行**:

    - 每分钟扫描一次需要发布的文章
    - 条件：status=2 AND releaseTime<=当前时间
    - 批量更新状态为激活（1）

3. **手动干预**:
    - 可以通过 API 或命令行强制发布
    - 可以修改发布时间触发状态变更

### 数据验证规则

1. **必填字段**: name, cover, content, category
2. **长度限制**:
    - name: 最大 10 字符
    - content: 最大 255 字符
    - perfect: 最大 255 字符
3. **数值验证**:
    - merchantId, userId: 大于等于 0 的整数
    - status: 只能是 1 或 2
4. **关联验证**: category 必须指向已存在的分类

## 监控和报警

### 1. 发布监控

系统自动记录发布日志，可以通过以下方式监控：

```bash
# 查看发布统计
php bin/console app:news:publish --stats

# 检查延迟发布
php bin/console app:news:publish --check-delayed
```

### 2. 日志文件

定时任务执行日志：

-   `/var/log/news-publish.log` (Linux)
-   项目根目录下的日志文件 (Windows)

### 3. 关键指标

-   发布成功率
-   延迟发布文章数量
-   待发布文章数量
-   发布任务执行频率

## 故障排除

### 常见问题

1. **文章未按计划发布**

    - 检查定时任务是否正常运行
    - 检查系统时间是否正确
    - 检查分布式锁是否正常

2. **API 返回验证错误**

    - 检查必填字段是否提供
    - 检查字段长度是否符合要求
    - 检查分类 ID 是否存在

3. **性能问题**
    - 检查数据库索引
    - 优化查询条件
    - 考虑分页查询

### 调试命令

```bash
# 检查系统状态
php bin/console about

# 检查数据库连接
php bin/console doctrine:query:sql "SELECT 1"

# 查看所有可用命令
php bin/console list
```

## 最佳实践

1. **定时任务配置**

    - 建议每分钟执行一次
    - 配置日志轮转防止日志文件过大
    - 监控任务执行状态

2. **API 使用**

    - 使用分页查询避免数据量过大
    - 合理设置查询条件提高性能
    - 处理网络超时和重试

3. **数据管理**
    - 定期清理已删除的文章
    - 监控延迟发布的文章
    - 备份重要数据

## 扩展开发

系统设计具有良好的扩展性，可以轻松添加：

1. **新的查询条件**
2. **额外的业务规则**
3. **集成第三方服务**
4. **自定义状态流转**

如需扩展功能，请参考现有代码结构和设计模式。
