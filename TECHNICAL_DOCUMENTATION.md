# 新闻文章系统用户关联功能技术文档

## 📋 项目概述

本文档详细描述了为新闻文章系统添加用户关联功能的完整实现，包括 JWT Token 解析、双数据库配置、用户实体创建、API 接口增强等功能。

## 🏗️ 系统架构

### 架构设计原则

1. **分离关注点**: 用户数据与文章数据存储在不同数据库
2. **向后兼容**: 新功能不影响现有 API 接口
3. **性能优化**: 使用 LEFT JOIN 避免 N+1 查询问题
4. **安全性**: JWT Token 验证和参数过滤

### 数据库架构

```
┌─────────────────┐    ┌─────────────────┐
│  主数据库        │    │  用户数据库      │
│ (official_website)│    │     (app)       │
├─────────────────┤    ├─────────────────┤
│ sys_news_article │    │      user       │
│ sys_news_category│    │                 │
│ ...             │    │                 │
└─────────────────┘    └─────────────────┘
         │                       │
         └───────────┬───────────┘
                     │
              ┌─────────────┐
              │  应用层      │
              │ (Symfony)   │
              └─────────────┘
```

## 🔒 安全架构：用户只读权限

### 安全设计原则

1. **多层防护**: 从数据库、应用、事件、服务四个层面确保只读权限
2. **权限分离**: 用户数据修改只能通过专门的用户管理系统
3. **最小权限**: 应用只能读取用户数据，不能修改
4. **审计日志**: 所有用户数据访问都有日志记录

### 只读权限实现层次

#### 1. 数据库层面

-   **只读用户**: 创建专门的用户数据库只读账户
-   **权限控制**: `GRANT SELECT ON app.* TO 'readonly_user'@'%';`
-   **配置文件**: [`config/packages/doctrine.yaml`](config/packages/doctrine.yaml:9-16)

```yaml
user:
    url: "%env(resolve:USER_DATABASE_URL)%"
    driver_options:
        1000: true # PDO::ATTR_EMULATE_PREPARES
        1002: "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'"
```

#### 2. 实体层面

-   **文件位置**: [`src/Entity/User.php`](src/Entity/User.php:1)
-   **移除 setter 方法**: 所有修改方法已被移除
-   **只读注释**: 明确标注只读用途

```php
// 注意：User实体为只读，不提供任何修改方法
// 所有用户数据的修改都应该通过专门的用户管理系统进行
```

#### 3. Repository 层面

-   **文件位置**: [`src/Repository/UserRepository.php`](src/Repository/UserRepository.php:1)
-   **只读方法**: 只提供查询方法，无 save/update/delete
-   **文档注释**: 明确标注只读特性

#### 4. 事件监听层面

-   **文件位置**: [`src/EventListener/UserDatabaseReadonlyListener.php`](src/EventListener/UserDatabaseReadonlyListener.php:1)
-   **阻止写操作**: 监听 prePersist/preUpdate/preRemove 事件
-   **异常抛出**: 任何写操作都会抛出 AccessDeniedHttpException

```php
public function prePersist(PrePersistEventArgs $args): void
{
    if ($this->isUserEntity($args->getObject())) {
        throw new AccessDeniedHttpException('用户数据库为只读模式，不允许创建用户数据');
    }
}
```

#### 5. 服务层面

-   **文件位置**: [`src/Service/UserReadOnlyService.php`](src/Service/UserReadOnlyService.php:1)
-   **只读服务**: 提供安全的用户数据访问接口
-   **数据格式化**: 统一的 API 响应格式

### 安全配置文件

-   **配置文件**: [`config/packages/security_readonly.yaml`](config/packages/security_readonly.yaml:1)
-   **事件监听**: 配置写操作阻止监听器
-   **缓存优化**: 只读模式的缓存配置

## 🔧 核心组件实现

### 1. JWT Token 解析服务

#### 文件位置

-   **服务类**: [`src/Service/JwtService.php`](src/Service/JwtService.php:1)
-   **配置文件**: [`config/jwt/private.pem`](config/jwt/private.pem:1), [`config/jwt/public.pem`](config/jwt/public.pem:1)

#### 核心功能

```php
class JwtService
{
    // 从请求中提取token
    public function getTokenFromRequest(Request $request): ?string

    // 解析JWT token
    public function decodeToken(string $token): ?array

    // 从token中获取用户ID
    public function getUserIdFromRequest(Request $request): ?int

    // 生成测试token
    public function generateToken(array $payload, int $expiresIn = 3600): string
}
```

#### 使用方式

