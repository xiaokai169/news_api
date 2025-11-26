<?php
/**
 * Swagger路由处理器
 * 为不同端口的服务器提供Swagger文档访问
 */

// 设置CORS头
header('Content-Type: text/html; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// 处理OPTIONS预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 获取请求的路径
$requestUri = $_SERVER['REQUEST_URI'];
$parsedUrl = parse_url($requestUri);
$path = $parsedUrl['path'] ?? '';

// 根据路径路由到不同的处理
switch ($path) {
    case '/swagger':
    case '/swagger/':
        // 重定向到主要的Swagger文档
        header('Location: swagger_http.php');
        exit;

    case '/api-docs':
    case '/api-docs/':
        // API文档JSON格式
        header('Content-Type: application/json');
        echo json_encode([
            'openapi' => '3.0.0',
            'info' => [
                'title' => '官方网站后台 API',
                'version' => '2.0.0',
                'description' => '官方网站后台系统API文档'
            ],
            'servers' => [
                ['url' => 'http://localhost:8000', 'description' => 'Symfony 服务器'],
                ['url' => 'http://localhost:8001', 'description' => '简单API服务器'],
                ['url' => 'http://localhost:8002', 'description' => '测试服务器']
            ],
            'paths' => [
                '/api/health' => [
                    'get' => [
                        'summary' => '健康检查',
                        'responses' => [
                            '200' => [
                                'description' => '服务正常'
                            ]
                        ]
                    ]
                ],
                '/api/test' => [
                    'get' => [
                        'summary' => '测试API',
                        'responses' => [
                            '200' => [
                                'description' => '成功响应'
                            ]
                        ]
                    ]
                ]
            ]
        ]);
        exit;

    default:
        // 默认显示导航页面
        break;
}

// 获取当前服务器信息
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$port = $_SERVER['SERVER_PORT'];
$baseUrl = $protocol . '://' . $host;

// 服务器状态映射
$serverStatus = [
    '8000' => ['name' => 'Symfony 服务器', 'status' => '运行中', 'type' => '主要API'],
    '8001' => ['name' => '简单API服务器', 'status' => '运行中', 'type' => '带CORS修复'],
    '8002' => ['name' => '测试服务器', 'status' => '运行中', 'type' => '测试环境']
];

$currentServer = $serverStatus[$port] ?? ['name' => '未知服务器', 'status' => '未知', 'type' => '未知'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API文档导航 - 官方网站后台</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: #4a90e2;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .server-info {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .server-info h3 {
            margin: 0 0 15px 0;
            color: #1976d2;
        }
        .server-info p {
            margin: 8px 0;
        }
        .links {
            display: grid;
            gap: 15px;
        }
        .link-card {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 20px;
            background: #fafafa;
            transition: all 0.3s ease;
        }
        .link-card:hover {
            border-color: #4a90e2;
            box-shadow: 0 2px 8px rgba(74, 144, 226, 0.2);
        }
        .link-card h4 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .link-card p {
            margin: 5px 0;
            color: #666;
            font-size: 14px;
        }
        .link-card a {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 16px;
            background: #4a90e2;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }
        .link-card a:hover {
            background: #357abd;
        }
        .status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }
        .status.running {
            background: #4caf50;
        }
        .note {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 15px;
            margin-top: 20px;
        }
        .note h4 {
            margin: 0 0 8px 0;
            color: #856404;
        }
        .note p {
            margin: 5px 0;
            color: #856404;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 官方网站后台 API 文档</h1>
            <p>选择您要访问的API文档</p>
        </div>

        <div class="content">
            <div class="server-info">
                <h3>🌐 当前服务器信息</h3>
                <p><strong>服务器名称:</strong> <?php echo htmlspecialchars($currentServer['name']); ?></p>
                <p><strong>服务器类型:</strong> <?php echo htmlspecialchars($currentServer['type']); ?></p>
                <p><strong>运行状态:</strong> <span class="status running"><?php echo htmlspecialchars($currentServer['status']); ?></span></p>
                <p><strong>访问地址:</strong> <code><?php echo htmlspecialchars($baseUrl); ?></code></p>
            </div>

            <div class="links">
                <div class="link-card">
                    <h4>📚 主要Swagger文档</h4>
                    <p>完整的API文档界面，支持在线测试</p>
                    <p><strong>推荐:</strong> 使用此入口避免CORS问题</p>
                    <a href="swagger_http.php">打开Swagger文档</a>
                </div>

                <div class="link-card">
                    <h4>📄 手动Swagger文档</h4>
                    <p>静态HTML版本的API文档</p>
                    <p><strong>注意:</strong> 可能存在CORS限制</p>
                    <a href="swagger_manual.html">打开手动文档</a>
                </div>

                <div class="link-card">
                    <h4>🔧 独立Swagger界面</h4>
                    <p>独立运行的Swagger UI界面</p>
                    <p><strong>用途:</strong> 用于测试和调试</p>
                    <a href="standalone_swagger.php">打开独立界面</a>
                </div>

                <div class="link-card">
                    <h4>📊 API文档JSON</h4>
                    <p>OpenAPI 3.0格式的API文档</p>
                    <p><strong>用途:</strong> 用于程序化访问</p>
                    <a href="api-docs">查看JSON文档</a>
                </div>
            </div>

            <div class="note">
                <h4>💡 使用提示</h4>
                <p>• 推荐使用 <strong>swagger_http.php</strong> 入口，已解决CORS问题</p>
                <p>• 端口8001的服务器已专门修复了CORS头设置</p>
                <p>• 如需测试，建议先访问 <code>/api/health</code> 检查服务状态</p>
                <p>• 所有API都支持JWT Bearer Token认证</p>
            </div>
        </div>
    </div>
</body>
</html>
