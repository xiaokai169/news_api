<?php

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

readonly class ApiResponse
{
    public function __construct(private NormalizerInterface $normalizer)
    {
    }

    public function success(mixed $data = null, int $status = Response::HTTP_OK, array $context = [], array $headers = [], ?Request $request = null): JsonResponse
    {
        // 对于成功的响应，状态码应该在 200-299 范围内
        if ($status < 200 || $status > 299) {
            // 如果不是成功状态码，自动转换为 200
            $status = Response::HTTP_OK;
        }

        $payload = [
            'status' => (string) $status,
            'message' => 'success',
            'data' => $this->normalizeData($data, $context),
            'timestamp' => time(),
        ];

        if ($request) {
            $payload['path'] = $request->getPathInfo();
        }

        return new JsonResponse($payload, $status, $headers);
    }

    public function created(mixed $data = null, array $context = [], array $headers = [], ?Request $request = null): JsonResponse
    {
        return $this->success($data, Response::HTTP_CREATED, $context, $headers, $request);
    }

    public function accepted(mixed $data = null, array $context = [], array $headers = [], ?Request $request = null): JsonResponse
    {
        return $this->success($data, Response::HTTP_ACCEPTED, $context, $headers, $request);
    }

    public function noContent(array $headers = [], ?Request $request = null): JsonResponse
    {
        // 204 No Content 不应该有响应体
        $payload = [
            'status' => (string) Response::HTTP_NO_CONTENT,
            'message' => 'No Content',
            'timestamp' => time(),
        ];

        if ($request) {
            $payload['path'] = $request->getPathInfo();
        }

        return new JsonResponse($payload, Response::HTTP_NO_CONTENT, $headers);
    }

    public function paginated($items, int $total, int $page, int $limit, int $pages, ?Request $request = null, array $context = []): JsonResponse
    {
        $data = [
            'items' => $this->normalizeData($items, $context),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => $pages,
        ];
        // 分页查询成功返回 200 OK
        return $this->success(
            data: $data,
            status: Response::HTTP_OK,
            context: $context,
            headers: [],
            request: $request
        );
    }

    public function error(string $message, int $status = Response::HTTP_BAD_REQUEST, ?array $details = null, ?Request $request = null, array $headers = []): JsonResponse
    {
        // 更灵活的状态码处理
        if ($status < 100 || $status > 599) {
            // 如果状态码完全无效，使用默认错误码
            $status = Response::HTTP_INTERNAL_SERVER_ERROR;
        } elseif ($status >= 200 && $status <= 299) {
            // 如果传入的是成功状态码，转换为相应的错误码
            $status = Response::HTTP_BAD_REQUEST;
        }

        $payload = [
            'status' => (string) $status,
            'message' => $message,
            'timestamp' => time(),
        ];

        if ($request) {
            $payload['path'] = $request->getPathInfo();
        }

        if ($details !== null) {
            $payload['errors'] = $details;
        }

        return new JsonResponse($payload, $status, $headers);
    }

    // 常用的错误响应快捷方法
    public function badRequest(string $message = 'Bad Request', ?array $details = null, ?Request $request = null): JsonResponse
    {
        return $this->error($message, Response::HTTP_BAD_REQUEST, $details, $request);
    }

    public function unauthorized(string $message = 'Unauthorized', ?Request $request = null): JsonResponse
    {
        return $this->error($message, Response::HTTP_UNAUTHORIZED, null, $request);
    }

    public function forbidden(string $message = 'Forbidden', ?Request $request = null): JsonResponse
    {
        return $this->error($message, Response::HTTP_FORBIDDEN, null, $request);
    }

    public function notFound(string $message = 'Resource not found', ?Request $request = null): JsonResponse
    {
        return $this->error($message, Response::HTTP_NOT_FOUND, null, $request);
    }

    public function methodNotAllowed(string $message = 'Method Not Allowed', ?Request $request = null): JsonResponse
    {
        return $this->error($message, Response::HTTP_METHOD_NOT_ALLOWED, null, $request);
    }

    public function validationError(array $errors, string $message = 'Validation failed', ?Request $request = null): JsonResponse
    {
        return $this->error($message, Response::HTTP_UNPROCESSABLE_ENTITY, $errors, $request);
    }

    public function internalServerError(string $message = 'Internal Server Error', ?Request $request = null): JsonResponse
    {
        return $this->error($message, Response::HTTP_INTERNAL_SERVER_ERROR, null, $request);
    }

    private function normalizeData(mixed $data, array $context): mixed
    {
        if ($data === null) {
            return null;
        }

        // 如果是数组，递归处理每个元素
        if (is_array($data)) {
            return array_map(function ($item) use ($context) {
                return $this->normalizeData($item, $context);
            }, $data);
        }

        // 如果是可归一化的对象，使用 normalizer
        if (is_object($data)) {
            try {
                // 检查对象是否有需要处理的关系
                if (method_exists($data, '__toString')) {
                    // 对于简单的对象，返回字符串表示
                    return $data->__toString();
                }

                // 为实体对象指定序列化组，避免循环引用
                // 优先使用传入的 context 中的 groups，如果没有则根据实体类型推断
                $groups = $context['groups'] ?? [];
                if (empty($groups)) {
                    if (strpos(get_class($data), 'SysNewsArticle') !== false) {
                        $groups = ['sysNewsArticle:read'];
                    } elseif (strpos(get_class($data), 'SysNewsArticleCategory') !== false) {
                        $groups = ['SysNewsArticleCategory:read'];
                    } else {
                        $groups = ['official:read'];
                    }
                }

                $context['groups'] = $groups;
                return $this->normalizer->normalize($data, null, $context);
            } catch (\Throwable $e) {
                // 更详细的错误日志
                if (function_exists('error_log')) {
                    error_log('Normalization failed for ' . get_class($data) . ': ' . $e->getMessage());
                    error_log('Trace: ' . $e->getTraceAsString());
                }

                // 返回更安全的数据结构
                if (method_exists($data, 'getId')) {
                    return [
                        'id' => $data->getId(),
                        'error' => 'Partial data due to normalization error'
                    ];
                }

                return ['error' => 'Data normalization failed'];
            }
        }

        return $data;
    }
}
