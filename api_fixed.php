<?php
// 修复版本的 API 文档解决方案
// 禁用错误显示，避免干扰
error_reporting(0);
ini_set('display_errors', 0);

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 获取请求路径
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];

// 移除查询字符串
$path = parse_url($requestUri, PHP_URL_PATH);

// 获取路径信息 - 修复路径解析
$path = str_replace($scriptName, '', $path);
if (empty($path)) {
    $path = '/';
}

$method = $_SERVER['REQUEST_METHOD'];

// 主页 - 显示 API 文档
if ($path === '/' || $path === '') {
    // 切换到HTML输出
    header('Content-Type: text/html; charset=utf-8');

    $baseUrl = get_base_url();

    echo '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API 文档 - 官方网站后台</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 8px 8px 0 0; }
        .content { padding: 30px; }
        .endpoint { border: 1px solid #e0e0e0; border-radius: 6px; margin: 20px 0; overflow: hidden; }
        .endpoint-header { background: #f8f9fa; padding: 15px; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; }
        .method { background: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .method.post { background: #007bff; }
        .endpoint-body { padding: 15px; }
        .test-btn { background: #007bff; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; }
        .test-btn:hover { background: #0056b3; }
        .result { margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 4px; display: none; }
        .result.success { border-left: 4px solid #28a745; }
        .result.error { border-left: 4px solid #dc3545; }
        pre { margin: 0; overflow-x: auto; }
        .status { font-weight: bold; padding: 2px 6px; border-radius: 3px; font-size: 11px; }
        .status.success { background: #d4edda; color: #155724; }
        .status.error { background: #f8d7da; color: #721c24; }
        .endpoint-url { font-family: monospace; background: #f1f3f4; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 API 文档</h1>
            <p>官方网站后台 RESTful API 接口文档</p>
            <p><small>基础URL: ' . $baseUrl . '</small></p>
        </div>
        <div class="content">
            <h2>📋 可用端点</h2>

            <div class="endpoint">
                <div class="endpoint-header">
                    <div>
                        <strong>健康检查</strong>
                        <span class="endpoint-url" id="url-health">' . $baseUrl . 'health</span>
                        <span class="method">GET</span>
                    </div>
                    <button class="test-btn" onclick="testEndpoint(\'health\')">🧪 测试</button>
                </div>
                <div class="endpoint-body">
                    <p><strong>描述:</strong> 健康检查接口，验证API服务是否正常运行</p>
                    <p><strong>响应:</strong> JSON格式的服务状态信息</p>
                    <div id="health-result" class="result"></div>
                </div>
            </div>

            <div class="endpoint">
                <div class="endpoint-header">
                    <div>
                        <strong>测试接口</strong>
                        <span class="endpoint-url" id="url-test">' . $baseUrl . 'test</span>
                        <span class="method">GET</span>
                    </div>
                    <button class="test-btn" onclick="testEndpoint(\'test\')">🧪 测试</button>
                </div>
                <div class="endpoint-body">
                    <p><strong>描述:</strong> 测试接口，用于验证API连接性</p>
                    <p><strong>响应:</strong> 返回Hello World消息</p>
                    <div id="test-result" class="result"></div>
                </div>
            </div>

            <div class="endpoint">
                <div class="endpoint-header">
                    <div>
                        <strong>API信息</strong>
                        <span class="endpoint-url" id="url-info">' . $baseUrl . 'info</span>
                        <span class="method">GET</span>
                    </div>
                    <button class="test-btn" onclick="testEndpoint(\'info\')">🧪 测试</button>
                </div>
                <div class="endpoint-body">
                    <p><strong>描述:</strong> 获取API基本信息和可用端点列表</p>
                    <p><strong>响应:</strong> API元数据信息</p>
                    <div id="info-result" class="result"></div>
                </div>
            </div>

            <div class="endpoint">
                <div class="endpoint-header">
                    <div>
                        <strong>新闻管理</strong>
                        <span class="endpoint-url" id="url-news">' . $baseUrl . 'news</span>
                        <span class="method">GET</span>
                        <span class="method post">POST</span>
                    </div>
                    <button class="test-btn" onclick="testEndpoint(\'news\')">🧪 测试</button>
                </div>
                <div class="endpoint-body">
                    <p><strong>描述:</strong> 新闻文章管理接口</p>
                    <p><strong>GET:</strong> 获取新闻列表</p>
                    <p><strong>POST:</strong> 创建新文章</p>
                    <div id="news-result" class="result"></div>
                </div>
            </div>

            <div style="margin-top: 30px; padding: 20px; background: #e7f3ff; border-radius: 6px;">
                <h3>💡 使用说明</h3>
                <ul>
                    <li>点击"测试"按钮可以直接测试每个API端点</li>
                    <li>所有接口都支持CORS跨域访问</li>
                    <li>响应格式统一为JSON</li>
                    <li>支持GET、POST等HTTP方法</li>
                    <li>完整的URL已显示在每个端点上方</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        const BASE_URL = "' . $baseUrl . '";

        async function testEndpoint(endpoint) {
            const resultDiv = document.getElementById(endpoint + \'-result\');
            resultDiv.style.display = \'block\';
            resultDiv.innerHTML = \'<p>🔄 正在测试...</p>\';

            try {
                const response = await fetch(BASE_URL + endpoint, {
                    method: \'GET\',
                    headers: {
                        \'Accept\': \'application/json\'
                    }
                });

                const data = await response.json();
                const statusClass = response.ok ? \'success\' : \'error\';
                const statusText = response.ok ? \'成功\' : \'失败\';

                resultDiv.className = `result ${statusClass}`;
                resultDiv.innerHTML = `
                    <div>
                        <span class="status ${statusClass}">${response.status} ${statusText}</span>
                        <p><strong>请求URL:</strong> ${BASE_URL + endpoint}</p>
                        <p><strong>响应数据:</strong></p>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    </div>
                `;
            } catch (error) {
                resultDiv.className = \'result error\';
                resultDiv.innerHTML = `
                    <div>
                        <span class="status error">请求失败</span>
                        <p><strong>请求URL:</strong> ${BASE_URL + endpoint}</p>
                        <p><strong>错误信息:</strong></p>
                        <pre>${error.message}</pre>
                    </div>
                `;
            }
        }

        // 页面加载完成后自动测试健康检查
        document.addEventListener(\'DOMContentLoaded\', function() {
            setTimeout(() => testEndpoint(\'health\'), 1000);
        });
    </script>
</body>
</html>';
    exit;
}

// API 响应处理
switch ($path) {
    case '/health':
        health_check();
        break;

    case '/test':
        test_endpoint();
        break;

    case '/info':
        api_info();
        break;

    case '/news':
        news_endpoint();
        break;

    default:
        send_error('未找到请求的端点', 404);
        break;
}

// 健康检查端点
function health_check() {
    $response = [
        'status' => 'ok',
        'timestamp' => date('c'),
        'service' => '官方网站后台API',
        'version' => '2.0.0'
    ];

    send_response($response);
}

// 测试端点
function test_endpoint() {
    $response = [
        'message' => 'Hello World',
        'timestamp' => date('c'),
        'method' => $_SERVER['REQUEST_METHOD']
    ];

    send_response($response);
}

// API信息端点
function api_info() {
    $baseUrl = get_base_url();
    $response = [
        'name' => '官方网站后台API',
        'version' => '2.0.0',
        'description' => '提供新闻文章管理、用户认证等功能的RESTful API',
        'base_url' => $baseUrl,
        'endpoints' => [
            'health' => $baseUrl . 'health',
            'test' => $baseUrl . 'test',
            'info' => $baseUrl . 'info',
            'news' => $baseUrl . 'news'
        ]
    ];

    send_response($response);
}

// 新闻端点
function news_endpoint() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        $response = [
            'success' => true,
            'message' => '新闻创建成功',
            'data' => $input ?: []
        ];

        send_response($response, 201);
    } else {
        $response = [
            'success' => true,
            'message' => '新闻列表',
            'data' => [
                ['id' => 1, 'title' => '示例新闻1', 'content' => '这是示例内容'],
                ['id' => 2, 'title' => '示例新闻2', 'content' => '这是另一个示例']
            ]
        ];

        send_response($response);
    }
}

// 获取基础URL
function get_base_url() {
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    return $scheme . '://' . $host . str_replace(basename($script), '', $script);
}

// 发送JSON响应
function send_response($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 发送错误响应
function send_error($message, $status_code = 400) {
    http_response_code($status_code);
    echo json_encode([
        'error' => true,
        'message' => $message,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
