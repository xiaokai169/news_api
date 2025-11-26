<?php

namespace App\DTO\Request\Wechat;

use App\DTO\Base\AbstractRequestDto;
use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

/**
 * 同步公众号请求DTO
 */
#[OA\Schema(
    schema: 'SyncWechatDto',
    title: '同步公众号请求',
    description: '用于同步微信公众号的请求数据传输对象'
)]
class SyncWechatDto extends AbstractRequestDto
{
    /**
     * 公众号ID
     */
    #[Assert\NotBlank(message: '公众号ID不能为空')]
    #[Assert\Type(type: 'string', message: '公众号ID必须是字符串')]
    #[Assert\Length(max: 100, maxMessage: '公众号ID不能超过100个字符')]
    #[OA\Property(
        description: '微信公众号ID',
        example: 'wx1234567890abcdef',
        maxLength: 100
    )]
    protected string $publicAccountId = '';

    /**
     * 同步类型
     */
    #[Assert\Choice(choices: ['info', 'articles', 'menu', 'all'], message: '同步类型必须是info、articles、menu或all')]
    #[OA\Property(
        description: '同步类型：info-基本信息，articles-文章，menu-菜单，all-全部',
        enum: ['info', 'articles', 'menu', 'all'],
        example: 'all'
    )]
    protected string $syncType = 'all';

    /**
     * 是否强制同步
     */
    #[Assert\Type(type: 'bool', message: '强制同步必须是布尔值')]
    #[OA\Property(
        description: '是否强制同步（覆盖现有数据）',
        example: false
    )]
    protected bool $forceSync = false;

    /**
     * 同步范围
     */
    #[Assert\Choice(choices: ['recent', 'all', 'custom'], message: '同步范围必须是recent、all或custom')]
    #[OA\Property(
        description: '同步范围：recent-最近，all-全部，custom-自定义',
        enum: ['recent', 'all', 'custom'],
        example: 'recent'
    )]
    protected string $syncScope = 'recent';

    /**
     * 同步开始时间
     */
    #[Assert\DateTime(message: '同步开始时间格式不正确')]
    #[OA\Property(
        description: '同步开始时间（用于custom范围）',
        example: '2024-01-01 00:00:00',
        format: 'date-time'
    )]
    protected ?string $syncStartTime = null;

    /**
     * 同步结束时间
     */
    #[Assert\DateTime(message: '同步结束时间格式不正确')]
    #[OA\Property(
        description: '同步结束时间（用于custom范围）',
        example: '2024-01-31 23:59:59',
        format: 'date-time'
    )]
    protected ?string $syncEndTime = null;

    /**
     * 文章数量限制
     */
    #[Assert\Type(type: 'integer', message: '文章数量限制必须是整数')]
    #[Assert\Positive(message: '文章数量限制必须大于0')]
    #[Assert\LessThanOrEqual(value: 1000, message: '文章数量限制不能超过1000')]
    #[OA\Property(
        description: '同步文章数量限制（仅用于recent范围）',
        example: 50,
        minimum: 1,
        maximum: 1000
    )]
    protected ?int $articleLimit = null;

    /**
     * 是否包含已删除内容
     */
    #[Assert\Type(type: 'bool', message: '包含已删除内容必须是布尔值')]
    #[OA\Property(
        description: '是否包含已删除的文章或菜单',
        example: false
    )]
    protected bool $includeDeleted = false;

    /**
     * 是否自动处理重复内容
     */
    #[Assert\Type(type: 'bool', message: '自动处理重复内容必须是布尔值')]
    #[OA\Property(
        description: '是否自动处理重复内容（更新或跳过）',
        example: true
    )]
    protected bool $autoHandleDuplicates = true;

    /**
     * 重复内容处理方式
     */
    #[Assert\Choice(choices: ['skip', 'update', 'replace'], message: '重复处理方式必须是skip、update或replace')]
    #[OA\Property(
        description: '重复内容处理方式：skip-跳过，update-更新，replace-替换',
        enum: ['skip', 'update', 'replace'],
        example: 'update'
    )]
    protected string $duplicateAction = 'update';

    /**
     * 同步回调URL
     */
    #[Assert\Url(message: '同步回调URL格式不正确')]
    #[OA\Property(
        description: '同步完成后的回调通知URL',
        example: 'https://example.com/callback/sync'
    )]
    protected ?string $callbackUrl = null;

    /**
     * 是否异步执行
     */
    #[Assert\Type(type: 'bool', message: '异步执行必须是布尔值')]
    #[OA\Property(
        description: '是否异步执行同步任务',
        example: true
    )]
    protected bool $async = true;

    /**
     * 任务优先级
     */
    #[Assert\Type(type: 'integer', message: '任务优先级必须是整数')]
    #[Assert\Range(min: 1, max: 10, notInRangeMessage: '任务优先级必须在1-10之间')]
    #[OA\Property(
        description: '同步任务优先级（1-10，数字越大优先级越高）',
        example: 5,
        minimum: 1,
        maximum: 10
    )]
    protected int $priority = 5;

    /**
     * 自定义选项
     */
    #[Assert\Type(type: 'array', message: '自定义选项必须是数组')]
    #[OA\Property(
        description: '自定义同步选项',
        type: 'object',
        additionalProperties: new OA\AdditionalProperties(type: 'mixed')
    )]
    protected array $customOptions = [];

    /**
     * 构造函数
     *
     * @param array $data 初始化数据
     */
    public function __construct(array $data = [])
    {
        parent::__construct($data);
        if (!empty($data)) {
            $this->populateFromData($data);
        }
    }

    /**
     * 获取公众号ID
     *
     * @return string
     */
    public function getPublicAccountId(): string
    {
        return $this->publicAccountId;
    }

    /**
     * 设置公众号ID
     *
     * @param string $publicAccountId
     * @return self
     */
    public function setPublicAccountId(string $publicAccountId): self
    {
        $this->publicAccountId = $this->cleanString($publicAccountId);
        return $this;
    }

    /**
     * 获取同步类型
     *
     * @return string
     */
    public function getSyncType(): string
    {
        return $this->syncType;
    }

    /**
     * 设置同步类型
     *
     * @param string $syncType
     * @return self
     */
    public function setSyncType(string $syncType): self
    {
        $this->syncType = in_array($syncType, ['info', 'articles', 'menu', 'all']) ? $syncType : 'all';
        return $this;
    }

    /**
     * 是否强制同步
     *
     * @return bool
     */
    public function isForceSync(): bool
    {
        return $this->forceSync;
    }

    /**
     * 设置强制同步
     *
     * @param bool $forceSync
     * @return self
     */
    public function setForceSync(bool $forceSync): self
    {
        $this->forceSync = $forceSync;
        return $this;
    }

    /**
     * 获取同步范围
     *
     * @return string
     */
    public function getSyncScope(): string
    {
        return $this->syncScope;
    }

    /**
     * 设置同步范围
     *
     * @param string $syncScope
     * @return self
     */
    public function setSyncScope(string $syncScope): self
    {
        $this->syncScope = in_array($syncScope, ['recent', 'all', 'custom']) ? $syncScope : 'recent';
        return $this;
    }

    /**
     * 获取同步开始时间
     *
     * @return string|null
     */
    public function getSyncStartTime(): ?string
    {
        return $this->syncStartTime;
    }

    /**
     * 设置同步开始时间
     *
     * @param string|null $syncStartTime
     * @return self
     */
    public function setSyncStartTime(?string $syncStartTime): self
    {
        $this->syncStartTime = $this->cleanString($syncStartTime);
        return $this;
    }

    /**
     * 获取同步结束时间
     *
     * @return string|null
     */
    public function getSyncEndTime(): ?string
    {
        return $this->syncEndTime;
    }

    /**
     * 设置同步结束时间
     *
     * @param string|null $syncEndTime
     * @return self
     */
    public function setSyncEndTime(?string $syncEndTime): self
    {
        $this->syncEndTime = $this->cleanString($syncEndTime);
        return $this;
    }

    /**
     * 获取文章数量限制
     *
     * @return int|null
     */
    public function getArticleLimit(): ?int
    {
        return $this->articleLimit;
    }

    /**
     * 设置文章数量限制
     *
     * @param int|null $articleLimit
     * @return self
     */
    public function setArticleLimit(?int $articleLimit): self
    {
        $this->articleLimit = $articleLimit ? max(1, min(1000, $articleLimit)) : null;
        return $this;
    }

    /**
     * 是否包含已删除内容
     *
     * @return bool
     */
    public function isIncludeDeleted(): bool
    {
        return $this->includeDeleted;
    }

    /**
     * 设置包含已删除内容
     *
     * @param bool $includeDeleted
     * @return self
     */
    public function setIncludeDeleted(bool $includeDeleted): self
    {
        $this->includeDeleted = $includeDeleted;
        return $this;
    }

    /**
     * 是否自动处理重复内容
     *
     * @return bool
     */
    public function isAutoHandleDuplicates(): bool
    {
        return $this->autoHandleDuplicates;
    }

    /**
     * 设置自动处理重复内容
     *
     * @param bool $autoHandleDuplicates
     * @return self
     */
    public function setAutoHandleDuplicates(bool $autoHandleDuplicates): self
    {
        $this->autoHandleDuplicates = $autoHandleDuplicates;
        return $this;
    }

    /**
     * 获取重复内容处理方式
     *
     * @return string
     */
    public function getDuplicateAction(): string
    {
        return $this->duplicateAction;
    }

    /**
     * 设置重复内容处理方式
     *
     * @param string $duplicateAction
     * @return self
     */
    public function setDuplicateAction(string $duplicateAction): self
    {
        $this->duplicateAction = in_array($duplicateAction, ['skip', 'update', 'replace']) ? $duplicateAction : 'update';
        return $this;
    }

    /**
     * 获取回调URL
     *
     * @return string|null
     */
    public function getCallbackUrl(): ?string
    {
        return $this->callbackUrl;
    }

    /**
     * 设置回调URL
     *
     * @param string|null $callbackUrl
     * @return self
     */
    public function setCallbackUrl(?string $callbackUrl): self
    {
        $this->callbackUrl = $this->cleanString($callbackUrl);
        return $this;
    }

    /**
     * 是否异步执行
     *
     * @return bool
     */
    public function isAsync(): bool
    {
        return $this->async;
    }

    /**
     * 设置异步执行
     *
     * @param bool $async
     * @return self
     */
    public function setAsync(bool $async): self
    {
        $this->async = $async;
        return $this;
    }

    /**
     * 获取任务优先级
     *
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * 设置任务优先级
     *
     * @param int $priority
     * @return self
     */
    public function setPriority(int $priority): self
    {
        $this->priority = max(1, min(10, $priority));
        return $this;
    }

    /**
     * 获取自定义选项
     *
     * @return array
     */
    public function getCustomOptions(): array
    {
        return $this->customOptions;
    }

    /**
     * 设置自定义选项
     *
     * @param array $customOptions
     * @return self
     */
    public function setCustomOptions(array $customOptions): self
    {
        $this->customOptions = $customOptions;
        return $this;
    }

    /**
     * 添加自定义选项
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function addCustomOption(string $key, $value): self
    {
        $this->customOptions[$key] = $value;
        return $this;
    }

    /**
     * 从数据数组填充属性
     *
     * @param array $data
     * @return self
     */
    public function populateFromData(array $data): self
    {
        if (isset($data['publicAccountId'])) {
            $this->setPublicAccountId($data['publicAccountId']);
        }

        if (isset($data['syncType'])) {
            $this->setSyncType($data['syncType']);
        }

        if (isset($data['forceSync'])) {
            $this->setForceSync((bool)$data['forceSync']);
        }

        if (isset($data['syncScope'])) {
            $this->setSyncScope($data['syncScope']);
        }

        if (isset($data['syncStartTime'])) {
            $this->setSyncStartTime($data['syncStartTime']);
        }

        if (isset($data['syncEndTime'])) {
            $this->setSyncEndTime($data['syncEndTime']);
        }

        if (isset($data['articleLimit'])) {
            $this->setArticleLimit($data['articleLimit']);
        }

        if (isset($data['includeDeleted'])) {
            $this->setIncludeDeleted((bool)$data['includeDeleted']);
        }

        if (isset($data['autoHandleDuplicates'])) {
            $this->setAutoHandleDuplicates((bool)$data['autoHandleDuplicates']);
        }

        if (isset($data['duplicateAction'])) {
            $this->setDuplicateAction($data['duplicateAction']);
        }

        if (isset($data['callbackUrl'])) {
            $this->setCallbackUrl($data['callbackUrl']);
        }

        if (isset($data['async'])) {
            $this->setAsync((bool)$data['async']);
        }

        if (isset($data['priority'])) {
            $this->setPriority((int)$data['priority']);
        }

        if (isset($data['customOptions']) && is_array($data['customOptions'])) {
            $this->setCustomOptions($data['customOptions']);
        }

        return $this;
    }

    /**
     * 转换为数组
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'publicAccountId' => $this->publicAccountId,
            'syncType' => $this->syncType,
            'forceSync' => $this->forceSync,
            'syncScope' => $this->syncScope,
            'syncStartTime' => $this->syncStartTime,
            'syncEndTime' => $this->syncEndTime,
            'articleLimit' => $this->articleLimit,
            'includeDeleted' => $this->includeDeleted,
            'autoHandleDuplicates' => $this->autoHandleDuplicates,
            'duplicateAction' => $this->duplicateAction,
            'callbackUrl' => $this->callbackUrl,
            'async' => $this->async,
            'priority' => $this->priority,
            'customOptions' => $this->customOptions,
        ]);
    }

    /**
     * 验证同步数据
     *
     * @return array 验证错误数组
     */
    public function validateSyncData(): array
    {
        $errors = [];

        // 验证公众号ID
        if (empty($this->publicAccountId)) {
            $errors['publicAccountId'] = '公众号ID不能为空';
        } elseif (strlen($this->publicAccountId) > 100) {
            $errors['publicAccountId'] = '公众号ID不能超过100个字符';
        }

        // 验证时间范围
        if ($this->syncStartTime && $this->syncEndTime) {
            $startTime = strtotime($this->syncStartTime);
            $endTime = strtotime($this->syncEndTime);
            if ($startTime && $endTime && $startTime > $endTime) {
                $errors['timeRange'] = '同步开始时间不能大于结束时间';
            }
        }

        // 验证自定义范围的必要条件
        if ($this->syncScope === 'custom' && (!$this->syncStartTime || !$this->syncEndTime)) {
            $errors['customRange'] = '自定义范围必须提供开始时间和结束时间';
        }

        // 验证recent范围的必要条件
        if ($this->syncScope === 'recent' && !$this->articleLimit) {
            $errors['recentRange'] = 'recent范围必须提供文章数量限制';
        }

        // 验证回调URL
        if ($this->callbackUrl && !filter_var($this->callbackUrl, FILTER_VALIDATE_URL)) {
            $errors['callbackUrl'] = '回调URL格式不正确';
        }

        return array_merge($errors, $this->validateRequest());
    }

    /**
     * 检查是否有有效的同步数据
     *
     * @return bool
     */
    public function hasValidSyncData(): bool
    {
        return !empty($this->publicAccountId);
    }

    /**
     * 获取同步摘要信息
     *
     * @return array
     */
    public function getSyncSummary(): array
    {
        return [
            'publicAccountId' => $this->publicAccountId,
            'syncType' => $this->syncType,
            'syncScope' => $this->syncScope,
            'forceSync' => $this->forceSync,
            'async' => $this->async,
            'priority' => $this->priority,
            'articleLimit' => $this->articleLimit,
            'includeDeleted' => $this->includeDeleted,
            'autoHandleDuplicates' => $this->autoHandleDuplicates,
            'duplicateAction' => $this->duplicateAction,
            'hasValidData' => $this->hasValidSyncData(),
            'timeRange' => [
                'start' => $this->syncStartTime,
                'end' => $this->syncEndTime,
            ],
            'callbackUrl' => $this->callbackUrl,
            'customOptionsCount' => count($this->customOptions),
            'requestSummary' => $this->getRequestSummary(),
        ];
    }
}
