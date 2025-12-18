<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * 强制CORS处理订阅者
 * 作为NelmioCorsBundle的备用方案，确保CORS头始终正确设置
 */
class ForceCorsSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -1000], // 最低优先级，确保最后执行
            KernelEvents::REQUEST => ['onKernelRequest', 1000],   // 高优先级，提前处理OPTIONS
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $method = $request->getMethod();
        $path = $request->getPathInfo();
        $origin = $request->headers->get('Origin');
        $requestHeaders = $request->headers->get('Access-Control-Request-Headers');
        $requestMethod = $request->headers->get('Access-Control-Request-Method');

        // 处理API路径和API文档的OPTIONS请求
        $isApiPath = str_starts_with($path, '/api') ||
                     str_starts_with($path, '/official-api') ||
                     str_starts_with($path, '/public-api') ||
                     str_starts_with($path, '/api_doc');

        // 详细日志记录OPTIONS请求
        if ($method === 'OPTIONS') {
            error_log('[FORCE CORS] OPTIONS请求详情:');
            error_log('[FORCE CORS] 路径: ' . $path);
            error_log('[FORCE CORS] Origin: ' . ($origin ?? 'none'));
            error_log('[FORCE CORS] Request-Method: ' . ($requestMethod ?? 'none'));
            error_log('[FORCE CORS] Request-Headers: ' . ($requestHeaders ?? 'none'));
            error_log('[FORCE CORS] 是否API路径: ' . ($isApiPath ? '是' : '否'));

            // 检查是否包含x-request-id
            $hasXRequestId = $requestHeaders && strpos(strtolower($requestHeaders), 'x-request-id') !== false;
            error_log('[FORCE CORS] 包含x-request-id: ' . ($hasXRequestId ? '是' : '否'));
        }

        if ($isApiPath && $method === 'OPTIONS') {
            error_log('[FORCE CORS] 处理API路径的OPTIONS请求: ' . $path);

            // 立即返回200状态码和CORS头
            $response = new \Symfony\Component\HttpFoundation\Response();
            $this->setCorsHeaders($response, $request);

            // 记录设置的CORS头
            error_log('[FORCE CORS] 设置的CORS头:');
            error_log('[FORCE CORS] Allow-Origin: ' . $response->headers->get('Access-Control-Allow-Origin'));
            error_log('[FORCE CORS] Allow-Methods: ' . $response->headers->get('Access-Control-Allow-Methods'));
            error_log('[FORCE CORS] Allow-Headers: ' . $response->headers->get('Access-Control-Allow-Headers'));

            $event->setResponse($response);
            $event->stopPropagation();
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $path = $request->getPathInfo();
        $method = $request->getMethod();
        $origin = $request->headers->get('Origin');

        // 为API路径和API文档设置CORS头
        $isApiPath = str_starts_with($path, '/api') ||
                     str_starts_with($path, '/official-api') ||
                     str_starts_with($path, '/public-api') ||
                     str_starts_with($path, '/api_doc');

        if ($isApiPath) {
            error_log('[FORCE CORS] 响应阶段设置CORS头:');
            error_log('[FORCE CORS] 路径: ' . $path);
            error_log('[FORCE CORS] 方法: ' . $method);
            error_log('[FORCE CORS] Origin: ' . ($origin ?? 'none'));
            error_log('[FORCE CORS] 状态码: ' . $response->getStatusCode());

            $this->setCorsHeaders($response, $request);

            // 记录最终设置的CORS头
            error_log('[FORCE CORS] 最终设置的CORS头:');
            error_log('[FORCE CORS] Allow-Origin: ' . $response->headers->get('Access-Control-Allow-Origin'));
            error_log('[FORCE CORS] Allow-Methods: ' . $response->headers->get('Access-Control-Allow-Methods'));
            error_log('[FORCE CORS] Allow-Headers: ' . $response->headers->get('Access-Control-Allow-Headers'));

            // 检查是否包含x-request-id
            $allowHeaders = $response->headers->get('Access-Control-Allow-Headers');
            $hasXRequestId = $allowHeaders && strpos(strtolower($allowHeaders), 'x-request-id') !== false;
            error_log('[FORCE CORS] Allow-Headers包含x-request-id: ' . ($hasXRequestId ? '是' : '否'));
        }
    }

    private function setCorsHeaders($response, $request): void
    {
        $origin = $request->headers->get('Origin');

        // 🔧 简化CORS处理逻辑，直接使用通配符或返回请求的Origin
        if ($origin && $this->isValidOrigin($origin)) {
            $allowedOrigin = $origin;
        } else {
            $allowedOrigin = '*';
        }

        // 设置CORS头
        $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, x-request-id, X-Request-ID, X-Custom-Header');
        $response->headers->set('Access-Control-Max-Age', '3600');
        $response->headers->set('Access-Control-Allow-Credentials', 'false');

        // 如果是OPTIONS请求，确保状态码为200
        if ($request->getMethod() === 'OPTIONS' && $response->getStatusCode() !== 200) {
            $response->setStatusCode(200);
        }

        // 添加调试头
        $response->headers->set('X-CORS-Handler', 'ForceCorsSubscriber');
        $response->headers->set('X-CORS-Request-Origin', $origin ?? 'none');
        $response->headers->set('X-CORS-Allowed-Origin', $allowedOrigin);
    }

    private function getAllowedOrigin($requestOrigin): string
    {
        // 从环境变量获取允许的域名
        $corsAllowOrigin = $_ENV['CORS_ALLOW_ORIGIN'] ?? '*';

        if ($corsAllowOrigin === '*') {
            return '*';
        }

        // 如果指定了具体域名，检查是否匹配
        $allowedOrigins = explode(',', $corsAllowOrigin);
        $allowedOrigins = array_map('trim', $allowedOrigins);

        // 🔧 增强匹配逻辑：支持协议无关的匹配
        foreach ($allowedOrigins as $allowedOrigin) {
            if ($this->isOriginMatch($requestOrigin, $allowedOrigin)) {
                return $requestOrigin;
            }
        }

        // 如果不匹配，返回第一个允许的域名或*
        return $allowedOrigins[0] ?? '*';
    }

    /**
     * 验证Origin是否有效
     * @param string|null $origin 请求的Origin
     * @return bool 是否有效
     */
    private function isValidOrigin(?string $origin): bool
    {
        if (empty($origin)) {
            return false;
        }

        // 检查是否为有效的URL格式
        if (!preg_match('/^https?:\/\/[a-zA-Z0-9.-]+(?::\d+)?(?:\/.*)?$/', $origin)) {
            return false;
        }

        // 检查是否为localhost或IP地址
        $host = parse_url($origin, PHP_URL_HOST);
        if ($host === false) {
            return false;
        }

        // 允许localhost和本地IP
        if ($host === 'localhost' ||
            preg_match('/^127\.\d+\.\d+\.\d+$/', $host) ||
            preg_match('/^192\.168\.\d+\.\d+$/', $host) ||
            preg_match('/^10\.\d+\.\d+\.\d+$/', $host)) {
            return true;
        }

        // 检查环境变量中是否允许该域名
        $corsAllowOrigin = $_ENV['CORS_ALLOW_ORIGIN'] ?? '*';
        if ($corsAllowOrigin === '*') {
            return true;
        }

        $allowedOrigins = array_map('trim', explode(',', $corsAllowOrigin));
        foreach ($allowedOrigins as $allowedOrigin) {
            if (strcasecmp($origin, $allowedOrigin) === 0) {
                return true;
            }
        }

        return false;
    }

}
