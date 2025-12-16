<?php

namespace App\Service;

use App\Interface\NotificationChannelInterface;
use App\DTO\NotificationMessage;
use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * 通知管理器
 */
class NotificationManager
{
    private array $channels = [];
    private array $channelPriorities = [];
    private LoggerInterface $logger;
    private EntityManagerInterface $entityManager;
    private NotificationRepository $notificationRepository;

    public function __construct(
        LoggerInterface $logger,
        EntityManagerInterface $entityManager,
        ContainerInterface $container
    ) {
        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->notificationRepository = $entityManager->getRepository(Notification::class);
        $this->initializeChannels($container);
    }

    /**
     * 初始化通知渠道
     */
    private function initializeChannels(ContainerInterface $container): void
    {
        // 从容器中获取所有标记为 notification.channel 的服务
        $channelIds = $container->getServiceIds();

        foreach ($channelIds as $serviceId) {
            if (str_contains($serviceId, 'notification.channel')) {
                $channel = $container->get($serviceId);
                if ($channel instanceof NotificationChannelInterface) {
                    $this->addChannel($channel);
                }
            }
        }
    }

    /**
     * 添加通知渠道
     */
    public function addChannel(NotificationChannelInterface $channel): void
    {
        $channelName = $channel->getChannelName();
        $this->channels[$channelName] = $channel;
        $this->channelPriorities[$channelName] = $channel->getPriority();

        // 按优先级排序
        arsort($this->channelPriorities);

        $this->logger->info('通知渠道已添加', [
            'channel' => $channelName,
            'priority' => $channel->getPriority()
        ]);
    }

    /**
     * 发送通知
     */
    public function sendNotification(NotificationMessage $message, ?array $preferredChannels = null): bool
    {
        $success = false;
        $attemptedChannels = [];

        // 确定要使用的渠道
        $channels = $this->determineChannels($message, $preferredChannels);

        // 创建通知记录
        $notification = $this->createNotificationRecord($message, $channels);

        foreach ($channels as $channelName) {
            if (!isset($this->channels[$channelName])) {
                $this->logger->warning('通知渠道不存在', ['channel' => $channelName]);
                continue;
            }

            $channel = $this->channels[$channelName];
            $attemptedChannels[] = $channelName;

            try {
                // 检查渠道可用性
                if (!$channel->isAvailable()) {
                    $this->logger->warning('通知渠道不可用', ['channel' => $channelName]);
                    continue;
                }

                // 验证消息格式
                if (!$channel->validateMessage($message)) {
                    $this->logger->warning('消息格式不被渠道支持', [
                        'channel' => $channelName,
                        'message_type' => $message->getType()
                    ]);
                    continue;
                }

                // 发送通知
                $result = $channel->send($message);

                if ($result) {
                    $success = true;
                    $this->logger->info('通知发送成功', [
                        'channel' => $channelName,
                        'message_id' => $message->getId(),
                        'recipient' => $message->getRecipient()
                    ]);

                    // 更新通知状态
                    $this->updateNotificationStatus($notification, $channelName, 'sent');
                } else {
                    $this->logger->error('通知发送失败', [
                        'channel' => $channelName,
                        'error' => $channel->getLastError(),
                        'message_id' => $message->getId()
                    ]);

                    // 更新通知状态
                    $this->updateNotificationStatus($notification, $channelName, 'failed', $channel->getLastError());
                }
            } catch (\Exception $e) {
                $this->logger->error('通知发送异常', [
                    'channel' => $channelName,
                    'error' => $e->getMessage(),
                    'message_id' => $message->getId()
                ]);

                // 更新通知状态
                $this->updateNotificationStatus($notification, $channelName, 'error', $e->getMessage());
            }
        }

        // 更新最终状态
        $finalStatus = $success ? 'completed' : 'failed';
        $notification->setStatus($finalStatus);
        $notification->setAttemptedChannels(implode(',', $attemptedChannels));
        $this->entityManager->flush();

        return $success;
    }

