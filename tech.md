# 官方网站后端技术文档

## 1. 技术栈概览

### 核心框架版本
- **PHP版本**: 8.2+ (必需)
- **Symfony框架**: 7.4.* (最新稳定版)
- **API Platform**: 4.2.9 (REST API框架)
- **Doctrine ORM**: 3.5.8 (数据库ORM)

### 主要依赖库清单

#### 核心框架依赖
```json
{
    "php": ">=8.2",
    "ext-ctype": "*",
    "ext-iconv": "*",
    "symfony/console": "7.4.*",
    "symfony/dotenv": "7.4.*",
    "symfony/flex": "^2",
    "symfony/framework-bundle": "7.4.*",
    "symfony/runtime": "7.4.*",
    "symfony/yaml": "7.4.*"
}
```

#### API和文档
```json
{
    "api-platform/core": "^4.2",
    "nelmio/api-doc-bundle": "^4.18"
}
```

#### 数据库相关
```json
{
    "doctrine/doctrine-bundle": "^2.12",
    "doctrine/doctrine-migrations-bundle": "^3.4",
    "doctrine/orm": "^3.5"
}
```

#### 安全认证
```json
{
    "lexik/jwt-authentication-bundle": "^2.20",
    "symfony/security-bundle": "7.4.*",
    "symfony/validator": "7.4.*"
}
```

#### 缓存和消息队列
```json
{
    "symfony/messenger": "7.4.*",
    "predis/predis": "^2.3"
}
```

#### 日志和监控
```json
{
    "symfony/monolog-bundle": "^3.10"
}
```

#### 微信集成
```json
{
    "overtrue/wechat": "^8.3"
}
```

#### 开发工具
```json
{
    "doctrine/doctrine-fixtures-bundle": "^3.6",
    "phpunit/phpunit": "^11.3",
    "symfony/browser-kit": "7.4.*",
    "symfony/css-selector": "7.4.*",
    "symfony/maker-bundle": "^1.60"
}
```

## 2. 核心框架详解

### Symfony框架配置

#### 基础配置 (config/packages/framework.yaml)
```yaml
framework:
    secret: '%env(APP_SECRET)%'
    #csrf_protection: true
    http_method_override: false

    # 启用会话支持
    session:
        handler_id: null
        cookie_secure: auto
        cookie_httponly: true
        cookie_samesite: lax
        storage_factory_id: session.storage.factory.native

    # 启用ESI（Edge Side Includes）
    esi: true

    # 表单组件配置
    form:
        enabled: true
        
    # 依赖注入配置
    php_errors:
        log: true
```

#### 关键Bundle说明

1. **FrameworkBundle**: Symfony核心框架包
2. **ApiPlatformBundle**: API平台框架，提供REST API支持
3. **DoctrineBundle**: 数据库ORM框架
4. **SecurityBundle**: 安全认证框架
5. **MessengerBundle**: 消息队列框架
6. **MonologBundle**: 日志记录框架
7. **TwigBundle**: 模板引擎框架
8. **NelmioApiDocBundle**: API文档生成

#### 框架特性使用

- **依赖注入**: 完全使用Symfony的DI容器
- **事件系统**: 使用EventDispatcher进行事件处理
- **命令行工具**: 使用Console组件创建管理命令
- **表单处理**: 使用Form组件处理数据验证
- **序列化**: 使用Serializer组件进行数据序列化

## 3. 数据库技术栈

### Doctrine ORM配置

#### 主配置 (config/packages/doctrine.yaml)
```yaml
doctrine:
    dbal:
        # 默认数据库连接（主数据库）
        default_connection: default
        connections:
            default:
                url: '%env(resolve:DATABASE_URL)%'
                charset: utf8mb4
                default_table_options:
                    charset: utf8mb4
                    collate: utf8mb4_unicode_ci
                driver: pdo_mysql
                server_version: '8.0'
                
            # 用户数据库连接
            user:
                url: '%env(resolve:USER_DATABASE_URL)%'
                charset: utf8mb4
                default_table_options:
                    charset: utf8mb4
                    collate: utf8mb4_unicode_ci
                driver: pdo_mysql
                server_version: '8.0'

    orm:
        default_entity_manager: default
        entity_managers:
            # 默认实体管理器
            default:
                connection: default
                mappings:
                    App:
                        type: attribute
                        dir: '%kernel.project_dir%/src/Entity'
                        prefix: 'App\Entity'
                        alias: App
                        
            # 用户实体管理器
            user:
                connection: user
                mappings:
                    App:
                        type: attribute
                        dir: '%kernel.project_dir%/src/Entity/User'
                        prefix: 'App\Entity\User'
                        alias: User
```

### 数据库连接配置

#### 环境变量配置 (.env)
```bash
# 主数据库配置
DATABASE_URL="mysql://root:password@127.0.0.1:3306/official_website?serverVersion=8.0&charset=utf8mb4"

# 用户数据库配置
USER_DATABASE_URL="mysql://root:password@127.0.0.1:3306/user_database?serverVersion=8.0&charset=utf8mb4"
```

