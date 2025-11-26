<?php

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BusinessException extends HttpException
{
    public function __construct(
        string $message = 'Business logic error',
        int $statusCode = Response::HTTP_BAD_REQUEST,
        \Throwable $previous = null,
        array $headers = [],
        ?int $code = 0
    ) {
        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }

    /**
     * 创建状态值无效异常
     */
    public static function invalidStatus(): self
    {
        return new self('状态值无效，必须是1(已发布)或2(未发布)', Response::HTTP_BAD_REQUEST);
    }

    /**
     * 创建文章不存在异常
     */
    public static function articleNotFound(): self
    {
        return new self('新闻文章不存在', Response::HTTP_NOT_FOUND);
    }

    /**
     * 创建操作失败异常
     */
    public static function operationFailed(string $message = '操作失败'): self
    {
        return new self($message, Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
