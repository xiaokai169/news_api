# 生产环境发布后操作指南

## 📋 概述

本指南针对本次微信同步 API 修复、分布式锁系统优化以及相关数据库结构调整的发布，提供详细的生产环境发布后验证步骤和操作流程。

### 🎯 本次修改核心内容

1. **微信同步 API 400 错误修复**

    - 修复了 [`WechatController::sync()`](src/Controller/WechatController.php:247) 方法的参数映射问题
    - 改进了 DTO 验证逻辑和错误处理
    - 增强了 [`SyncWechatDto`](src/DTO/Request/Wechat/SyncWechatDto.php) 的字段兼容性

2. **分布式锁系统重建**

    - 创建了新的 [`distributed_locks`](migrations/Version20251204084207.php:23) 表结构
    - 优化了 [`DistributedLockService`](src/Service/DistributedLockService.php) 的锁管理逻辑
    - 添加了 [`DistributedLockManagerCommand`](src/Command/DistributedLockManagerCommand.php) 管理工具

3. **数据库结构调整**
    - 新增 `official` 表用于存储微信文章
    - 优化了 `wechat_public_account` 表的索引结构
    - 添加了必要的外键约束

---

## 🚀 发布后立即执行步骤

### 第一步：基础环境验证（发布后 5 分钟内）

```bash
# 1. 检查应用启动状态
php bin/console about --env=prod

# 2. 验证路由配置
php bin/console debug:router --env=prod | grep wechat

# 3. 检查数据库连接
php bin/console doctrine:database:import --env=prod

# 4. 验证缓存状态
php bin/console cache:pool:clear cache.app --env=prod
```

### 第二步：数据库迁移验证（发布后 10 分钟内）

```bash
# 1. 检查迁移状态
php bin/console doctrine:migrations:current --env=prod

# 2. 验证新表结构
mysql -u root -p -e "
SHOW TABLES LIKE 'distributed_locks';
DESCRIBE distributed_locks;
SHOW INDEX FROM distributed_locks;
"

# 3. 检查表数据完整性
mysql -u root -p -e "
SELECT COUNT(*) as lock_count FROM distributed_locks;
SELECT * FROM distributed_locks WHERE expire_time > NOW() LIMIT 5;
"
```

### 第三步：微信同步接口专项验证

#### 3.1 基础接口可用性检查

```bash
# 检查接口响应状态
curl -I -X POST https://your-domain.com/official-api/wechat/sync \
  -H "Content-Type: application/json"

# 验证接口路由存在
curl -X GET https://your-domain.com/official-api/wechat/sync/status/test_account
```

#### 3.2 微信同步 API 功能测试

```bash
# 测试 1: 缺少必需参数的验证（应返回 400）
curl -X POST https://your-domain.com/official-api/wechat/sync \
  -H "Content-Type: application/json" \
  -d '{"accountId":"test","force":false}'

# 测试 2: 空 accountId 验证（应返回 400）
curl -X POST https://your-domain.com/official-api/wechat/sync \
  -H "Content-Type: application/json" \
  -d '{"accountId":"","force":false,"articleLimit":50}'

# 测试 3: 完整参数格式验证
curl -X POST https://your-domain.com/official-api/wechat/sync \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "gh_test_account_id",
    "force": false,
    "articleLimit": 50,
    "syncScope": "recent"
  }'
```

#### 3.3 分布式锁系统验证

```bash
# 1. 检查分布式锁表状态
php bin/console distributed-lock:manager list

# 2. 清理过期锁（如果需要）
php bin/console distributed-lock:manager cleanup

# 3. 验证锁服务功能
php bin/console distributed-lock:manager test wechat_sync_test_account
```

---

## 🔍 微信同步接口专项验证计划

### 阶段一：基础功能验证（发布后 30 分钟内）

#### 1.1 API 端点可用性测试

```bash
#!/bin/bash
# 创建验证脚本: verify_wechat_endpoints.sh

API_BASE="https://your-domain.com/official-api/wechat"

echo "=== 微信 API 端点验证 ==="

# 测试同步接口
echo "1. 测试同步接口端点..."
response=$(curl -s -w "%{http_code}" -X POST "$API_BASE/sync" \
  -H "Content-Type: application/json" \
  -d '{"test": true}' -o /tmp/sync_response.json)

if [ "$response" = "400" ] || [ "$response" = "500" ]; then
    echo "✅ 同步接口端点可用 (HTTP $response)"
else
    echo "❌ 同步接口端点异常 (HTTP $response)"
    cat /tmp/sync_response.json
fi

# 测试状态查询接口
echo "2. 测试状态查询接口..."
response=$(curl -s -w "%{http_code}" -X GET "$API_BASE/sync/status/test" \
  -o /tmp/status_response.json)

if [ "$response" = "404" ] || [ "$response" = "200" ]; then
    echo "✅ 状态查询接口端点可用 (HTTP $response)"
else
    echo "❌ 状态查询接口端点异常 (HTTP $response)"
    cat /tmp/status_response.json
fi

# 测试文章列表接口
echo "3. 测试文章列表接口..."
response=$(curl -s -w "%{http_code}" -X GET "$API_BASE/articles?page=1&limit=10" \
  -o /tmp/articles_response.json)

if [ "$response" = "200" ]; then
    echo "✅ 文章列表接口端点可用 (HTTP $response)"
else
    echo "❌ 文章列表接口端点异常 (HTTP $response)"
    cat /tmp/articles_response.json
fi

rm -f /tmp/*.json
```