```php
// 在Controller中使用
$userId = $this->jwtService->getUserIdFromRequest($request);
if ($userId) {
    $article->setUserId($userId);
}
```

### 2. 双数据库配置

#### 配置文件

-   **Doctrine 配置**: [`config/packages/doctrine.yaml`](config/packages/doctrine.yaml:1)
-   **环境变量**: [`.env.local`](.env.local:1)

#### 配置结构

```yaml
doctrine:
    dbal:
        default_connection: default
        connections:
            default:
                url: "%env(resolve:DATABASE_URL)%"
            user:
                url: "%env(resolve:USER_DATABASE_URL)%"

    orm:
        default_entity_manager: default
        entity_managers:
            default:
                connection: default
                mappings:
                    App:
                        prefix: 'App\Entity'
            user:
                connection: user
                mappings:
                    User:
                        prefix: 'App\Entity'
```

#### 环境变量配置

```env
DATABASE_URL="mysql://root:qwe147258..@127.0.0.1:3306/official_website?serverVersion=8.0.32&charset=utf8mb4"
USER_DATABASE_URL="mysql://root:qwe147258..@127.0.0.1:3306/app?serverVersion=8.0.32&charset=utf8mb4"
JWT_SECRET_KEY="your-secret-key-change-in-production-12345"
```

### 3. 用户实体系统

#### User 实体

-   **文件位置**: [`src/Entity/User.php`](src/Entity/User.php:1)
-   **表名**: `users`
-   **主要字段**:

    ```php
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $username;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $email;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $nickname = null;

    #[ORM\Column(type: 'smallint')]
    private int $status = 1;
    ```

#### UserRepository

-   **文件位置**: [`src/Repository/UserRepository.php`](src/Repository/UserRepository.php:1)
-   **特点**: 使用 user 实体管理器
-   **主要方法**:
    ```php
    public function findByIds(array $userIds): array
    public function findByUsername(string $username): ?User
    public function findByEmail(string $email): ?User
    public function searchByKeyword(string $keyword, int $limit = 20): array
    ```

### 4. 文章 Repository 增强

#### 新增方法

-   **文件位置**: [`src/Repository/SysNewsArticleRepository.php`](src/Repository/SysNewsArticleRepository.php:1)

##### findByCriteriaWithUser()

```php
public function findByCriteriaWithUser(
    array $criteria = [],
    ?int $limit = null,
    ?int $offset = null,
    ?string $sortBy = 'createTime',
    ?string $sortOrder = 'desc'
): array
```

-   支持跨数据库 JOIN 查询
-   包含用户信息预加载
-   支持按用户名/昵称搜索

##### findWithUser()

```php
public function findWithUser(int $id): ?SysNewsArticle
```

-   查询单个文章包含用户信息
-   使用 LEFT JOIN 避免 N+1 问题

#### 查询优化

```php
$qb = $this->createQueryBuilder('article')
    ->leftJoin('article.category', 'category')
    ->leftJoin(User::class, 'user', 'WITH', 'user.id = article.userId')
    ->addSelect('category')
    ->addSelect('user');
```

### 5. API 接口增强

#### NewsController 更新

-   **文件位置**: [`src/Controller/NewsController.php`](src/Controller/NewsController.php:1)

##### JWT 集成 (create 方法)

```php
public function create(Request $request): JsonResponse
{
    // 从token中解析userId
    $userId = $this->jwtService->getUserIdFromRequest($request);

    // 优先使用token中的userId
    if ($userId) {
        $article->setUserId($userId);
    } elseif (isset($data['userId'])) {
        $article->setUserId($data['userId']);
    }
}
```

##### 用户信息查询 (list 方法)

```php
public function list(Request $request): JsonResponse
{
    // 新增参数支持
    $userName = $request->query->get('userName');
    $includeUser = $request->query->get('includeUser', 'false');
    $includeUser = filter_var($includeUser, FILTER_VALIDATE_BOOLEAN);

    // 条件性查询
    if ($includeUser) {
        $articles = $this->sysNewsArticleRepository->findByCriteriaWithUser($criteria, $limit, $offset, $sortBy, $sortOrder);
    } else {
        $articles = $this->sysNewsArticleRepository->findByCriteria($criteria, $limit, $offset, $sortBy, $sortOrder);
    }
}
```

##### 单个文章详情 (show 方法)

```php
public function show(int $id, Request $request): JsonResponse
{
    $includeUser = $request->query->get('includeUser', 'false');
    $includeUser = filter_var($includeUser, FILTER_VALIDATE_BOOLEAN);

    if ($includeUser) {
        $article = $this->sysNewsArticleRepository->findWithUser($id);
    } else {
        $article = $this->sysNewsArticleRepository->find($id);
    }
}
```

