<?php

namespace App\EventSubscriber;

use App\Exception\BusinessException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $exception = $event->getThrowable();

        // 🔍 判断是否为 API 请求（处理 /api、/official-api 和 /public-api 路径）
        $path = $request->getPathInfo();
        $isApiRequest = str_starts_with($path, '/api') ||
                        str_starts_with($path, '/official-api') ||
                        str_starts_with($path, '/public-api');

        // 如果不是 API 请求，不处理，交给 Symfony 默认异常处理（比如返回 HTML 500 页面）
        if (!$isApiRequest) {
            return;
        }

        // 🔍 临时禁用API异常处理以调试NelmioApiDocBundle
        if (str_contains($path, '/api/doc')) {
            return; // 让NelmioApiDocBundle的异常正常显示
        }

        // 🧠 根据异常类型构造统一 JSON 格式响应
        $response = $this->createApiResponse($exception);

        // 📤 设置响应对象
        $event->setResponse($response);
    }

    private function createApiResponse(Throwable $exception): JsonResponse
    {
        $statusCode = $this->resolveStatusCode($exception);
        $message = $this->resolveUserMessage($exception);
        $debug = $_ENV['APP_DEBUG'] ?? false;

        $data = [
            'success' => false,
            'message' => $message,
        ];
        if ($exception instanceof ForeignKeyConstraintViolationException) {
            return new JsonResponse([
                'success' => false,
                'message' => '无法删除：存在关联数据，请先处理相关记录',
            ], Response::HTTP_CONFLICT); // 409 Conflict
        }
        // 🔒 生产环境不返回详细错误，避免泄露敏感信息
        if ($debug) {
            $data['error'] = $exception->getMessage();
            // 可选：返回更多调试信息，如文件、行号等（谨慎使用！）
            // $data['trace'] = $exception->getTrace();
        }

        // ✅ 特殊处理表单/参数验证异常
        if ($exception instanceof ValidationFailedException) {
            $data = $this->handleValidationException($exception, $data);
        }

        return new JsonResponse($data, $statusCode);
    }

    private function resolveStatusCode(Throwable $exception): int
    {
        // 根据异常类型返回合适的 HTTP 状态码
        if ($exception instanceof NotFoundHttpException) {
            return Response::HTTP_NOT_FOUND; // 404
        } elseif ($exception instanceof AccessDeniedHttpException) {
            return Response::HTTP_FORBIDDEN; // 403
        } elseif ($exception instanceof BadRequestHttpException) {
            return Response::HTTP_BAD_REQUEST; // 400
        } elseif ($exception instanceof ValidationFailedException) {
            return Response::HTTP_BAD_REQUEST; // 400
        } elseif ($exception instanceof BusinessException) {
            return $exception->getStatusCode(); // 使用BusinessException自身的状态码
        }

        // 默认：500 服务器内部错误
        return Response::HTTP_INTERNAL_SERVER_ERROR; // 500
    }

    private function resolveUserMessage(Throwable $exception): string
    {
        $debug = $_ENV['APP_DEBUG'] ?? false;

        if ($debug) {
            return $exception->getMessage();
        }

        // 对BusinessException显示具体的错误消息，其他异常显示通用提示
        if ($exception instanceof BusinessException) {
            return $exception->getMessage();
        }

        // 对用户显示友好、通用的提示，避免泄露细节
        return 'An error occurred. Please try again later.';
    }

    private function handleValidationException(ValidationFailedException $e, array $data): array
    {
        $violations = $e->getViolations();

        $errors = [];

        /** @var \Symfony\Component\Validator\ConstraintViolationInterface $violation */
        foreach ($violations as $violation) {
            $propertyPath = $violation->getPropertyPath(); // e.g. "email", "user.address.city"
            $message = $violation->getMessage();

            // 可选：对 propertyPath 做格式优化，比如去掉开头的 "data." 等
            // 这里直接使用原值，你可以按需格式化
            $errors[$propertyPath] = $message;
        }

        $data['message'] = 'Validation failed';
        $data['errors'] = $errors;

        return $data;
    }
}