#### 1.2 参数验证测试

```bash
#!/bin/bash
# 创建参数验证脚本: test_parameter_validation.sh

API_BASE="https://your-domain.com/official-api/wechat"

echo "=== 参数验证测试 ==="

# 测试用例数组
declare -a test_cases=(
    '{"force":false}'
    '{"accountId":"","force":false,"articleLimit":50}'
    '{"accountId":"test","force":false,"syncScope":"recent"}'
    '{"accountId":"test","force":false,"syncScope":"custom"}'
    '{"accountId":"test","force":false,"articleLimit":1500}'
    'invalid json'
)

for i in "${!test_cases[@]}"; do
    echo "测试用例 $((i+1)): ${test_cases[$i]}"

    response=$(curl -s -w "%{http_code}" -X POST "$API_BASE/sync" \
      -H "Content-Type: application/json" \
      -d "${test_cases[$i]}" \
      -o /tmp/test_case_$i.json)

    echo "响应状态码: $response"

    if [ "$response" = "400" ]; then
        echo "✅ 参数验证正确工作"
    else
        echo "⚠️  意外响应码: $response"
        cat /tmp/test_case_$i.json
    fi
    echo "---"
done

rm -f /tmp/test_case_*.json
```

### 阶段二：集成功能验证（发布后 1 小时内）

#### 2.1 微信公众号账户验证

```bash
#!/bin/bash
# 创建公众号验证脚本: verify_wechat_accounts.sh

echo "=== 微信公众号账户验证 ==="

# 检查数据库中的公众号账户
mysql -u root -p -e "
SELECT
    id,
    name,
    app_id,
    app_secret,
    created_at,
    updated_at
FROM wechat_public_account
WHERE app_id IS NOT NULL AND app_secret IS NOT NULL
LIMIT 10;
"

# 验证测试账户是否存在
test_accounts=("gh_test_account_1" "gh_test_account_2")

for account in "${test_accounts[@]}"; do
    echo "验证账户: $account"

    # 检查数据库中是否存在
    exists=$(mysql -u root -p -sN -e "
    SELECT COUNT(*) FROM wechat_public_account WHERE id = '$account'
    ")

    if [ "$exists" -gt 0 ]; then
        echo "✅ 账户 $account 存在于数据库"

        # 测试 access_token 获取
        token_response=$(curl -s "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=test&secret=test")
        echo "Token API 响应: $token_response"

    else
        echo "⚠️  账户 $account 不存在，将创建测试账户"

        # 创建测试账户
        mysql -u root -p -e "
        INSERT INTO wechat_public_account (id, name, app_id, app_secret, created_at, updated_at)
        VALUES ('$account', '测试账户', 'test_app_id', 'test_app_secret', NOW(), NOW())
        ON DUPLICATE KEY UPDATE updated_at = NOW();
        "

        echo "✅ 测试账户已创建"
    fi
done
```

#### 2.2 分布式锁集成测试

```bash
#!/bin/bash
# 创建分布式锁测试脚本: test_distributed_locks.sh

echo "=== 分布式锁集成测试 ==="

# 1. 清理测试环境
echo "1. 清理测试锁..."
php bin/console distributed-lock:manager cleanup

# 2. 测试锁获取
echo "2. 测试锁获取..."
test_result=$(php bin/console distributed-lock:manager test wechat_sync_integration_test 2>&1)

if echo "$test_result" | grep -q "成功"; then
    echo "✅ 分布式锁获取测试通过"
else
    echo "❌ 分布式锁获取测试失败"
    echo "$test_result"
fi

# 3. 测试并发锁
echo "3. 测试并发锁..."
(
    php bin/console distributed-lock:manager test concurrent_test_1 &
    php bin/console distributed-lock:manager test concurrent_test_2 &
    wait
)

# 4. 检查锁状态
echo "4. 检查当前锁状态..."
php bin/console distributed-lock:manager list

# 5. 清理测试锁
echo "5. 清理测试锁..."
php bin/console distributed-lock:manager cleanup
```

---

## 🔧 分布式锁系统检查流程

### 检查清单

#### 1. 表结构验证

```sql
-- 检查 distributed_locks 表结构
DESCRIBE distributed_locks;

-- 验证必需字段
SELECT
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_KEY
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'distributed_locks'
ORDER BY ORDINAL_POSITION;

-- 检查索引
SHOW INDEX FROM distributed_locks;

-- 验证唯一索引
SELECT
    INDEX_NAME,
    COLUMN_NAME,
    NON_UNIQUE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'distributed_locks';
```

#### 2. 锁功能验证

