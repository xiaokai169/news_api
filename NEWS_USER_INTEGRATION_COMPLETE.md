# 新闻文章用户关联功能实现完成

## 📋 实现概述

已成功完成新闻文章系统与用户系统的集成，实现了 JWT Token 解析、双数据库配置、用户关联查询等功能。

## ✅ 完成的功能

### 1. JWT Token 解析功能

-   **创建了 JwtService**: [`src/Service/JwtService.php`](src/Service/JwtService.php:1)

    -   支持从 Authorization header 提取 token
    -   支持从 Cookie 获取 token
    -   提供用户 ID 解析功能
    -   包含 token 验证和错误处理

-   **更新了 NewsController**: [`src/Controller/NewsController.php`](src/Controller/NewsController.php:1)
    -   在 create 方法中集成 JWT 解析
    -   优先使用 token 中的 userId，支持请求参数 fallback
    -   自动将 userId 存储到文章记录中

### 2. 双数据库配置

-   **配置了 Doctrine 多连接**: [`config/packages/doctrine.yaml`](config/packages/doctrine.yaml:1)

    ```yaml
    doctrine:
        dbal:
            default_connection: default
            connections:
                default: # 主数据库
                    url: "%env(resolve:DATABASE_URL)%"
                user: # 用户数据库
                    url: "%env(resolve:USER_DATABASE_URL)%"
    ```

-   **配置了实体管理器**:
    -   `default`: 管理主要实体（SysNewsArticle 等）
    -   `user`: 专门管理 User 实体

### 3. 用户系统实现

-   **创建了 User 实体**: [`src/Entity/User.php`](src/Entity/User.php:1)

    -   包含完整的用户字段（id, username, email, nickname, phone, avatar 等）
    -   提供 getDisplayName()方法优先显示 nickname
    -   支持状态管理和时间戳

-   **创建了 UserRepository**: [`src/Repository/UserRepository.php`](src/Repository/UserRepository.php:1)
    -   配置使用 user 实体管理器
    -   提供按 ID、用户名查询等方法
    -   支持批量查询功能

### 4. 文章 Repository 增强

-   **更新了 SysNewsArticleRepository**: [`src/Repository/SysNewsArticleRepository.php`](src/Repository/SysNewsArticleRepository.php:1)
    -   新增`findByCriteriaWithUser()`方法：支持用户关联查询
    -   新增`findWithUser()`方法：查询单个文章包含用户信息
    -   支持按用户名/昵称搜索
    -   优化跨数据库 JOIN 查询

### 5. API 接口增强

-   **更新了 NewsController 的 list 方法**:

    -   新增`includeUser`参数：控制是否包含用户信息
    -   新增`userName`参数：支持按用户名/昵称搜索
    -   保持向后兼容性

-   **更新了 NewsController 的 show 方法**:
    -   支持单个文章详情包含用户信息
    -   通过`includeUser=true`参数控制

### 6. 数据库迁移

-   **创建了用户表迁移**: [`migrations/Version20251120032400.php`](migrations/Version20251120032400.php:1)
    ```sql
    CREATE TABLE user (
        id INT AUTO_INCREMENT NOT NULL,
        username VARCHAR(180) NOT NULL UNIQUE,
        email VARCHAR(255) NOT NULL UNIQUE,
        nickname VARCHAR(100) DEFAULT NULL,
        phone VARCHAR(20) DEFAULT NULL,
        avatar VARCHAR(500) DEFAULT NULL,
        status TINYINT(1) DEFAULT 1 NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY(id)
    )
    ```

## 🔧 环境配置

### 新增环境变量

```env
# .env.local
DATABASE_URL="mysql://root:qwe147258..@127.0.0.1:3306/official_website?serverVersion=8.0.32&charset=utf8mb4"
USER_DATABASE_URL="mysql://root:qwe147258..@127.0.0.1:3306/app?serverVersion=8.0.32&charset=utf8mb4"
JWT_SECRET_KEY="your-secret-key-change-in-production-12345"
```

### JWT 密钥生成

-   生成了 RSA 密钥对：
    -   私钥: [`config/jwt/private.pem`](config/jwt/private.pem:1)
    -   公钥: [`config/jwt/public.pem`](config/jwt/public.pem:1)

## 📚 API 使用示例

### 1. 创建文章（自动解析 JWT）

