# 生产环境分布式锁字段名紧急修复指南

## 🚨 问题说明

生产环境数据库字段已改为 `lockKey`，但代码中仍有部分地方使用旧字段名 `lock_key`，导致 SQL 错误：

```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'lock_key' in 'field list'
```

## 📍 位置信息

-   生产环境目录：`/www/wwwroot/newsapi.arab-bee.com/`
-   错误来源：`src/Service/DistributedLockService.php` 第 62 行

## 🔧 立即修复步骤

### 步骤 1: 登录生产服务器

```bash
ssh your-production-server
```

### 步骤 2: 切换到生产环境目录

```bash
cd /www/wwwroot/newsapi.arab-bee.com/
```

### 步骤 3: 备份关键文件

```bash
# 创建备份目录
mkdir -p backup_$(date +%Y%m%d_%H%M%S)

# 备份关键文件
cp src/Service/DistributedLockService.php backup_$(date +%Y%m%d_%H%M%S)/
cp src/Command/DistributedLockManagerCommand.php backup_$(date +%Y%m%d_%H%M%S)/ 2>/dev/null || true
```

### 步骤 4: 检查并修复数据库字段

```bash
# 检查当前字段名
mysql -u root -p -e "DESCRIBE distributed_locks;" official_website

# 如果字段名仍是 lock_key，执行重命名
mysql -u root -p -e "
ALTER TABLE distributed_locks
CHANGE COLUMN lock_key lockKey VARCHAR(255) NOT NULL;
" official_website

# 验证字段名已更改
mysql -u official_website -p -e "DESCRIBE distributed_locks;" official_website | grep lockKey
```

### 步骤 5: 修复 DistributedLockService.php

```bash
# 检查是否包含旧字段名
grep -n "lock_key" src/Service/DistributedLockService.php

# 如果包含旧字段名，执行替换
sed -i 's/lock_key/lockKey/g' src/Service/DistributedLockService.php

# 验证修复结果
grep -n "lockKey" src/Service/DistributedLockService.php | head -5
```

### 步骤 6: 修复 DistributedLockManagerCommand.php

```bash
# 检查文件是否存在
if [ -f "src/Command/DistributedLockManagerCommand.php" ]; then
    # 检查是否包含旧字段名
    grep -n "lock_key" src/Command/DistributedLockManagerCommand.php

    # 如果包含旧字段名，执行替换
    sed -i 's/lock_key/lockKey/g' src/Command/DistributedLockManagerCommand.php

    # 验证修复结果
    grep -n "lockKey" src/Command/DistributedLockManagerCommand.php | head -5
fi
```

### 步骤 7: 清理所有缓存

```bash
# 清理 Symfony 缓存
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:clear --env=dev --no-warmup

# 清理 Doctrine 缓存
php bin/console doctrine:cache:clear-metadata --env=prod
php bin/console doctrine:cache:clear-query --env=prod
php bin/console doctrine:cache:clear-result --env=prod

# 清理 OPcache
php -r "if(function_exists('opcache_reset')) opcache_reset(); echo 'OPcache cleared';"

# 删除缓存目录
rm -rf var/cache/*

# 重新生成 Doctrine 代理
php bin/console doctrine:generate:proxies --env=prod --regenerate
```

### 步骤 8: 重启服务

```bash
# 重启 PHP-FPM
systemctl restart php-fpm

# 重启 Nginx
systemctl restart nginx

# 检查服务状态
systemctl status php-fpm
systemctl status nginx
```

### 步骤 9: 验证修复结果

```bash
# 1. 验证数据库结构
mysql -u root -p -e "DESCRIBE distributed_locks;" official_website | grep lockKey

# 2. 验证代码文件
echo "=== 检查 DistributedLockService.php ==="
grep -n "lock_key" src/Service/DistributedLockService.php || echo "✅ 无旧字段名"

echo "=== 检查 DistributedLockManagerCommand.php ==="
if [ -f "src/Command/DistributedLockManagerCommand.php" ]; then
    grep -n "lock_key" src/Command/DistributedLockManagerCommand.php || echo "✅ 无旧字段名"
fi

# 3. 验证 Doctrine 映射
php bin/console doctrine:schema:validate --env=prod

# 4. 测试分布式锁功能
php bin/console app:distributed-lock:manage status
```

## 🔍 关键修复点

### 1. 数据库层面

-   ✅ 字段名：`lock_key` → `lockKey`
-   ✅ 索引名：`idx_lock_key` → `idx_lockKey`

### 2. 代码层面

-   ✅ [`src/Service/DistributedLockService.php`](src/Service/DistributedLockService.php:55) SQL 查询
-   ✅ [`src/Command/DistributedLockManagerCommand.php`](src/Command/DistributedLockManagerCommand.php:165) SQL 查询

### 3. 缓存层面

-   ✅ Symfony 应用缓存
-   ✅ Doctrine 元数据缓存
-   ✅ OPcache 字节码缓存
-   ✅ Doctrine 代理文件

## 🚨 如果修复后仍有问题

### 检查错误日志

```bash
# PHP 错误日志
tail -f /var/log/php_errors.log

# Nginx 错误日志
tail -f /var/log/nginx/error.log

# Symfony 生产日志
tail -f var/log/prod.log
```

### 手动测试分布式锁

```bash
# 创建测试锁
php bin/console app:distributed-lock:manage status

# 清理所有锁
php bin/console app:distributed-lock:manage clean --force

# 再次检查状态
php bin/console app:distributed-lock:manage status
```

### 验证微信同步功能

```bash
# 测试微信同步接口
curl -X POST "https://newsapi.arab-bee.com/api/wechat/sync" \
  -H "Content-Type: application/json" \
  -d '{"accountId":"test"}'
```

## 📞 紧急联系

如果修复过程中遇到问题：

1. 立即停止修复，恢复备份文件
2. 检查数据库连接和权限
3. 验证 PHP 和扩展版本兼容性
4. 联系技术支持团队

## ✅ 修复完成标志

修复成功后，您应该看到：

1. ✅ 数据库字段显示为 `lockKey`
2. ✅ 代码文件中无 `lock_key` 引用
3. ✅ Doctrine 架构验证通过
4. ✅ 分布式锁状态命令正常执行
5. ✅ 微信同步接口返回正常响应

## 🔄 回滚方案

如果修复失败，立即执行：

```bash
# 恢复备份文件
cp backup_$(date +%Y%m%d_%H%M%S)/DistributedLockService.php src/Service/
cp backup_$(date +%Y%m%d_%H%M%S)/DistributedLockManagerCommand.php src/Command/ 2>/dev/null || true

# 恢复数据库字段
mysql -u root -p -e "
ALTER TABLE distributed_locks
CHANGE COLUMN lockKey lock_key VARCHAR(255) NOT NULL;
" official_website

# 清理缓存并重启服务
php bin/console cache:clear --env=prod --no-warmup
systemctl restart php-fpm nginx
```