```bash
#!/bin/bash
# 分布式锁功能验证脚本

echo "=== 分布式锁系统验证 ==="

# 测试锁的获取和释放
test_lock_functionality() {
    local lock_key="test_lock_$(date +%s)"
    echo "测试锁键: $lock_key"

    # 获取锁
    acquire_result=$(php -r "
    require_once 'vendor/autoload.php';

    use App\Service\DistributedLockService;
    use Doctrine\ORM\EntityManagerInterface;
    use Symfony\Component\DependencyInjection\ContainerBuilder;

    // 这里需要根据实际环境调整
    \$kernel = new App\Kernel('prod', false);
    \$kernel->boot();
    \$container = \$kernel->getContainer();
    \$lockService = \$container->get(DistributedLockService::class);

    \$result = \$lockService->acquire('$lock_key', 300);
    echo \$result ? 'SUCCESS' : 'FAILED';
    ")

    if [ "$acquire_result" = "SUCCESS" ]; then
        echo "✅ 锁获取成功"

        # 检查锁状态
        lock_status=$(mysql -u root -p -sN -e "
        SELECT COUNT(*) FROM distributed_locks
        WHERE lock_key = '$lock_key' AND expire_time > NOW()
        ")

        if [ "$lock_status" -gt 0 ]; then
            echo "✅ 锁状态验证成功"
        else
            echo "❌ 锁状态验证失败"
        fi

        # 释放锁
        release_result=$(php -r "
        require_once 'vendor/autoload.php';

        use App\Service\DistributedLockService;

        \$kernel = new App\Kernel('prod', false);
        \$kernel->boot();
        \$container = \$kernel->getContainer();
        \$lockService = \$container->get(DistributedLockService::class);

        \$result = \$lockService->release('$lock_key');
        echo \$result ? 'SUCCESS' : 'FAILED';
        ")

        if [ "$release_result" = "SUCCESS" ]; then
            echo "✅ 锁释放成功"
        else
            echo "❌ 锁释放失败"
        fi
    else
        echo "❌ 锁获取失败"
    fi
}

# 执行测试
test_lock_functionality

# 检查过期锁清理
echo "=== 过期锁清理测试 ==="
expired_count_before=$(mysql -u root -p -sN -e "
SELECT COUNT(*) FROM distributed_locks WHERE expire_time < NOW()
")

echo "清理前过期锁数量: $expired_count_before"

php bin/console distributed-lock:manager cleanup

expired_count_after=$(mysql -u root -p -sN -e "
SELECT COUNT(*) FROM distributed_locks WHERE expire_time < NOW()
")

echo "清理后过期锁数量: $expired_count_after"

if [ "$expired_count_after" -eq 0 ]; then
    echo "✅ 过期锁清理成功"
else
    echo "⚠️  仍有 $expired_count_after 个过期锁"
fi
```

#### 3. 性能测试

```bash
#!/bin/bash
# 分布式锁性能测试

echo "=== 分布式锁性能测试 ==="

# 并发锁获取测试
concurrent_lock_test() {
    local num_threads=10
    local lock_key="perf_test_$(date +%s)"

    echo "启动 $num_threads 个并发线程测试锁: $lock_key"

    # 创建临时脚本
    cat > /tmp/lock_test.php << 'EOF'
<?php
require_once 'vendor/autoload.php';

use App\Service\DistributedLockService;

$kernel = new App\Kernel('prod', false);
$kernel->boot();
$container = $kernel->getContainer();
$lockService = $container->get(DistributedLockService::class);

$lockKey = $argv[1];
$threadId = $argv[2];

$startTime = microtime(true);
$acquired = $lockService->acquire($lockKey, 300);
$endTime = microtime(true);

if ($acquired) {
    sleep(1); // 持有锁 1 秒
    $lockService->release($lockKey);
    echo "Thread $threadId: SUCCESS, Time: " . round(($endTime - $startTime) * 1000, 2) . "ms\n";
} else {
    echo "Thread $threadId: FAILED, Time: " . round(($endTime - $startTime) * 1000, 2) . "ms\n";
}
EOF

    # 启动并发测试
    for i in $(seq 1 $num_threads); do
        php /tmp/lock_test.php "$lock_key" "$i" &
    done

    wait
    rm -f /tmp/lock_test.php

    # 检查最终状态
    final_status=$(mysql -u root -p -sN -e "
    SELECT COUNT(*) FROM distributed_locks
    WHERE lock_key = '$lock_key'
    ")

    echo "最终锁状态: $final_status 个锁记录"

    if [ "$final_status" -eq 0 ]; then
        echo "✅ 并发锁测试通过"
    else
        echo "⚠️  可能存在锁泄漏"
    fi
}

# 执行性能测试
concurrent_lock_test
```

---

## 📊 数据库迁移验证步骤

### 迁移状态检查

```bash
#!/bin/bash
# 数据库迁移验证脚本

echo "=== 数据库迁移验证 ==="

# 1. 检查迁移版本
echo "1. 检查当前迁移版本..."
current_migration=$(php bin/console doctrine:migrations:current --env=prod)
echo "当前迁移版本: $current_migration"

# 2. 检查待执行迁移
echo "2. 检查待执行迁移..."
pending_migrations=$(php bin/console doctrine:migrations:up-to-date --env=prod)
if echo "$pending_migrations" | grep -q "Up-to-date"; then
    echo "✅ 所有迁移已执行"
else
    echo "⚠️  有待执行的迁移"
    php bin/console doctrine:migrations:status --env=prod
fi

# 3. 验证新表结构
echo "3. 验证新表结构..."

# 检查 distributed_locks 表
if mysql -u root -p -e "DESCRIBE distributed_locks" >/dev/null 2>&1; then
    echo "✅ distributed_locks 表存在"

    # 检查表结构
    required_columns=("id" "lockKey" "lockId" "expire_time" "created_at")
    for column in "${required_columns[@]}"; do
        if mysql -u root -p -e "SELECT $column FROM distributed_locks LIMIT 1" >/dev/null 2>&1; then
            echo "  ✅ 列 $column 存在"
        else
            echo "  ❌ 列 $column 缺失"
        fi
    done

    # 检查索引
    if mysql -u root -p -e "SHOW INDEX FROM distributed_locks WHERE Key_name = 'UNIQ_3327048557F10DA4'" >/dev/null 2>&1; then
        echo "  ✅ 唯一索引存在"
    else
        echo "  ❌ 唯一索引缺失"
    fi

else
    echo "❌ distributed_locks 表不存在"
fi

# 4. 检查 official 表
echo "4. 验证 official 表..."
if mysql -u root -p -e "DESCRIBE official" >/dev/null 2>&1; then
    echo "✅ official 表存在"

    # 检查外键约束
    if mysql -u root -p -e "
    SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'official'
      AND REFERENCED_TABLE_NAME = 'sys_news_article_category'
    " | grep -q "1"; then
        echo "  ✅ 外键约束存在"
    else
        echo "  ❌ 外键约束缺失"
    fi
else
    echo "❌ official 表不存在"
fi

# 5. 数据一致性检查
echo "5. 数据一致性检查..."

# 检查 wechat_public_account 表的索引更新
if mysql -u root -p -e "SHOW INDEX FROM wechat_public_account WHERE Key_name = 'UNIQ_EEB657707987212D'" >/dev/null 2>&1; then
    echo "✅ wechat_public_account 表索引已更新"
else
    echo "⚠️  wechat_public_account 表索引可能未更新"
fi
```

