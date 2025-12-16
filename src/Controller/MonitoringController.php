<?php

namespace App\Controller;

use App\Service\MonitoringService;
use App\Service\LoggingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

/**
 * 监控控制器
 *
 * 提供系统监控和日志管理的API接口，包括：
 * - 系统健康监控
 * - 性能指标查询
 * - 日志查询和分析
 * - 监控报告生成
 */
#[Route('/api/monitoring')]
class MonitoringController extends AbstractController
{
    private MonitoringService $monitoringService;
    private LoggingService $loggingService;
    private LoggerInterface $logger;

    public function __construct(
        MonitoringService $monitoringService,
        LoggingService $loggingService,
        LoggerInterface $logger
    ) {
        $this->monitoringService = $monitoringService;
        $this->loggingService = $loggingService;
        $this->logger = $logger;
    }

    /**
     * 获取系统健康状态
     */
    #[Route('/health', name: 'monitoring_health', methods: ['GET'])]
    public function getSystemHealth(): JsonResponse
    {
        try {
            $health = $this->monitoringService->getSystemHealth();

            $this->logger->debug('系统健康状态查询成功', [
                'status' => $health['status']
            ]);

            return $this->json([
                'success' => true,
                'data' => $health,
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('系统健康状态查询失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve system health',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取性能指标
     */
    #[Route('/metrics', name: 'monitoring_metrics', methods: ['GET'])]
    public function getPerformanceMetrics(Request $request): JsonResponse
    {
        try {
            $filters = $this->extractMetricFilters($request);
            $metrics = $this->monitoringService->getPerformanceMetrics($filters);

            $this->logger->debug('性能指标查询成功', [
                'filters' => $filters
            ]);

            return $this->json([
                'success' => true,
                'data' => $metrics,
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('性能指标查询失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve performance metrics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 检查异常情况
     */
    #[Route('/anomalies', name: 'monitoring_anomalies', methods: ['GET'])]
    public function checkAnomalies(): JsonResponse
    {
        try {
            $anomalies = $this->monitoringService->checkAnomalies();

            $this->logger->debug('异常检测完成', [
                'anomalies_count' => count($anomalies)
            ]);

            return $this->json([
                'success' => true,
                'data' => [
                    'anomalies' => $anomalies,
                    'total_count' => count($anomalies),
                    'critical_count' => count(array_filter($anomalies, fn($a) => $a['severity'] === 'critical')),
                    'high_count' => count(array_filter($anomalies, fn($a) => $a['severity'] === 'high'))
                ],
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('异常检测失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to check anomalies',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 生成监控报告
     */
    #[Route('/report', name: 'monitoring_report', methods: ['GET'])]
    public function generateMonitoringReport(Request $request): JsonResponse
    {
        try {
            $period = $request->query->get('period', '24h');
            $report = $this->monitoringService->generateMonitoringReport($period);

            $this->logger->info('监控报告生成成功', [
                'period' => $period
            ]);

            return $this->json([
                'success' => true,
                'data' => $report,
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('监控报告生成失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to generate monitoring report',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 发送告警
     */
    #[Route('/alert', name: 'monitoring_alert', methods: ['POST'])]
    public function sendAlert(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $requiredFields = ['type', 'severity', 'message'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    return $this->json([
                        'success' => false,
                        'error' => "Missing required field: {$field}"
                    ], 400);
                }
            }

            $success = $this->monitoringService->sendAlert($data);

            $this->logger->info('告警发送完成', [
                'type' => $data['type'],
                'severity' => $data['severity'],
                'success' => $success
            ]);

            return $this->json([
                'success' => $success,
                'message' => $success ? 'Alert sent successfully' : 'Failed to send alert',
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('告警发送失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to send alert',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取监控统计
     */
    #[Route('/stats', name: 'monitoring_stats', methods: ['GET'])]
    public function getMonitoringStats(): JsonResponse
    {
        try {
            $stats = $this->monitoringService->getMonitoringStats();

            $this->logger->debug('监控统计查询成功');

            return $this->json([
                'success' => true,
                'data' => $stats,
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('监控统计查询失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve monitoring statistics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 配置监控规则
     */
    #[Route('/rules', name: 'monitoring_rules', methods: ['POST'])]
    public function configureMonitoringRules(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $rules = $data['rules'] ?? [];

            $result = $this->monitoringService->configureMonitoringRules($rules);

            $this->logger->info('监控规则配置完成', [
                'configured_count' => $result['success_count'],
                'total_rules' => $result['total_rules']
            ]);

            return $this->json([
                'success' => true,
                'data' => $result,
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('监控规则配置失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to configure monitoring rules',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 查询日志
     */
    #[Route('/logs', name: 'monitoring_logs', methods: ['GET'])]
    public function queryLogs(Request $request): JsonResponse
    {
        try {
            $filters = $this->extractLogFilters($request);
            $pagination = $this->extractPagination($request);

            $result = $this->loggingService->queryLogs($filters, $pagination);

            $this->logger->debug('日志查询成功', [
                'result_count' => count($result['logs']),
                'total_count' => $result['total_count']
            ]);

            return $this->json([
                'success' => true,
                'data' => $result,
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('日志查询失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to query logs',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取日志统计
     */
    #[Route('/logs/stats', name: 'monitoring_logs_stats', methods: ['GET'])]
    public function getLogStatistics(Request $request): JsonResponse
    {
        try {
            $filters = $this->extractLogFilters($request);
            $stats = $this->loggingService->getLogStatistics($filters);

            $this->logger->debug('日志统计查询成功');

            return $this->json([
                'success' => true,
                'data' => $stats,
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('日志统计查询失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve log statistics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 归档日志
     */
    #[Route('/logs/archive', name: 'monitoring_logs_archive', methods: ['POST'])]
    public function archiveLogs(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $filters = $data['filters'] ?? [];
            $beforeDate = isset($data['before_date'])
                ? new \DateTime($data['before_date'])
                : null;

            $result = $this->loggingService->archiveLogs($filters, $beforeDate);

            $this->logger->info('日志归档完成', [
                'archived_count' => $result['archived_count'],
                'errors_count' => count($result['errors'])
            ]);

            return $this->json([
                'success' => true,
                'data' => $result,
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('日志归档失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to archive logs',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 清理日志
     */
    #[Route('/logs/cleanup', name: 'monitoring_logs_cleanup', methods: ['POST'])]
    public function cleanupLogs(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $filters = $data['filters'] ?? [];
            $beforeDate = isset($data['before_date'])
                ? new \DateTime($data['before_date'])
                : null;

            $result = $this->loggingService->cleanupLogs($filters, $beforeDate);

            $this->logger->info('日志清理完成', [
                'deleted_count' => $result['deleted_count'],
                'errors_count' => count($result['errors'])
            ]);

            return $this->json([
                'success' => true,
                'data' => $result,
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('日志清理失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to cleanup logs',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 生成日志报告
     */
    #[Route('/logs/report', name: 'monitoring_logs_report', methods: ['GET'])]
    public function generateLogReport(Request $request): JsonResponse
    {
        try {
            $period = $request->query->get('period', '24h');
            $filters = $this->extractLogFilters($request);

            $report = $this->loggingService->generateLogReport($period, $filters);

            $this->logger->info('日志报告生成成功', [
                'period' => $period
            ]);

            return $this->json([
                'success' => true,
                'data' => $report,
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('日志报告生成失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to generate log report',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 记录任务日志
     */
    #[Route('/logs/task', name: 'monitoring_logs_task', methods: ['POST'])]
    public function logTaskEvent(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $requiredFields = ['task_id', 'log_level', 'message'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    return $this->json([
                        'success' => false,
                        'error' => "Missing required field: {$field}"
                    ], 400);
                }
            }

            $success = $this->loggingService->logTaskEvent(
                $data['task_id'],
                $data['log_level'],
                $data['message'],
                $data['context'] ?? [],
                $data['category'] ?? null
            );

            $this->logger->debug('任务日志记录完成', [
                'task_id' => $data['task_id'],
                'log_level' => $data['log_level'],
                'success' => $success
            ]);

            return $this->json([
                'success' => $success,
                'message' => $success ? 'Task log recorded successfully' : 'Failed to record task log',
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('任务日志记录失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to log task event',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 记录系统日志
     */
    #[Route('/logs/system', name: 'monitoring_logs_system', methods: ['POST'])]
    public function logSystemEvent(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $requiredFields = ['log_level', 'message'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    return $this->json([
                        'success' => false,
                        'error' => "Missing required field: {$field}"
                    ], 400);
                }
            }

            $success = $this->loggingService->logSystemEvent(
                $data['log_level'],
                $data['message'],
                $data['context'] ?? [],
                $data['component'] ?? null
            );

            $this->logger->debug('系统日志记录完成', [
                'log_level' => $data['log_level'],
                'component' => $data['component'] ?? null,
                'success' => $success
            ]);

            return $this->json([
                'success' => $success,
                'message' => $success ? 'System log recorded successfully' : 'Failed to record system log',
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('系统日志记录失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to log system event',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 记录性能日志
     */
    #[Route('/logs/performance', name: 'monitoring_logs_performance', methods: ['POST'])]
    public function logPerformanceEvent(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $requiredFields = ['operation', 'duration'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    return $this->json([
                        'success' => false,
                        'error' => "Missing required field: {$field}"
                    ], 400);
                }
            }

            $success = $this->loggingService->logPerformanceEvent(
                $data['operation'],
                $data['duration'],
                $data['context'] ?? [],
                $data['category'] ?? null
            );

            $this->logger->debug('性能日志记录完成', [
                'operation' => $data['operation'],
                'duration' => $data['duration'],
                'success' => $success
            ]);

            return $this->json([
                'success' => $success,
                'message' => $success ? 'Performance log recorded successfully' : 'Failed to record performance log',
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('性能日志记录失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to log performance event',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 记录安全日志
     */
    #[Route('/logs/security', name: 'monitoring_logs_security', methods: ['POST'])]
    public function logSecurityEvent(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $requiredFields = ['event_type', 'message'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    return $this->json([
                        'success' => false,
                        'error' => "Missing required field: {$field}"
                    ], 400);
                }
            }

            $success = $this->loggingService->logSecurityEvent(
                $data['event_type'],
                $data['message'],
                $data['context'] ?? [],
                $data['severity'] ?? null
            );

            $this->logger->debug('安全日志记录完成', [
                'event_type' => $data['event_type'],
                'severity' => $data['severity'] ?? null,
                'success' => $success
            ]);

            return $this->json([
                'success' => $success,
                'message' => $success ? 'Security log recorded successfully' : 'Failed to record security log',
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('安全日志记录失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to log security event',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取监控仪表板数据
     */
    #[Route('/dashboard', name: 'monitoring_dashboard', methods: ['GET'])]
    public function getDashboardData(): JsonResponse
    {
        try {
            $dashboard = [
                'health' => $this->monitoringService->getSystemHealth(),
                'metrics' => $this->monitoringService->getPerformanceMetrics(),
                'anomalies' => $this->monitoringService->checkAnomalies(),
                'log_stats' => $this->loggingService->getLogStatistics(),
                'monitoring_stats' => $this->monitoringService->getMonitoringStats(),
                'recent_errors' => $this->getRecentErrors(),
                'system_alerts' => $this->getSystemAlerts()
            ];

            $this->logger->debug('监控仪表板数据获取成功');

            return $this->json([
                'success' => true,
                'data' => $dashboard,
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('监控仪表板数据获取失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve dashboard data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 提取指标过滤器
     */
    private function extractMetricFilters(Request $request): array
    {
        $filters = [];

        if ($request->query->has('start_time')) {
            $filters['start_time'] = $request->query->get('start_time');
        }

        if ($request->query->has('end_time')) {
            $filters['end_time'] = $request->query->get('end_time');
        }

        if ($request->query->has('metric_type')) {
            $filters['metric_type'] = $request->query->get('metric_type');
        }

        if ($request->query->has('component')) {
            $filters['component'] = $request->query->get('component');
        }

        return $filters;
    }

    /**
     * 提取日志过滤器
     */
    private function extractLogFilters(Request $request): array
    {
        $filters = [];

        if ($request->query->has('task_id')) {
            $filters['task_id'] = $request->query->get('task_id');
        }

        if ($request->query->has('log_level')) {
            $filters['log_level'] = $request->query->get('log_level');
        }

        if ($request->query->has('log_level_in')) {
            $filters['log_level_in'] = explode(',', $request->query->get('log_level_in'));
        }

        if ($request->query->has('created_after')) {
            $filters['created_after'] = new \DateTime($request->query->get('created_after'));
        }

        if ($request->query->has('created_before')) {
            $filters['created_before'] = new \DateTime($request->query->get('created_before'));
        }

        if ($request->query->has('message_contains')) {
            $filters['message_contains'] = $request->query->get('message_contains');
        }

        if ($request->query->has('category')) {
            $filters['category'] = $request->query->get('category');
        }

        return $filters;
    }

    /**
     * 提取分页参数
     */
    private function extractPagination(Request $request): array
    {
        $pagination = [];

        if ($request->query->has('limit')) {
            $pagination['limit'] = (int) $request->query->get('limit');
        }

        if ($request->query->has('offset')) {
            $pagination['offset'] = (int) $request->query->get('offset');
        }

        if ($request->query->has('page')) {
            $page = (int) $request->query->get('page');
            $limit = $pagination['limit'] ?? 50;
            $pagination['offset'] = ($page - 1) * $limit;
        }

        return $pagination;
    }

    /**
     * 获取最近错误
     */
    private function getRecentErrors(): array
    {
        try {
            $filters = [
                'log_level_in' => ['error', 'critical'],
                'created_after' => new \DateTime('-24 hours')
            ];
            $pagination = ['limit' => 10];

            $result = $this->loggingService->queryLogs($filters, $pagination);

            return $result['logs'] ?? [];

        } catch (\Exception $e) {
            $this->logger->error('获取最近错误失败', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * 获取系统告警
     */
    private function getSystemAlerts(): array
    {
        try {
            $anomalies = $this->monitoringService->checkAnomalies();

            // 只返回严重和高级别的异常作为告警
            $alerts = array_filter($anomalies, function($anomaly) {
                return in_array($anomaly['severity'], ['critical', 'high']);
            });

            return array_values($alerts);

        } catch (\Exception $e) {
            $this->logger->error('获取系统告警失败', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