### 迁移管理

#### Doctrine Migrations配置
```yaml
doctrine_migrations:
    # 迁移文件存储路径
    migrations_paths:
        'DoctrineMigrations': '%kernel.project_dir%/migrations'
    
    # 迁移表名
    table_name: 'doctrine_migration_versions'
    
    # 迁移版本格式
    version_column_length: 1024
    
    # 迁移组织方式
    organize_migrations: BY_YEAR # BY_YEAR, BY_YEAR_AND_MONTH, FALSE
```

### 查询优化

#### 性能优化配置
```yaml
doctrine:
    orm:
        # 查询缓存配置
        query_cache:
            # 使用Redis缓存
            driver: redis
            host: '%env(REDIS_HOST)%'
            port: '%env(int:REDIS_PORT)%'
            
        # 结果缓存配置
        result_cache:
            driver: redis
            host: '%env(REDIS_HOST)%'
            port: '%env(int:REDIS_PORT)%'
            
        # 元数据缓存配置
        metadata_cache:
            driver: redis
            host: '%env(REDIS_HOST)%'
            port: '%env(int:REDIS_PORT)%'
```

## 4. API框架和文档

### API Platform配置

#### 核心配置 (config/packages/api_platform.yaml)
```yaml
api_platform:
    title: '官方网站API'
    description: '官方网站后端API接口文档'
    version: '1.0.0'
    
    # 启用Swagger UI
    enable_swagger_ui: true
    enable_re_doc: true
    
    # API路径前缀
    path_segment_name_generator: api_platform.path_segment_name_generator.underscore
    
    # 序列化配置
    serializer:
        groups: ['read', 'write']
        
    # 分页配置
    pagination:
        client_enabled: true
        client_items_per_page: true
        enabled_parameter_name: 'pagination'
        items_per_page_parameter_name: 'itemsPerPage'
        page_parameter_name: 'page'
        
    # 集合操作
    collection:
        pagination:
            enabled: true
            items_per_page: 30
            maximum_items_per_page: 100
            
    # 异常处理
    exception_to_status:
        App\Exception\BusinessException: 400
        
    # 映射配置
    mapping:
        paths:
            - '%kernel.project_dir%/src/Entity'
            - '%kernel.project_dir%/src/ApiResource'
```

### NelmioApiDoc配置

#### API文档配置 (config/packages/nelmio_api_doc.yaml)
```yaml
nelmio_api_doc:
    areas:
        path_patterns: # 过滤API路径
            - ^/api(?!/doc$) # 接受以/api/开头的路径，除了/api/doc
        host_patterns:
            - ^api\.
            
    documentation:
        # API基本信息
        info:
            title: '官方网站API'
            description: '官方网站后端API接口文档'
            version: '1.0.0'
            
        # 安全定义
        securityDefinitions:
            Bearer:
                type: apiKey
                description: 'JWT Token认证'
                name: Authorization
                in: header
                
        # 全局安全配置
        security:
            - Bearer: []
            
        # 标签分组
        tags:
            - name: '新闻管理'
              description: '新闻相关的API接口'
            - name: '微信同步'
              description: '微信公众号同步相关接口'
            - name: '用户管理'
              description: '用户管理相关接口'
            - name: '系统监控'
              description: '系统监控相关接口'
```

### API版本控制

#### 版本控制策略
```yaml
api_platform:
    # 版本控制配置
    version: '1.0.0'
    versioning:
        enabled: true
        default_version: '1.0.0'
        resolvers:
            query:
                enabled: true
                parameter_name: 'version'
            header:
                enabled: true
                header_name: 'Accept'
                regex: '/application\/json;version=(\d+\.?\d*)/'
```

### 序列化配置

#### 序列化组定义
```php
// src/Entity/SysNewsArticle.php
namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    normalizationContext: ['groups' => ['news:read']],
    denormalizationContext: ['groups' => ['news:write']]
)]
class SysNewsArticle
{
    #[Groups(['news:read'])]
    public function getId(): ?int
    {
        return $this->id;
    }
    
    #[Groups(['news:read', 'news:write'])]
    public function getTitle(): ?string
    {
        return $this->title;
    }
    
    #[Groups(['news:write'])]
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }
}
```

## 5. 安全技术栈

### JWT认证配置

#### Lexik JWT配置 (config/packages/lexik_jwt_authentication.yaml)
```yaml
lexik_jwt_authentication:
    # JWT Token配置
    secret_key: '%env(resolve:JWT_SECRET_KEY)%'
    public_key: '%env(resolve:JWT_PUBLIC_KEY)%'
    pass_phrase: '%env(JWT_PASSPHRASE)%'
    
    # Token配置
    token_ttl: 3600 # 1小时
    clock_skew: 0
    
    # 编码器配置
    encoder:
        service: lexik_jwt_authentication.encoder.lcobucci
        signature_algorithm: RS256
        
    # Token提取配置
    token_extractors:
        cookie:
            enabled: true
            name: BEARER
        query_parameter:
            enabled: true
            name: bearer
        authorization_header:
            enabled: true
            prefix: Bearer
```

