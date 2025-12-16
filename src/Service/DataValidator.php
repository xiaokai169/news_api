<?php

namespace App\Service;

use App\Entity\AsyncTask;
use App\Entity\Official;
use App\Entity\WechatPublicAccount;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * 数据校验器
 *
 * 负责验证数据的完整性和有效性，包括：
 * - 实体数据校验
 * - 业务规则校验
 * - 数据格式校验
 * - 关联关系校验
 */
class DataValidator
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
     * 验证微信同步任务数据
     */
    public function validateWechatSyncTask(AsyncTask $task): array
    {
        $errors = [];
        $warnings = [];

        // 基础任务验证
        $basicValidation = $this->validateBasicTask($task);
        $errors = array_merge($errors, $basicValidation['errors']);
        $warnings = array_merge($warnings, $basicValidation['warnings']);

        // 微信同步特定验证
        if ($task->getTaskType() !== 'wechat_sync') {
            $errors[] = "任务类型不是微信同步: {$task->getTaskType()}";
            return ['valid' => false, 'errors' => $errors, 'warnings' => $warnings];
        }

        $parameters = $task->getParameters();
        if (!is_array($parameters)) {
            $errors[] = "任务参数格式无效";
            return ['valid' => false, 'errors' => $errors, 'warnings' => $warnings];
        }

        // 验证必要参数
        $requiredParams = ['account_id'];
        foreach ($requiredParams as $param) {
            if (!isset($parameters[$param]) || empty($parameters[$param])) {
                $errors[] = "缺少必要参数: {$param}";
            }
        }

        // 验证公众号存在性
        if (isset($parameters['account_id'])) {
            $accountValidation = $this->validateWechatAccount($parameters['account_id']);
            $errors = array_merge($errors, $accountValidation['errors']);
            $warnings = array_merge($warnings, $accountValidation['warnings']);
        }

        // 验证同步选项
        if (isset($parameters['force_sync'])) {
            if (!is_bool($parameters['force_sync'])) {
                $errors[] = "force_sync参数必须是布尔值";
            }
        }

        if (isset($parameters['batch_size'])) {
            if (!is_int($parameters['batch_size']) || $parameters['batch_size'] < 1 || $parameters['batch_size'] > 1000) {
                $errors[] = "batch_size参数必须是1-1000之间的整数";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * 验证基础任务数据
     */
    public function validateBasicTask(AsyncTask $task): array
    {
        $errors = [];
        $warnings = [];

        // 验证任务类型
        if (empty($task->getTaskType())) {
            $errors[] = "任务类型不能为空";
        }

        // 验证任务状态
        if (empty($task->getStatus())) {
            $errors[] = "任务状态不能为空";
        } elseif (!in_array($task->getStatus(), [
            AsyncTask::STATUS_PENDING,
            AsyncTask::STATUS_RUNNING,
            AsyncTask::STATUS_COMPLETED,
            AsyncTask::STATUS_FAILED,
            AsyncTask::STATUS_CANCELLED
        ])) {
            $errors[] = "无效的任务状态: {$task->getStatus()}";
        }

        // 验证进度
        $progress = $task->getProgress();
        if (!is_int($progress) || $progress < 0 || $progress > 100) {
            $errors[] = "进度必须是0-100之间的整数";
        }

        // 验证优先级
        $priority = $task->getPriority();
        if (!is_int($priority) || $priority < 1 || $priority > 10) {
            $warnings[] = "优先级建议是1-10之间的整数";
        }

        // 验证创建时间
        if (!$task->getCreatedAt()) {
            $errors[] = "创建时间不能为空";
        }

        // 验证更新时间
        if (!$task->getUpdatedAt()) {
            $warnings[] = "更新时间建议设置";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * 验证微信公众号账号
     */
    public function validateWechatAccount(string $accountId): array
    {
        $errors = [];
        $warnings = [];

        try {
            $account = $this->entityManager->getRepository(WechatPublicAccount::class)
                ->find($accountId);

            if (!$account) {
                $errors[] = "微信公众号账号不存在: {$accountId}";
                return ['valid' => false, 'errors' => $errors, 'warnings' => $warnings];
            }

            // 验证账号状态
            if (!$account->getIsActive()) {
                $warnings[] = "微信公众号账号未激活: {$accountId}";
            }

            // 验证必要配置
            if (empty($account->getAppId())) {
                $errors[] = "微信公众号缺少AppId配置";
            }

            if (empty($account->getAppSecret())) {
                $errors[] = "微信公众号缺少AppSecret配置";
            }

            // 验证配置格式
            if ($account->getAppId() && !preg_match('/^wx[a-f0-9]{16}$/', $account->getAppId())) {
                $errors[] = "AppId格式无效";
            }

            if ($account->getAppSecret() && strlen($account->getAppSecret()) !== 32) {
                $errors[] = "AppSecret长度无效，应为32位";
            }

        } catch (\Exception $e) {
            $errors[] = "验证微信公众号账号时发生异常: " . $e->getMessage();
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * 验证Official实体数据
     */
    public function validateOfficial(Official $official): array
    {
        $errors = [];
        $warnings = [];

        // 验证必要字段
        if (empty($official->getTitle())) {
            $errors[] = "文章标题不能为空";
        } elseif (strlen($official->getTitle()) > 100) {
            $warnings[] = "文章标题过长，建议控制在100字符以内";
        }

        if (empty($official->getContent())) {
            $errors[] = "文章内容不能为空";
        }

        if (empty($official->getArticleId())) {
            $errors[] = "文章ID不能为空";
        }

        // 验证状态
        if (!in_array($official->getStatus(), [1, 2])) {
            $warnings[] = "文章状态建议使用1(启用)或2(禁用)";
        }

        // 验证URL格式
        if ($official->getOriginalUrl() && !filter_var($official->getOriginalUrl(), FILTER_VALIDATE_URL)) {
            $errors[] = "原始URL格式无效";
        }

        if ($official->getThumbUrl() && !filter_var($official->getThumbUrl(), FILTER_VALIDATE_URL)) {
            $warnings[] = "缩略图URL格式可能无效";
        }

        // 验证微信公众号关联
        if (empty($official->getWechatAccountId())) {
            $errors[] = "微信公众号账号ID不能为空";
        }

        // 验证分类关联
        if ($official->getCategoryId() <= 0) {
            $warnings[] = "分类ID可能无效";
        }

        // 验证时间字段
        if (!$official->getCreateAt()) {
            $errors[] = "创建时间不能为空";
        }

        if (!$official->getUpdatedAt()) {
            $warnings[] = "更新时间建议设置";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * 验证批量数据一致性
     */
    public function validateBatchDataConsistency(array $officials, string $accountId): array
    {
        $errors = [];
        $warnings = [];

        if (empty($officials)) {
            $warnings[] = "批量数据为空";
            return ['valid' => true, 'errors' => $errors, 'warnings' => $warnings];
        }

        // 检查重复的article_id
        $articleIds = array_column($officials, 'article_id');
        $duplicateIds = array_diff_assoc($articleIds, array_unique($articleIds));
        if (!empty($duplicateIds)) {
            $errors[] = "存在重复的article_id: " . implode(', ', array_keys($duplicateIds));
        }

        // 验证每条数据
        foreach ($officials as $index => $officialData) {
            $itemErrors = [];
            $itemWarnings = [];

            // 基础字段验证
            if (empty($officialData['article_id'])) {
                $itemErrors[] = "第{$index}条数据缺少article_id";
            }

            if (empty($officialData['title'])) {
                $itemErrors[] = "第{$index}条数据缺少title";
            }

            if (empty($officialData['content'])) {
                $itemErrors[] = "第{$index}条数据缺少content";
            }

            // 验证微信公众号账号一致性
            if (isset($officialData['wechat_account_id']) && $officialData['wechat_account_id'] !== $accountId) {
                $itemErrors[] = "第{$index}条数据wechat_account_id不匹配";
            }

            // 验证时间格式
            if (isset($officialData['release_time']) && !empty($officialData['release_time'])) {
                if (!$this->isValidDateTime($officialData['release_time'])) {
                    $itemWarnings[] = "第{$index}条数据release_time格式可能无效";
                }
            }

            if (!empty($itemErrors)) {
                $errors = array_merge($errors, $itemErrors);
            }
            if (!empty($itemWarnings)) {
                $warnings = array_merge($warnings, $itemWarnings);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'total_count' => count($officials)
        ];
    }

    /**
     * 验证数据完整性约束
     */
    public function validateDataIntegrityConstraints(): array
    {
        $errors = [];
        $warnings = [];

        try {
            // 检查孤立的任务记录
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('COUNT(t.id)')
               ->from(AsyncTask::class, 't')
               ->where('t.status NOT IN (:validStatuses)')
               ->setParameter('validStatuses', [
                   AsyncTask::STATUS_PENDING,
                   AsyncTask::STATUS_RUNNING,
                   AsyncTask::STATUS_COMPLETED,
                   AsyncTask::STATUS_FAILED,
                   AsyncTask::STATUS_CANCELLED
               ]);

            $invalidTaskCount = $qb->getQuery()->getSingleScalarResult();
            if ($invalidTaskCount > 0) {
                $warnings[] = "发现{$invalidTaskCount}条无效状态的任务记录";
            }

            // 检查孤立的Official记录
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('COUNT(o.id)')
               ->from(Official::class, 'o')
               ->where('o.wechatAccountId IS NOT NULL')
               ->andWhere('o.wechatAccountId NOT IN (SELECT wa.id FROM App\Entity\WechatPublicAccount wa)');

            $orphanedOfficialCount = $qb->getQuery()->getSingleScalarResult();
            if ($orphanedOfficialCount > 0) {
                $warnings[] = "发现{$orphanedOfficialCount}条孤立的Official记录";
            }

            // 检查数据一致性
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('COUNT(o.id)')
               ->from(Official::class, 'o')
               ->where('(o.articleId IS NULL OR o.title IS NULL OR o.content IS NULL)')
               ->andWhere('o.isDeleted = false');

            $incompleteOfficialCount = $qb->getQuery()->getSingleScalarResult();
            if ($incompleteOfficialCount > 0) {
                $warnings[] = "发现{$incompleteOfficialCount}条不完整的Official记录";
            }

        } catch (\Exception $e) {
            $errors[] = "数据完整性约束验证失败: " . $e->getMessage();
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * 验证业务规则
     */
    public function validateBusinessRules(array $data, string $ruleType): array
    {
        $errors = [];
        $warnings = [];

        switch ($ruleType) {
            case 'wechat_sync_frequency':
                $this->validateSyncFrequency($data, $errors, $warnings);
                break;
            case 'article_uniqueness':
                $this->validateArticleUniqueness($data, $errors, $warnings);
                break;
            case 'content_length':
                $this->validateContentLength($data, $errors, $warnings);
                break;
            default:
                $warnings[] = "未知的业务规则类型: {$ruleType}";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * 验证同步频率限制
     */
    private function validateSyncFrequency(array $data, array &$errors, array &$warnings): void
    {
        if (!isset($data['account_id'])) {
            $errors[] = "缺少account_id参数";
            return;
        }

        try {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('COUNT(t.id)')
               ->from(AsyncTask::class, 't')
               ->where('t.taskType = :taskType')
               ->andWhere('t.parameters LIKE :accountId')
               ->andWhere('t.status IN (:activeStatuses)')
               ->andWhere('t.createdAt > :timeThreshold')
               ->setParameter('taskType', 'wechat_sync')
               ->setParameter('accountId', '%"' . $data['account_id'] . '"%')
               ->setParameter('activeStatuses', [AsyncTask::STATUS_PENDING, AsyncTask::STATUS_RUNNING])
               ->setParameter('timeThreshold', new \DateTime('-1 hour'));

            $activeSyncCount = $qb->getQuery()->getSingleScalarResult();

            if ($activeSyncCount >= 3) {
                $errors[] = "同一账号1小时内同步任务过多，请稍后再试";
            } elseif ($activeSyncCount >= 1) {
                $warnings[] = "同一账号已有正在进行的同步任务";
            }

        } catch (\Exception $e) {
            $errors[] = "验证同步频率时发生异常: " . $e->getMessage();
        }
    }

    /**
     * 验证文章唯一性
     */
    private function validateArticleUniqueness(array $data, array &$errors, array &$warnings): void
    {
        if (!isset($data['article_id']) || !isset($data['account_id'])) {
            $errors[] = "缺少article_id或account_id参数";
            return;
        }

        try {
            $existingOfficial = $this->entityManager->getRepository(Official::class)
                ->findOneBy([
                    'articleId' => $data['article_id'],
                    'wechatAccountId' => $data['account_id'],
                    'isDeleted' => false
                ]);

            if ($existingOfficial) {
                $warnings[] = "文章已存在: {$data['article_id']}";
            }

        } catch (\Exception $e) {
            $errors[] = "验证文章唯一性时发生异常: " . $e->getMessage();
        }
    }

    /**
     * 验证内容长度
     */
    private function validateContentLength(array $data, array &$errors, array &$warnings): void
    {
        if (!isset($data['content'])) {
            return;
        }

        $content = $data['content'];
        $contentLength = mb_strlen($content, 'UTF-8');

        if ($contentLength > 1000000) { // 1MB
            $errors[] = "文章内容过长，超过1MB限制";
        } elseif ($contentLength > 500000) { // 500KB
            $warnings[] = "文章内容较长，可能影响性能";
        }

        if ($contentLength < 10) {
            $warnings[] = "文章内容过短";
        }
    }

    /**
     * 验证日期时间格式
     */
    private function isValidDateTime(string $dateTime): bool
    {
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d',
            'Y-m-d\TH:i:s\Z',
            'Y-m-d\TH:i:sP'
        ];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateTime);
            if ($date !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取验证统计信息
     */
    public function getValidationStatistics(): array
    {
        $stats = [];

        try {
            // 统计任务验证状态
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('t.status, COUNT(t.id) as count')
               ->from(AsyncTask::class, 't')
               ->groupBy('t.status');

            $stats['task_status_stats'] = $qb->getQuery()->getResult();

            // 统计Official数据质量
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('COUNT(o.id) as total,
                        SUM(CASE WHEN o.articleId IS NULL THEN 1 ELSE 0 END) as missing_article_id,
                        SUM(CASE WHEN o.title IS NULL THEN 1 ELSE 0 END) as missing_title,
                        SUM(CASE WHEN o.content IS NULL THEN 1 ELSE 0 END) as missing_content')
               ->from(Official::class, 'o')
               ->where('o.isDeleted = false');

            $stats['official_data_quality'] = $qb->getQuery()->getSingleResult();

        } catch (\Exception $e) {
            $this->logger->error('获取验证统计信息失败', ['error' => $e->getMessage()]);
        }

        return $stats;
    }
}