### 数据完整性验证

```sql
-- 数据完整性检查脚本

-- 1. 检查 distributed_locks 表数据完整性
SELECT
    'distributed_locks' as table_name,
    COUNT(*) as total_records,
    COUNT(CASE WHEN lockKey IS NULL OR lockKey = '' THEN 1 END) as null_lockkey,
    COUNT(CASE WHEN lockId IS NULL OR lockId = '' THEN 1 END) as null_lockid,
    COUNT(CASE WHEN expire_time IS NULL THEN 1 END) as null_expire_time,
    COUNT(CASE WHEN created_at IS NULL THEN 1 END) as null_created_at,
    COUNT(CASE WHEN expire_time <= NOW() THEN 1 END) as expired_locks
FROM distributed_locks;

-- 2. 检查 official 表数据完整性
SELECT
    'official' as table_name,
    COUNT(*) as total_records,
    COUNT(CASE WHEN title IS NULL OR title = '' THEN 1 END) as null_title,
    COUNT(CASE WHEN content IS NULL OR content = '' THEN 1 END) as null_content,
    COUNT(CASE WHEN category_id IS NULL THEN 1 END) as null_category_id,
    COUNT(CASE WHEN article_id IS NULL OR article_id = '' THEN 1 END) as null_article_id
FROM official;

-- 3. 检查外键约束完整性
SELECT
    'foreign_key_check' as check_type,
    COUNT(*) as orphaned_records
FROM official o
LEFT JOIN sys_news_article_category c ON o.category_id = c.id
WHERE c.id IS NULL;

-- 4. 检查重复数据
SELECT
    'duplicate_check' as check_type,
    lockKey,
    COUNT(*) as duplicate_count
FROM distributed_locks
GROUP BY lockKey
HAVING COUNT(*) > 1;

-- 5. 检查微信文章重复
SELECT
    'wechat_article_duplicates' as check_type,
    article_id,
    COUNT(*) as duplicate_count
FROM official
WHERE article_id IS NOT NULL AND article_id != ''
GROUP BY article_id
HAVING COUNT(*) > 1;
```

---

## ⚡ 性能和安全验证

### 性能基准测试