#### JWT密钥生成
```bash
# 生成私钥
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096

# 生成公钥
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
```

### Security组件配置

#### 安全配置 (config/packages/security.yaml)
```yaml
security:
    # 密码哈希配置
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface:
            algorithm: 'auto'
            cost: 12
            
    # 提供者配置
    providers:
        app_user_provider:
            entity:
                class: App\Entity\User
                property: username
                
    # 防火墙配置
    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false
            
        api:
            pattern: ^/api/
            stateless: true
            provider: app_user_provider
            jwt: ~
            
        main:
            lazy: true
            provider: app_user_provider
            
            # 自定义认证器
            custom_authenticator: App\Security\LoginFormAuthenticator
            
            # 登录表单
            form_login:
                login_path: app_login
                check_path: app_login
                enable_csrf: true
                
            # 登出
            logout:
                path: app_logout
                target: app_login
                
    # 访问控制
    access_control:
        - { path: ^/admin, roles: ROLE_ADMIN }
        - { path: ^/api/docs, roles: PUBLIC_ACCESS }
        - { path: ^/api/, roles: IS_AUTHENTICATED_FULLY }
        - { path: ^/login, roles: PUBLIC_ACCESS }
        - { path: ^/, roles: PUBLIC_ACCESS }
```

### 密码加密策略

#### 密码哈希配置
```yaml
security:
    password_hashers:
        # 默认哈希算法
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface:
            algorithm: 'auto'
            cost: 12
            
        # 特定用户的哈希配置
        App\Entity\User:
            algorithm: 'bcrypt'
            cost: 15
```

### CORS配置

#### Nelmio CORS配置 (config/packages/nelmio_cors.yaml)
```yaml
nelmio_cors:
    defaults:
        origin_regex: true
        allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
        allow_methods: ['GET', 'OPTIONS', 'POST', 'PUT', 'PATCH', 'DELETE']
        allow_headers: ['Content-Type', 'Authorization', 'X-Requested-With']
        expose_headers: ['Link']
        max_age: 3600
    paths:
        '^/api/': null
```

## 6. 缓存和性能

### Redis配置

#### 缓存配置 (config/packages/cache.yaml)
```yaml
framework:
    cache:
        # 应用缓存池
        app: cache.adapter.redis
        default_redis_provider: '%env(REDIS_URL)%'
        
        # 缓存池配置
        pools:
            # Doctrine查询缓存
            doctrine.result_cache_pool:
                adapter: cache.adapter.redis
                
            # Doctrine系统缓存
            doctrine.system_cache_pool:
                adapter: cache.adapter.redis
                
            # 应用缓存
            app.cache:
                adapter: cache.adapter.redis
                default_lifetime: 3600
                
            # 会话缓存
            session.cache:
                adapter: cache.adapter.redis
                default_lifetime: 7200
                
            # API响应缓存
            api.cache:
                adapter: cache.adapter.redis
                default_lifetime: 1800
```

### 缓存策略

#### 缓存管理器实现
```php
// src/Service/CacheManager.php
namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CacheManager
{
    private CacheInterface $cache;
    
    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }
    
    public function get(string $key, callable $callback, int $ttl = 3600): mixed
    {
        return $this->cache->get($key, function (ItemInterface $item) use ($callback, $ttl) {
            $item->expiresAfter($ttl);
            return $callback();
        });
    }
    
    public function delete(string $key): bool
    {
        return $this->cache->delete($key);
    }
    
    public function clear(): bool
    {
        return $this->cache->clear();
    }
}
```

### 性能优化配置

#### 环境变量配置 (.env)
```bash
# Redis配置
REDIS_URL=redis://127.0.0.1:6379
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# 缓存配置
CACHE_POOL=cache.app
DEFAULT_TTL=3600

# 性能配置
OPCACHE_ENABLE=1
OPCACHE_MEMORY_CONSUMPTION=256
OPCACHE_MAX_ACCELERATED_FILES=10000
```

## 7. 消息队列技术栈

### Messenger配置

