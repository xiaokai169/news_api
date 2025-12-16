<?php

namespace App\Controller;

use App\Http\ApiResponse;
use App\Service\ErrorHandler;
use App\Service\RetryManager;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * 错误处理和重试管理控制器
 *
 * 提供错误处理、重试管理的API接口
 */
#[Route('/official-api/error-handling')]
class ErrorHandlingController extends AbstractController
{
    private ErrorHandler $errorHandler;
    private RetryManager $retryManager;
    private LoggerInterface $logger;

    public function __construct(
        ErrorHandler $errorHandler,
        RetryManager $retryManager,
        LoggerInterface $logger
    ) {
        $this->errorHandler = $errorHandler;
        $this->retryManager = $retryManager;
        $this->logger = $logger;
    }

    /**
     * 获取错误统计信息
     */
    #[Route('/error-statistics', methods: ['GET'])]
    public function getErrorStatistics(Request $request): JsonResponse
    {
        try {
            $filters = [
                'date_from' => $request->query->get('date_from'),
                'date_to' => $request->query->get('date_to'),
                'task_type' => $request->query->get('task_type')
            ];

            $statistics = $this->errorHandler->getErrorStatistics($filters);

            return $this->apiResponse->success('错误统计信息获取成功', [
                'statistics' => $statistics,
                'filters' => $filters,
                'generated_at' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('获取错误统计信息失败', ['error' => $e->getMessage()]);
            return $this->apiResponse->error('获取错误统计信息失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取重试统计信息
     */
    #[Route('/retry-statistics', methods: ['GET'])]
    public function getRetryStatistics(Request $request): JsonResponse
    {
        try {
            $filters = [
                'date_from' => $request->query->get('date_from'),
                'date_to' => $request->query->get('date_to'),
                'task_type' => $request->query->get('task_type')
            ];

            $statistics = $this->retryManager->getRetryStatistics($filters);

            return $this->apiResponse->success('重试统计信息获取成功', [
                'statistics' => $statistics,
                'filters' => $filters,
                'generated_at' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('获取重试统计信息失败', ['error' => $e->getMessage()]);
            return $this->apiResponse->error('获取重试统计信息失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取重试队列状态
     */
    #[Route('/retry-queue-status', methods: ['GET'])]
    public function getRetryQueueStatus(): JsonResponse
    {
        try {
            $queueStatus = $this->retryManager->getRetryQueueStatus();

            return $this->apiResponse->success('重试队列状态获取成功', [
                'queue_status' => $queueStatus,
                'total_queues' => count($queueStatus),
                'checked_at' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('获取重试队列状态失败', ['error' => $e->getMessage()]);
            return $this->apiResponse->error('获取重试队列状态失败: ' . $e->getMessage());
        }
    }

    /**
     * 手动重试任务
     */
    #[Route('/manual-retry/{taskId}', methods: ['POST'])]
    public function manualRetry(string $taskId, Request $request): JsonResponse
    {
        try {
            $options = json_decode($request->getContent(), true) ?? [];

            $result = $this->retryManager->manualRetry($taskId, $options);

            if ($result['success']) {
                return $this->apiResponse->success('任务手动重试成功', [
                    'task_id' => $taskId,
                    'retry_result' => $result,
                    'executed_at' => date('Y-m-d H:i:s')
                ]);
            } else {
                return $this->apiResponse->error('任务手动重试失败: ' . $result['error'], 400, [
                    'task_id' => $taskId,
                    'error_details' => $result
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->error('任务手动重试失败', ['task_id' => $taskId, 'error' => $e->getMessage()]);
            return $this->apiResponse->error('任务手动重试失败: ' . $e->getMessage());
        }
    }

    /**
     * 处理到期的重试任务
     */
    #[Route('/process-due-retries', methods: ['POST'])]
    public function processDueRetries(): JsonResponse
    {
        try {
            $processed = $this->retryManager->processDueRetries();

            return $this->apiResponse->success('到期重试任务处理完成', [
                'processed_tasks' => $processed,
                'total_processed' => count($processed),
                'successful_count' => count(array_filter($processed, fn($r) => $r['success'])),
                'failed_count' => count(array_filter($processed, fn($r) => !$r['success'])),
                'processed_at' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('处理到期重试任务失败', ['error' => $e->getMessage()]);
            return $this->apiResponse->error('处理到期重试任务失败: ' . $e->getMessage());
        }
    }

    /**
     * 处理死信队列
     */
    #[Route('/handle-dead-letter-queue', methods: ['POST'])]
    public function handleDeadLetterQueue(): JsonResponse
    {
        try {
            $processed = $this->errorHandler->handleDeadLetterQueue();

            return $this->apiResponse->success('死信队列处理完成', [
                'processed_tasks' => $processed,
                'total_processed' => count($processed),
                'successful_count' => count(array_filter($processed, fn($r) => $r['result'] === 'processed')),
                'failed_count' => count(array_filter($processed, fn($r) => $r['result'] === 'failed')),
                'processed_at' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('处理死信队列失败', ['error' => $e->getMessage()]);
            return $this->apiResponse->error('处理死信队列失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取重试建议
     */
    #[Route('/retry-recommendations/{taskId}', methods: ['GET'])]
    public function getRetryRecommendations(string $taskId): JsonResponse
    {
        try {
            $task = $this->getDoctrine()->getRepository(\App\Entity\AsyncTask::class)->find($taskId);
            if (!$task) {
                return $this->apiResponse->error('任务不存在', 404);
            }

            $recommendations = $this->retryManager->getRetryRecommendations($task);

            return $this->apiResponse->success('重试建议获取成功', [
                'task_id' => $taskId,
                'recommendations' => $recommendations,
                'generated_at' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('获取重试建议失败', ['task_id' => $taskId, 'error' => $e->getMessage()]);
            return $this->apiResponse->error('获取重试建议失败: ' . $e->getMessage());
        }
    }

    /**
     * 清理过期的重试任务
     */
    #[Route('/cleanup-expired-retries', methods: ['POST'])]
    public function cleanupExpiredRetries(Request $request): JsonResponse
    {
        try {
            $daysOld = $request->request->get('days_old', 30);
            $deletedCount = $this->retryManager->cleanupExpiredRetries($daysOld);

            return $this->apiResponse->success('过期重试任务清理完成', [
                'deleted_count' => $deletedCount,
                'days_old' => $daysOld,
                'cleanup_time' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('清理过期重试任务失败', ['error' => $e->getMessage()]);
            return $this->apiResponse->error('清理过期重试任务失败: ' . $e->getMessage());
        }
    }

    /**
     * 分析错误
     */
    #[Route('/analyze-error', methods: ['POST'])]
    public function analyzeError(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['error_message'])) {
                return $this->apiResponse->error('缺少错误信息', 400);
            }

            // 创建模拟异常对象进行分析
            $exception = new \Exception($data['error_message'], $data['error_code'] ?? 0);

            $errorAnalysis = $this->errorHandler->analyzeError($exception);

            return $this->apiResponse->success('错误分析完成', [
                'error_analysis' => $errorAnalysis,
                'analyzed_at' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('错误分析失败', ['error' => $e->getMessage()]);
            return $this->apiResponse->error('错误分析失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取错误处理配置
     */
    #[Route('/error-config', methods: ['GET'])]
    public function getErrorConfig(): JsonResponse
    {
        try {
            $config = [
                'error_categories' => [
                    'network' => [
                        'description' => '网络相关错误',
                        'recoverable' => true,
                        'max_retries' => 5,
                        'retry_strategy' => 'exponential_backoff'
                    ],
                    'database' => [
                        'description' => '数据库相关错误',
                        'recoverable' => true,
                        'max_retries' => 3,
                        'retry_strategy' => 'linear_backoff'
                    ],
                    'validation' => [
                        'description' => '数据验证错误',
                        'recoverable' => false,
                        'max_retries' => 0,
                        'retry_strategy' => 'none'
                    ],
                    'authentication' => [
                        'description' => '认证授权错误',
                        'recoverable' => true,
                        'max_retries' => 2,
                        'retry_strategy' => 'fixed_delay'
                    ],
                    'rate_limit' => [
                        'description' => '频率限制错误',
                        'recoverable' => true,
                        'max_retries' => 3,
                        'retry_strategy' => 'exponential_backoff'
                    ],
                    'business_logic' => [
                        'description' => '业务逻辑错误',
                        'recoverable' => false,
                        'max_retries' => 0,
                        'retry_strategy' => 'none'
                    ],
                    'system' => [
                        'description' => '系统资源错误',
                        'recoverable' => true,
                        'max_retries' => 2,
                        'retry_strategy' => 'linear_backoff'
                    ]
                ],
                'retry_strategies' => [
                    'exponential_backoff' => [
                        'description' => '指数退避策略',
                        'formula' => 'delay = base_delay * 2^retry_count',
                        'example' => '10s, 20s, 40s, 80s, 160s'
                    ],
                    'linear_backoff' => [
                        'description' => '线性退避策略',
                        'formula' => 'delay = base_delay * (retry_count + 1)',
                        'example' => '10s, 20s, 30s, 40s, 50s'
                    ],
                    'fixed_delay' => [
                        'description' => '固定延迟策略',
                        'formula' => 'delay = fixed_delay',
                        'example' => '60s, 60s, 60s, 60s, 60s'
                    ]
                ],
                'queue_priorities' => [
                    'high_priority' => [
                        'description' => '高优先级队列',
                        'max_retries' => 5,
                        'base_delay' => 5,
                        'max_delay' => 60
                    ],
                    'normal' => [
                        'description' => '普通优先级队列',
                        'max_retries' => 3,
                        'base_delay' => 10,
                        'max_delay' => 300
                    ],
                    'low_priority' => [
                        'description' => '低优先级队列',
                        'max_retries' => 2,
                        'base_delay' => 30,
                        'max_delay' => 600
                    ]
                ]
            ];

            return $this->apiResponse->success('错误处理配置获取成功', [
                'config' => $config,
                'retrieved_at' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('获取错误处理配置失败', ['error' => $e->getMessage()]);
            return $this->apiResponse->error('获取错误处理配置失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取错误处理健康状态
     */
    #[Route('/health', methods: ['GET'])]
    public function getHealthStatus(): JsonResponse
    {
        try {
            $health = [
                'error_handler' => [
                    'status' => 'healthy',
                    'last_check' => date('Y-m-d H:i:s'),
                    'metrics' => [
                        'error_processing_rate' => $this->calculateErrorProcessingRate(),
                        'average_resolution_time' => $this->calculateAverageResolutionTime(),
                        'success_rate' => $this->calculateErrorResolutionSuccessRate()
                    ]
                ],
                'retry_manager' => [
                    'status' => 'healthy',
                    'last_check' => date('Y-m-d H:i:s'),
                    'metrics' => [
                        'retry_queue_size' => $this->getRetryQueueSize(),
                        'retry_success_rate' => $this->calculateRetrySuccessRate(),
                        'average_retry_delay' => $this->calculateAverageRetryDelay()
                    ]
                ],
                'system' => [
                    'status' => 'healthy',
                    'uptime' => $this->getSystemUptime(),
                    'memory_usage' => memory_get_usage(true),
                    'memory_peak' => memory_get_peak_usage(true)
                ]
            ];

            // 确定整体健康状态
            $overallStatus = 'healthy';
            if ($health['error_handler']['metrics']['success_rate'] < 90) {
                $overallStatus = 'degraded';
            }
            if ($health['retry_manager']['metrics']['retry_success_rate'] < 80) {
                $overallStatus = 'degraded';
            }

            $health['overall_status'] = $overallStatus;

            return $this->apiResponse->success('错误处理健康状态获取成功', [
                'health' => $health,
                'checked_at' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('获取错误处理健康状态失败', ['error' => $e->getMessage()]);
            return $this->apiResponse->error('获取错误处理健康状态失败: ' . $e->getMessage());
        }
    }

    /**
     * 计算错误处理率
     */
    private function calculateErrorProcessingRate(): float
    {
        // 这里可以实现具体的计算逻辑
        // 暂时返回模拟数据
        return 95.5;
    }

    /**
     * 计算平均解决时间
     */
    private function calculateAverageResolutionTime(): string
    {
        // 这里可以实现具体的计算逻辑
        // 暂时返回模拟数据
        return '45 seconds';
    }

    /**
     * 计算错误解决成功率
     */
    private function calculateErrorResolutionSuccessRate(): float
    {
        // 这里可以实现具体的计算逻辑
        // 暂时返回模拟数据
        return 92.3;
    }

    /**
     * 获取重试队列大小
     */
    private function getRetryQueueSize(): int
    {
        // 这里可以实现具体的计算逻辑
        // 暂时返回模拟数据
        return 15;
    }

    /**
     * 计算重试成功率
     */
    private function calculateRetrySuccessRate(): float
    {
        // 这里可以实现具体的计算逻辑
        // 暂时返回模拟数据
        return 87.6;
    }

    /**
     * 计算平均重试延迟
     */
    private function calculateAverageRetryDelay(): string
    {
        // 这里可以实现具体的计算逻辑
        // 暂时返回模拟数据
        return '25 seconds';
    }

    /**
     * 获取系统运行时间
     */
    private function getSystemUptime(): string
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return sprintf('Load average: %.2f, %.2f, %.2f', $load[0], $load[1], $load[2]);
        }
        return 'N/A';
    }
}
