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

        // 只处理API路径的OPTIONS请求
        $isApiPath = str_starts_with($path, '/api') ||
                     str_starts_with($path, '/official-api') ||
                     str_starts_with($path, '/public-api');

        if ($isApiPath && $method === 'OPTIONS') {
            error_log('[FORCE CORS] Handling OPTIONS request for path: ' . $path);

            // 立即返回200状态码和CORS头
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

        // 只为API路径设置CORS头
        $isApiPath = str_starts_with($path, '/api') ||
                     str_starts_with($path, '/official-api') ||
                     str_starts_with($path, '/public-api');

        if ($isApiPath) {
            $this->setCorsHeaders($response, $request);

            error_log('[FORCE CORS] Set CORS headers for path: ' . $path .
                     ', Origin: ' . ($request->headers->get('Origin') ?? 'none'));
        }
    }

    private function setCorsHeaders($response, $request): void
    {
        $origin = $request->headers->get('Origin');
        $allowedOrigin = $this->getAllowedOrigin($origin);

        // 设置CORS头
        $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
        $response->headers->set('Access-Control-Max-Age', '3600');
        $response->headers->set('Access-Control-Allow-Credentials', 'false');

        // 如果是OPTIONS请求，确保状态码为200
        if ($request->getMethod() === 'OPTIONS' && $response->getStatusCode() !== 200) {
            $response->setStatusCode(200);
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

        if (in_array($requestOrigin, $allowedOrigins)) {
            return $requestOrigin;
        }

        // 如果不匹配，返回第一个允许的域名或*
        return $allowedOrigins[0] ?? '*';
    }
}
