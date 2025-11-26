<?php
// 最简单的 API 文档解决方案
// 启用错误显示
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 简单的路由处理
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];

// 移除查询字符串
$requestUri = parse_url($requestUri, PHP_URL_PATH);

// 获取路径信息
$path = str_replace($scriptName, '', $requestUri);
if (empty($path)) {
    $path = '/';
}

$method = $_SERVER['REQUEST_METHOD'];

// 调试输出
if (isset($_GET['debug'])) {
    echo "Debug Info:\n";
    echo "REQUEST_URI: " . $requestUri . "\n";
    echo "SCRIPT_NAME: " . $scriptName . "\n";
    echo "PATH: " . $path . "\n";
    echo "METHOD: " . $method . "\n";
    exit;
}

// 主页 - 显示 API 文档
if ($path === '/' || $path === '' || $path === '/api_simple.php') {
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>API 文档</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; text-align: center; }
        .endpoint { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 15px; margin: 10px 0; }
        .method { display: inline-block; padding: 4px 8px; border-radius: 3px; color: white; font-size: 12px; font-weight: bold; }
        .get { background: #28a745; }
        .post { background: #007bff; }
        .url { font-family: monospace; background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
        .test-btn { background: #17a2b8; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; margin-left: 10px; }
        .result { margin-top: 10px; padding: 10px; border-radius: 4px; display: none; font-family: monospace; white-space: pre-wrap; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 官方网站后台 API 文档</h1>

        <div class="endpoint">
            <h3><span class="method get">GET</span> 健康检查</h3>
            <p>URL: <span class="url" id="url-health">/health</span></p>
            <p>描述: 检查 API 服务状态</p>
            <button class="test-btn" onclick="testAPI(\'GET\', \'/health\', null)">测试</button>
            <div id="result-health" class="result"></div>
        </div>

        <div class="endpoint">
            <h3><span class="method get">GET</span> 测试接口</h3>
            <p>URL: <span class="url" id="url-test">/test</span></p>
            <p>描述: 简单的测试接口</p>
            <button class="test-btn" onclick="testAPI(\'GET\', \'/test\', null)">测试</button>
            <div id="result-test" class="result"></div>
        </div>

        <div class="endpoint">
            <h3><span class="method get">GET</span> API 信息</h3>
            <p>URL: <span class="url" id="url-info">/info</span></p>
            <p>描述: 获取 API 系统信息</p>
            <button class="test-btn" onclick="testAPI(\'GET\', \'/info\', null)">测试</button>
            <div id="result-info" class="result"></div>
        </div>

        <div class="endpoint">
            <h3><span class="method post">POST</span> 创建新闻</h3>
            <p>URL: <span class="url" id="url-news">/news</span></p>
            <p>描述: 创建新的新闻文章</p>
            <button class="test-btn" onclick="testAPI(\'POST\', \'/news\', {name: \'测试新闻\', content: \'这是测试内容\', category: \'test\'})">测试</button>
            <div id="result-news" class="result"></div>
        </div>
    </div>

    <script>
        // 获取基础URL并更新所有端点URL显示
        const baseUrl = window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, \'\');

        // 更新页面上的URL显示
        document.getElementById(\'url-health\').textContent = baseUrl + \'health\';
        document.getElementById(\'url-test\').textContent = baseUrl + \'test\';
        document.getElementById(\'url-info\').textContent = baseUrl + \'info\';
        document.getElementById(\'url-news\').textContent = baseUrl + \'news\';

        async function testAPI(method, path, data) {
            const resultDiv = document.getElementById(\'result-\' + path.replace(\'/\', \'\').replace(\'news\', \'news\'));
            resultDiv.style.display = "block";
            resultDiv.innerHTML = "🔄 正在请求...";
            resultDiv.className = "result";

            const fullUrl = baseUrl + path;
            const options = {
                method: method,
                headers: {
                    \'Content-Type\': \'application/json\',
                    \'Accept\': \'application/json\'
                }
            };

            if (data && method === \'POST\') {
                options.body = JSON.stringify(data);
            }

            try {
                const response = await fetch(fullUrl, options);
                const text = await response.text();

                resultDiv.innerHTML = `请求URL: ${fullUrl}\\n状态: ${response.status}\\n\\n响应:\\n${text}`;
                resultDiv.className = response.ok ? "result success" : "result error";
            } catch (error) {
                resultDiv.innerHTML = `请求URL: ${fullUrl}\\n错误: ${error.message}`;
                resultDiv.className = "result error";
            }
        }
    </script>
</body>
</html>';
    exit;
}

// API 响应处理
header('Content-Type: application/json; charset=utf-8');

switch ($path) {
    case '/health':
        echo json_encode([
            'status' => 'ok',
            'timestamp' => date('c'),
            'service' => '官方网站后台API',
            'version' => '2.0.0'
        ]);
        break;

    case '/test':
        echo json_encode([
            'message' => 'Hello World',
            'timestamp' => date('c'),
            'method' => $method
        ]);
        break;

    case '/info':
        // 获取基础URL
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $scriptPath = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
        $baseUrl .= $scriptPath;

        echo json_encode([
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
        ]);
        break;

    case '/news':
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            echo json_encode([
                'success' => true,
                'message' => '新闻创建成功',
                'data' => [
                    'id' => rand(1000, 9999),
                    'name' => $input['name'] ?? '',
                    'content' => $input['content'] ?? '',
                    'category' => $input['category'] ?? '',
                    'createTime' => date('c')
                ]
            ], JSON_PRETTY_PRINT);
        } else {
            echo json_encode([
                'success' => false,
                'message' => '只支持 POST 方法',
                'data' => []
            ]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode([
            'error' => true,
            'message' => '接口不存在',
            'path' => $path
        ]);
        break;
}
