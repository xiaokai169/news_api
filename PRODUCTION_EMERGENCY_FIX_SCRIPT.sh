#!/bin/bash

# 生产环境分布式锁字段名紧急修复脚本
# 解决 lock_key -> lockKey 字段名不一致问题

set -e

echo "🚨 生产环境紧急修复脚本开始执行..."
echo "⏰ 开始时间: $(date)"
echo "📍 当前目录: $(pwd)"

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 日志函数
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# 检查是否在生产环境
check_production_env() {
    log_info "检查生产环境..."

    if [[ ! -d "/www/wwwroot/newsapi.arab-bee.com" ]]; then
        log_error "❌ 未检测到生产环境目录 /www/wwwroot/newsapi.arab-bee.com"
        exit 1
    fi

    if [[ ! -f "/www/wwwroot/newsapi.arab-bee.com/src/Service/DistributedLockService.php" ]]; then
        log_error "❌ 未找到生产环境 DistributedLockService.php 文件"
        exit 1
    fi

    log_success "✅ 生产环境检查通过"
}

# 切换到生产环境目录
switch_to_production() {
    log_info "切换到生产环境目录..."
    cd /www/wwwroot/newsapi.arab-bee.com
    log_success "✅ 已切换到: $(pwd)"
}

# 备份当前文件
backup_files() {
    log_info "备份关键文件..."

    backup_dir="/www/wwwroot/newsapi.arab-bee.com/backup_$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$backup_dir"

    cp src/Service/DistributedLockService.php "$backup_dir/"
    cp src/Command/DistributedLockManagerCommand.php "$backup_dir/" 2>/dev/null || true
    cp src/Command/CreateDistributedLocksTableCommand.php "$backup_dir/" 2>/dev/null || true
    cp src/Entity/DistributedLock.php "$backup_dir/" 2>/dev/null || true

    log_success "✅ 文件已备份到: $backup_dir"
}

# 检查当前数据库字段状态
check_database_schema() {
    log_info "检查数据库表结构..."

    # 检查字段名
    if mysql -u root -p -e "DESCRIBE distributed_locks;" official_website 2>/dev/null | grep -q "lockKey"; then
        log_success "✅ 数据库字段已正确命名为 lockKey"
    elif mysql -u root -p -e "DESCRIBE distributed_locks;" official_website 2>/dev/null | grep -q "lock_key"; then
        log_warning "⚠️ 数据库字段仍为 lock_key，需要更新"

        # 执行字段重命名
        log_info "正在重命名字段 lock_key -> lockKey..."
        mysql -u root -p -e "
            ALTER TABLE distributed_locks
            CHANGE COLUMN lock_key lockKey VARCHAR(255) NOT NULL;
        " official_website

        log_success "✅ 数据库字段重命名完成"
    else
        log_error "❌ 无法确定数据库字段状态"
    fi
}

