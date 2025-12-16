<?php

namespace App\MessageHandler;

use App\Message\WechatSyncMessage;
use App\Service\AsyncTaskManager;
use App\Service\TaskStatusService;
use App\Service\WechatApiService;
use App\Service\WechatArticleSyncService;
use App\Entity\AsyncTask;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * 微信同步消息处理器
 */
#[AsMessageHandler]
class WechatSyncMessageHandler
{
    public function __construct(
        private AsyncTaskManager $asyncTaskManager,
        private TaskStatusService $taskStatusService,
        private WechatApiService $wechatApiService,
        private WechatArticleSyncService $wechatArticleSyncService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private MessageBusInterface $messageBus
    ) {
    }

    /**
     * 处理微信同步消息
     */
    public function __invoke(WechatSyncMessage $message): void
    {
        $taskId = $message->getTaskId();
        $accountId = $message->getAccountId();

        $this->logger->info('Processing WeChat sync message', [
            'task_id' => $taskId,
            'account_id' => $accountId,
            'sync_type' => $message->getSyncType(),
            'sync_scope' => $message->getSyncScope(),
            'article_limit' => $message->getArticleLimit(),
        ]);

        try {
            // 验证消息
            if (!$message->isValid()) {
                throw new \InvalidArgumentException('Invalid WeChat sync message');
            }

            // 标记任务为运行中
            if (!$this->asyncTaskManager->markTaskAsRunning($taskId)) {
                throw new \RuntimeException('Failed to mark task as running');
            }

            // 执行同步逻辑
            $result = $this->performWechatSync($message);

            // 标记任务为完成
            $this->asyncTaskManager->markTaskAsCompleted(
                $taskId,
                $result,
                $result['processed_count'] ?? null
            );

            $this->logger->info('WeChat sync completed successfully', [
                'task_id' => $taskId,
                'account_id' => $accountId,
                'result' => $result,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('WeChat sync failed', [
                'task_id' => $taskId,
                'account_id' => $accountId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 标记任务为失败
            $this->asyncTaskManager->markTaskAsFailed($taskId, $e);

            // 如果可以重试，创建重试任务
            if ($this->shouldRetry($e, $message)) {
                $this->scheduleRetry($message, $e);
            }
        }
    }

    /**
     * 执行微信同步
     */
    private function performWechatSync(WechatSyncMessage $message): array
    {
        $taskId = $message->getTaskId();
        $accountId = $message->getAccountId();
        $syncScope = $message->getSyncScope();
        $articleLimit = $message->getArticleLimit();
        $forceSync = $message->isForceSync();

        // 获取微信账号信息
        $wechatAccount = $this->getWechatAccount($accountId);
        if (!$wechatAccount) {
            throw new \RuntimeException("WeChat account not found: {$accountId}");
        }

        // 获取访问令牌
        $accessToken = $this->wechatApiService->getAccessToken($wechatAccount);
        if (!$accessToken) {
            throw new \RuntimeException('Failed to get WeChat access token');
        }

        // 更新任务进度
        $this->updateTaskProgress($taskId, 'fetching_articles', 10);

        // 获取文章列表
        $articles = $this->fetchArticles($accessToken, $syncScope, $articleLimit, $forceSync);

        if (empty($articles)) {
            return [
                'status' => 'success',
                'message' => 'No articles found',
                'processed_count' => 0,
                'new_articles' => 0,
                'updated_articles' => 0,
            ];
        }

        $this->updateTaskProgress($taskId, 'processing_articles', 30);

        // 处理文章
        $result = $this->processArticles($taskId, $articles, $message);

        // 处理媒体资源（如果需要）
        if ($message->shouldProcessMedia()) {
            $this->updateTaskProgress($taskId, 'downloading_media', 70);
            $this->processMediaResources($result['processed_articles'], $message);
        }

        $this->updateTaskProgress($taskId, 'finalizing', 90);

        return [
            'status' => 'success',
            'message' => 'WeChat sync completed successfully',
            'processed_count' => $result['processed_count'],
            'new_articles' => $result['new_articles'],
            'updated_articles' => $result['updated_articles'],
            'media_processed' => $message->shouldProcessMedia(),
            'sync_scope' => $syncScope,
            'article_limit' => $articleLimit,
            'force_sync' => $forceSync,
        ];
    }

    /**
     * 获取微信账号
     */
    private function getWechatAccount(string $accountId): ?\App\Entity\WechatPublicAccount
    {
        try {
            $repository = $this->entityManager->getRepository(\App\Entity\WechatPublicAccount::class);
            return $repository->find($accountId);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get WeChat account', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 获取文章列表
     */
    private function fetchArticles(string $accessToken, string $syncScope, int $articleLimit, bool $forceSync): array
    {
        try {
            $articles = [];
            $offset = 0;
            $batchSize = min(20, $articleLimit); // 每批最多20篇

            while (count($articles) < $articleLimit) {
                $currentBatchSize = min($batchSize, $articleLimit - count($articles));

                $batchArticles = $this->wechatApiService->getArticleList(
                    $accessToken,
                    $offset,
                    $currentBatchSize
                );

                if (empty($batchArticles)) {
                    break;
                }

                $articles = array_merge($articles, $batchArticles);
                $offset += $currentBatchSize;

                // 如果不是强制同步且获取的文章数少于请求数，说明已经获取完所有文章
                if (!$forceSync && count($batchArticles) < $currentBatchSize) {
                    break;
                }
            }

            return array_slice($articles, 0, $articleLimit);
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch articles', [
                'access_token' => substr($accessToken, 0, 10) . '...',
                'sync_scope' => $syncScope,
                'article_limit' => $articleLimit,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to fetch articles: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 处理文章
     */
    private function processArticles(string $taskId, array $articles, WechatSyncMessage $message): array
    {
        $result = [
            'processed_count' => 0,
            'new_articles' => 0,
            'updated_articles' => 0,
            'processed_articles' => [],
        ];

        $batchSize = $message->getBatchSize();
        $totalArticles = count($articles);

        foreach ($articles as $index => $article) {
            try {
                // 使用现有的微信文章同步服务
                $syncResult = $this->wechatArticleSyncService->syncArticle(
                    $article,
                    $message->getAccountId(),
                    $message->shouldForceDownload()
                );

                $result['processed_count']++;
                $result['processed_articles'][] = $syncResult;

                if ($syncResult['is_new']) {
                    $result['new_articles']++;
                } else {
                    $result['updated_articles']++;
                }

                // 更新进度
                $progress = 30 + (int)(($index + 1) / $totalArticles * 40);
                $this->updateTaskProgress($taskId, 'processing_articles', $progress);

                // 批量处理
                if (($index + 1) % $batchSize === 0) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                }

            } catch (\Exception $e) {
                $this->logger->warning('Failed to process article', [
                    'task_id' => $taskId,
                    'article_id' => $article['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
                // 继续处理其他文章
            }
        }

        // 最后一次刷新
        $this->entityManager->flush();
        $this->entityManager->clear();

        return $result;
    }

    /**
     * 处理媒体资源
     */
    private function processMediaResources(array $processedArticles, WechatSyncMessage $message): void
    {
        $mediaUrls = [];

        // 收集所有媒体URL
        foreach ($processedArticles as $article) {
            if (isset($article['media_urls']) && is_array($article['media_urls'])) {
                $mediaUrls = array_merge($mediaUrls, $article['media_urls']);
            }
        }

        if (empty($mediaUrls)) {
            return;
        }

        // 这里可以发送媒体处理消息到专门的队列
        // 目前简化处理，直接记录日志
        $this->logger->info('Media resources to process', [
            'total_media' => count($mediaUrls),
            'task_id' => $message->getTaskId(),
        ]);

        // TODO: 实现媒体处理队列
        // $mediaProcessMessage = new MediaProcessMessage($mediaUrls, $message->getTaskId());
        // $this->messageBus->dispatch($mediaProcessMessage);
    }

    /**
     * 更新任务进度
     */
    private function updateTaskProgress(string $taskId, string $step, int $percentage): void
    {
        try {
            $task = $this->asyncTaskManager->getTaskStatus($taskId);
            if ($task) {
                $payload = $task->getPayload();
                $payload['current_step'] = $step;
                $payload['progress_percentage'] = $percentage;

                // 根据步骤更新已处理的数量
                switch ($step) {
                    case 'processing_articles':
                        $payload['processed_articles'] = (int)(($percentage - 30) / 40 * $payload['article_limit']);
                        break;
                    case 'downloading_media':
                        $payload['processed_media'] = (int)(($percentage - 70) / 20 * ($payload['media_count'] ?? 0));
                        break;
                }

                $task->setPayload($payload);
                $this->entityManager->persist($task);
                $this->entityManager->flush();
            }
        } catch (\Exception $e) {
            $this->logger->warning('Failed to update task progress', [
                'task_id' => $taskId,
                'step' => $step,
                'percentage' => $percentage,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 判断是否应该重试
     */
    private function shouldRetry(\Exception $e, WechatSyncMessage $message): bool
    {
        // 获取任务信息
        $task = $this->asyncTaskManager->getTaskStatus($message->getTaskId());
        if (!$task || !$task->canRetry()) {
            return false;
        }

        // 根据异常类型判断
        $retryableErrors = [
            'timeout',
            'connection',
            'network',
            'service unavailable',
            'rate limit',
        ];

        $errorMessage = strtolower($e->getMessage());
        foreach ($retryableErrors as $error) {
            if (str_contains($errorMessage, $error)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 安排重试
     */
    private function scheduleRetry(WechatSyncMessage $message, \Exception $exception): void
    {
        $taskId = $message->getTaskId();

        try {
            if ($this->asyncTaskManager->retryTask($taskId)) {
                $this->logger->info('Task retry scheduled', [
                    'task_id' => $taskId,
                    'retry_reason' => $exception->getMessage(),
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to schedule retry', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 发送回调通知（如果配置了）
     */
    private function sendCallback(WechatSyncMessage $message, array $result): void
    {
        $callbackUrl = $message->getCallbackUrl();
        if (!$callbackUrl) {
            return;
        }

        try {
            $payload = [
                'task_id' => $message->getTaskId(),
                'account_id' => $message->getAccountId(),
                'status' => $result['status'],
                'result' => $result,
                'timestamp' => time(),
            ];

            // 使用Guzzle或其他HTTP客户端发送回调
            // TODO: 实现HTTP回调逻辑
            $this->logger->info('Callback would be sent', [
                'callback_url' => $callbackUrl,
                'payload' => $payload,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send callback', [
                'task_id' => $message->getTaskId(),
                'callback_url' => $callbackUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
