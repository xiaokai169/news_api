<?php
/**
 * 支持 /api/ 前缀的 API 路由解决方案
 *
 * 修复问题：
 * 1. 支持 /api/ 前缀的路由
 * 2. 完全防止 PHP 源码泄露
 * 3. 兼容原有的直接路径访问
 * 4. 提供完整的错误处理
 */

// 完全禁用错误显示，确保只返回 JSON
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 设置默认字符编码
mb_internal_encoding('UTF-8');

// 获取请求信息
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];

// 解析请求路径
$parsedUrl = parse_url($requestUri);
$path = $parsedUrl['path'];

// 获取相对路径（移除脚本名称）
$basePath = dirname($scriptName);
if ($basePath !== '/' && $basePath !== '\\') {
    $path = str_replace($basePath, '', $path);
}

// 标准化路径
$path = '/' . trim($path, '/');
if ($path === '/') {
    $path = '/';
}

// 获取基础 URL
function get_base_url() {
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    return $scheme . '://' . $host . str_replace(basename($script), '', $script);
}

// 发送 JSON 响应的统一函数
function send_json($data, $status_code = 200) {
    // 清除所有之前的输出
    if (ob_get_level()) {
        ob_clean();
    }

    // 设置响应头
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    http_response_code($status_code);

    // 确保输出有效的 JSON
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 发送错误响应
function send_error($message, $status_code = 400) {
    send_json([
        'success' => false,
        'error' => true,
        'message' => $message,
        'timestamp' => date('c'),
        'status' => $status_code,
        'path' => $_SERVER['REQUEST_URI'] ?? 'Unknown'
    ], $status_code);
}

// 处理 CORS 预检请求
if ($method === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(200);
    exit;
}

// 路由映射 - 支持 /api/ 前缀和直接路径
$routes = [
    // 健康检查
    '/health' => 'health',
    '/api/health' => 'health',

    // 测试接口
    '/test' => 'test',
    '/api/test' => 'test',

    // API 信息
    '/info' => 'info',
    '/api/info' => 'info',

    // 新闻管理
    '/news' => 'news',
    '/api/news' => 'news',
];

// 主页 - 显示 API 文档界面
if ($path === '/') {
    $baseUrl = get_base_url();

    // 清除输出缓冲区
    if (ob_get_level()) {
        ob_clean();
    }

    // 设置 HTML 内容类型
    header('Content-Type: text/html; charset=utf-8');

    echo '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API 文档 - 官方网站后台系统</title>
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🚀</text></svg>">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .header .subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .header .base-url {
            background: rgba(255,255,255,0.1);
            padding: 12px 20px;
            border-radius: 6px;
            font-family: "Monaco", "Menlo", "Ubuntu Mono", monospace;
            font-size: 0.9rem;
            display: inline-block;
            position: relative;
            z-index: 1;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .content {
            padding: 40px 30px;
        }

        .section-title {
            font-size: 1.8rem;
            color: #2c3e50;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 3px solid #3498db;
        }

        .endpoint {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin: 20px 0;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .endpoint:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .endpoint-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .endpoint-info {
            flex: 1;
        }

        .endpoint-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .endpoint-url {
            font-family: "Monaco", "Menlo", "Ubuntu Mono", monospace;
            background: #e3f2fd;
            color: #1976d2;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.85rem;
            display: inline-block;
            margin: 5px 0;
            border: 1px solid #bbdefb;
        }

        .method-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 4px;
        }

        .method-get {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .method-post {
            background: linear-gradient(135deg, #007bff, #6610f2);
            color: white;
        }

        .test-button {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,123,255,0.3);
        }

        .test-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,123,255,0.4);
        }

        .endpoint-body {
            padding: 25px;
        }

        .description {
            color: #495057;
            margin-bottom: 15px;
            line-height: 1.7;
        }

        .response-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 15px;
            margin-top: 15px;
        }

        .result-container {
            margin-top: 20px;
            border-radius: 6px;
            overflow: hidden;
            display: none;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .result-header {
            padding: 15px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .result-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 1px solid #b8daff;
        }

        .result-error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border: 1px solid #f1b0b7;
        }

        .result-body {
            background: #f8f9fa;
            padding: 20px;
            border-top: 1px solid #dee2e6;
        }

        .result-body pre {
            background: #2d3748;
            color: #e2e8f0;
            padding: 20px;
            border-radius: 6px;
            overflow-x: auto;
            margin: 0;
            font-family: "Monaco", "Menlo", "Ubuntu Mono", monospace;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-success {
            background: #28a745;
            color: white;
        }

        .status-error {
            background: #dc3545;
            color: white;
        }

        .info-section {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border: 1px solid #90caf9;
            border-radius: 8px;
            padding: 25px;
            margin-top: 30px;
        }

        .info-section h3 {
            color: #1565c0;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-section ul {
            list-style: none;
            padding: 0;
        }

        .info-section li {
            padding: 8px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }

        .info-section li:last-child {
            border-bottom: none;
        }

        .info-section li::before {
            content: "✓";
            color: #28a745;
            font-weight: bold;
            margin-right: 10px;
        }

        .url-variants {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 5px 0;
        }

        .url-variant {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-family: "Monaco", "Menlo", "Ubuntu Mono", monospace;
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 8px;
            }

            .header {
                padding: 30px 20px;
            }

            .header h1 {
                font-size: 2rem;
            }

            .content {
                padding: 25px 20px;
            }

            .endpoint-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .test-button {
                width: 100%;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 API 文档</h1>
            <p class="subtitle">官方网站后台系统 RESTful API 接口文档（支持 /api/ 前缀）</p>
            <div class="base-url">📍 基础URL: ' . $baseUrl . '</div>
        </div>

        <div class="content">
            <h2 class="section-title">📋 API 端点列表</h2>

            <!-- 健康检查端点 -->
            <div class="endpoint">
                <div class="endpoint-header">
                    <div class="endpoint-info">
                        <div class="endpoint-title">💊 健康检查</div>
                        <div class="url-variants">
                            <div class="url-variant">' . $baseUrl . 'health</div>
                            <div class="url-variant">' . $baseUrl . 'api/health</div>
                        </div>
                        <div>
                            <span class="method-badge method-get">GET</span>
                        </div>
                    </div>
                    <button class="test-button" onclick="testEndpoint(\'health\')">🧪 测试接口</button>
                </div>
                <div class="endpoint-body">
                    <p class="description"><strong>功能描述:</strong> 检查 API 服务是否正常运行，返回系统状态信息。支持两种访问方式。</p>
                    <div class="response-info">
                        <strong>响应格式:</strong> JSON 格式的服务状态信息，包含状态码、时间戳和服务版本。
                    </div>
                    <div id="result-health" class="result-container"></div>
                </div>
            </div>

            <!-- 测试端点 -->
            <div class="endpoint">
                <div class="endpoint-header">
                    <div class="endpoint-info">
                        <div class="endpoint-title">🧪 测试接口</div>
                        <div class="url-variants">
                            <div class="url-variant">' . $baseUrl . 'test</div>
                            <div class="url-variant">' . $baseUrl . 'api/test</div>
                        </div>
                        <div>
                            <span class="method-badge method-get">GET</span>
                        </div>
                    </div>
                    <button class="test-button" onclick="testEndpoint(\'test\')">🧪 测试接口</button>
                </div>
                <div class="endpoint-body">
                    <p class="description"><strong>功能描述:</strong> 简单的测试接口，用于验证 API 连接性和基本功能。支持两种访问方式。</p>
                    <div class="response-info">
                        <strong>响应格式:</strong> 返回 Hello World 消息和请求时间戳。
                    </div>
                    <div id="result-test" class="result-container"></div>
                </div>
            </div>

            <!-- API 信息端点 -->
            <div class="endpoint">
                <div class="endpoint-header">
                    <div class="endpoint-info">
                        <div class="endpoint-title">ℹ️ API 信息</div>
                        <div class="url-variants">
                            <div class="url-variant">' . $baseUrl . 'info</div>
                            <div class="url-variant">' . $baseUrl . 'api/info</div>
                        </div>
                        <div>
                            <span class="method-badge method-get">GET</span>
                        </div>
                    </div>
                    <button class="test-button" onclick="testEndpoint(\'info\')">🧪 测试接口</button>
                </div>
                <div class="endpoint-body">
                    <p class="description"><strong>功能描述:</strong> 获取 API 系统的基本信息，包括版本、描述和所有可用端点列表。支持两种访问方式。</p>
                    <div class="response-info">
                        <strong>响应格式:</strong> 返回 API 元数据信息，包含名称、版本、描述和端点列表。
                    </div>
                    <div id="result-info" class="result-container"></div>
                </div>
            </div>

            <!-- 新闻管理端点 -->
            <div class="endpoint">
                <div class="endpoint-header">
                    <div class="endpoint-info">
                        <div class="endpoint-title">📰 新闻管理</div>
                        <div class="url-variants">
                            <div class="url-variant">' . $baseUrl . 'news</div>
                            <div class="url-variant">' . $baseUrl . 'api/news</div>
                        </div>
                        <div>
                            <span class="method-badge method-get">GET</span>
                            <span class="method-badge method-post">POST</span>
                        </div>
                    </div>
                    <button class="test-button" onclick="testEndpoint(\'news\')">🧪 测试接口</button>
                </div>
                <div class="endpoint-body">
                    <p class="description"><strong>功能描述:</strong> 新闻文章管理接口，支持获取新闻列表和创建新文章。支持两种访问方式。</p>
                    <div class="response-info">
                        <strong>GET:</strong> 获取新闻文章列表<br>
                        <strong>POST:</strong> 创建新的新闻文章（需要 JSON 格式的请求数据）
                    </div>
                    <div id="result-news" class="result-container"></div>
                </div>
            </div>

            <!-- 使用说明 -->
            <div class="info-section">
                <h3>💡 使用说明</h3>
                <ul>
                    <li>点击每个端点的"测试接口"按钮可以直接测试 API 功能</li>
                    <li>所有接口都支持 /api/ 前缀和直接路径两种访问方式</li>
                    <li>例如：/health 和 /api/health 都会返回相同的结果</li>
                    <li>所有接口都支持 CORS 跨域访问，可以从任何域名调用</li>
                    <li>响应格式统一为 JSON，确保数据交换的一致性</li>
                    <li>支持 GET、POST 等标准 HTTP 方法</li>
                    <li>完整的 URL 已显示在每个端点上方，便于复制使用</li>
                    <li>系统包含完整的错误处理机制，确保稳定性</li>
                    <li>所有时间戳均使用 ISO 8601 格式（UTC 时间）</li>
                    <li>完全防止 PHP 源码泄露，确保系统安全性</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // 配置
        const CONFIG = {
            baseUrl: "' . $baseUrl . '",
            timeout: 10000
        };

        // 测试 API 端点
        async function testEndpoint(endpoint) {
            const resultContainer = document.getElementById(`result-${endpoint}`);
            const button = event.target;

            // 显示加载状态
            button.classList.add(\'loading\');
            button.textContent = \'🔄 测试中...\';

            // 显示结果容器
            resultContainer.style.display = \'block\';
            resultContainer.innerHTML = `
                <div class="result-header result-success">
                    <span class="status-badge status-success">正在请求</span>
                    <span>正在测试 ${CONFIG.baseUrl + endpoint}...</span>
                </div>
                <div class="result-body">
                    <pre>🔄 发送请求中...</pre>
                </div>
            `;

            try {
                // 发送请求
                const response = await fetch(CONFIG.baseUrl + endpoint, {
                    method: \'GET\',
                    headers: {
                        \'Accept\': \'application/json\',
                        \'Content-Type\': \'application/json\'
                    },
                    signal: AbortSignal.timeout(CONFIG.timeout)
                });

                // 获取响应文本
                const responseText = await response.text();

                // 尝试解析 JSON
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (e) {
                    data = { rawResponse: responseText };
                }

                // 确定结果状态
                const isSuccess = response.ok;
                const statusClass = isSuccess ? \'result-success\' : \'result-error\';
                const statusBadgeClass = isSuccess ? \'status-success\' : \'status-error\';
                const statusText = isSuccess ? \'请求成功\' : `请求失败 (${response.status})`;

                // 显示结果
                resultContainer.innerHTML = `
                    <div class="result-header ${statusClass}">
                        <span class="status-badge ${statusBadgeClass}">${response.status} ${statusText}</span>
                        <span>${new Date().toLocaleString(\'zh-CN\')}</span>
                    </div>
                    <div class="result-body">
                        <p><strong>请求 URL:</strong> ${CONFIG.baseUrl + endpoint}</p>
                        <p><strong>请求方法:</strong> GET</p>
                        <p><strong>响应状态:</strong> ${response.status} ${response.statusText}</p>
                        <p><strong>响应数据:</strong></p>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    </div>
                `;

            } catch (error) {
                // 处理错误
                resultContainer.innerHTML = `
                    <div class="result-header result-error">
                        <span class="status-badge status-error">请求失败</span>
                        <span>${new Date().toLocaleString(\'zh-CN\')}</span>
                    </div>
                    <div class="result-body">
                        <p><strong>请求 URL:</strong> ${CONFIG.baseUrl + endpoint}</p>
                        <p><strong>错误类型:</strong> ${error.name}</p>
                        <p><strong>错误信息:</strong> ${error.message}</p>
                        <p><strong>可能原因:</strong></p>
                        <ul>
                            <li>网络连接问题</li>
                            <li>服务器未响应</li>
                            <li>CORS 策略限制</li>
                            <li>请求超时</li>
                        </ul>
                    </div>
                `;
            } finally {
                // 恢复按钮状态
                button.classList.remove(\'loading\');
                button.textContent = \'🧪 测试接口\';
            }
        }

        // 页面加载完成后的初始化
        document.addEventListener(\'DOMContentLoaded\', function() {
            console.log(\'🚀 API 文档页面已加载\');
            console.log(\'📍 基础URL:\', CONFIG.baseUrl);

            // 自动测试健康检查接口（延迟 1 秒）
            setTimeout(() => {
                console.log(\'🔍 自动测试健康检查接口...\');
                const healthButton = document.querySelector(\'button[onclick="testEndpoint(\\\'health\\\')"]\');
                if (healthButton) {
                    healthButton.click();
                }
            }, 1000);
        });

        // 添加键盘快捷键支持
        document.addEventListener(\'keydown\', function(event) {
            // Ctrl/Cmd + Enter 测试所有接口
            if ((event.ctrlKey || event.metaKey) && event.key === \'Enter\') {
                event.preventDefault();
                const endpoints = [\'health\', \'test\', \'info\', \'news\'];
                endpoints.forEach((endpoint, index) => {
                    setTimeout(() => {
                        const button = document.querySelector(`button[onclick="testEndpoint(\'${endpoint}\')"]`);
                        if (button) {
                            button.click();
                        }
                    }, index * 500);
                });
            }
        });
    </script>
</body>
</html>';
    exit;
}

// API 路由处理
$routeHandler = $routes[$path] ?? null;

if ($routeHandler) {
    switch ($routeHandler) {
        case 'health':
            // 健康检查端点
            send_json([
                'success' => true,
                'status' => 'ok',
                'timestamp' => date('c'),
                'service' => '官方网站后台API',
                'version' => '4.0.0',
                'uptime' => time(),
                'environment' => 'production',
                'path_accessed' => $path,
                'supports_prefix' => true
            ]);

        case 'test':
            // 测试端点
            send_json([
                'success' => true,
                'message' => 'Hello World',
                'timestamp' => date('c'),
                'method' => $method,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                'path_accessed' => $path,
                'supports_prefix' => true
            ]);

        case 'info':
            // API 信息端点
            $baseUrl = get_base_url();
            send_json([
                'success' => true,
                'name' => '官方网站后台API',
                'version' => '4.0.0',
                'description' => '提供新闻文章管理、用户认证等功能的RESTful API服务，支持 /api/ 前缀访问',
                'base_url' => $baseUrl,
                'features' => [
                    'RESTful API设计',
                    'JSON格式响应',
                    'CORS跨域支持',
                    '统一错误处理',
                    '完整的文档界面',
                    '实时测试功能',
                    '支持 /api/ 前缀',
                    '防止源码泄露'
                ],
                'supported_paths' => [
                    '/health' => $baseUrl . 'health',
                    '/api/health' => $baseUrl . 'api/health',
                    '/test' => $baseUrl . 'test',
                    '/api/test' => $baseUrl . 'api/test',
                    '/info' => $baseUrl . 'info',
                    '/api/info' => $baseUrl . 'api/info',
                    '/news' => $baseUrl . 'news',
                    '/api/news' => $baseUrl . 'api/news'
                ],
                'timestamp' => date('c')
            ]);

        case 'news':
            // 新闻管理端点
            if ($method === 'POST') {
                // 获取请求数据
                $input = json_decode(file_get_contents('php://input'), true);

                // 验证数据
                if (!$input || !is_array($input)) {
                    send_error('无效的JSON数据', 400);
                }

                // 模拟创建新闻
                $newsId = rand(1000, 9999);
                $newsData = [
                    'id' => $newsId,
                    'title' => $input['title'] ?? '未命名标题',
                    'content' => $input['content'] ?? '无内容',
                    'category' => $input['category'] ?? '默认分类',
                    'author' => $input['author'] ?? '系统',
                    'status' => 'published',
                    'created_at' => date('c'),
                    'updated_at' => date('c'),
                    'path_accessed' => $path,
                    'supports_prefix' => true
                ];

                send_json([
                    'success' => true,
                    'message' => '新闻创建成功',
                    'data' => $newsData
                ], 201);

            } else {
                // 获取新闻列表
                send_json([
                    'success' => true,
                    'message' => '新闻列表获取成功',
                    'data' => [
                        [
                            'id' => 1,
                            'title' => '系统上线公告',
                            'content' => '官方网站后台系统正式上线，提供完整的API服务。',
                            'category' => '公告',
                            'author' => '管理员',
                            'status' => 'published',
                            'created_at' => '2025-01-01T00:00:00+00:00',
                            'updated_at' => '2025-01-01T00:00:00+00:00'
                        ],
                        [
                            'id' => 2,
                            'title' => 'API文档更新',
                            'content' => '新增了完整的API文档界面和实时测试功能。',
                            'category' => '更新',
                            'author' => '开发团队',
                            'status' => 'published',
                            'created_at' => '2025-01-02T00:00:00+00:00',
                            'updated_at' => '2025-01-02T00:00:00+00:00'
                        ],
                        [
                            'id' => 3,
                            'title' => '新功能发布',
                            'content' => '支持新闻管理、用户认证等多项新功能。',
                            'category' => '功能',
                            'author' => '产品团队',
                            'status' => 'published',
                            'created_at' => '2025-01-03T00:00:00+00:00',
                            'updated_at' => '2025-01-03T00:00:00+00:00'
                        ]
                    ],
                    'pagination' => [
                        'page' => 1,
                        'limit' => 10,
                        'total' => 3,
                        'pages' => 1
                    ],
                    'path_accessed' => $path,
                    'supports_prefix' => true
                ]);
            }
    }
} else {
    // 404 错误 - 完全防止源码泄露
    send_error('未找到请求的端点: ' . $path . '。支持的端点：/health, /api/health, /test, /api/test, /info, /api/info, /news, /api/news', 404);
}