## 📊 API 接口文档

### 1. 创建文章 (支持 JWT)

```http
POST /official-api/news
Authorization: Bearer <JWT_TOKEN>
Content-Type: application/json

{
    "name": "文章标题",
    "cover": "封面图片URL",
    "content": "文章内容",
    "categoryCode": "GZH_001",
    "userId": 123  // 可选，会被token中的userId覆盖
}
```

### 2. 查询文章列表

```http
GET /official-api/news?page=1&limit=20&includeUser=true
```

#### 支持的查询参数

| 参数         | 类型    | 说明              | 示例                 |
| ------------ | ------- | ----------------- | -------------------- |
| page         | int     | 页码              | page=1               |
| limit        | int     | 每页数量          | limit=20             |
| includeUser  | boolean | 是否包含用户信息  | includeUser=true     |
| userId       | int     | 按用户 ID 筛选    | userId=123           |
| userName     | string  | 按用户名/昵称搜索 | userName=admin       |
| merchantId   | int     | 商户 ID           | merchantId=1         |
| status       | int     | 文章状态          | status=1             |
| categoryCode | string  | 分类编码          | categoryCode=GZH_001 |
| name         | string  | 文章名称搜索      | name=关键词          |
| sortBy       | string  | 排序字段          | sortBy=createTime    |
| sortOrder    | string  | 排序方向          | sortOrder=desc       |

### 3. 获取单个文章详情

```http
GET /official-api/news/123?includeUser=true
```

### 4. 响应格式

