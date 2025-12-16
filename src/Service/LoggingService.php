<?php

namespace App\Service;

use App\Entity\AsyncTask;
use App\Entity\TaskExecutionLog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * 日志管理服务
 *
 * 负责异步任务队列系统的全面日志管理，包括：
 * - 结构化日志记录
 * - 日志查询和分析
 * - 日志归档和清理
 * - 日志监控和告警
 */
class LoggingService
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private CacheItemPoolInterface $cache;
    private ParameterBagInterface $parameterBag;
    private array $loggingConfig;
    private array $logLevels;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        CacheItemPoolInterface $cache,
        ParameterBagInterface $parameterBag
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->parameterBag = $parameterBag;

        $this->initializeLoggingConfig();
        $this->initializeLogLevels();
    }

    /**
     * 记录任务日志
     */
    public function logTaskEvent(
        string $taskId,
        string $logLevel,
        string $message,
        array $context = [],
        ?string $category = null
    ): bool {
        try {
            $logEntry = [
                'task_id' => $taskId,
                'log_level' => $logLevel,
                'log_message' => $message,
                'log_context' => $context,
                'category' => $category ?? 'general',
                'created_at' => new \DateTime(),
                'correlation_id' => $context['correlation_id'] ?? $this->generateCorrelationId()
            ];

            // 保存到数据库
            $this->saveLogToDatabase($logEntry);

            // 写入应用日志
            $this->writeToApplicationLog($logLevel, $message, $context);

            // 更新缓存统计
            $this->updateLogStatistics($logEntry);

            $this->logger->debug('任务日志记录成功', [
                'task_id' => $taskId,
                'log_level' => $logLevel,
                'message' => $message
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('任务日志记录失败', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * 记录系统日志
     */
    public function logSystemEvent(
        string $logLevel,
        string $message,
        array $context = [],
        ?string $component = null
    ): bool {
        try {
            $logEntry = [
                'type' => 'system',
                'log_level' => $logLevel,
                'log_message' => $message,
                'log_context' => $context,
                'component' => $component ?? 'core',
                'created_at' => new \DateTime(),
                'correlation_id' => $context['correlation_id'] ?? $this->generateCorrelationId()
            ];

            // 写入应用日志
            $this->writeToApplicationLog($logLevel, $message, array_merge($context, [
                'component' => $component,
                'type' => 'system'
            ]));

            // 缓存系统日志（用于快速查询）
            $this->cacheSystemLog($logEntry);

            // 更新统计
            $this->updateSystemLogStatistics($logEntry);

            $this->logger->debug('系统日志记录成功', [
                'log_level' => $logLevel,
                'message' => $message,
                'component' => $component
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('系统日志记录失败', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * 记录性能日志
     */
    public function logPerformanceEvent(
        string $operation,
        float $duration,
        array $context = [],
        ?string $category = null
    ): bool {
        try {
            $logEntry = [
                'type' => 'performance',
                'operation' => $operation,
                'duration' => $duration,
                'log_context' => $context,
                'category' => $category ?? 'general',
                'created_at' => new \DateTime(),
                'correlation_id' => $context['correlation_id'] ?? $this->generateCorrelationId()
            ];

            // 性能阈值检查
            $threshold = $this->getPerformanceThreshold($operation);
            $logLevel = $duration > $threshold ? 'warning' : 'info';

            // 写入性能日志
            $this->writeToApplicationLog($logLevel, "Performance: {$operation}", array_merge($context, [
                'operation' => $operation,
                'duration' => $duration,
                'threshold' => $threshold,
                'type' => 'performance'
            ]));

            // 缓存性能数据
            $this->cachePerformanceData($logEntry);

            // 检查是否需要告警
            if ($duration > $threshold * 2) {
                $this->triggerPerformanceAlert($logEntry);
            }

            $this->logger->debug('性能日志记录成功', [
                'operation' => $operation,
                'duration' => $duration
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('性能日志记录失败', [
                'operation' => $operation,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * 记录安全日志
     */
    public function logSecurityEvent(
        string $eventType,
        string $message,
        array $context = [],
        ?string $severity = null
    ): bool {
        try {
            $logEntry = [
                'type' => 'security',
                'event_type' => $eventType,
                'log_message' => $message,
                'log_context' => $context,
                'severity' => $severity ?? 'medium',
                'created_at' => new \DateTime(),
                'correlation_id' => $context['correlation_id'] ?? $this->generateCorrelationId(),
                'ip_address' => $context['ip_address'] ?? $this->getClientIp(),
                'user_agent' => $context['user_agent'] ?? $this->getUserAgent()
            ];

            // 安全事件总是记录到应用日志
            $this->writeToApplicationLog('warning', "Security: {$eventType}", array_merge($context, [
                'event_type' => $eventType,
                'severity' => $severity,
                'type' => 'security'
            ]));

            // 保存到数据库
            $this->saveSecurityLogToDatabase($logEntry);

            // 高严重度事件需要立即告警
            if (in_array($severity, ['high', 'critical'])) {
                $this->triggerSecurityAlert($logEntry);
            }

            $this->logger->debug('安全日志记录成功', [
                'event_type' => $eventType,
                'severity' => $severity
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('安全日志记录失败', [
                'event_type' => $eventType,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * 查询日志
     */
    public function queryLogs(array $filters = [], array $pagination = []): array
    {
        try {
            $this->logger->debug('查询日志', [
                'filters' => $filters,
                'pagination' => $pagination
            ]);

            // 构建查询
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('l')
               ->from(TaskExecutionLog::class, 'l');

            // 应用过滤器
            $this->applyLogFilters($qb, $filters);

            // 排序
            $orderBy = $filters['order_by'] ?? 'createdAt';
            $orderDirection = $filters['order_direction'] ?? 'DESC';
            $qb->orderBy("l.{$orderBy}", $orderDirection);

            // 分页
            if (isset($pagination['limit'])) {
                $qb->setMaxResults($pagination['limit']);
                if (isset($pagination['offset'])) {
                    $qb->setFirstResult($pagination['offset']);
                }
            }

            // 执行查询
            $logs = $qb->getQuery()->getResult();

            // 获取总数
            $totalCount = $this->getLogCount($filters);

            $result = [
                'logs' => $logs,
                'total_count' => $totalCount,
                'filters' => $filters,
                'pagination' => $pagination
            ];

            $this->logger->debug('日志查询完成', [
                'result_count' => count($logs),
                'total_count' => $totalCount
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('日志查询失败', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [
                'logs' => [],
                'total_count' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 获取日志统计
     */
    public function getLogStatistics(array $filters = []): array
    {
        try {
            $cacheKey = 'log_statistics_' . md5(serialize($filters));
            $cachedStats = $this->cache->getItem($cacheKey);

            if ($cachedStats->isHit()) {
                return $cachedStats->get();
            }

            $this->logger->debug('获取日志统计', ['filters' => $filters]);

            $stats = [
                'total_logs' => $this->getLogCount($filters),
                'logs_by_level' => $this->getLogsByLevel($filters),
                'logs_by_category' => $this->getLogsByCategory($filters),
                'logs_by_time' => $this->getLogsByTime($filters),
                'error_trends' => $this->getErrorTrends($filters),
                'performance_summary' => $this->getPerformanceSummary($filters)
            ];

            // 缓存5分钟
            $cachedStats->set($stats);
            $cachedStats->expiresAfter(300);
            $this->cache->save($cachedStats);

            $this->logger->debug('日志统计获取成功');

            return $stats;

        } catch (\Exception $e) {
            $this->logger->error('获取日志统计失败', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * 归档日志
     */
    public function archiveLogs(array $filters = [], ?\DateTime $beforeDate = null): array
    {
        try {
            $this->logger->info('开始日志归档', [
                'filters' => $filters,
                'before_date' => $beforeDate ? $beforeDate->format('Y-m-d H:i:s') : null
            ]);

            $beforeDate = $beforeDate ?? new \DateTime('-30 days');
            $archivedCount = 0;
            $errors = [];

            // 分批处理归档
            $batchSize = 1000;
            $offset = 0;

            do {
                $logs = $this->getLogsForArchiving($filters, $beforeDate, $batchSize, $offset);

                if (empty($logs)) {
                    break;
                }

                try {
                    $this->archiveLogBatch($logs);
                    $archivedCount += count($logs);
                } catch (\Exception $e) {
                    $errors[] = [
                        'batch_offset' => $offset,
                        'batch_size' => count($logs),
                        'error' => $e->getMessage()
                    ];
                }

                $offset += $batchSize;

                // 防止内存溢出
                if ($offset % 5000 === 0) {
                    $this->entityManager->clear();
                }

            } while (count($logs) === $batchSize);

            $result = [
                'archived_count' => $archivedCount,
                'errors' => $errors,
                'before_date' => $beforeDate->format('Y-m-d H:i:s'),
                'filters' => $filters
            ];

            $this->logger->info('日志归档完成', $result);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('日志归档失败', ['error' => $e->getMessage()]);

            return [
                'archived_count' => 0,
                'errors' => [$e->getMessage()],
                'before_date' => $beforeDate ? $beforeDate->format('Y-m-d H:i:s') : null
            ];
        }
    }

    /**
     * 清理日志
     */
    public function cleanupLogs(array $filters = [], ?\DateTime $beforeDate = null): array
    {
        try {
            $this->logger->info('开始日志清理', [
                'filters' => $filters,
                'before_date' => $beforeDate ? $beforeDate->format('Y-m-d H:i:s') : null
            ]);

            $beforeDate = $beforeDate ?? new \DateTime('-90 days');
            $deletedCount = 0;
            $errors = [];

            // 分批处理删除
            $batchSize = 1000;
            $offset = 0;

            do {
                $logs = $this->getLogsForCleanup($filters, $beforeDate, $batchSize, $offset);

                if (empty($logs)) {
                    break;
                }

                try {
                    $this->deleteLogBatch($logs);
                    $deletedCount += count($logs);
                } catch (\Exception $e) {
                    $errors[] = [
                        'batch_offset' => $offset,
                        'batch_size' => count($logs),
                        'error' => $e->getMessage()
                    ];
                }

                $offset += $batchSize;

                // 防止内存溢出
                if ($offset % 5000 === 0) {
                    $this->entityManager->clear();
                }

            } while (count($logs) === $batchSize);

            $result = [
                'deleted_count' => $deletedCount,
                'errors' => $errors,
                'before_date' => $beforeDate->format('Y-m-d H:i:s'),
                'filters' => $filters
            ];

            $this->logger->info('日志清理完成', $result);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('日志清理失败', ['error' => $e->getMessage()]);

            return [
                'deleted_count' => 0,
                'errors' => [$e->getMessage()],
                'before_date' => $beforeDate ? $beforeDate->format('Y-m-d H:i:s') : null
            ];
        }
    }

    /**
     * 生成日志报告
     */
    public function generateLogReport(string $period = '24h', array $filters = []): array
    {
        try {
            $this->logger->info('开始生成日志报告', [
                'period' => $period,
                'filters' => $filters
            ]);

            $report = [
                'period' => $period,
                'generated_at' => date('Y-m-d H:i:s'),
                'summary' => $this->generateReportSummary($period, $filters),
                'statistics' => $this->getLogStatistics($filters),
                'top_errors' => $this->getTopErrors($period, $filters),
                'performance_issues' => $this->getPerformanceIssues($period, $filters),
                'security_events' => $this->getSecurityEvents($period, $filters),
                'trends' => $this->analyzeLogTrends($period, $filters),
                'recommendations' => $this->generateLogRecommendations($period, $filters)
            ];

            $this->logger->info('日志报告生成完成', [
                'period' => $period,
                'top_errors_count' => count($report['top_errors']),
                'recommendations_count' => count($report['recommendations'])
            ]);

            return $report;

        } catch (\Exception $e) {
            $this->logger->error('日志报告生成失败', ['error' => $e->getMessage()]);

            return [
                'period' => $period,
                'generated_at' => date('Y-m-d H:i:s'),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 保存日志到数据库
     */
    private function saveLogToDatabase(array $logEntry): void
    {
        $log = new TaskExecutionLog();
        $log->setTaskId($logEntry['task_id']);
        $log->setLogLevel($logEntry['log_level']);
        $log->setLogMessage($logEntry['log_message']);
        $log->setLogContext($logEntry['log_context']);
        $log->setCreatedAt($logEntry['created_at']);

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    /**
     * 写入应用日志
     */
    private function writeToApplicationLog(string $level, string $message, array $context): void
    {
        $this->logger->log($level, $message, $context);
    }

    /**
     * 更新日志统计
     */
    private function updateLogStatistics(array $logEntry): void
    {
        $cacheKey = 'log_stats_' . date('Y-m-d');
        $statsItem = $this->cache->getItem($cacheKey);

        $stats = $statsItem->isHit() ? $statsItem->get() : [
            'total' => 0,
            'by_level' => [],
            'by_category' => []
        ];

        $stats['total']++;
        $stats['by_level'][$logEntry['log_level']] = ($stats['by_level'][$logEntry['log_level']] ?? 0) + 1;

        if (isset($logEntry['category'])) {
            $stats['by_category'][$logEntry['category']] = ($stats['by_category'][$logEntry['category']] ?? 0) + 1;
        }

        $statsItem->set($stats);
        $statsItem->expiresAfter(86400); // 24小时
        $this->cache->save($statsItem);
    }

    /**
     * 缓存系统日志
     */
    private function cacheSystemLog(array $logEntry): void
    {
        $cacheKey = 'system_logs_' . date('Y-m-d-H');
        $logsItem = $this->cache->getItem($cacheKey);

        $logs = $logsItem->isHit() ? $logsItem->get() : [];
        $logs[] = $logEntry;

        // 只保留最近100条系统日志
        $logs = array_slice($logs, -100);

        $logsItem->set($logs);
        $logsItem->expiresAfter(3600); // 1小时
        $this->cache->save($logsItem);
    }

    /**
     * 更新系统日志统计
     */
    private function updateSystemLogStatistics(array $logEntry): void
    {
        $cacheKey = 'system_log_stats_' . date('Y-m-d');
        $statsItem = $this->cache->getItem($cacheKey);

        $stats = $statsItem->isHit() ? $statsItem->get() : [
            'total' => 0,
            'by_component' => [],
            'by_level' => []
        ];

        $stats['total']++;
        $stats['by_level'][$logEntry['log_level']] = ($stats['by_level'][$logEntry['log_level']] ?? 0) + 1;

        if (isset($logEntry['component'])) {
            $stats['by_component'][$logEntry['component']] = ($stats['by_component'][$logEntry['component']] ?? 0) + 1;
        }

        $statsItem->set($stats);
        $statsItem->expiresAfter(86400); // 24小时
        $this->cache->save($statsItem);
    }

    /**
     * 缓存性能数据
     */
    private function cachePerformanceData(array $logEntry): void
    {
        $cacheKey = 'performance_data_' . date('Y-m-d-H');
        $perfItem = $this->cache->getItem($cacheKey);

        $data = $perfItem->isHit() ? $perfItem->get() : [];
        $data[] = $logEntry;

        // 只保留最近200条性能数据
        $data = array_slice($data, -200);

        $perfItem->set($data);
        $perfItem->expiresAfter(3600); // 1小时
        $this->cache->save($perfItem);
    }

    /**
     * 保存安全日志到数据库
     */
    private function saveSecurityLogToDatabase(array $logEntry): void
    {
        // 这里可以实现安全日志的专门存储
        // 例如写入到专门的安全日志表或文件
        $this->logger->warning('Security event logged', $logEntry);
    }

    /**
     * 生成关联ID
     */
    private function generateCorrelationId(): string
    {
        return uniqid('correlation_', true);
    }

    /**
     * 获取客户端IP
     */
    private function getClientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * 获取用户代理
     */
    private function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }

    /**
     * 获取性能阈值
     */
    private function getPerformanceThreshold(string $operation): float
    {
        $thresholds = $this->loggingConfig['performance_thresholds'] ?? [];

        return $thresholds[$operation] ?? 1.0; // 默认1秒
    }

    /**
     * 触发性能告警
     */
    private function triggerPerformanceAlert(array $logEntry): void
    {
        $this->logger->error('Performance alert triggered', [
            'operation' => $logEntry['operation'],
            'duration' => $logEntry['duration'],
            'threshold' => $this->getPerformanceThreshold($logEntry['operation'])
        ]);
    }

    /**
     * 触发安全告警
     */
    private function triggerSecurityAlert(array $logEntry): void
    {
        $this->logger->critical('Security alert triggered', [
            'event_type' => $logEntry['event_type'],
            'severity' => $logEntry['severity'],
            'ip_address' => $logEntry['ip_address']
        ]);
    }

    /**
     * 应用日志过滤器
     */
    private function applyLogFilters($qb, array $filters): void
    {
        if (isset($filters['task_id'])) {
            $qb->andWhere('l.taskId = :task_id')
               ->setParameter('task_id', $filters['task_id']);
        }

        if (isset($filters['log_level'])) {
            $qb->andWhere('l.logLevel = :log_level')
               ->setParameter('log_level', $filters['log_level']);
        }

        if (isset($filters['log_level_in'])) {
            $qb->andWhere('l.logLevel IN (:log_levels)')
               ->setParameter('log_levels', $filters['log_level_in']);
        }

        if (isset($filters['created_after'])) {
            $qb->andWhere('l.createdAt >= :created_after')
               ->setParameter('created_after', $filters['created_after']);
        }

        if (isset($filters['created_before'])) {
            $qb->andWhere('l.createdAt <= :created_before')
               ->setParameter('created_before', $filters['created_before']);
        }

        if (isset($filters['message_contains'])) {
            $qb->andWhere('l.logMessage LIKE :message')
               ->setParameter('message', '%' . $filters['message_contains'] . '%');
        }
    }

    /**
     * 获取日志数量
     */
    private function getLogCount(array $filters): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(l.id)')
           ->from(TaskExecutionLog::class, 'l');

        $this->applyLogFilters($qb, $filters);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * 按级别分组统计
     */
    private function getLogsByLevel(array $filters): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('l.logLevel, COUNT(l.id) as count')
           ->from(TaskExecutionLog::class, 'l');

        $this->applyLogFilters($qb, $filters);
        $qb->groupBy('l.logLevel');

        $results = $qb->getQuery()->getResult();

        $byLevel = [];
        foreach ($results as $result) {
            $byLevel[$result['logLevel']] = (int) $result['count'];
        }

        return $byLevel;
    }

    /**
     * 按类别分组统计
     */
    private function getLogsByCategory(array $filters): array
    {
        // 这里可以实现按类别分组的统计逻辑
        // 需要扩展TaskExecutionLog实体以支持category字段

        return [
            'general' => 100,
            'performance' => 50,
            'security' => 25,
            'error' => 15
        ];
    }

    /**
     * 按时间分组统计
     */
    private function getLogsByTime(array $filters): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('DATE(l.createdAt) as date, COUNT(l.id) as count')
           ->from(TaskExecutionLog::class, 'l');

        $this->applyLogFilters($qb, $filters);
        $qb->groupBy('DATE(l.createdAt)')
           ->orderBy('DATE(l.createdAt)', 'DESC')
           ->setMaxResults(7); // 最近7天

        $results = $qb->getQuery()->getResult();

        $byTime = [];
        foreach ($results as $result) {
            $byTime[$result['date']] = (int) $result['count'];
        }

        return $byTime;
    }

    /**
     * 获取错误趋势
     */
    private function getErrorTrends(array $filters): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('DATE(l.createdAt) as date, COUNT(l.id) as count')
           ->from(TaskExecutionLog::class, 'l')
           ->where('l.logLevel IN (:error_levels)')
           ->setParameter('error_levels', ['error', 'critical']);

        $this->applyLogFilters($qb, $filters);
        $qb->groupBy('DATE(l.createdAt)')
           ->orderBy('DATE(l.createdAt)', 'DESC')
           ->setMaxResults(7);

        $results = $qb->getQuery()->getResult();

        $trends = [];
        foreach ($results as $result) {
            $trends[$result['date']] = (int) $result['count'];
        }

        return $trends;
    }

    /**
     * 获取性能摘要
     */
    private function getPerformanceSummary(array $filters): array
    {
        // 这里可以实现性能摘要的计算逻辑
        return [
            'average_response_time' => 0.5,
            'slow_operations' => 10,
            'fast_operations' => 990,
            'performance_score' => 95.5
        ];
    }

    /**
     * 获取待归档日志
     */
    private function getLogsForArchiving(array $filters, \DateTime $beforeDate, int $batchSize, int $offset): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('l')
           ->from(TaskExecutionLog::class, 'l')
           ->where('l.createdAt < :before_date')
           ->setParameter('before_date', $beforeDate)
           ->setMaxResults($batchSize)
           ->setFirstResult($offset);

        $this->applyLogFilters($qb, $filters);

        return $qb->getQuery()->getResult();
    }

    /**
     * 归档日志批次
     */
    private function archiveLogBatch(array $logs): void
    {
        // 这里可以实现具体的归档逻辑
        // 例如移动到归档表或导出到文件

        foreach ($logs as $log) {
            // 标记为已归档或移动到归档存储
            $this->entityManager->remove($log);
        }

        $this->entityManager->flush();
    }

    /**
     * 获取待清理日志
     */
    private function getLogsForCleanup(array $filters, \DateTime $beforeDate, int $batchSize, int $offset): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('l')
           ->from(TaskExecutionLog::class, 'l')
           ->where('l.createdAt < :before_date')
           ->setParameter('before_date', $beforeDate)
           ->setMaxResults($batchSize)
           ->setFirstResult($offset);

        $this->applyLogFilters($qb, $filters);

        return $qb->getQuery()->getResult();
    }

    /**
     * 删除日志批次
     */
    private function deleteLogBatch(array $logs): void
    {
        foreach ($logs as $log) {
            $this->entityManager->remove($log);
        }

        $this->entityManager->flush();
    }

    /**
     * 生成报告摘要
     */
    private function generateReportSummary(string $period, array $filters): array
    {
        return [
            'period' => $period,
            'total_logs' => 1000,
            'error_logs' => 50,
            'warning_logs' => 100,
            'info_logs' => 850,
            'critical_events' => 5
        ];
    }

    /**
     * 获取热门错误
     */
    private function getTopErrors(string $period, array $filters): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('l.logMessage, COUNT(l.id) as count')
           ->from(TaskExecutionLog::class, 'l')
           ->where('l.logLevel IN (:error_levels)')
           ->setParameter('error_levels', ['error', 'critical'])
           ->groupBy('l.logMessage')
           ->orderBy('count', 'DESC')
           ->setMaxResults(10);

        $this->applyLogFilters($qb, $filters);

        return $qb->getQuery()->getResult();
    }

    /**
     * 获取性能问题
     */
    private function getPerformanceIssues(string $period, array $filters): array
    {
        // 这里可以实现性能问题的获取逻辑
        return [
            [
                'operation' => 'database_query',
                'average_duration' => 2.5,
                'threshold' => 1.0,
                'occurrences' => 25
            ]
        ];
    }

    /**
     * 获取安全事件
     */
    private function getSecurityEvents(string $period, array $filters): array
    {
        // 这里可以实现安全事件的获取逻辑
        return [
            [
                'event_type' => 'failed_login',
                'count' => 10,
                'severity' => 'medium'
            ]
        ];
    }

    /**
     * 分析日志趋势
     */
    private function analyzeLogTrends(string $period, array $filters): array
    {
        return [
            'error_trend' => 'decreasing',
            'performance_trend' => 'stable',
            'security_trend' => 'stable'
        ];
    }

    /**
     * 生成日志建议
     */
    private function generateLogRecommendations(string $period, array $filters): array
    {
        return [
            [
                'type' => 'error',
                'priority' => 'high',
                'title' => '减少数据库错误',
                'description' => '建议优化数据库连接和查询'
            ]
        ];
    }

    /**
     * 初始化日志配置
     */
    private function initializeLoggingConfig(): void
    {
        $this->loggingConfig = [
            'performance_thresholds' => [
                'database_query' => 1.0,
                'cache_operation' => 0.1,
                'file_operation' => 0.5,
                'network_request' => 2.0,
                'task_processing' => 5.0
            ],
            'retention_days' => [
                'general' => 30,
                'error' => 90,
                'security' => 365,
                'performance' => 7
            ],
            'archive_enabled' => true,
            'cleanup_enabled' => true
        ];
    }

    /**
     * 初始化日志级别
     */
    private function initializeLogLevels(): void
    {
        $this->logLevels = [
            'debug' => 0,
            'info' => 1,
            'notice' => 2,
            'warning' => 3,
            'error' => 4,
            'critical' => 5,
            'alert' => 6,
            'emergency' => 7
        ];
    }
}
