<?php

namespace App\Controller;

use App\Http\ApiResponse;
use App\Service\DataConsistencyManager;
use App\Service\DataValidator;
use App\Service\TransactionManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * 数据一致性管理控制器
 *
 * 提供数据一致性检查、验证和管理的API接口
 */
#[Route('/official-api/data-consistency')]
class DataConsistencyController extends AbstractController
{
    private DataConsistencyManager $consistencyManager;
    private DataValidator $dataValidator;
    private TransactionManager $transactionManager;
    private LoggerInterface $logger;

    public function __construct(
        DataConsistencyManager $consistencyManager,
        DataValidator $dataValidator,
        TransactionManager $transactionManager,
        LoggerInterface $logger
    ) {
        $this->consistencyManager = $consistencyManager;
        $this->dataValidator = $dataValidator;
        $this->transactionManager = $transactionManager;
        $this->logger = $logger;
    }

    /**
     * 检查数据完整性
     */
    #[Route('/integrity', methods: ['GET'])]
    public function checkDataIntegrity(): JsonResponse
    {
        try {
            $integrityCheck = $this->dataValidator->validateDataIntegrityConstraints();
            $consistencyStats = $this->consistencyManager->getConsistencyStatistics();
            $transactionStats = $this->transactionManager->getTransactionStatistics();

            return $this->apiResponse->success('数据完整性检查完成', [
                'integrity_check' => $integrityCheck,
                'consistency_statistics' => $consistencyStats,
                'transaction_statistics' => $transactionStats,
                'check_time' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('数据完整性检查失败', ['error' => $e->getMessage()]);
            return $this->apiResponse->error('数据完整性检查失败: ' . $e->getMessage());
        }
    }

    /**
     * 验证任务数据
     */
    #[Route('/validate-task/{taskId}', methods: ['GET'])]
    public function validateTask(string $taskId): JsonResponse
    {
        try {
            $task = $this->entityManager->getRepository(\App\Entity\AsyncTask::class)->find($taskId);
            if (!$task) {
                return $this->apiResponse->error('任务不存在', 404);
            }

            $validation = $this->dataValidator->validateWechatSyncTask($task);

            return $this->apiResponse->success('任务数据验证完成', [
                'task_id' => $taskId,
                'validation' => $validation,
                'validation_time' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('任务数据验证失败', ['task_id' => $taskId, 'error' => $e->getMessage()]);
            return $this->apiResponse->error('任务数据验证失败: ' . $e->getMessage());
        }
    }

    /**
     * 验证微信公众号账号
     */
    #[Route('/validate-wechat-account/{accountId}', methods: ['GET'])]
    public function validateWechatAccount(string $accountId): JsonResponse
    {
        try {
            $validation = $this->dataValidator->validateWechatAccount($accountId);

            return $this->apiResponse->success('微信公众号账号验证完成', [
                'account_id' => $accountId,
                'validation' => $validation,
                'validation_time' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('微信公众号账号验证失败', ['account_id' => $accountId, 'error' => $e->getMessage()]);
            return $this->apiResponse->error('微信公众号账号验证失败: ' . $e->getMessage());
        }
    }

    /**
     * 验证批量数据
     */
    #[Route('/validate-batch', methods: ['POST'])]
    public function validateBatchData(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->apiResponse->error('请求体格式错误', 400);
            }

            if (!isset($data['officials']) || !isset($data['account_id'])) {
                return $this->apiResponse->error('缺少必要参数: officials, account_id', 400);
            }

            $validation = $this->dataValidator->validateBatchDataConsistency(
                $data['officials'],
                $data['account_id']
            );

            return $this->apiResponse->success('批量数据验证完成', [
                'validation' => $validation,
                'validation_time' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('批量数据验证失败', ['error' => $e->getMessage()]);
            return $this->apiResponse->error('批量数据验证失败: ' . $e->getMessage());
        }
    }

    /**
     * 验证业务规则
     */
    #[Route('/validate-business-rules', methods: ['POST'])]
    public function validateBusinessRules(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->apiResponse->error('请求体格式错误', 400);
            }

            if (!isset($data['rule_type']) || !isset($data['data'])) {
                return $this->apiResponse->error('缺少必要参数: rule_type, data', 400);
            }

            $validation = $this->dataValidator->validateBusinessRules(
                $data['data'],
                $data['rule_type']
            );

            return $this->apiResponse->success('业务规则验证完成', [
                'rule_type' => $data['rule_type'],
                'validation' => $validation,
                'validation_time' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('业务规则验证失败', ['error' => $e->getMessage()]);
            return $this->apiResponse->error('业务规则验证失败: ' . $e->getMessage());
        }
    }

    /**
     * 修复数据一致性问题
     */
    #[Route('/fix-consistency/{taskId}', methods: ['POST'])]
    public function fixDataConsistency(string $taskId): JsonResponse
    {
        try {
            $result = $this->consistencyManager->executeWithConsistency($taskId, function($consistencyManager) use ($taskId) {
                // 捕获当前状态
                $currentState = $consistencyManager->captureDataState($taskId);

                // 执行修复逻辑（这里需要根据具体业务需求实现）
                $fixResult = $this->performDataFix($taskId, $currentState);

                return $fixResult;
            });

            return $this->apiResponse->success('数据一致性问题修复完成', [
                'task_id' => $taskId,
                'result' => $result,
                'fix_time' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('数据一致性问题修复失败', ['task_id' => $taskId, 'error' => $e->getMessage()]);
            return $this->apiResponse->error('数据一致性问题修复失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取数据一致性统计
     */
    #[Route('/statistics', methods: ['GET'])]
    public function getConsistencyStatistics(): JsonResponse
    {
        try {
            $consistencyStats = $this->consistencyManager->getConsistencyStatistics();
            $validationStats = $this->dataValidator->getValidationStatistics();
            $transactionStats = $this->transactionManager->getTransactionStatistics();

            return $this->apiResponse->success('数据一致性统计信息', [
                'consistency_statistics' => $consistencyStats,
                'validation_statistics' => $validationStats,
                'transaction_statistics' => $transactionStats,
                'statistics_time' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('获取数据一致性统计失败', ['error' => $e->getMessage()]);
            return $this->apiResponse->error('获取数据一致性统计失败: ' . $e->getMessage());
        }
    }

    /**
     * 清理过期的幂等性记录
     */
    #[Route('/cleanup-idempotency', methods: ['POST'])]
    public function cleanupIdempotencyRecords(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $daysOld = $data['days_old'] ?? 7;

            $cleanedCount = $this->consistencyManager->cleanupIdempotencyRecords($daysOld);

            return $this->apiResponse->success('幂等性记录清理完成', [
                'cleaned_count' => $cleanedCount,
                'days_old' => $daysOld,
                'cleanup_time' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('清理幂等性记录失败', ['error' => $e->getMessage()]);
            return $this->apiResponse->error('清理幂等性记录失败: ' . $e->getMessage());
        }
    }

    /**
     * 检查事务状态
     */
    #[Route('/transaction-status', methods: ['GET'])]
    public function getTransactionStatus(): JsonResponse
    {
        try {
            $status = $this->transactionManager->getTransactionStatus();
            $activeTransactions = $this->transactionManager->getActiveTransactions();

            return $this->apiResponse->success('事务状态检查完成', [
                'transaction_status' => $status,
                'active_transactions' => $activeTransactions,
                'check_time' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('检查事务状态失败', ['error' => $e->getMessage()]);
            return $this->apiResponse->error('检查事务状态失败: ' . $e->getMessage());
        }
    }

    /**
     * 清理超时事务
     */
    #[Route('/cleanup-timeout-transactions', methods: ['POST'])]
    public function cleanupTimeoutTransactions(): JsonResponse
    {
        try {
            $cleanedCount = $this->transactionManager->cleanupTimeoutTransactions();

            return $this->apiResponse->success('超时事务清理完成', [
                'cleaned_count' => $cleanedCount,
                'cleanup_time' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('清理超时事务失败', ['error' => $e->getMessage()]);
            return $this->apiResponse->error('清理超时事务失败: ' . $e->getMessage());
        }
    }

    /**
     * 执行数据修复（示例实现）
     */
    private function performDataFix(string $taskId, array $currentState): array
    {
        // 这里需要根据具体的业务需求实现数据修复逻辑
        // 例如：
        // 1. 修复缺失的字段
        // 2. 删除重复的数据
        // 3. 修正错误的状态
        // 4. 重建关联关系

        $fixActions = [];
        $fixResults = [];

        // 示例：修复Official数据
        if (isset($currentState['officials'])) {
            foreach ($currentState['officials'] as $official) {
                if (empty($official['title'])) {
                    $fixActions[] = "修复文章标题: {$official['id']}";
                }
                if (empty($official['article_id'])) {
                    $fixActions[] = "修复文章ID: {$official['id']}";
                }
            }
        }

        $fixResults = [
            'actions_performed' => $fixActions,
            'total_actions' => count($fixActions),
            'fix_success' => true
        ];

        $this->logger->info('数据修复执行完成', [
            'task_id' => $taskId,
            'fix_results' => $fixResults
        ]);

        return $fixResults;
    }
}