#### 包含用户信息的响应

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "文章标题",
            "cover": "封面URL",
            "content": "文章内容",
            "userId": 123,
            "user": {
                "id": 123,
                "username": "admin",
                "nickname": "管理员",
                "email": "admin@example.com",
                "phone": "13800138000",
                "avatar": "头像URL",
                "status": 1,
                "createdAt": "2025-11-20T03:00:00+00:00",
                "updatedAt": "2025-11-20T03:00:00+00:00"
            },
            "createTime": "2025-11-20T03:00:00+00:00"
        }
    ],
    "meta": {
        "total": 1,
        "page": 1,
        "limit": 20,
        "pages": 1
    }
}
```

## 🗄️ 数据库迁移

### 用户表创建

-   **迁移文件**: [`migrations/Version20251120032400.php`](migrations/Version20251120032400.php:1)
-   **表名**: `user`
-   **SQL 结构**:

```sql
CREATE TABLE user (
    id INT AUTO_INCREMENT NOT NULL,
    username VARCHAR(180) NOT NULL,
    email VARCHAR(255) NOT NULL,
    nickname VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    avatar VARCHAR(500) DEFAULT NULL,
    status TINYINT(1) DEFAULT 1 NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
    UNIQUE INDEX UNIQ_8D93D649F85E0677 (username),
    UNIQUE INDEX UNIQ_8D93D649E7927C74 (email),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
```

### 运行迁移

```bash
# 创建用户表
php bin/console doctrine:migrations:migrate --em=user

# 查看迁移状态
php bin/console doctrine:migrations:status --em=user
```

## 🧪 测试工具

### 1. JWT 功能测试

-   **文件**: [`test_news_jwt_token.php`](test_news_jwt_token.php:1)
-   **功能**: 测试 JWT token 解析和文章创建
-   **测试场景**:
    -   无 token 请求
    -   无效 token 请求
    -   有效 token 请求
    -   纯 token 请求

### 2. 用户关联功能测试

-   **文件**: [`test_news_with_user.php`](test_news_with_user.php:1)
-   **功能**: 测试用户关联查询功能
-   **测试场景**:
    -   基本查询（不包含用户信息）
    -   包含用户信息的查询
    -   按用户名搜索
    -   按用户 ID 查询
    -   单个文章详情（包含用户信息）

### 运行测试

```bash
# 测试JWT功能
php test_news_jwt_token.php

# 测试用户关联功能
php test_news_with_user.php
```

## 🔒 安全考虑

### 1. JWT 安全

-   **密钥管理**: 使用 RSA 非对称加密
-   **Token 过期**: 设置合理的过期时间
-   **签名验证**: 验证 token 完整性

### 2. 数据库安全

-   **权限分离**: 用户数据库独立访问权限
-   **连接加密**: 使用 SSL 连接数据库
-   **敏感信息**: 避免在日志中记录敏感信息

### 3. API 安全

-   **参数验证**: 严格验证所有输入参数
-   **SQL 注入防护**: 使用 Doctrine ORM 防护
-   **访问控制**: 基于用户权限控制访问

## 📈 性能优化

### 1. 查询优化

-   **预加载**: 使用 JOIN 避免 N+1 查询
-   **索引优化**: 为常用查询字段添加索引
-   **分页查询**: 使用 LIMIT 和 OFFSET 优化大数据集

### 2. 缓存策略

```php
// 用户信息缓存（示例）
public function findWithCache(int $userId): ?User
{
    $cacheKey = "user_{$userId}";
    $user = $this->cache->get($cacheKey);

    if (!$user) {
        $user = $this->find($userId);
        if ($user) {
            $this->cache->set($cacheKey, $user, 3600); // 1小时
        }
    }

    return $user;
}
```

### 3. 数据库连接优化

-   **连接池**: 配置数据库连接池
-   **读写分离**: 主从数据库配置
-   **查询优化**: 分析慢查询并优化

## 🚀 部署指南

### 1. 环境准备

```bash
# 安装依赖
composer install --no-dev --optimize-autoloader

# 清除缓存
php bin/console cache:clear --env=prod

# 设置权限
chmod -R 755 var/
```

### 2. 数据库设置

```bash
# 创建用户数据库
mysql -u root -p -e "CREATE DATABASE app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 运行迁移
php bin/console doctrine:migrations:migrate --env=prod
php bin/console doctrine:migrations:migrate --em=user --env=prod
```

### 3. 配置验证

```bash
# 验证Doctrine配置
php bin/console debug:config doctrine --env=prod

# 验证路由配置
php bin/console debug:router --env=prod

# 验证服务配置
php bin/console debug:container jwt_service --env=prod
```

## 🔍 故障排查

### 常见问题及解决方案

#### 1. 数据库连接问题

**症状**: 连接用户数据库失败
**排查步骤**:

```bash
# 检查环境变量
php bin/console debug:container --parameter=env(USER_DATABASE_URL)

# 测试数据库连接
php bin/console doctrine:database:create --connection=user

# 检查用户数据库权限
mysql -u root -p -e "SHOW GRANTS FOR 'root'@'%';"
```

#### 2. JWT Token 解析失败

**症状**: 无法从 token 中解析用户 ID
**排查步骤**:

```bash
# 检查JWT配置
php bin/console debug:config lexik_jwt_authentication

# 验证密钥文件
ls -la config/jwt/
cat config/jwt/private.pem
```

#### 3. 用户信息不显示

**症状**: API 响应中缺少用户信息
**排查步骤**:

```bash
# 检查请求参数
curl "http://localhost:8000/official-api/news?includeUser=true"

# 检查数据库中是否有用户记录
mysql -u root -p app -e "SELECT COUNT(*) FROM user;"

# 检查文章是否关联了用户
mysql -u root -p official_website -e "SELECT id, user_id FROM sys_news_article WHERE user_id IS NOT NULL LIMIT 5;"
```

#### 4. 性能问题

**症状**: 查询响应缓慢
**排查步骤**:

```bash
# 启用Doctrine查询日志
# 在config/packages/doctrine.yaml中添加:
# logging: true
# profiling: true

# 分析慢查询
mysql -u root -p -e "SHOW PROCESSLIST;"

# 检查索引使用情况
mysql -u root -p -e "SHOW INDEX FROM user;"
mysql -u root -p -e "SHOW INDEX FROM sys_news_article;"
```

## 📚 相关文档

1. **完整实现总结**: [`NEWS_USER_INTEGRATION_COMPLETE.md`](NEWS_USER_INTEGRATION_COMPLETE.md:1)
2. **JWT 实现总结**: [`NEWS_CONTROLLER_JWT_IMPLEMENTATION_SUMMARY.md`](NEWS_CONTROLLER_JWT_IMPLEMENTATION_SUMMARY.md:1)
3. **微信 API 测试报告**: [`WECHAT_SYNC_API_TEST_REPORT.md`](WECHAT_SYNC_API_TEST_REPORT.md:1)

## 🔄 版本历史

-   **v1.0** (2025-11-20): 初始实现
    -   JWT Token 解析功能
    -   双数据库配置
    -   用户实体和 Repository
    -   API 接口增强
    -   测试工具

## 📞 技术支持

如有技术问题，请参考：

1. 检查日志文件: `var/log/prod.log`
2. 运行诊断命令: `php bin/console debug:config`
3. 查看测试脚本输出
4. 参考本文档的故障排查部分

---

_本文档最后更新时间: 2025-11-20_
_文档版本: v1.0_

## 🔒 用户只读权限详细说明

### 安全措施总结

#### 1. 数据库层面安全

```sql
-- 创建只读用户（示例）
CREATE USER 'app_readonly'@'%' IDENTIFIED BY 'secure_password';
GRANT SELECT ON app.* TO 'app_readonly'@'%';
FLUSH PRIVILEGES;

-- 验证只读权限
SHOW GRANTS FOR 'app_readonly'@'%';
```

#### 2. 应用层面安全

```php
// User实体 - 无setter方法
class User
{
    // 只有getter方法
    public function getId(): ?int { return $this->id; }
    public function getUsername(): string { return $this->username; }
    // ... 其他getter方法

    // 注意：无setter方法，确保实体不可修改
}

// UserRepository - 只读方法
class UserRepository extends ServiceEntityRepository
{
    public function findByIds(array $userIds): array { /* 只读 */ }
    public function findByUsername(string $username): ?User { /* 只读 */ }
    // ... 其他只读方法

    // 注意：无save/update/delete方法
}
```

#### 3. 事件监听安全

```php
// 任何尝试写操作都会被阻止
try {
    $entityManager->persist(new User()); // 抛出异常
} catch (AccessDeniedHttpException $e) {
    echo "用户数据库为只读模式，不允许创建用户数据";
}
```

#### 4. 服务层安全

```php
// 使用专门的只读服务
class UserReadOnlyService
{
    public function getUserById(int $userId): ?User { /* 安全读取 */ }
    public function formatUserForApi(?User $user): ?array { /* 安全格式化 */ }

    // 注意：只提供读取和格式化方法
}
```

### 使用指南

#### 正确的使用方式

```php
// ✅ 正确：使用只读服务查询用户
$user = $this->userReadOnlyService->getUserById($userId);
$userData = $this->userReadOnlyService->formatUserForApi($user);

// ✅ 正确：在查询中包含用户信息
$articles = $this->sysNewsArticleRepository->findByCriteriaWithUser($criteria);
```

#### 错误的使用方式

```php
// ❌ 错误：尝试创建用户
$user = new User();
$user->setUsername('test'); // 方法不存在
$entityManager->persist($user); // 抛出异常

// ❌ 错误：尝试修改用户
$user->setEmail('new@email.com'); // 方法不存在
$entityManager->flush(); // 抛出异常

// ❌ 错误：尝试删除用户
$entityManager->remove($user); // 抛出异常
```

### 测试验证

#### 自动化测试

-   **测试脚本**: [`test_user_readonly.php`](test_user_readonly.php:1)
-   **测试内容**:
    -   User 实体只读检查
    -   UserRepository 只读检查
    -   数据库权限验证
    -   API 接口功能验证

#### 手动验证

```bash
# 1. 检查实体方法
php -r "
\$reflection = new ReflectionClass('App\Entity\User');
\$setters = array_filter(\$reflection->getMethods(), fn(\$m) => str_starts_with(\$m->getName(), 'set'));
echo 'Setter methods: ' . count(\$setters) . PHP_EOL;
"

# 2. 测试数据库权限
mysql -u readonly_user -p -e "
SHOW GRANTS FOR CURRENT_USER();
SELECT COUNT(*) FROM user; -- 应该成功
INSERT INTO user (username) VALUES ('test'); -- 应该失败
"
```

### 配置验证清单

-   [ ] 数据库用户只有 SELECT 权限
-   [ ] User 实体无 setter 方法
-   [ ] UserRepository 无写操作方法
-   [ ] 事件监听器已配置
-   [ ] 只读服务已注入到 Controller
-   [ ] 测试脚本运行通过

### 故障排查

#### 常见问题

**问题 1**: 仍然可以修改用户数据

```bash
# 检查数据库权限
mysql -u root -p -e "SHOW GRANTS FOR 'app_user'@'%';"

# 确保只有SELECT权限
REVOKE ALL PRIVILEGES ON app.* FROM 'app_user'@'%';
GRANT SELECT ON app.* TO 'app_user'@'%';
```

**问题 2**: 事件监听器未生效

```bash
# 检查服务配置
php bin/console debug:container UserDatabaseReadonlyListener

# 检查事件监听器
php bin/console debug:event-dispatcher
```

**问题 3**: API 无法获取用户信息

```bash
# 检查服务注入
php bin/console debug:container UserReadOnlyService

# 测试数据库连接
php bin/console doctrine:query:sql "SELECT COUNT(*) FROM user" --connection=user
```

### 运行测试

```bash
# 测试JWT功能
php test_news_jwt_token.php

# 测试用户关联功能
php test_news_with_user.php

# 测试用户只读权限
php test_user_readonly.php
```