#### 队列配置 (config/packages/messenger.yaml)
```yaml
framework:
    messenger:
        # 默认传输方式
        default_bus: messenger.bus.default
        
        # 传输配置
        transports:
            # 同步传输（开发测试）
            sync: 'sync://'
            
            # Redis异步传输
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    stream: tcp
                    timeout: 5
                    read_timeout: 10
                    persistent: true
                    db: 0
                    prefix: 'messenger'
                retry_strategy:
                    max_retries: 3
                    delay: 1000
                    multiplier: 2
                    max_delay: 30000
                    
            # 高优先级队列
            high_priority:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    queue_name: 'high_priority'
                retry_strategy:
                    max_retries: 3
                    delay: 500
                    multiplier: 2
                    max_delay: 10000
                    
            # 微信同步队列
            wechat_sync:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    queue_name: 'wechat_sync'
                retry_strategy:
                    max_retries: 3
                    delay: 2000
                    multiplier: 2
                    max_delay: 60000
                    
            # 媒体处理队列
            media_process:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    queue_name: 'media_process'
                retry_strategy:
                    max_retries: 2
                    delay: 5000
                    multiplier: 2
                    max_delay: 120000
                    
        # 路由配置
        routing:
            'App\Message\WechatSyncMessage': wechat_sync
            'App\Message\MediaProcessMessage': media_process
            'App\Message\BatchProcessMessage': batch_process
            'App\Message\HighPriorityMessage': high_priority
            'App\Message\SyncMessage': sync
            
        # 失败队列
        failure_transport: failed
        
        # 工作者配置
        workers:
            default:
                transports: ['async']
                options:
                    sleep: 1
                    time_limit: 300
                    memory_limit: 256
                    grace_period: 30
                    
            wechat_sync:
                transports: ['wechat_sync']
                options:
                    sleep: 2
                    time_limit: 600
                    memory_limit: 512
                    grace_period: 60
```

### 队列传输配置

#### 环境变量配置
```bash
# Messenger传输DSN
MESSENGER_TRANSPORT_DSN=redis://127.0.0.1:6379/messages

# 队列配置
QUEUE_DEFAULT_RETRY=3
QUEUE_RETRY_DELAY=1000
QUEUE_MAX_DELAY=30000
```

### 消息处理器

#### 消息处理器示例
```php
// src/MessageHandler/WechatSyncMessageHandler.php
namespace App\MessageHandler;

use App\Message\WechatSyncMessage;
use App\Service\WechatArticleSyncService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class WechatSyncMessageHandler
{
    public function __construct(
        private WechatArticleSyncService $syncService
    ) {}
    
    public function __invoke(WechatSyncMessage $message): void
    {
        $this->syncService->syncArticles($message->getAccountId());
    }
}
```

### 任务调度

#### 命令行调度
```php
// src/Command/ProcessAsyncTasksCommand.php
namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:process-async-tasks',
    description: '处理异步任务队列'
)]
class ProcessAsyncTasksCommand extends Command
{
    public function __construct(
        private MessageBusInterface $bus
    ) {
        parent::__construct();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // 处理队列任务的逻辑
        return Command::SUCCESS;
    }
}
```

## 8. 日志和监控

### Monolog配置

#### 日志配置 (config/packages/monolog.yaml)
```yaml
monolog:
    # 日志通道
    channels:
        - 'wechat'
        - 'api'
        - 'database'
        - 'performance'
        
    handlers:
        # 主日志处理器
        main:
            type: stream
            path: "%kernel.logs_dir%/%kernel.environment%.log"
            level: debug
            channels: ["!event", "!wechat", "!api", "!database", "!performance"]
            
        # 微信日志
        wechat:
            type: stream
            path: "%kernel.logs_dir%/wechat.log"
            level: info
            channels: ["wechat"]
            
        # API日志
        api:
            type: stream
            path: "%kernel.logs_dir%/api.log"
            level: info
            channels: ["api"]
            
        # 数据库日志
        database:
            type: stream
            path: "%kernel.logs_dir%/database.log"
            level: debug
            channels: ["database"]
            
        # 性能日志
        performance:
            type: stream
            path: "%kernel.logs_dir%/performance.log"
            level: info
            channels: ["performance"]
            
        # 错误日志
        error:
            type: stream
            path: "%kernel.logs_dir%/error.log"
            level: error
            channels: ["!event"]
            
        # 控制台日志
        console:
            type: console
            process_psr_3_messages: false
            channels: ["!event", "!doctrine"]

# 生产环境配置
when@prod:
    monolog:
        handlers:
            main:
                type: rotating_file
                path: "%kernel.logs_dir%/%kernel.environment%.log"
                level: warning
                max_files: 30
                channels: ["!event", "!wechat", "!api", "!database", "!performance"]
                
            wechat:
                type: rotating_file
                path: "%kernel.logs_dir%/wechat.log"
                level: info
                max_files: 15
                channels: ["wechat"]
                
            api:
                type: rotating_file
                path: "%kernel.logs_dir%/api.log"
                level: warning
                max_files: 15
                channels: ["api"]
```

### 日志渠道配置

#### 自定义日志服务
```php
// src/Service/LoggingService.php
namespace App\Service;

use Psr\Log\LoggerInterface;

class LoggingService
{
    public function __construct(
        private LoggerInterface $apiLogger,
        private LoggerInterface $wechatLogger,
        private LoggerInterface $databaseLogger,
        private LoggerInterface $performanceLogger
    ) {}
    
    public function logApiRequest(string $method, string $uri, array $data = []): void
    {
        $this->apiLogger->info('API Request', [
            'method' => $method,
            'uri' => $uri,
            'data' => $data,
            'timestamp' => time()
        ]);
    }
    
    public function logWechatOperation(string $operation, array $data = []): void
    {
        $this->wechatLogger->info('WeChat Operation', [
            'operation' => $operation,
            'data' => $data,
            'timestamp' => time()
        ]);
    }
    
    public function logPerformance(string $operation, float $duration): void
    {
        $this->performanceLogger->info('Performance Metric', [
            'operation' => $operation,
            'duration' => $duration,
            'timestamp' => time()
        ]);
    }
}
```