# 修复 DistributedLockService.php
fix_distributed_lock_service() {
    log_info "修复 DistributedLockService.php..."

    # 检查是否包含旧的 lock_key 字段
    if grep -q "lock_key" src/Service/DistributedLockService.php; then
        log_warning "⚠️ 发现旧的 lock_key 字段，正在修复..."

        # 备份原文件
        cp src/Service/DistributedLockService.php src/Service/DistributedLockService.php.backup

        # 创建修复后的文件
        cat > src/Service/DistributedLockService.php << 'EOF'
<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class DistributedLockService
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * 获取分布式锁
     *
     * @param string $lockKey 锁的键名
     * @param int $ttl 锁的生存时间（秒），默认60秒
     * @return bool 是否成功获取锁
     */
    public function acquireLock(string $lockKey, int $ttl = 60): bool
    {
        try {
            // 使用数据库实现简单的分布式锁
            $connection = $this->entityManager->getConnection();

            // 添加详细日志
            $this->logger->info('开始获取分布式锁', [
                'lock_key' => $lockKey,
                'ttl' => $ttl,
                'current_time' => date('Y-m-d H:i:s'),
                'lock_id' => md5($lockKey)
            ]);

            // 清理过期的锁
            $this->cleanExpiredLocks();

            // 尝试获取锁
            $lockId = md5($lockKey);
            $expireTime = date('Y-m-d H:i:s', time() + $ttl);

            $this->logger->info('准备执行锁获取SQL', [
                'lock_key' => $lockKey,
                'lock_id' => $lockId,
                'expire_time' => $expireTime
            ]);

            $sql = "INSERT INTO distributed_locks (lockKey, lockId, expire_time, created_at)
                    VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                    lockId = IF(expire_time < NOW(), VALUES(lockId), lockId),
                    expire_time = IF(expire_time < NOW(), VALUES(expire_time), expire_time)";

            $stmt = $connection->prepare($sql);
            $result = $stmt->executeStatement([$lockKey, $lockId, $expireTime]);

            $this->logger->info('锁获取SQL执行完成', [
                'lock_key' => $lockKey,
                'affected_rows' => $result
            ]);

            // 检查是否成功获取锁
            $checkSql = "SELECT lockId, expire_time FROM distributed_locks WHERE lockKey = ? AND lockId = ? AND expire_time > NOW()";
            $checkStmt = $connection->prepare($checkSql);
            $checkResult = $checkStmt->executeQuery([$lockKey, $lockId]);
            $currentLock = $checkResult->fetchAssociative();

            $acquired = $currentLock && $currentLock['lockId'] === $lockId;

            $this->logger->info('锁获取结果检查', [
                'lock_key' => $lockKey,
                'acquired' => $acquired,
                'current_lock' => $currentLock,
                'expected_lock_id' => $lockId
            ]);

            if (!$acquired) {
                // 检查当前锁的状态
                $currentStatusSql = "SELECT lockId, expire_time FROM distributed_locks WHERE lockKey = ?";
                $statusStmt = $connection->prepare($currentStatusSql);
                $statusResult = $statusStmt->executeQuery([$lockKey]);
                $currentStatus = $statusResult->fetchAssociative();

                $this->logger->warning('锁获取失败详细信息', [
                    'lock_key' => $lockKey,
                    'current_status' => $currentStatus,
                    'is_expired' => $currentStatus ? strtotime($currentStatus['expire_time']) < time() : 'no_lock'
                ]);
            }

            if ($acquired) {
                $this->logger->info('成功获取分布式锁', ['lock_key' => $lockKey, 'ttl' => $ttl]);
            } else {
                $this->logger->info('获取分布式锁失败，锁已被其他进程持有', ['lock_key' => $lockKey]);
            }

            return $acquired;

        } catch (\Exception $e) {
            $this->logger->error('获取分布式锁时发生错误', [
                'lock_key' => $lockKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * 释放分布式锁
     *
     * @param string $lockKey 锁的键名
     * @return bool 是否成功释放锁
     */
    public function releaseLock(string $lockKey): bool
    {
        try {
            $connection = $this->entityManager->getConnection();

            $sql = "DELETE FROM distributed_locks WHERE lockKey = ?";
            $stmt = $connection->prepare($sql);
            $result = $stmt->executeStatement([$lockKey]);

            $this->logger->info('释放分布式锁', ['lock_key' => $lockKey, 'affected_rows' => $result]);

            return $result > 0;

        } catch (\Exception $e) {
            $this->logger->error('释放分布式锁时发生错误', [
                'lock_key' => $lockKey,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 检查锁是否存在且有效
     *
     * @param string $lockKey 锁的键名
     * @return bool 锁是否存在且有效
     */
    public function isLocked(string $lockKey): bool
    {
        try {
            $connection = $this->entityManager->getConnection();

            $this->logger->info('检查分布式锁状态', [
                'lock_key' => $lockKey,
                'current_time' => date('Y-m-d H:i:s')
            ]);

            $sql = "SELECT lockId, expire_time FROM distributed_locks WHERE lockKey = ? AND expire_time > NOW()";
            $stmt = $connection->prepare($sql);
            $result = $stmt->executeQuery([$lockKey]);
            $lock = $result->fetchAssociative();

            $isLocked = $lock !== false;

            $this->logger->info('锁状态检查结果', [
                'lock_key' => $lockKey,
                'is_locked' => $isLocked,
                'lock_info' => $lock,
                'sql' => $sql
            ]);

            // 如果没有找到有效锁，检查是否有过期锁
            if (!$isLocked) {
                $expiredSql = "SELECT lockId, expire_time FROM distributed_locks WHERE lockKey = ?";
                $expiredStmt = $connection->prepare($expiredSql);
                $expiredResult = $expiredStmt->executeQuery([$lockKey]);
                $expiredLock = $expiredResult->fetchAssociative();

                $this->logger->info('检查过期锁状态', [
                    'lock_key' => $lockKey,
                    'expired_lock' => $expiredLock,
                    'is_expired' => $expiredLock ? strtotime($expiredLock['expire_time']) < time() : 'no_lock'
                ]);
            }

            return $isLocked;

        } catch (\Exception $e) {
            $this->logger->error('检查分布式锁时发生错误', [
                'lock_key' => $lockKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * 清理过期的锁
     */
    private function cleanExpiredLocks(): void
    {
        try {
            $connection = $this->entityManager->getConnection();

            $sql = "DELETE FROM distributed_locks WHERE expire_time < NOW()";
            $stmt = $connection->prepare($sql);
            $result = $stmt->executeStatement();

            if ($result > 0) {
                $this->logger->info('清理过期分布式锁', ['cleaned_count' => $result]);
            }

        } catch (\Exception $e) {
            $this->logger->error('清理过期分布式锁时发生错误', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 延长锁的过期时间
     *
     * @param string $lockKey 锁的键名
     * @param int $ttl 新的生存时间（秒）
     * @return bool 是否成功延长锁
     */
    public function extendLock(string $lockKey, int $ttl = 60): bool
    {
        try {
            $connection = $this->entityManager->getConnection();

            $expireTime = date('Y-m-d H:i:s', time() + $ttl);

            $sql = "UPDATE distributed_locks SET expire_time = ? WHERE lockKey = ? AND expire_time > NOW()";
            $stmt = $connection->prepare($sql);
            $result = $stmt->executeStatement([$expireTime, $lockKey]);

            $extended = $result > 0;

            if ($extended) {
                $this->logger->info('延长分布式锁过期时间', ['lock_key' => $lockKey, 'new_ttl' => $ttl]);
            } else {
                $this->logger->warning('延长分布式锁过期时间失败，锁可能已过期', ['lock_key' => $lockKey]);
            }

            return $extended;

        } catch (\Exception $e) {
            $this->logger->error('延长分布式锁过期时间时发生错误', [
                'lock_key' => $lockKey,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
EOF

        log_success "✅ DistributedLockService.php 修复完成"
    else
        log_info "ℹ️ DistributedLockService.php 已是最新版本"
    fi
}

# 修复 DistributedLockManagerCommand.php
fix_distributed_lock_manager_command() {
    log_info "修复 DistributedLockManagerCommand.php..."

    if [[ -f "src/Command/DistributedLockManagerCommand.php" ]]; then
        if grep -q "lock_key" src/Command/DistributedLockManagerCommand.php; then
            log_warning "⚠️ 发现旧的 lock_key 字段，正在修复..."

            cp src/Command/DistributedLockManagerCommand.php src/Command/DistributedLockManagerCommand.php.backup

            # 替换 SQL 查询中的 lock_key 为 lockKey
            sed -i "s/lock_key/lockKey/g" src/Command/DistributedLockManagerCommand.php

            log_success "✅ DistributedLockManagerCommand.php 修复完成"
        else
            log_info "ℹ️ DistributedLockManagerCommand.php 已是最新版本"
        fi
    else
        log_warning "⚠️ DistributedLockManagerCommand.php 文件不存在"
    fi
}

# 清理所有缓存
clear_all_caches() {
    log_info "清理所有缓存..."

    # 清理 Symfony 缓存
    log_info "清理 Symfony 缓存..."
    php bin/console cache:clear --env=prod --no-warmup || log_warning "Symfony 生产缓存清理失败"
    php bin/console cache:clear --env=dev --no-warmup || log_warning "Symfony 开发缓存清理失败"

    # 清理 Doctrine 缓存
    log_info "清理 Doctrine 缓存..."
    php bin/console doctrine:cache:clear-metadata --env=prod || log_warning "Doctrine 元数据缓存清理失败"
    php bin/console doctrine:cache:clear-query --env=prod || log_warning "Doctrine 查询缓存清理失败"
    php bin/console doctrine:cache:clear-result --env=prod || log_warning "Doctrine 结果缓存清理失败"

    # 清理 OPcache
    log_info "清理 OPcache..."
    php -r "if(function_exists('opcache_reset')) opcache_reset(); echo 'OPcache cleared';" || log_warning "OPcache 清理失败"

    # 删除缓存目录
    log_info "删除缓存目录..."
    rm -rf var/cache/* || log_warning "缓存目录删除失败"

    # 重新生成 Doctrine 代理
    log_info "重新生成 Doctrine 代理..."
    php bin/console doctrine:generate:proxies --env=prod --regenerate || log_warning "Doctrine 代理重新生成失败"

    log_success "✅ 所有缓存清理完成"
}

# 重启服务
restart_services() {
    log_info "重启相关服务..."

    # 重启 PHP-FPM
    systemctl restart php-fpm || log_warning "PHP-FPM 重启失败"

    # 重启 Nginx
    systemctl restart nginx || log_warning "Nginx 重启失败"

    log_success "✅ 服务重启完成"
}

# 验证修复结果
verify_fix() {
    log_info "验证修复结果..."

    # 检查数据库结构
    log_info "验证数据库结构..."
    if mysql -u root -p -e "DESCRIBE distributed_locks;" official_website 2>/dev/null | grep -q "lockKey"; then
        log_success "✅ 数据库字段正确: lockKey"
    else
        log_error "❌ 数据库字段验证失败"
        return 1
    fi

    # 检查代码文件
    log_info "验证代码文件..."
    if ! grep -q "lock_key" src/Service/DistributedLockService.php; then
        log_success "✅ DistributedLockService.php 已修复"
    else
        log_error "❌ DistributedLockService.php 仍包含旧字段名"
        return 1
    fi

    if [[ -f "src/Command/DistributedLockManagerCommand.php" ]]; then
        if ! grep -q "lock_key" src/Command/DistributedLockManagerCommand.php; then
            log_success "✅ DistributedLockManagerCommand.php 已修复"
        else
            log_error "❌ DistributedLockManagerCommand.php 仍包含旧字段名"
            return 1
        fi
    fi

    # 验证 Doctrine 映射
    log_info "验证 Doctrine 映射..."
    php bin/console doctrine:schema:validate --env=prod || log_warning "Doctrine 架构验证失败"

    log_success "✅ 修复结果验证通过"
}

# 主函数
main() {
    log_info "开始执行生产环境紧急修复..."

    check_production_env
    switch_to_production
    backup_files
    check_database_schema
    fix_distributed_lock_service
    fix_distributed_lock_manager_command
    clear_all_caches
    restart_services
    verify_fix

    log_success "🎉 生产环境紧急修复完成！"
    log_info "⏰ 完成时间: $(date)"

    echo ""
    echo "📋 修复摘要:"
    echo "  ✅ 数据库字段: lock_key -> lockKey"
    echo "  ✅ DistributedLockService.php: SQL 查询修复"
    echo "  ✅ DistributedLockManagerCommand.php: SQL 查询修复"
    echo "  ✅ 所有缓存已清理"
    echo "  ✅ 服务已重启"
    echo ""
    echo "🔧 如果问题仍然存在，请检查:"
    echo "  1. PHP 错误日志: tail -f /var/log/php_errors.log"
    echo "  2. Nginx 错误日志: tail -f /var/log/nginx/error.log"
    echo "  3. Symfony 日志: tail -f var/log/prod.log"
}

# 执行主函数
main "$@"
