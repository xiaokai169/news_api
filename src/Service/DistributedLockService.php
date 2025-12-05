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