```bash
#!/bin/bash
# 性能基准测试脚本

echo "=== 性能基准测试 ==="

API_BASE="https://your-domain.com/official-api/wechat"

# 1. API 响应时间测试
echo "1. API 响应时间测试..."

test_api_response_time() {
    local endpoint="$1"
    local method="$2"
    local data="$3"
    local iterations=10

    echo "测试端点: $method $endpoint"

    total_time=0
    for i in $(seq 1 $iterations); do
        start_time=$(date +%s%N)

        if [ "$method" = "GET" ]; then
            response=$(curl -s -w "%{http_code}" -X GET "$endpoint" -o /dev/null)
        else
            response=$(curl -s -w "%{http_code}" -X POST "$endpoint" \
              -H "Content-Type: application/json" \
              -d "$data" -o /dev/null)
        fi

        end_time=$(date +%s%N)
        elapsed=$((($end_time - $start_time) / 1000000))
        total_time=$(($total_time + $elapsed))

        echo "  请求 $i: ${elapsed}ms (HTTP $response)"
    done

    avg_time=$(($total_time / $iterations))
    echo "  平均响应时间: ${avg_time}ms"

    if [ "$avg_time" -lt 1000 ]; then
        echo "  ✅ 响应时间良好"
    elif [ "$avg_time" -lt 3000 ]; then
        echo "  ⚠️  响应时间一般"
    else
        echo "  ❌ 响应时间过长"
    fi
}

# 测试各个端点
test_api_response_time "$API_BASE/sync/status/test" "GET"
test_api_response_time "$API_BASE/articles?page=1&limit=10" "GET"
test_api_response_time "$API_BASE/sync" "POST" '{"accountId":"test","force":false,"articleLimit":50}'

# 2. 数据库查询性能测试
echo "2. 数据库查询性能测试..."

test_db_query_performance() {
    local query="$1"
    local description="$2"

    echo "测试查询: $description"

    start_time=$(date +%s%N)
    result=$(mysql -u root -p -sN -e "$query" 2>/dev/null)
    end_time=$(date +%s%N)

    elapsed=$((($end_time - $start_time) / 1000000))
    echo "  查询时间: ${elapsed}ms"
    echo "  结果行数: $(echo "$result" | wc -l)"

    if [ "$elapsed" -lt 100 ]; then
        echo "  ✅ 查询性能优秀"
    elif [ "$elapsed" -lt 500 ]; then
        echo "  ✅ 查询性能良好"
    elif [ "$elapsed" -lt 2000 ]; then
        echo "  ⚠️  查询性能一般"
    else
        echo "  ❌ 查询性能需要优化"
    fi
}

# 执行数据库性能测试
test_db_query_performance "SELECT COUNT(*) FROM distributed_locks" "分布式锁计数查询"
test_db_query_performance "SELECT COUNT(*) FROM official" "文章计数查询"
test_db_query_performance "SELECT * FROM distributed_locks WHERE expire_time > NOW() LIMIT 10" "分布式锁有效查询"
test_db_query_performance "SELECT o.*, c.name as category_name FROM official o LEFT JOIN sys_news_article_category c ON o.category_id = c.id LIMIT 10" "文章关联查询"

# 3. 并发性能测试
echo "3. 并发性能测试..."

concurrent_test() {
    local endpoint="$1"
    local method="$2"
    local data="$3"
    local concurrent_users=5
    local requests_per_user=10

    echo "并发测试: $concurrent_users 个用户，每人 $requests_per_user 次请求"

    # 创建并发测试脚本
    cat > /tmp/concurrent_test.sh << EOF
#!/bin/bash
for i in \$(seq 1 $requests_per_user); do
    if [ "$method" = "GET" ]; then
        curl -s -X GET "$endpoint" > /dev/null
    else
        curl -s -X POST "$endpoint" \\
          -H "Content-Type: application/json" \\
          -d "$data" > /dev/null
    fi
done
EOF

    chmod +x /tmp/concurrent_test.sh

    start_time=$(date +%s)

    # 启动并发用户
    for user in $(seq 1 $concurrent_users); do
        /tmp/concurrent_test.sh &
    done

    wait

    end_time=$(date +%s)
    total_time=$(($end_time - $start_time))
    total_requests=$(($concurrent_users * $requests_per_user))

    echo "总请求数: $total_requests"
    echo "总耗时: ${total_time}s"
    echo "平均 QPS: $(echo "scale=2; $total_requests / $total_time" | bc)"

    rm -f /tmp/concurrent_test.sh
}

# 执行并发测试
concurrent_test "$API_BASE/articles?page=1&limit=10" "GET"
```

### 安全验证

```bash
#!/bin/bash
# 安全验证脚本

echo "=== 安全验证 ==="

API_BASE="https://your-domain.com/official-api/wechat"

# 1. 输入验证测试
echo "1. 输入验证测试..."

test_input_validation() {
    local test_name="$1"
    local payload="$2"
    local expected_status="$3"

    echo "测试: $test_name"

    response=$(curl -s -w "%{http_code}" -X POST "$API_BASE/sync" \
      -H "Content-Type: application/json" \
      -d "$payload" -o /tmp/security_test.json)

    if [ "$response" = "$expected_status" ]; then
        echo "  ✅ 安全验证通过 (HTTP $response)"
    else
        echo "  ❌ 安全验证失败 (期望: $expected_status, 实际: $response)"
        echo "  响应内容:"
        cat /tmp/security_test.json
    fi
}

# 执行安全测试
test_input_validation "SQL注入测试" '{"accountId":"'\'' OR 1=1 --","force":false,"articleLimit":50}' "400"
test_input_validation "XSS测试" '{"accountId":"<script>alert(1)</script>","force":false,"articleLimit":50}' "400"
test_input_validation "大整数测试" '{"accountId":"999999999999999999999","force":false,"articleLimit":50}' "400"
test_input_validation "特殊字符测试" '{"accountId":"!@#$%^&*()","force":false,"articleLimit":50}' "400"

# 2. 认证和授权测试
echo "2. 认证和授权测试..."

# 测试未认证访问（如果需要认证）
echo "测试未认证访问..."
response=$(curl -s -w "%{http_code}" -X POST "$API_BASE/sync" \
  -H "Content-Type: application/json" \
  -d '{"accountId":"test","force":false,"articleLimit":50}' -o /tmp/auth_test.json)

if [ "$response" = "200" ] || [ "$response" = "401" ] || [ "$response" = "403" ]; then
    echo "✅ 认证机制正常 (HTTP $response)"
else
    echo "⚠️  认证机制可能有问题 (HTTP $response)"
fi

# 3. 速率限制测试
echo "3. 速率限制测试..."

echo "发送多个快速请求..."
for i in {1..20}; do
    response=$(curl -s -w "%{http_code}" -X POST "$API_BASE/sync" \
      -H "Content-Type: application/json" \
      -d '{"accountId":"test'$i'","force":false,"articleLimit":50}' -o /dev/null)
    echo "请求 $i: HTTP $response"
    sleep 0.1
done

# 4. 文件上传安全测试（如果有相关接口）
echo "4. 文件上传安全测试..."
echo "  (当前版本暂无文件上传接口)"

# 5. 数据泄露测试
echo "5. 数据泄露测试..."

# 测试错误信息是否暴露敏感信息
response=$(curl -s -X POST "$API_BASE/sync" \
  -H "Content-Type: application/json" \
  -d '{"accountId":"nonexistent_account_12345","force":false,"articleLimit":50}')

if echo "$response" | grep -qi -E "(password|secret|key|token|internal|stack trace)"; then
    echo "⚠️  错误响应可能包含敏感信息"
    echo "$response"
else
    echo "✅ 错误响应安全"
fi

rm -f /tmp/security_test.json /tmp/auth_test.json
```

