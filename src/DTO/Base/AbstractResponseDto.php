<?php

namespace App\DTO\Base;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * 抽象响应DTO基类
 * 用于处理API响应数据的传输对象
 */
abstract class AbstractResponseDto extends AbstractDto
{
    /**
     * 响应状态码
     */
    #[Assert\Type(type: 'integer', message: '状态码必须是整数')]
    #[Assert\Choice(choices: [200, 201, 204, 400, 401, 403, 404, 422, 500], message: '无效的状态码')]
    #[Groups(['response:read', 'response:write'])]
    protected int $statusCode = 200;

    /**
     * 响应消息
     */
    #[Assert\Type(type: 'string', message: '响应消息必须是字符串')]
    #[Assert\Length(max: 255, maxMessage: '响应消息不能超过255个字符')]
    #[Groups(['response:read', 'response:write'])]
    protected string $message = '';

    /**
     * 响应时间戳
     */
    #[Assert\Type(type: 'integer', message: '响应时间戳必须是整数')]
    #[Groups(['response:read', 'response:write'])]
    protected int $timestamp;

    /**
     * 响应唯一标识
     */
    #[Assert\Type(type: 'string', message: '响应ID必须是字符串')]
    #[Assert\Length(max: 100, maxMessage: '响应ID不能超过100个字符')]
    #[Groups(['response:read', 'response:write'])]
    protected ?string $responseId = null;

    /**
     * 请求ID（与请求对应）
     */
    #[Assert\Type(type: 'string', message: '请求ID必须是字符串')]
    #[Assert\Length(max: 100, maxMessage: '请求ID不能超过100个字符')]
    #[Groups(['response:read', 'response:write'])]
    protected ?string $requestId = null;

    /**
     * 处理时间（毫秒）
     */
    #[Assert\Type(type: 'integer', message: '处理时间必须是整数')]
    #[Assert\PositiveOrZero(message: '处理时间不能为负数')]
    #[Groups(['response:read', 'response:write'])]
    protected ?int $processingTime = null;

    /**
     * API版本
     */
    #[Assert\Type(type: 'string', message: 'API版本必须是字符串')]
    #[Assert\Length(max: 20, maxMessage: 'API版本不能超过20个字符')]
    #[Groups(['response:read', 'response:write'])]
    protected ?string $apiVersion = null;

    /**
     * 数据类型
     */
    #[Assert\Type(type: 'string', message: '数据类型必须是字符串')]
    #[Assert\Choice(choices: ['success', 'error', 'warning', 'info'], message: '无效的数据类型')]
    #[Groups(['response:read', 'response:write'])]
    protected string $dataType = 'success';

    /**
     * 错误代码（仅在错误响应时使用）
     */
    #[Assert\Type(type: 'string', message: '错误代码必须是字符串')]
    #[Assert\Length(max: 50, maxMessage: '错误代码不能超过50个字符')]
    #[Groups(['response:read', 'response:write'])]
    protected ?string $errorCode = null;

    /**
     * 错误详情（仅在错误响应时使用）
     */
    #[Assert\Type(type: 'array', message: '错误详情必须是数组')]
    #[Groups(['response:read', 'response:write'])]
    protected array $errorDetails = [];

    /**
     * 元数据
     */
    #[Assert\Type(type: 'array', message: '元数据必须是数组')]
    #[Groups(['response:read', 'response:write'])]
    protected array $metadata = [];

    /**
     * 构造函数
     *
     * @param int $statusCode 状态码
     * @param string $message 响应消息
     * @param array $data 初始化数据
     */
    public function __construct(int $statusCode = 200, string $message = '', array $data = [])
    {
        $this->statusCode = $statusCode;
        $this->message = $message;
        $this->timestamp = time();
        $this->responseId = $this->generateResponseId();

        // 根据状态码设置数据类型
        $this->setDataTypeByStatusCode($statusCode);

        if (!empty($data)) {
            $this->fromArray($data);
        }
    }

    /**
     * 生成唯一的响应ID
     *
     * @return string
     */
    protected function generateResponseId(): string
    {
        return uniqid('resp_', true) . '_' . mt_rand(1000, 9999);
    }