    /**
     * 批量发送通知
     */
    public function sendBatchNotifications(array $messages, ?array $preferredChannels = null): array
    {
        $results = [];

        foreach ($messages as $index => $message) {
            try {
                $results[$index] = $this->sendNotification($message, $preferredChannels);
            } catch (\Exception $e) {
                $this->logger->error('批量通知发送异常', [
                    'index' => $index,
                    'error' => $e->getMessage()
                ]);
                $results[$index] = false;
            }
        }

        return $results;
    }

    /**
     * 确定通知渠道
     */
    private function determineChannels(NotificationMessage $message, ?array $preferredChannels): array
    {
        if ($preferredChannels && !empty($preferredChannels)) {
            return array_intersect($preferredChannels, array_keys($this->channels));
        }

        // 根据消息类型和优先级自动选择渠道
        $availableChannels = [];

        foreach ($this->channels as $name => $channel) {
            if (in_array($message->getType(), $channel->getSupportedMessageTypes()) && $channel->isAvailable()) {
                $availableChannels[$name] = $channel->getPriority();
            }
        }

        // 按优先级排序
        arsort($availableChannels);

        return array_keys($availableChannels);
    }

    /**
     * 创建通知记录
     */
    private function createNotificationRecord(NotificationMessage $message, array $channels): Notification
    {
        $notification = new Notification();
        $notification->setId($message->getId());
        $notification->setType($message->getType());
        $notification->setTitle($message->getTitle());
        $notification->setContent($message->getContent());
        $notification->setRecipient($message->getRecipient());
        $notification->setPriority($message->getPriority());
        $notification->setChannels(implode(',', $channels));
        $notification->setStatus('pending');
        $notification->setCreatedAt(new \DateTime());

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    /**
     * 更新通知状态
     */
    private function updateNotificationStatus(Notification $notification, string $channel, string $status, ?string $error = null): void
    {
        $notification->setStatus($status);
        $notification->setLastChannel($channel);

        if ($error) {
            $notification->setLastError($error);
        }

        $notification->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();
    }

    /**
     * 获取通知历史
     */
    public function getNotificationHistory(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return $this->notificationRepository->findByFilters($filters, $limit, $offset);
    }

    /**
     * 重试失败的通知
     */
    public function retryFailedNotifications(int $maxRetries = 3): int
    {
        $failedNotifications = $this->notificationRepository->findFailedNotifications($maxRetries);
        $retryCount = 0;

        foreach ($failedNotifications as $notification) {
            try {
                $message = new NotificationMessage(
                    $notification->getId(),
                    $notification->getType(),
                    $notification->getTitle(),
                    $notification->getContent(),
                    $notification->getRecipient(),
                    $notification->getPriority(),
                    json_decode($notification->getMetadata() ?: '{}', true)
                );

                $channels = explode(',', $notification->getChannels());
                if ($this->sendNotification($message, $channels)) {
                    $retryCount++;
                }
            } catch (\Exception $e) {
                $this->logger->error('重试通知失败', [
                    'notification_id' => $notification->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $retryCount;
    }

    /**
     * 获取渠道列表
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /**
     * 获取渠道统计信息
     */
    public function getChannelStats(): array
    {
        $stats = [];

        foreach ($this->channels as $name => $channel) {
            $stats[$name] = array_merge([
                'name' => $name,
                'priority' => $channel->getPriority(),
                'available' => $channel->isAvailable()
            ], $channel->getStats());
        }

        return $stats;
    }

    /**
     * 清理过期通知
     */
    public function cleanupExpiredNotifications(int $days = 30): int
    {
        $cutoffDate = new \DateTime();
        $cutoffDate->sub(new \DateInterval("P{$days}D"));

        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete(Notification::class, 'n')
           ->where('n.createdAt < :cutoffDate')
           ->setParameter('cutoffDate', $cutoffDate);

        return $qb->getQuery()->execute();
    }
}