---

## 📈 监控和回滚准备方案

### 监控设置

```bash
#!/bin/bash
# 监控设置脚本

echo "=== 监控设置 ==="

# 1. 应用监控配置
echo "1. 配置应用监控..."

# 创建监控配置文件
cat > config/monitoring/production_monitoring.yaml << 'EOF'
monitoring:
  metrics:
    - name: wechat_sync_requests_total
      type: counter
      description: "微信同步请求总数"
      labels: [status, account_id]

    - name: wechat_sync_duration_seconds
      type: histogram
      description: "微信同步请求耗时"
      buckets: [0.1, 0.5, 1.0, 2.0, 5.0, 10.0]

    - name: distributed_lock_acquisitions_total
      type: counter
      description: "分布式锁获取总数"
      labels: [lock_key, result]

    - name: distributed_lock_duration_seconds
      type: histogram
      description: "分布式锁持有时间"
      buckets: [1, 5, 10, 30, 60, 300]

  alerts:
    - name: wechat_sync_high_error_rate
      condition: "rate(wechat_sync_requests_total{status='5xx'}[5m]) > 0.1"
      severity: critical
      message: "微信同步错误率过高"

    - name: distributed_lock_contention
      condition: "rate(distributed_lock_acquisitions_total{result='failed'}[5m]) > 0.05"
      severity: warning
      message: "分布式锁竞争激烈"

    - name: api_response_time_high
      condition: "histogram_quantile(0.95, wechat_sync_duration_seconds) > 5"
      severity: warning
      message: "API 响应时间过高"
EOF

echo "✅ 监控配置已创建"

# 2. 日志监控设置
echo "2. 配置日志监控..."

# 创建日志监控脚本
cat > scripts/log_monitor.sh << 'EOF'
#!/bin/bash

LOG_FILE="/var/log/nginx/access.log"
ERROR_PATTERN="5[0-9][0-9]"
WECHAT_PATTERN="/official-api/wechat"

# 监控错误率
monitor_error_rate() {
    local window=300  # 5分钟
    local current_time=$(date +%s)
    local start_time=$((current_time - window))

    local total_requests=$(awk -v start="$start_time" '$4 >= start && $7 ~ /\/official-api\/wechat/ {count++} END {print count+0}' "$LOG_FILE")
    local error_requests=$(awk -v start="$start_time" '$4 >= start && $7 ~ /\/official-api\/wechat/ && $9 ~ /5[0-9][0-9]/ {count++} END {print count+0}' "$LOG_FILE")

    if [ "$total_requests" -gt 0 ]; then
        local error_rate=$(echo "scale=4; $error_requests / $total_requests * 100" | bc)
        echo "错误率: ${error_rate}% ($error_requests/$total_requests)"

        if (( $(echo "$error_rate > 10" | bc -l) )); then
            echo "🚨 错误率过高: ${error_rate}%"
            # 发送告警通知
            # send_alert "微信API错误率过高: ${error_rate}%"
        fi
    fi
}

# 监控响应时间
monitor_response_time() {
    local window=300
    local current_time=$(date +%s)
    local start_time=$((current_time - window))

    # 这里需要根据实际的日志格式调整
    local avg_response_time=$(awk -v start="$start_time" '$4 >= start && $7 ~ /\/official-api\/wechat/ {sum+=$NF; count++} END {if(count>0) print sum/count; else print 0}' "$LOG_FILE")

    echo "平均响应时间: ${avg_response_time}s"

    if (( $(echo "$avg_response_time > 3" | bc -l) )); then
        echo "🚨 响应时间过长: ${avg_response_time}s"
        # send_alert "微信API响应时间过长: ${avg_response_time}s"
    fi
}

# 主监控循环
while true; do
    echo "$(date): 监控检查..."
    monitor_error_rate
    monitor_response_time
    sleep 60
done
EOF

chmod +x scripts/log_monitor.sh
echo "✅ 日志监控脚本已创建"

# 3. 健康检查设置
echo "3. 配置健康检查..."

# 创建健康检查端点
cat > src/Controller/HealthController.php << 'EOF'
<?php

namespace App\Controller;

use App\Http\ApiResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/health')]
class HealthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ApiResponse $apiResponse
    ) {
    }

    #[Route('', name: 'health_check', methods: ['GET'])]
    public function check(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => date('c'),
            'checks' => []
        ];

        // 数据库连接检查
        try {
            $this->entityManager->getConnection()->connect();
            $health['checks']['database'] = [
                'status' => 'healthy',
                'message' => 'Database connection successful'
            ];
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['checks']['database'] = [
                'status' => 'unhealthy',
                'message' => $e->getMessage()
            ];
        }

        // 分布式锁表检查
        try {
            $result = $this->entityManager->getConnection()
                ->executeQuery('SELECT COUNT(*) FROM distributed_locks')
                ->fetchOne();
            $health['checks']['distributed_locks'] = [
                'status' => 'healthy',
                'message' => "Lock table accessible, $result locks"
            ];
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['checks']['distributed_locks'] = [
                'status' => 'unhealthy',
                'message' => $e->getMessage()
            ];
        }

        $statusCode = $health['status'] === 'healthy' ? 200 : 503;
        return $this->apiResponse->success($health, $statusCode);
    }
}
EOF

echo "✅ 健康检查端点已创建"
```