### 监控服务配置

#### 性能监控服务
```php
// src/Service/MonitoringService.php
namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MonitoringService
{
    private array $metrics = [];
    
    public function recordRequest(Request $request, Response $response): void
    {
        $this->metrics[] = [
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'status_code' => $response->getStatusCode(),
            'duration' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'memory_usage' => memory_get_peak_usage(true),
            'timestamp' => time()
        ];
    }
    
    public function getMetrics(): array
    {
        return $this->metrics;
    }
    
    public function getAverageResponseTime(): float
    {
        if (empty($this->metrics)) {
            return 0.0;
        }
        
        $total = array_sum(array_column($this->metrics, 'duration'));
        return $total / count($this->metrics);
    }
}
```

## 9. 第三方服务集成

### 微信SDK配置

#### 微信配置
```php
// src/Service/WechatApiService.php
namespace App\Service;

use EasyWeChat\OfficialAccount\Application;

class WechatApiService
{
    private Application $app;
    
    public function __construct()
    {
        $this->app = new Application([
            'app_id' => $_ENV['WECHAT_APP_ID'],
            'secret' => $_ENV['WECHAT_SECRET'],
            'response_type' => 'array',
            
            // 日志配置
            'log' => [
                'level' => 'debug',
                'file' => __DIR__ . '/../../var/log/wechat_sdk.log',
            ],
            
            // OAuth配置
            'oauth' => [
                'scopes' => ['snsapi_userinfo'],
                'callback' => $_ENV['WECHAT_OAUTH_CALLBACK'],
            ],
        ]);
    }
    
    public function getApplication(): Application
    {
        return $this->app;
    }
}
```

#### 环境变量配置
```bash
# 微信公众号配置
WECHAT_APP_ID=your_app_id
WECHAT_SECRET=your_app_secret
WECHAT_TOKEN=your_token
WECHAT_AES_KEY=your_aes_key
WECHAT_OAUTH_CALLBACK=https://yourdomain.com/wechat/callback

# 微信API配置
WECHAT_API_BASE_URL=https://api.weixin.qq.com
WECHAT_MEDIA_UPLOAD_URL=https://api.weixin.qq.com/cgi-bin/media/upload
```

### 对象存储配置

#### 文件上传服务
```php
// src/Service/ImageUploadService.php
namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageUploadService
{
    private string $uploadDir;
    private array $allowedTypes;
    
    public function __construct(string $uploadDir)
    {
        $this->uploadDir = $uploadDir;
        $this->allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    }
    
    public function upload(UploadedFile $file): string
    {
        if (!in_array($file->getMimeType(), $this->allowedTypes)) {
            throw new \InvalidArgumentException('Invalid file type');
        }
        
        $filename = uniqid() . '.' . $file->guessExtension();
        $file->move($this->uploadDir, $filename);
        
        return $filename;
    }
}
```

### 外部API配置

#### HTTP客户端配置
```php
// src/Service/ExternalApiService.php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ExternalApiService
{
    public function __construct(
        private HttpClientInterface $httpClient
    ) {}
    
    public function makeRequest(string $method, string $url, array $options = []): array
    {
        $response = $this->httpClient->request($method, $url, $options);
        
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('API request failed');
        }
        
        return $response->toArray();
    }
}
```

## 10. 开发和部署工具

### 开发环境配置

#### Docker配置
```dockerfile
# Dockerfile
FROM php:8.2-fpm

# 安装系统依赖
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql gd exif pcntl bcmath zip

# 安装Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 设置工作目录
WORKDIR /var/www/html

# 复制项目文件
COPY . .

# 安装PHP依赖
RUN composer install --no-dev --optimize-autoloader

# 设置权限
RUN chown -R www-data:www-data /var/www/html
```

#### Docker Compose配置
```yaml
# docker-compose.yml
version: '3.8'

services:
  app:
    build: .
    ports:
      - "8080:80"
    volumes:
      - .:/var/www/html
      - /var/www/html/var
    environment:
      - APP_ENV=dev
      - DATABASE_URL=mysql://root:password@db:3306/official_website
      - REDIS_URL=redis://redis:6379
    depends_on:
      - db
      - redis

  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: password
      MYSQL_DATABASE: official_website
    ports:
      - "3306:3306"
    volumes:
      - db_data:/var/lib/mysql

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"

volumes:
  db_data:
```

### 测试框架配置

