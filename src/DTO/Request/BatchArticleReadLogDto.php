<?php

namespace App\DTO\Request;

use App\DTO\Base\AbstractRequestDto;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * 批量文章阅读记录DTO
 */
class BatchArticleReadLogDto extends AbstractRequestDto
{
    #[Assert\NotBlank(message: '阅读记录数据不能为空')]
    #[Assert\Type(type: 'array', message: '阅读记录必须是数组格式')]
    #[Assert\Count(
        min: 1,
        max: 100,
        minMessage: '至少需要1条记录',
        maxMessage: '批量操作不能超过100条记录'
    )]
    public array $readLogs;

    public function __construct(array $data = [])
    {
        $this->readLogs = $data['readLogs'] ?? [];
        parent::__construct($data);
    }

    public function getReadLogs(): array
    {
        return $this->readLogs;
    }

    public function setReadLogs(array $readLogs): self
    {
        $this->readLogs = $readLogs;
        return $this;
    }

    /**
     * 验证业务规则
     */
    public function validateBusinessRules(): array
    {
        $errors = [];

        if (empty($this->readLogs)) {
            $errors[] = '阅读记录数据不能为空';
        }

        return $errors;
    }
}
