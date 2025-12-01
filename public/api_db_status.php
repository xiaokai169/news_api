<?php

use App\Kernel;
use App\Service\DatabaseMonitorService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

// 加载环境变量
if (file_exists(dirname(__DIR__).'/.env')) {
    $lines = file(dirname(__DIR__).'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
        putenv(trim($key).'='.trim($value));
    }
}

require_once dirname(__DIR__).'/vendor/autoload.php';

/**
 * 数据库状态API接口
 *
 * 提供以下端点：
 * - GET /api_db_status.php - 完整数据库状态
 * - GET /api_db_status.php?health - 健康检查
 * - GET /api_db_status.php?metrics - 性能指标
 * - GET /api_db_status.php?history=24 - 历史统计（24小时）
 * - GET /api_db_status.php?connection=default - 特定连接状态
 */

class DatabaseStatusApi
{
    private DatabaseMonitorService $monitorService;
    private array $config;

    public function __construct()
    {
        // 安全检查：只允许特定IP访问或需要令牌
        $this->validateAccess();

        // 初始化Symfony内核
        $env = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? 'prod';
        $debug = $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? false;

        $kernel = new Kernel($env, (bool) $debug);
        $kernel->boot();

        $container = $kernel->getContainer();
        $this->monitorService = $container->get(DatabaseMonitorService::class);

        // API配置
        $this->config = [
            'allowed_ips' => ['127.0.0.1', '::1', 'localhost'],
            'access_token' => 'db_monitor_2024_secure',
            'rate_limit' => 60, // 每分钟请求次数
            'cors_enabled' => true,
        ];
    }

    /**
     * 处理API请求
     */
    public function handleRequest(): void
    {
        try {
            $request = Request::createFromGlobals();
            $response = $this->processRequest($request);
            $response->send();
        } catch (\Exception $e) {
            $this->sendError('内部服务器错误', 500, [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }

    /**
     * 处理具体请求
     */
    private function processRequest(Request $request): JsonResponse
    {
        $method = $request->getMethod();

        // 只允许GET请求
        if ($method !== 'GET') {
            return $this->sendError('只支持GET请求', 405);
        }

        // 设置CORS头
        if ($this->config['cors_enabled']) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
        }

        // 根据查询参数处理不同类型的请求
        $query = $request->query;

        if ($query->has('health')) {
            return $this->handleHealthCheck();
        }

        if ($query->has('metrics')) {
            return $this->handleMetrics();
        }

        if ($query->has('history')) {
            $hours = (int)($query->get('history', 24));
            return $this->handleHistory($hours);
        }

        if ($query->has('connection')) {
            $connectionName = $query->get('connection');
            return $this->handleConnectionStatus($connectionName);
        }

        // 默认返回完整状态
        return $this->handleFullStatus();
    }

    /**
     * 处理完整状态请求
     */
    private function handleFullStatus(): JsonResponse
    {
        $status = $this->monitorService->getFullDatabaseStatus();

        return new JsonResponse([
            'success' => true,
            'data' => $status,
            'meta' => [
                'endpoint' => 'full_status',
                'timestamp' => date('Y-m-d H:i:s'),
                'version' => '1.0.0'
            ]
        ]);
    }

    /**
     * 处理健康检查请求
     */
    private function handleHealthCheck(): JsonResponse
    {
        $health = $this->monitorService->healthCheck();

        $httpStatus = $health['status'] === 'healthy' ? 200 : 503;

        return new JsonResponse([
            'success' => $health['status'] === 'healthy',
            'data' => $health,
            'meta' => [
                'endpoint' => 'health_check',
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ], $httpStatus);
    }

    /**
     * 处理性能指标请求
     */
    private function handleMetrics(): JsonResponse
    {
        $fullStatus = $this->monitorService->getFullDatabaseStatus();
        $metrics = $fullStatus['performance_metrics'];

        return new JsonResponse([
            'success' => true,
            'data' => $metrics,
            'meta' => [
                'endpoint' => 'performance_metrics',
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
    }

    /**
     * 处理历史统计请求
     */
    private function handleHistory(int $hours): JsonResponse
    {
        if ($hours < 1 || $hours > 168) { // 最多7天
            return $this->sendError('小时数必须在1-168之间', 400);
        }

        $history = $this->monitorService->getHistoricalStats($hours);

        return new JsonResponse([
            'success' => true,
            'data' => $history,
            'meta' => [
                'endpoint' => 'historical_stats',
                'period_hours' => $hours,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
    }

    /**
     * 处理特定连接状态请求
     */
    private function handleConnectionStatus(string $connectionName): JsonResponse
    {
        try {
            $connectionStatus = $this->monitorService->checkConnectionStatus($connectionName);

            return new JsonResponse([
                'success' => true,
                'data' => $connectionStatus,
                'meta' => [
                    'endpoint' => 'connection_status',
                    'connection' => $connectionName,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            return $this->sendError("获取连接 '{$connectionName}' 状态失败: " . $e->getMessage(), 404);
        }
    }

    /**
     * 验证访问权限
     */
    private function validateAccess(): void
    {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $isProd = ($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'prod';

        // 生产环境需要验证
        if ($isProd) {
            // 检查IP白名单
            if (!in_array($clientIp, $this->config['allowed_ips'])) {
                // 检查访问令牌
                $token = $_GET['token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
                $token = str_replace('Bearer ', '', $token);

                if ($token !== $this->config['access_token']) {
                    $this->sendError('访问被拒绝', 403, [
                        'message' => '需要有效的访问令牌或IP白名单',
                        'client_ip' => $clientIp
                    ]);
                }
            }
        }
    }

    /**
     * 发送错误响应
     */
    private function sendError(string $message, int $httpStatus = 400, array $details = []): JsonResponse
    {
        $response = new JsonResponse([
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $httpStatus,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ], $httpStatus);

        if (!empty($details)) {
            $response->setData(array_merge($response->getData(), ['details' => $details]));
        }

        $response->send();
        exit;
    }
}

// 处理OPTIONS请求（CORS预检）
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 86400');
    http_response_code(200);
    exit;
}

// 执行API处理
$api = new DatabaseStatusApi();
$api->handleRequest();