#### PHPUnit配置
```xml
<!-- phpunit.xml.dist -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         backupGlobals="false"
         colors="true"
         bootstrap="tests/bootstrap.php"
         convertDeprecationsToExceptions="false">
    <php>
        <ini name="display_errors" value="1" />
        <ini name="error_reporting" value="-1" />
        <server name="APP_ENV" value="test" force="true" />
        <server name="SHELL_VERBOSITY" value="-1" />
        <server name="SYMFONY_PHPUNIT_REMOVE" value="" />
        <server name="SYMFONY_PHPUNIT_VERSION" value="9.5" />
    </php>

    <testsuites>
        <testsuite name="Project Test Suite">
            <directory>tests</directory>
        </testsuite>
    </testsuites>

    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </coverage>

    <listeners>
        <listener class="Symfony\Bridge\PhpUnit\SymfonyTestsListener" />
    </listeners>
</php>
```

### 部署脚本

#### 生产部署脚本
```bash
#!/bin/bash
# scripts/production_deploy.sh

set -e

echo "开始生产环境部署..."

# 1. 备份当前版本
echo "备份当前版本..."
cp -r /var/www/html /var/www/html_backup_$(date +%Y%m%d_%H%M%S)

# 2. 拉取最新代码
echo "拉取最新代码..."
git pull origin main

# 3. 安装依赖
echo "安装PHP依赖..."
composer install --no-dev --optimize-autoloader

# 4. 清理缓存
echo "清理缓存..."
php bin/console cache:clear --env=prod

# 5. 运行数据库迁移
echo "运行数据库迁移..."
php bin/console doctrine:migrations:migrate --env=prod --no-interaction

# 6. 设置权限
echo "设置文件权限..."
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html
chmod -R 777 /var/www/html/var

# 7. 重启服务
echo "重启服务..."
systemctl restart nginx
systemctl restart php-fpm

echo "部署完成!"
```

## 11. 环境配置详解

### 环境变量说明

#### 完整环境配置 (.env)
```bash
# 应用配置
APP_ENV=dev
APP_SECRET=your_secret_key_here
APP_DEBUG=true

# 数据库配置
DATABASE_URL="mysql://root:password@127.0.0.1:3306/official_website?serverVersion=8.0&charset=utf8mb4"
USER_DATABASE_URL="mysql://root:password@127.0.0.1:3306/user_database?serverVersion=8.0&charset=utf8mb4"

# Redis配置
REDIS_URL=redis://127.0.0.1:6379
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# JWT配置
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your_jwt_passphrase

# Messenger配置
MESSENGER_TRANSPORT_DSN=redis://127.0.0.1:6379/messages

# 微信配置
WECHAT_APP_ID=your_wechat_app_id
WECHAT_SECRET=your_wechat_secret
WECHAT_TOKEN=your_wechat_token
WECHAT_AES_KEY=your_wechat_aes_key
WECHAT_OAUTH_CALLBACK=https://yourdomain.com/wechat/callback

# CORS配置
CORS_ALLOW_ORIGIN=http://localhost:3000,https://yourdomain.com

# 邮件配置
MAILER_DSN=smtp://localhost:1025

# 文件上传配置
UPLOAD_DIR=%kernel.project_dir%/public/uploads
MAX_FILE_SIZE=5242880

# 性能配置
OPCACHE_ENABLE=1
OPCACHE_MEMORY_CONSUMPTION=256
OPCACHE_MAX_ACCELERATED_FILES=10000
```

### 配置文件结构

```
config/
├── bundles.php              # Bundle配置
├── packages/                 # 包配置目录
│   ├── api_platform.yaml   # API Platform配置
│   ├── cache.yaml           # 缓存配置
│   ├── doctrine.yaml        # Doctrine配置
│   ├── framework.yaml       # Symfony框架配置
│   ├── messenger.yaml       # 消息队列配置
│   ├── monolog.yaml         # 日志配置
│   ├── nelmio_api_doc.yaml  # API文档配置
│   ├── nelmio_cors.yaml     # CORS配置
│   ├── security.yaml        # 安全配置
│   └── twig.yaml           # 模板配置
├── routes.yaml              # 路由配置
├── services.yaml            # 服务配置
└── preload.php              # 预加载配置
```

### 配置优先级

Symfony配置加载优先级（从高到低）：
1. 环境特定配置 (`config/packages/{environment}/`)
2. 主配置文件 (`config/packages/`)
3. 应用配置 (`config/`)
4. Bundle默认配置
5. 环境变量 (`.env`文件)

### 敏感信息处理

#### 安全配置管理
```bash
# 1. 使用环境变量存储敏感信息
DATABASE_URL=mysql://user:password@host:port/database

# 2. 使用Symfony Secrets
php bin/console secrets:set DATABASE_PASSWORD
php bin/console secrets:list --reveal

# 3. 加密配置文件
php bin/console secrets:generate-keys
php bin/console secrets:encrypt-from-local --env=prod
```

#### 保密文件配置
```bash
# .env.local (不提交到版本控制)
APP_SECRET=your_actual_secret_key
DATABASE_URL=your_actual_database_url
JWT_PASSPHRASE=your_actual_jwt_passphrase

# .env.prod.local (生产环境专用)
APP_ENV=prod
APP_DEBUG=false
```

