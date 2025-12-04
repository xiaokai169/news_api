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

        // 只处理API路径的OPTIONS请求
        $isApiPath = str_starts_with($path, '/api') ||
                     str_starts_with($path, '/official-api') ||
                     str_starts_with($path, '/public-api');

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

        // 只为API路径设置CORS头
        $isApiPath = str_starts_with($path, '/api') ||
                     str_starts_with($path, '/official-api') ||
                     str_starts_with($path, '/public-api');

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
        $allowedOrigin = $this->getAllowedOrigin($origin);

        // 🔧 修复URL协议前缀问题
        $processedOrigin = $this->processOriginUrl($origin);
        if ($processedOrigin !== $origin) {
            error_log('[FORCE CORS] Origin已修复: ' . ($origin ?? 'null') . ' -> ' . $processedOrigin);
            $origin = $processedOrigin;
            $allowedOrigin = $this->getAllowedOrigin($origin);
        }

        // 设置CORS头
        $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, x-request-id, X-Request-ID');
        $response->headers->set('Access-Control-Max-Age', '3600');
        $response->headers->set('Access-Control-Allow-Credentials', 'false');

        // 如果是OPTIONS请求，确保状态码为200
        if ($request->getMethod() === 'OPTIONS' && $response->getStatusCode() !== 200) {
            $response->setStatusCode(200);
        }

        // 添加调试头，便于前端识别修复
        $response->headers->set('X-CORS-Fix-Applied', $processedOrigin !== $origin ? 'true' : 'false');
        if ($processedOrigin !== $origin) {
            $response->headers->set('X-CORS-Original-Origin', $origin ?? 'none');
            $response->headers->set('X-CORS-Fixed-Origin', $processedOrigin);
        }
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
     * 处理Origin URL，修复协议前缀问题
     * @param string|null $origin 原始Origin
     * @return string 处理后的Origin
     */
    private function processOriginUrl(?string $origin): string
    {
        if (empty($origin)) {
            return '*';
        }

        // 如果URL已经包含协议，直接返回
        if (preg_match('/^https?:\/\//', $origin)) {
            return $origin;
        }

        // 如果URL以localhost开头，添加http://前缀
        if (strpos($origin, 'localhost') === 0) {
            return 'http://' . $origin;
        }

        // 如果URL以//开头，添加https://前缀
        if (strpos($origin, '//') === 0) {
            return 'https:' . $origin;
        }

        // 如果URL以IP地址开头，添加http://前缀
        if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}(:\d+)?/', $origin)) {
            return 'http://' . $origin;
        }

        // 其他情况，默认添加https://前缀
        return 'https://' . $origin;
    }

    /**
     * 检查Origin是否匹配允许的域名（支持协议无关匹配）
     * @param string|null $requestOrigin 请求的Origin
     * @param string $allowedOrigin 允许的Origin
     * @return bool 是否匹配
     */
    private function isOriginMatch(?string $requestOrigin, string $allowedOrigin): bool
    {
        if (empty($requestOrigin)) {
            return false;
        }

        // 直接匹配
        if ($requestOrigin === $allowedOrigin) {
            return true;
        }

        // 处理通配符匹配
        if ($allowedOrigin === '*') {
            return true;
        }

        // 移除协议进行比较
        $requestHost = preg_replace('/^https?:\/\//', '', $requestOrigin);
        $allowedHost = preg_replace('/^https?:\/\//', '', $allowedOrigin);

        // 比较主机名（包含端口）
        if ($requestHost === $allowedHost) {
            return true;
        }

        // 比较处理后的URL
        $processedOrigin = $this->processOriginUrl($requestOrigin);
        return $processedOrigin === $allowedOrigin;
    }
}
