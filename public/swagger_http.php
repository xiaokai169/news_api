<?php
/**
 * HTTP访问的Swagger文档入口
 * 解决file://协议导致的CORS问题
 */

// 设置正确的HTTP头
header('Content-Type: text/html; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// 处理OPTIONS预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 获取当前服务器信息
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . '://' . $host;

// 获取当前端口
$port = $_SERVER['SERVER_PORT'];
$currentServer = '';
switch ($port) {
    case '8000':
        $currentServer = 'Symfony 服务器 (主要API)';
        break;
    case '8001':
        $currentServer = '简单API服务器 (带CORS修复)';
        break;
    case '8002':
        $currentServer = '测试服务器';
        break;
    default:
        $currentServer = '未知服务器';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>官方网站后台 API 文档 - HTTP访问</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@3/swagger-ui.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #fafafa;
        }

        .header {
            background: #4a90e2;
            color: white;
            padding: 20px;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .header p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            min-height: 80vh;
        }

        .info-box {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 4px;
            padding: 15px;
            margin: 20px;
        }

        .server-info {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: 15px;
            margin: 20px;
        }

        .api-list {
            padding: 20px;
        }

        .api-item {
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 10px 0;
            padding: 15px;
        }

        .api-item h3 {
            margin: 0 0 10px 0;
            color: #333;
        }

        .method {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            color: white;
            font-size: 12px;
            font-weight: bold;
            margin-right: 10px;
        }

        .get { background: #61affe; }
        .post { background: #49cc90; }
        .put { background: #fca130; }
        .delete { background: #f93e3e; }
        .patch { background: #50e3c2; }

        .server-selector {
            margin: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .server-selector select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>🚀 官方网站后台 API 文档</h1>
        <p>RESTful API 接口文档和测试工具 - HTTP访问模式</p>
    </div>

    <div class="container">
        <div class="server-info">
            <h3>🌐 当前访问信息</h3>
            <p><strong>当前服务器:</strong> <?php echo htmlspecialchars($currentServer); ?></p>
            <p><strong>访问端口:</strong> <?php echo htmlspecialchars($port); ?></p>
            <p><strong>Base URL:</strong> <code id="baseUrl"><?php echo htmlspecialchars($baseUrl); ?></code></p>
            <p><strong>访问协议:</strong> <span style="color: green;">✓ HTTP协议 (无CORS问题)</span></p>
        </div>

        <div class="server-selector">
            <h3>🔄 选择API服务器</h3>
            <select id="serverSelect" onchange="changeServer()">
                <option value="http://localhost:8000" <?php echo $port == '8000' ? 'selected' : ''; ?>>端口 8000 - Symfony 服务器 (主要API)</option>
                <option value="http://localhost:8001" <?php echo $port == '8001' ? 'selected' : ''; ?>>端口 8001 - 简单API服务器 (带CORS修复)</option>
                <option value="http://localhost:8002" <?php echo $port == '8002' ? 'selected' : ''; ?>>端口 8002 - 测试服务器</option>
            </select>
        </div>

        <div class="info-box">
            <h3>📖 使用说明</h3>
            <p><strong>认证方式:</strong> JWT Bearer Token</p>
            <p><strong>推荐服务器:</strong> 端口8001 (已修复CORS问题)</p>
            <p><strong>注意:</strong> 您正在通过HTTP协议访问，避免了file://协议的CORS限制</p>
        </div>

        <div id="swagger-ui"></div>

        <div class="api-list">
            <h2>📋 API 端点列表</h2>

            <div class="api-item">
                <h3><span class="method get">GET</span> /api/health</h3>
                <p><strong>描述:</strong> 健康检查接口</p>
                <p><strong>认证:</strong> 不需要</p>
            </div>

            <div class="api-item">
                <h3><span class="method get">GET</span> /api/test</h3>
                <p><strong>描述:</strong> 测试接口</p>
                <p><strong>认证:</strong> 不需要</p>
            </div>

            <div class="api-item">
                <h3><span class="method get">GET</span> /api/info</h3>
                <p><strong>描述:</strong> API 系统信息</p>
                <p><strong>认证:</strong> 不需要</p>
            </div>

            <div class="api-item">
                <h3><span class="method get">GET</span> /official-api/news</h3>
                <p><strong>描述:</strong> 获取新闻文章列表</p>
                <p><strong>认证:</strong> 需要 JWT Token</p>
                <p><strong>参数:</strong> page, limit, status, categoryCode 等</p>
            </div>

            <div class="api-item">
                <h3><span class="method post">POST</span> /official-api/news</h3>
                <p><strong>描述:</strong> 创建新闻文章</p>
                <p><strong>认证:</strong> 需要 JWT Token</p>
                <p><strong>请求体:</strong> name, cover, content, category 等</p>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/swagger-ui-dist@3/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@3/swagger-ui-standalone-preset.js"></script>
    <script>
        // 手动构建 API 文档
        const apiDoc = {
            openapi: '3.0.0',
            info: {
                title: '官方网站后台 API',
                version: '2.0.0',
                description: '官方网站后台系统API文档\n\n## 认证说明\n本API使用JWT Bearer Token进行认证。请在请求头中添加：\n`Authorization: Bearer <your_jwt_token>`\n\n## CORS说明\n通过HTTP协议访问本页面可避免file://协议的CORS限制。'
            },
            servers: [
                { url: '<?php echo htmlspecialchars($baseUrl); ?>', description: '当前服务器' },
                { url: 'http://localhost:8000', description: 'Symfony 服务器 (主要API)' },
                { url: 'http://localhost:8001', description: '简单API服务器 (带CORS修复)' },
                { url: 'http://localhost:8002', description: '测试服务器' }
            ],
            components: {
                securitySchemes: {
                    bearerAuth: {
                        type: 'http',
                        scheme: 'bearer',
                        bearerFormat: 'JWT',
                        description: 'JWT Bearer Token认证'
                    }
                }
            },
            security: [
                { bearerAuth: [] }
            ],
            paths: {
                '/api/health': {
                    get: {
                        summary: '健康检查',
                        description: '健康检查接口，用于验证API服务是否正常运行',
                        tags: ['系统状态'],
                        responses: {
                            200: {
                                description: '服务正常',
                                content: {
                                    'application/json': {
                                        schema: {
                                            type: 'object',
                                            properties: {
                                                status: { type: 'string', example: 'ok' },
                                                timestamp: { type: 'string', example: '<?php echo date('c'); ?>' },
                                                service: { type: 'string', example: '官方网站后台API' },
                                                version: { type: 'string', example: '2.0.0' }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                },
                '/api/test': {
                    get: {
                        summary: '测试API',
                        description: '测试API端点',
                        tags: ['测试'],
                        responses: {
                            200: {
                                description: '成功响应',
                                content: {
                                    'application/json': {
                                        schema: {
                                            type: 'object',
                                            properties: {
                                                message: { type: 'string', example: 'Hello World' },
                                                server: { type: 'string', example: '<?php echo htmlspecialchars($currentServer); ?>' }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        };

        // 初始化 Swagger UI
        const ui = SwaggerUIBundle({
            spec: apiDoc,
            dom_id: '#swagger-ui',
            deepLinking: true,
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIStandalonePreset
            ],
            plugins: [
                SwaggerUIBundle.plugins.DownloadUrl
            ],
            layout: "StandaloneLayout",
            supportedSubmitMethods: ['get', 'post', 'put', 'delete', 'patch'],
            tryItOutEnabled: true,
            requestInterceptor: function (request) {
                request.headers['Accept'] = 'application/json';
                return request;
            }
        });

        // 暴露到全局作用域
        window.ui = ui;

        // 切换服务器函数
        function changeServer() {
            const select = document.getElementById('serverSelect');
            const selectedUrl = select.value;

            // 更新Base URL显示
            document.getElementById('baseUrl').textContent = selectedUrl;

            // 更新Swagger UI的服务器URL
            if (window.ui) {
                window.ui.specActions.updateServer(selectedUrl);
            }

            // 更新第一个服务器选项为当前选择的服务器
            apiDoc.servers[0].url = selectedUrl;
            apiDoc.servers[0].description = '当前选择的服务器';

            // 重新加载Swagger UI
            window.ui.specActions.download();
        }

        // 页面加载完成后的初始化
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Swagger HTTP访问页面已加载');
            console.log('当前服务器:', '<?php echo htmlspecialchars($currentServer); ?>');
            console.log('访问端口:', '<?php echo htmlspecialchars($port); ?>');
        });
    </script>
</body>

</html>
