<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * CORS调试订阅者
 * 用于追踪CORS请求的完整处理流程
 */
class CorsDebugSubscriber implements EventSubscriberInterface
{
    private array $debugLog = [];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 999], // 优先级高，确保在其他监听器之前执行
            KernelEvents::RESPONSE => ['onKernelResponse', -999], // 优先级低，确保在其他监听器之后执行
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $method = $request->getMethod();

        // 只记录API请求的调试信息
        $isApiRequest = str_starts_with($path, '/api') ||
                        str_starts_with($path, '/official-api') ||
                        str_starts_with($path, '/public-api');

        if (!$isApiRequest) {
            return;
        }

        $this->debugLog[] = [
            'timestamp' => microtime(true),
            'event' => 'REQUEST_START',
            'method' => $method,
            'path' => $path,
            'origin' => $request->headers->get('Origin'),
            'request_method' => $request->headers->get('Access-Control-Request-Method'),
            'request_headers' => $request->headers->get('Access-Control-Request-Headers'),
            'content_type' => $request->headers->get('Content-Type'),
            'authorization' => $request->headers->get('Authorization') ? 'present' : 'absent',
            'user_agent' => $request->headers->get('User-Agent'),
            'all_headers' => $this->sanitizeHeaders($request->headers->all()),
            'query_params' => $request->query->all(),
            'request_data' => $method !== 'GET' ? $this->sanitizeRequestData($request->request->all()) : []
        ];

        // 特殊处理OPTIONS请求
        if ($method === 'OPTIONS') {
            $this->debugLog[] = [
                'timestamp' => microtime(true),
                'event' => 'OPTIONS_DETECTED',
                'is_preflight' => true,
                'cors_headers' => [
                    'origin' => $request->headers->get('Origin'),
                    'request_method' => $request->headers->get('Access-Control-Request-Method'),
                    'request_headers' => $request->headers->get('Access-Control-Request-Headers')
                ]
            ];
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $path = $request->getPathInfo();

        // 只记录API请求的调试信息
        $isApiRequest = str_starts_with($path, '/api') ||
                        str_starts_with($path, '/official-api') ||
                        str_starts_with($path, '/public-api');

        if (!$isApiRequest) {
            return;
        }

        $this->debugLog[] = [
            'timestamp' => microtime(true),
            'event' => 'RESPONSE_END',
            'status_code' => $response->getStatusCode(),
            'cors_headers' => [
                'access_control_allow_origin' => $response->headers->get('Access-Control-Allow-Origin'),
                'access_control_allow_methods' => $response->headers->get('Access-Control-Allow-Methods'),
                'access_control_allow_headers' => $response->headers->get('Access-Control-Allow-Headers'),
                'access_control_max_age' => $response->headers->get('Access-Control-Max-Age'),
                'access_control_expose_headers' => $response->headers->get('Access-Control-Expose-Headers')
            ],
            'all_response_headers' => $response->headers->all(),
            'content_type' => $response->headers->get('Content-Type'),
            'content_length' => $response->headers->get('Content-Length')
        ];

        // 在响应结束时写入日志
        $this->writeDebugLog($request);
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $exception = $event->getThrowable();
        $path = $request->getPathInfo();

        // 只记录API请求的调试信息
        $isApiRequest = str_starts_with($path, '/api') ||
                        str_starts_with($path, '/official-api') ||
                        str_starts_with($path, '/public-api');

        if (!$isApiRequest) {
            return;
        }

        $this->debugLog[] = [
            'timestamp' => microtime(true),
            'event' => 'EXCEPTION',
            'exception_type' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];
    }

    private function writeDebugLog($request): void
    {
        if (empty($this->debugLog)) {
            return;
        }

        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'request_id' => uniqid('cors_debug_', true),
            'client_ip' => $request->getClientIp(),
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'user_agent' => $request->headers->get('User-Agent'),
            'origin' => $request->headers->get('Origin'),
            'debug_log' => $this->debugLog,
            'environment' => [
                'app_env' => $_ENV['APP_ENV'] ?? 'unknown',
                'app_debug' => $_ENV['APP_DEBUG'] ?? 'unknown',
                'php_version' => PHP_VERSION,
                'symfony_version' => \Symfony\Component\HttpKernel\Kernel::VERSION
            ]
        ];

        // 写入到错误日志
        $logMessage = '[CORS DEBUG] ' . json_encode($logData, JSON_UNESCAPED_UNICODE);
        error_log($logMessage);

        // 如果是调试模式，也写入到文件
        if ($_ENV['APP_DEBUG'] ?? false) {
            $logFile = __DIR__ . '/../../public/cors_debug.log';
            $logEntry = date('Y-m-d H:i:s') . ' - ' . $logMessage . PHP_EOL . PHP_EOL;
            file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        }

        // 清空当前请求的调试日志
        $this->debugLog = [];
    }

    private function sanitizeHeaders(array $headers): array
    {
        $sanitized = [];
        foreach ($headers as $key => $values) {
            if (strtolower($key) === 'authorization') {
                $sanitized[$key] = ['[REDACTED]'];
            } else {
                $sanitized[$key] = $values;
            }
        }
        return $sanitized;
    }

    private function sanitizeRequestData(array $data): array
    {
        $sanitized = $data;

        // 移除敏感信息
        $sensitiveKeys = ['password', 'token', 'secret', 'key'];
        foreach ($sensitiveKeys as $sensitiveKey) {
            if (isset($sanitized[$sensitiveKey])) {
                $sanitized[$sensitiveKey] = '[REDACTED]';
            }
        }

        return $sanitized;
    }
}