    /**
     * 根据状态码设置数据类型
     *
     * @param int $statusCode
     */
    protected function setDataTypeByStatusCode(int $statusCode): void
    {
        if ($statusCode >= 200 && $statusCode < 300) {
            $this->dataType = 'success';
        } elseif ($statusCode >= 400 && $statusCode < 500) {
            $this->dataType = 'error';
        } elseif ($statusCode >= 500) {
            $this->dataType = 'error';
        } else {
            $this->dataType = 'info';
        }
    }

    /**
     * 获取状态码
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * 设置状态码
     *
     * @param int $statusCode
     * @return self
     */
    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        $this->setDataTypeByStatusCode($statusCode);
        return $this;
    }

    /**
     * 获取响应消息
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * 设置响应消息
     *
     * @param string $message
     * @return self
     */
    public function setMessage(string $message): self
    {
        $this->message = $this->cleanString($message);
        return $this;
    }

    /**
     * 获取响应时间戳
     *
     * @return int
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * 设置响应时间戳
     *
     * @param int $timestamp
     * @return self
     */
    public function setTimestamp(int $timestamp): self
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    /**
     * 获取响应ID
     *
     * @return string|null
     */
    public function getResponseId(): ?string
    {
        return $this->responseId;
    }

    /**
     * 设置响应ID
     *
     * @param string|null $responseId
     * @return self
     */
    public function setResponseId(?string $responseId): self
    {
        $this->responseId = $this->cleanString($responseId);
        return $this;
    }

    /**
     * 获取请求ID
     *
     * @return string|null
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * 设置请求ID
     *
     * @param string|null $requestId
     * @return self
     */
    public function setRequestId(?string $requestId): self
    {
        $this->requestId = $this->cleanString($requestId);
        return $this;
    }

    /**
     * 获取处理时间
     *
     * @return int|null
     */
    public function getProcessingTime(): ?int
    {
        return $this->processingTime;
    }

    /**
     * 设置处理时间
     *
     * @param int|null $processingTime
     * @return self
     */
    public function setProcessingTime(?int $processingTime): self
    {
        $this->processingTime = $processingTime;
        return $this;
    }

    /**
     * 获取API版本
     *
     * @return string|null
     */
    public function getApiVersion(): ?string
    {
        return $this->apiVersion;
    }

    /**
     * 设置API版本
     *
     * @param string|null $apiVersion
     * @return self
     */
    public function setApiVersion(?string $apiVersion): self
    {
        $this->apiVersion = $this->cleanString($apiVersion);
        return $this;
    }

    /**
     * 获取数据类型
     *
     * @return string
     */
    public function getDataType(): string
    {
        return $this->dataType;
    }

    /**
     * 设置数据类型
     *
     * @param string $dataType
     * @return self
     */
    public function setDataType(string $dataType): self
    {
        $this->dataType = $dataType;
        return $this;
    }

    /**
     * 获取错误代码
     *
     * @return string|null
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * 设置错误代码
     *
     * @param string|null $errorCode
     * @return self
     */
    public function setErrorCode(?string $errorCode): self
    {
        $this->errorCode = $this->cleanString($errorCode);
        return $this;
    }

    /**
     * 获取错误详情
     *
     * @return array
     */
    public function getErrorDetails(): array
    {
        return $this->errorDetails;
    }

    /**
     * 设置错误详情
     *
     * @param array $errorDetails
     * @return self
     */
    public function setErrorDetails(array $errorDetails): self
    {
        $this->errorDetails = $errorDetails;
        return $this;
    }

    /**
     * 添加错误详情
     *
     * @param string $field 字段名
     * @param string $message 错误消息
     * @return self
     */
    public function addErrorDetail(string $field, string $message): self
    {
        $this->errorDetails[$field] = $message;
        return $this;
    }

    /**
     * 获取元数据
     *
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * 设置元数据
     *
     * @param array $metadata
     * @return self
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * 添加元数据
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function addMetadata(string $key, $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * 检查是否为成功响应
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * 检查是否为错误响应
     *
     * @return bool
     */
    public function isError(): bool
    {
        return $this->statusCode >= 400;
    }

    /**
     * 设置为成功响应
     *
     * @param string $message
     * @return self
     */
    public function setSuccess(string $message = '操作成功'): self
    {
        return $this->setStatusCode(200)->setMessage($message)->setDataType('success');
    }

    /**
     * 设置为错误响应
     *
     * @param string $message
     * @param string|null $errorCode
     * @return self
     */
    public function setError(string $message, ?string $errorCode = null): self
    {
        $this->setStatusCode(400)->setMessage($message)->setDataType('error');
        if ($errorCode) {
            $this->setErrorCode($errorCode);
        }
        return $this;
    }

    /**
     * 获取格式化的响应时间
     *
     * @param string $format
     * @return string
     */
    public function getFormattedTime(string $format = 'Y-m-d H:i:s'): string
    {
        return date($format, $this->timestamp);
    }

    /**
     * 获取响应摘要信息
     *
     * @return array
     */
    public function getResponseSummary(): array
    {
        return [
            'statusCode' => $this->statusCode,
            'message' => $this->message,
            'responseId' => $this->responseId,
            'requestId' => $this->requestId,
            'timestamp' => $this->timestamp,
            'formattedTime' => $this->getFormattedTime(),
            'processingTime' => $this->processingTime,
            'dataType' => $this->dataType,
            'apiVersion' => $this->apiVersion,
            'isSuccess' => $this->isSuccess(),
            'isError' => $this->isError(),
        ];
    }

    /**
     * 转换为数组（包含响应基础信息）
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_merge($this->getPublicProperties(), [
            'statusCode' => $this->statusCode,
            'message' => $this->message,
            'timestamp' => $this->timestamp,
            'responseId' => $this->responseId,
            'requestId' => $this->requestId,
            'processingTime' => $this->processingTime,
            'apiVersion' => $this->apiVersion,
            'dataType' => $this->dataType,
            'errorCode' => $this->errorCode,
            'errorDetails' => $this->errorDetails,
            'metadata' => $this->metadata,
        ]);
    }

    /**
     * 从数组创建实例（包含响应基础信息）
     *
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        $statusCode = $data['statusCode'] ?? 200;
        $message = $data['message'] ?? '';

        $instance = new static($statusCode, $message);

        // 设置基础属性
        if (isset($data['timestamp'])) {
            $instance->setTimestamp($data['timestamp']);
        }

        if (isset($data['responseId'])) {
            $instance->setResponseId($data['responseId']);
        }

        if (isset($data['requestId'])) {
            $instance->setRequestId($data['requestId']);
        }

        if (isset($data['processingTime'])) {
            $instance->setProcessingTime($data['processingTime']);
        }

        if (isset($data['apiVersion'])) {
            $instance->setApiVersion($data['apiVersion']);
        }

        if (isset($data['dataType'])) {
            $instance->setDataType($data['dataType']);
        }

        if (isset($data['errorCode'])) {
            $instance->setErrorCode($data['errorCode']);
        }

        if (isset($data['errorDetails'])) {
            $instance->setErrorDetails($data['errorDetails']);
        }

        if (isset($data['metadata'])) {
            $instance->setMetadata($data['metadata']);
        }

        // 设置其他公共属性
        $instance->setPublicProperties($data);

        return $instance;
    }

    /**
     * 创建成功响应
     *
     * @param string $message
     * @param array $data
     * @return static
     */
    public static function success(string $message = '操作成功', array $data = []): static
    {
        return (new static(200, $message, $data))->setSuccess($message);
    }

    /**
     * 创建错误响应
     *
     * @param string $message
     * @param string|null $errorCode
     * @param array $data
     * @return static
     */
    public static function error(string $message = '操作失败', ?string $errorCode = null, array $data = []): static
    {
        return (new static(400, $message, $data))->setError($message, $errorCode);
    }

    /**
     * 创建未找到响应
     *
     * @param string $message
     * @param array $data
     * @return static
     */
    public static function notFound(string $message = '资源未找到', array $data = []): static
    {
        return new static(404, $message, $data);
    }

    /**
     * 创建验证失败响应
     *
     * @param array $errors
     * @param string $message
     * @param array $data
     * @return static
     */
    public static function validationFailed(array $errors, string $message = '数据验证失败', array $data = []): static
    {
        $instance = new static(422, $message, $data);
        $instance->setErrorDetails($errors);
        return $instance;
    }

    /**
     * 创建服务器错误响应
     *
     * @param string $message
     * @param array $data
     * @return static
     */
    public static function serverError(string $message = '服务器内部错误', array $data = []): static
    {
        return new static(500, $message, $data);
    }
}