### 回滚准备

```bash
#!/bin/bash
# 回滚准备脚本

echo "=== 回滚准备 ==="

# 1. 创建回滚脚本
echo "1. 创建回滚脚本..."

cat > scripts/rollback.sh << 'EOF'
#!/bin/bash

set -e

BACKUP_DIR="/var/backups/official_website"
CURRENT_DATE=$(date +%Y%m%d_%H%M%S)
ROLLBACK_LOG="/var/log/rollback_$CURRENT_DATE.log"

echo "=== 开始回滚操作 ===" | tee -a "$ROLLBACK_LOG"
echo "回滚时间: $(date)" | tee -a "$ROLLBACK_LOG"

# 函数：记录日志
log() {
    echo "$1" | tee -a "$ROLLBACK_LOG"
}

# 函数：检查命令执行结果
check_result() {
    if [ $? -eq 0 ]; then
        log "✅ $1 成功"
    else
        log "❌ $1 失败"
        exit 1
    fi
}

# 1. 数据库回滚
log "1. 开始数据库回滚..."

# 检查备份文件是否存在
LATEST_BACKUP=$(ls -t "$BACKUP_DIR"/backup_*.sql | head -1)
if [ -z "$LATEST_BACKUP" ]; then
    log "❌ 未找到数据库备份文件"
    exit 1
fi

log "使用备份文件: $LATEST_BACKUP"

# 执行数据库回滚
mysql -u root -p official_website < "$LATEST_BACKUP"
check_result "数据库回滚"

# 2. 代码回滚
log "2. 开始代码回滚..."

cd /www/wwwroot/official_website_backend

# 获取当前提交
CURRENT_COMMIT=$(git rev-parse HEAD)
log "当前提交: $CURRENT_COMMIT"

# 回滚到上一个提交
git checkout HEAD~1
check_result "代码回滚"

# 3. 依赖重新安装
log "3. 重新安装依赖..."

composer install --no-dev --optimize-autoloader --no-interaction
check_result "依赖安装"

# 4. 缓存清理
log "4. 清理缓存..."

php bin/console cache:clear --env=prod --no-warmup
check_result "缓存清理"

php bin/console cache:warmup --env=prod
check_result "缓存预热"

# 5. 权限设置
log "5. 设置文件权限..."

chmod -R 755 var/
chown -R www-data:www-data var/ 2>/dev/null || true
check_result "权限设置"

# 6. 服务重启
log "6. 重启服务..."

# 重启 PHP-FPM
systemctl restart php8.2-fpm || systemctl restart php-fpm
check_result "PHP-FPM 重启"

# 重启 Nginx
systemctl restart nginx
check_result "Nginx 重启"

# 7. 验证回滚
log "7. 验证回滚结果..."

# 检查应用状态
if php bin/console about --env=prod >/dev/null 2>&1; then
    log "✅ 应用状态正常"
else
    log "❌ 应用状态异常"
    exit 1
fi

# 检查数据库连接
if php bin/console doctrine:database:import --env=prod >/dev/null 2>&1; then
    log "✅ 数据库连接正常"
else
    log "❌ 数据库连接异常"
    exit 1
fi

# 检查 API 端点
if curl -f -s -o /dev/null http://localhost/official-api/wechat/sync/status/test; then
    log "✅ API 端点正常"
else
    log "❌ API 端点异常"
    exit 1
fi

log "=== 回滚完成 ==="
log "回滚完成时间: $(date)"
log "回滚日志: $ROLLBACK_LOG"

EOF

chmod +x scripts/rollback.sh
echo "✅ 回滚脚本已创建"

# 2. 创建快速回滚脚本
echo "2. 创建快速回滚脚本..."

cat > scripts/quick_rollback.sh << 'EOF'
#!/bin/bash

echo "=== 快速回滚 ==="

# 快速回滚到上一个 git 提交
cd /www/wwwroot/official_website_backend

git checkout HEAD~1
composer install --no-dev --optimize-autoloader --no-interaction
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod

# 重启服务
systemctl restart php8.2-fpm || systemctl restart php-fpm
systemctl restart nginx

echo "✅ 快速回滚完成"
EOF

chmod +x scripts/quick_rollback.sh
echo "✅ 快速回滚脚本已创建"

# 3. 创建回滚触发条件检查脚本
echo "3. 创建回滚触发条件检查..."

cat > scripts/check_rollback_triggers.sh << 'EOF'
#!/bin/bash

API_BASE="https://your-domain.com/official-api/wechat"
ERROR_THRESHOLD=10
RESPONSE_TIME_THRESHOLD=5000
CHECK_INTERVAL=60

check_rollback_triggers() {
    local error_count=0
    local total_response_time=0
    local request_count=0

    # 检查错误率
    for i in {1..10}; do
        response=$(curl -s -w "%{http_code}" -X POST "$API_BASE/sync" \
          -H "Content-Type: application/json" \
          -d '{"accountId":"test_'$i'","force":false,"articleLimit":50}' \
          -o /dev/null)

        if [ "$response" -ge 500 ]; then
            error_count=$((error_count + 1))
        fi

        request_count=$((request_count + 1))
        sleep 1
    done

    error_rate=$(echo "scale=2; $error_count * 100 / $request_count" | bc)

    echo "错误率: ${error_rate}% ($error_count/$request_count)"

    # 检查回滚条件
    if (( $(echo "$error_rate > $ERROR_THRESHOLD" | bc -l) )); then
        echo "🚨 触发回滚条件: 错误率过高 (${error_rate}%)"
        echo "建议执行: ./scripts/quick_rollback.sh"
        return 1
    fi

    echo "✅ 未触发回滚条件"
    return 0
}

# 持续监控
while true; do
    echo "$(date): 检查回滚触发条件..."
    if ! check_rollback_triggers; then
        # 可以在这里添加自动回滚逻辑
        echo "请手动执行回滚操作"
        break
    fi
    sleep $CHECK_INTERVAL
done
EOF

chmod +x scripts/check_rollback_triggers.sh
echo "✅ 回滚触发条件检查脚本已创建"

echo "=== 回滚准备完成 ==="
echo "回滚脚本位置:"
echo "  - 完整回滚: ./scripts/rollback.sh"
echo "  - 快速回滚: ./scripts/quick_rollback.sh"
echo "  - 触发检查: ./scripts/check_rollback_triggers.sh"
```