## 12. 性能调优配置

### PHP配置优化

#### php.ini配置
```ini
# 内存配置
memory_limit = 256M
max_execution_time = 300
max_input_time = 300

# 文件上传配置
upload_max_filesize = 10M
post_max_size = 10M
max_file_uploads = 20

# OPcache配置
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 0
opcache.validate_timestamps = 0
opcache.save_comments = 1
opcache.load_comments = 1

# 会话配置
session.gc_maxlifetime = 7200
session.cookie_lifetime = 7200

# 错误报告
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log
```

### 数据库优化

#### MySQL配置优化
```ini
# my.cnf
[mysqld]
# 内存配置
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_log_buffer_size = 16M

# 连接配置
max_connections = 200
max_connect_errors = 1000

# 查询缓存
query_cache_type = 1
query_cache_size = 256M

# 慢查询日志
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2
```

#### Doctrine查询优化
```php
// 配置查询缓存
$entityManager->getConfiguration()->setQueryCacheImpl(
    new RedisCache()
);

// 使用DQL优化
$query = $entityManager->createQuery('
    SELECT n, c 
    FROM App\Entity\SysNewsArticle n 
    JOIN n.category c 
    WHERE n.status = :status
');
$query->setParameter('status', 'published');
$query->useQueryCache(true);
$query->useResultCache(true, 3600, 'news_published_cache');
```

### 缓存优化

#### Redis配置优化
```conf
# redis.conf
# 内存配置
maxmemory 512mb
maxmemory-policy allkeys-lru

# 持久化配置
save 900 1
save 300 10
save 60 10000

# 网络配置
tcp-keepalive 300
timeout 0

# 安全配置
requirepass your_redis_password
```

#### 缓存策略实现
```php
// src/Service/PerformanceOptimizer.php
namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class PerformanceOptimizer
{
    public function __construct(
        private CacheInterface $cache
    ) {}
    
    public function cacheApiResponse(string $key, callable $callback, int $ttl = 300): mixed
    {
        return $this->cache->get($key, function (ItemInterface $item) use ($callback, $ttl) {
            $item->expiresAfter($ttl);
            $item->tag(['api_response']);
            return $callback();
        });
    }
    
    public function invalidateApiCache(): void
    {
        $this->cache->invalidateTags(['api_response']);
    }
}
```

### 队列优化

#### 消息队列性能调优
```yaml
framework:
    messenger:
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    # 连接池配置
                    pool: 10
                    # 预取数量
                    prefetch_count: 5
                    # 确认模式
                    ack_mode: auto
                    # 重试配置
                    retry_strategy:
                        max_retries: 3
                        delay: 1000
                        multiplier: 2
                        max_delay: 30000
```

## 13. 安全配置最佳实践

### 安全头配置

#### 安全中间件
```php
// src/EventSubscriber/SecurityHeadersSubscriber.php
namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }
    
    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        
        // 安全头配置
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy', "default-src 'self'");
        
        // HSTS (仅HTTPS)
        if ($event->getRequest()->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
    }
}
```

### 输入验证

#### 数据验证器
```php
// src/Service/DataValidator.php
namespace App\Service;

use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

class DataValidator
{
    public function __construct(
        private ValidatorInterface $validator
    ) {}
    
    public function validateNewsData(array $data): array
    {
        $constraints = new Assert\Collection([
            'title' => [
                new Assert\NotBlank(),
                new Assert\Length(['min' => 1, 'max' => 255])
            ],
            'content' => [
                new Assert\NotBlank(),
                new Assert\Length(['min' => 1])
            ],
            'category_id' => [
                new Assert\NotBlank(),
                new Assert\Positive()
            ]
        ]);
        
        $violations = $this->validator->validate($data, $constraints);
        
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }
            return $errors;
        }
        
        return [];
    }
}
```

### SQL注入防护

#### 安全查询实践
```php
// 使用参数化查询
$query = $entityManager->createQuery('
    SELECT n 
    FROM App\Entity\SysNewsArticle n 
    WHERE n.title LIKE :title 
    AND n.status = :status
');
$query->setParameter('title', '%' . $searchTerm . '%');
$query->setParameter('status', 'published');

// 使用Repository方法
$articles = $repository->findBy([
    'status' => 'published',
    'category' => $categoryId
]);

// 避免直接拼接SQL
// 错误示例：
// $sql = "SELECT * FROM news WHERE title = '" . $_GET['title'] . "'";
```

### XSS防护

#### 输出转义
```php
// 使用Twig模板自动转义
{{ article.title }}  {# 自动转义 #}
{{ article.content|raw }}  {# 允许HTML，需谨慎使用 #}

// 手动转义
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;

class ContentSanitizer
{
    public function __construct(
        private HtmlSanitizer $sanitizer
    ) {}
    
    public function sanitize(string $content): string
    {
        return $this->sanitizer->sanitize($content);
    }
}
```

