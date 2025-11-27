<?php

namespace App\DTO\Request;

use App\DTO\Base\AbstractRequestDto;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * 清理阅读记录DTO
 */
class CleanupReadLogsDto extends AbstractRequestDto
{
    #[Assert\NotBlank(message: '清理日期不能为空')]
    #[Assert\DateTime(format: 'Y-m-d', message: '日期格式必须是Y-m-d')]
    public string $beforeDate;

    public function __construct(array $data = [])
    {
        $this->beforeDate = $data['beforeDate'] ?? '';
        parent::__construct($data);
    }

    public function getBeforeDate(): string
    {
        return $this->beforeDate;
    }

    public function setBeforeDate(string $beforeDate): self
    {
        $this->beforeDate = $beforeDate;
        return $this;
    }

    /**
     * 验证业务规则
     */
    public function validateBusinessRules(): array
    {
        $errors = [];

        try {
            $beforeDate = new \DateTime($this->beforeDate);

            // 不允许清理最近30天的数据
            $minDate = (new \DateTime())->sub(new \DateInterval('P30D'));
            if ($beforeDate > $minDate) {
                $errors[] = '不能清理最近30天的数据';
            }
        } catch (\Exception $e) {
            $errors[] = '日期格式不正确';
        }

        return $errors;
    }
}
