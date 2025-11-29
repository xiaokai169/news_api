<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * 生产环境 CORS 订阅者
 * 不写入文件日志，避免权限问题
 */
class ProductionCorsSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1024],   // 最高优先级
            KernelEvents::RESPONSE => ['onKernelResponse', -1024], // 最低优先级
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $method = $request->getMethod();
        $path = $request->getPathInfo();

        // 只处理 API 路径的 OPTIONS 请求
        $isApiPath = str_starts_with($path, '/api') ||
                     str_starts_with($path, '/official-api') ||
                     str_starts_with($path, '/public-api');

        if ($isApiPath && $method === 'OPTIONS') {
            // 记录到系统日志，不写文件
            error_log('[PROD CORS] Handling OPTIONS request for path: ' . $path);

            // 立即返回 200 状态码和 CORS 头
            $response = new \Symfony\Component\HttpFoundation\Response();
            $this->setCorsHeaders($response, $request);

            $event->setResponse($response);
            $event->stopPropagation();
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $path = $request->getPathInfo();

        // 只为 API 路径设置 CORS 头
        $isApiPath = str_starts_with($path, '/api') ||
                     str_starts_with($path, '/official-api') ||
                     str_starts_with($path, '/public-api');

        if ($isApiPath) {
            $this->setCorsHeaders($response, $request);

            // 记录到系统日志，不写文件
            error_log('[PROD CORS] Set CORS headers for path: ' . $path .
                     ', Status: ' . $response->getStatusCode());
        }
    }

    private function setCorsHeaders($response, $request): void
    {
        $origin = $request->headers->get('Origin');
        $allowedOrigin = $this->getAllowedOrigin($origin);

        // 设置 CORS 头
        $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-Custom-Header, X-Request-Id, x-request-id, X-Request-ID');
        $response->headers->set('Access-Control-Max-Age', '3600');
        $response->headers->set('Access-Control-Allow-Credentials', 'false');

        // 如果是 OPTIONS 请求，确保状态码为 200
        if ($request->getMethod() === 'OPTIONS' && $response->getStatusCode() !== 200) {
            $response->setStatusCode(200);
        }
    }

    private function getAllowedOrigin($requestOrigin): string
    {
        // 🔧 处理环境变量缺失问题
        $corsAllowOrigin = $_ENV['CORS_ALLOW_ORIGIN'] ?? getenv('CORS_ALLOW_ORIGIN') ?? '*';

        // 如果环境变量未设置，强制设置为 *
        if (empty($corsAllowOrigin) || $corsAllowOrigin === 'not_set') {
            $corsAllowOrigin = '*';
            // 记录到系统日志
            error_log('[PROD CORS] CORS_ALLOW_ORIGIN not set, using "*" as fallback');
        }

        if ($corsAllowOrigin === '*') {
            return '*';
        }

        // 如果指定了具体域名，检查是否匹配
        $allowedOrigins = array_map('trim', explode(',', $corsAllowOrigin));

        if (in_array($requestOrigin, $allowedOrigins)) {
            return $requestOrigin;
        }

        // 如果不匹配，返回第一个允许的域名
        return $allowedOrigins[0] ?? '*';
    }
}