## 14. 故障排除和维护

### 常见问题解决

#### 数据库连接问题
```bash
# 检查数据库连接
php bin/console doctrine:database:create --if-not-exists

# 验证数据库配置
php bin/console debug:config doctrine

# 运行数据库诊断
php bin/console doctrine:schema:validate

# 修复数据库结构
php bin/console doctrine:schema:update --force
```

#### 缓存问题
```bash
# 清理所有缓存
php bin/console cache:clear

# 清理特定环境缓存
php bin/console cache:clear --env=prod

# 清理路由缓存
php bin/console cache:clear --no-warmup

# 预热缓存
php bin/console cache:warmup
```

#### 队列问题
```bash
# 检查队列状态
php bin/console messenger:stats

# 重试失败消息
php bin/console messenger:failed:retry

# 清理失败消息
php bin/console messenger:failed:remove

# 消费队列消息
php bin/console messenger:consume async --time-limit=3600
```

### 日志分析

#### 日志分析脚本
```php
// scripts/analyze_logs.php
<?php

$logFile = __DIR__ . '/../var/log/prod.log';
$patterns = [
    'ERROR' => '/ERROR/',
    'WARNING' => '/WARNING/',
    'CRITICAL' => '/CRITICAL/'
];

$stats = [];
$handle = fopen($logFile, 'r');

while (($line = fgets($handle)) !== false) {
    foreach ($patterns as $level => $pattern) {
        if (preg_match($pattern, $line)) {
            $stats[$level] = ($stats[$level] ?? 0) + 1;
        }
    }
}

fclose($handle);

echo "日志统计结果:\n";
foreach ($stats as $level => $count) {
    echo "{$level}: {$count}\n";
}
```

### 性能监控

#### 性能监控命令
```bash
# 检查应用性能
php bin/console debug:container --tag=monolog.logger

# 监控内存使用
php bin/console debug:container --parameter=memory_limit

# 检查路由性能
php bin/console debug:router --show-aliases

# 监控服务配置
php bin/console debug:autowiring
```

### 备份策略

#### 数据库备份脚本
```bash
#!/bin/bash
# scripts/backup_database.sh

BACKUP_DIR="/var/backups/database"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="official_website"

# 创建备份目录
mkdir -p $BACKUP_DIR

# 备份数据库
mysqldump -u root -p$DB_PASSWORD $DB_NAME > $BACKUP_DIR/backup_$DATE.sql

# 压缩备份文件
gzip $BACKUP_DIR/backup_$DATE.sql

# 删除7天前的备份
find $BACKUP_DIR -name "backup_*.sql.gz" -mtime +7 -delete

echo "数据库备份完成: backup_$DATE.sql.gz"
```

#### 应用备份脚本
```bash
#!/bin/bash
# scripts/backup_application.sh

BACKUP_DIR="/var/backups/application"
DATE=$(date +%Y%m%d_%H%M%S)
APP_DIR="/var/www/html"

# 创建备份目录
mkdir -p $BACKUP_DIR

# 备份应用文件
tar -czf $BACKUP_DIR/app_backup_$DATE.tar.gz -C $APP_DIR .

# 备份配置文件
cp -r $APP_DIR/config $BACKUP_DIR/config_backup_$DATE

# 删除30天前的备份
find $BACKUP_DIR -name "app_backup_*.tar.gz" -mtime +30 -delete
find $BACKUP_DIR -name "config_backup_*" -mtime +30 -delete

echo "应用备份完成: app_backup_$DATE.tar.gz"
```

#### 自动备份配置
```bash
# 添加到crontab
# 每天凌晨2点备份数据库
0 2 * * * /path/to/scripts/backup_database.sh

# 每周日凌晨3点备份应用
0 3 * * 0 /path/to/scripts/backup_application.sh

# 每小时检查应用状态
0 * * * * /path/to/scripts/health_check.sh
```

---

## 总结

本文档详细记录了官方网站后端系统的完整技术栈配置，包括：

1. **技术栈概览**: PHP 8.2+、Symfony 7.4、API Platform 4.2.9等核心依赖
2. **核心框架**: Symfony框架配置、Bundle管理、特性使用
3. **数据库**: Doctrine ORM配置、双数据库架构、迁移管理
4. **API框架**: API Platform配置、版本控制、序列化
5. **安全**: JWT认证、Security组件、CORS配置
6. **性能**: Redis缓存、消息队列、优化策略
7. **日志**: Monolog配置、多渠道日志、监控服务
8. **集成**: 微信SDK、对象存储、外部API
9. **工具**: Docker、测试框架、部署脚本
10. **环境**: 变量配置、文件结构、安全管理
11. **优化**: PHP配置、数据库优化、缓存策略
12. **安全**: 安全头、输入验证、注入防护
13. **维护**: 故障排除、日志分析、备份策略

所有配置都基于项目实际文件，确保了文档的准确性和实用性。通过遵循本文档的配置和最佳实践，可以构建一个高性能、安全、可维护的官方网站后端系统。