```bash
POST /official-api/news
Authorization: Bearer <JWT_TOKEN>

{
  "name": "文章标题",
  "cover": "封面图片URL",
  "content": "文章内容",
  "category": "news"
}
```

### 2. 查询文章列表（包含用户信息）

```bash
GET /official-api/news?includeUser=true&page=1&limit=10
```

### 3. 按用户名搜索文章

```bash
GET /official-api/news?includeUser=true&userName=admin
```

### 4. 按用户 ID 查询文章

```bash
GET /official-api/news?includeUser=true&userId=1
```

### 5. 获取单个文章详情（包含用户信息）

```bash
GET /official-api/news/1?includeUser=true
```

## 📊 响应格式示例

### 包含用户信息的文章列表

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "文章标题",
            "cover": "封面URL",
            "content": "文章内容",
            "userId": 1,
            "user": {
                "id": 1,
                "username": "admin",
                "nickname": "管理员",
                "email": "admin@example.com",
                "avatar": "头像URL"
            },
            "createTime": "2025-11-20T03:00:00+00:00"
        }
    ],
    "meta": {
        "total": 1,
        "page": 1,
        "limit": 10,
        "pages": 1
    }
}
```

## 🧪 测试工具

### 1. JWT Token 测试脚本

-   **文件**: [`test_news_jwt_token.php`](test_news_jwt_token.php:1)
-   **功能**: 测试 JWT token 解析和文章创建
-   **测试场景**:
    -   无 token 请求
    -   无效 token 请求
    -   有效 token 请求

### 2. 用户关联功能测试脚本

-   **文件**: [`test_news_with_user.php`](test_news_with_user.php:1)
-   **功能**: 测试用户关联查询功能
-   **测试场景**:
    -   基本查询（不包含用户信息）
    -   包含用户信息的查询
    -   按用户名搜索
    -   按用户 ID 查询
    -   单个文章详情（包含用户信息）

## 🚀 部署步骤

### 1. 运行数据库迁移

```bash
# 创建用户表
php bin/console doctrine:migrations:migrate --em=user

# 如果有其他迁移
php bin/console doctrine:migrations:migrate
```

### 2. 清除缓存

```bash
php bin/console cache:clear
php bin/console cache:clear --env=prod
```

### 3. 验证配置

```bash
php bin/console debug:config doctrine
php bin/console debug:container jwt_service
```

## 🔍 故障排查

### 常见问题

1. **用户数据库连接失败**

    - 检查`USER_DATABASE_URL`环境变量
    - 确认用户数据库存在
    - 验证数据库权限

2. **JWT Token 解析失败**

    - 检查 token 格式：`Bearer <token>`
    - 验证 JWT_SECRET_KEY 配置
    - 确认 token 未过期

3. **用户信息不显示**
    - 确认`includeUser=true`参数
    - 检查文章是否关联了用户 ID
    - 验证用户表中是否存在对应记录

### 调试命令

```bash
# 检查数据库连接
php bin/console doctrine:database:create --connection=user

# 查看迁移状态
php bin/console doctrine:migrations:status --em=user

# 验证实体映射
php bin/console doctrine:mapping:info
```

## 📈 性能优化

### 1. 查询优化

-   使用 LEFT JOIN 避免 N+1 查询问题
-   在 Repository 中预加载用户信息
-   支持条件性用户信息加载

### 2. 缓存策略

-   用户信息可以缓存（变化不频繁）
-   文章列表查询结果缓存
-   JWT token 验证结果缓存

## 🔒 安全考虑

1. **JWT 安全**

    - 使用 RSA 非对称加密
    - 设置合理的 token 过期时间
    - 生产环境更换密钥

2. **数据库安全**

    - 用户数据库权限分离
    - 敏感信息加密存储
    - 定期备份数据

3. **API 安全**
    - 参数验证和过滤
    - SQL 注入防护
    - 访问权限控制

## ✅ 总结

本次实现成功完成了以下核心功能：

1. ✅ **JWT Token 解析**: 自动从请求中提取用户 ID
2. ✅ **双数据库配置**: 主数据库 + 用户数据库分离
3. ✅ **用户关联查询**: 支持文章与用户的关联显示
4. ✅ **API 接口增强**: 新增用户相关查询参数
5. ✅ **向后兼容**: 不影响现有 API 功能
6. ✅ **测试工具**: 提供完整的测试脚本

系统现在具备了完整的用户管理和文章关联功能，可以安全地从 JWT token 中解析用户信息，并在 API 响应中包含用户详情。