---

## 📋 完整发布后操作清单

### 立即执行（发布后 0-30 分钟）

-   [ ] **基础环境检查**

    -   [ ] 应用启动状态验证
    -   [ ] 路由配置检查
    -   [ ] 数据库连接测试
    -   [ ] 缓存状态确认

-   [ ] **数据库迁移验证**

    -   [ ] 迁移版本确认
    -   [ ] 新表结构验证
    -   [ ] 数据完整性检查
    -   [ ] 外键约束验证

-   [ ] **API 基础功能测试**
    -   [ ] 微信同步接口可用性
    -   [ ] 参数验证功能
    -   [ ] 错误处理机制
    -   [ ] 响应格式验证

### 详细验证（发布后 30 分钟-2 小时）

-   [ ] **微信同步接口专项测试**

    -   [ ] 完整参数流程测试
    -   [ ] 字段兼容性验证
    -   [ ] 并发同步测试
    -   [ ] 错误场景处理

-   [ ] **分布式锁系统验证**

    -   [ ] 锁获取/释放功能
    -   [ ] 并发锁竞争测试
    -   [ ] 过期锁清理机制
    -   [ ] 性能基准测试

-   [ ] **性能和安全验证**
    -   [ ] API 响应时间测试
    -   [ ] 数据库查询性能
    -   [ ] 输入验证安全测试
    -   [ ] 认证授权机制

### 监控部署（发布后 2-4 小时）

-   [ ] **监控配置**

    -   [ ] 应用指标监控
    -   [ ] 日志监控设置
    -   [ ] 健康检查端点
    -   [ ] 告警规则配置

-   [ ] **回滚准备**
    -   [ ] 回滚脚本准备
    -   [ ] 备份验证
    -   [ ] 触发条件设置
    -   [ ] 回滚流程测试

### 长期监控（发布后 24 小时）

-   [ ] **持续监控**

    -   [ ] 错误率监控
    -   [ ] 性能指标跟踪
    -   [ ] 用户体验反馈
    -   [ ] 系统资源使用

-   [ ] **文档更新**
    -   [ ] 操作手册更新
    -   [ ] 故障处理记录
    -   [ ] 性能基准文档
    -   [ ] 监控配置文档

---

## 🆘 应急处理流程

### 紧急情况处理

1. **API 大量 500 错误**

    ```bash
    # 立即检查应用日志
    tail -f var/log/prod.log | grep ERROR

    # 检查数据库连接
    php bin/console doctrine:database:import --env=prod

    # 快速回滚（如果需要）
    ./scripts/quick_rollback.sh
    ```

2. **分布式锁系统故障**

    ```bash
    # 检查锁表状态
    php bin/console distributed-lock:manager list

    # 清理异常锁
    php bin/console distributed-lock:manager cleanup --force

    # 重建锁表（最后手段）
    php bin/console distributed-lock:manager create-table
    ```

3. **数据库性能问题**

    ```bash
    # 检查慢查询
    mysql -u root -p -e "SHOW PROCESSLIST;"

    # 检查表锁
    mysql -u root -p -e "SHOW OPEN TABLES WHERE In_use > 0;"

    # 优化表
    mysql -u root -p -e "OPTIMIZE TABLE distributed_locks, official;"
    ```

### 联系信息

-   **技术负责人**: [联系方式]
-   **运维团队**: [联系方式]
-   **产品负责人**: [联系方式]
-   **紧急响应**: [联系方式]

---

## 📝 总结

本发布后操作指南涵盖了微信同步 API 修复和分布式锁系统优化的全面验证流程。请严格按照时间节点执行各项检查，确保系统稳定运行。

**关键要点**:

1. 立即验证基础功能和数据库迁移
2. 重点测试微信同步接口和分布式锁系统
3. 部署监控和回滚机制
4. 持续监控系统性能和错误率

**成功标准**:

-   所有 API 端点正常响应
-   数据库迁移完成且数据完整
-   分布式锁系统功能正常
-   错误率低于 1%，平均响应时间小于 2 秒

如遇到任何问题，请参考应急处理流程或联系相关负责人。

---

_最后更新: $(date)_  
_版本: 1.0_  
_适用于: Symfony 7.3 + PHP 8.2+ + MySQL 8.0+_
