<?php

// 启用错误显示
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 获取请求信息
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// 移除查询字符串
$path = explode('?', $path)[0];

// 如果是根路径访问，显示 Swagger UI
if ($path === '/' || $path === '/standalone_swagger.php') {
    echo '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>官方网站后台 API 文档</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@3/swagger-ui.css">
    <style>
        body { margin: 0; padding: 0; background: #fafafa; }
        .header { background: #4a90e2; color: white; padding: 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .header p { margin: 5px 0 0 0; opacity: 0.9; }
        .container { max-width: 1200px; margin: 0 auto; background: white; min-height: 80vh; }
        .info-box { background: #e3f2fd; border: 1px solid #2196f3; border-radius: 4px; padding: 15px; margin: 20px; }
        .test-button { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-top: 10px; }
        #testResult { margin-top: 10px; padding: 10px; border-radius: 4px; display: none; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🚀 官方网站后台 API 文档</h1>
        <p>RESTful API 接口文档和测试工具</p>
    </div>
    <div class="container">
        <div class="info-box">
            <h3>📖 使用说明</h3>
            <p><strong>认证方式:</strong> JWT Bearer Token</p>
            <p><strong>Base URL:</strong> <code id="baseUrl">' . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '</code></p>
            <p><strong>获取 Token:</strong> 请通过登录接口获取 JWT Token</p>
            <button class="test-button" onclick="testAPI()">🧪 测试 API 连接</button>
            <div id="testResult"></div>
        </div>
        <div id="swagger-ui"></div>
    </div>
    <script src="https://unpkg.com/swagger-ui-dist@3/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@3/swagger-ui-standalone-preset.js"></script>
    <script>
        const apiDoc = {
            openapi: "3.0.0",
            info: {
                title: "官方网站后台 API",
                version: "2.0.0",
                description: "官方网站后台系统API文档\\n\\n## 认证说明\\n本API使用JWT Bearer Token进行认证。请在请求头中添加：\\n`Authorization: Bearer <your_jwt_token>`"
            },
            servers: [
                { url: "' . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '", description: "本地开发服务器" }
            ],
            components: {
                securitySchemes: {
                    bearerAuth: {
                        type: "http",
                        scheme: "bearer",
                        bearerFormat: "JWT",
                        description: "JWT Bearer Token认证"
                    }
                }
            },
            security: [
                { bearerAuth: [] }
            ],
            paths: {
                "/standalone_swagger.php/api/health": {
                    get: {
                        summary: "健康检查",
                        description: "健康检查接口，用于验证API服务是否正常运行",
                        tags: ["系统状态"],
                        responses: {
                            200: {
                                description: "服务正常",
                                content: {
                                    "application/json": {
                                        schema: {
                                            type: "object",
                                            properties: {
                                                status: { type: "string", example: "ok" },
                                                timestamp: { type: "string", example: "2025-11-25T05:35:00+00:00" },
                                                service: { type: "string", example: "官方网站后台API" },
                                                version: { type: "string", example: "2.0.0" }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                },
                "/standalone_swagger.php/api/test": {
                    get: {
                        summary: "测试API",
                        description: "测试API端点",
                        tags: ["测试"],
                        responses: {
                            200: {
                                description: "成功响应",
                                content: {
                                    "application/json": {
                                        schema: {
                                            type: "object",
                                            properties: {
                                                message: { type: "string", example: "Hello World" }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                },
                "/standalone_swagger.php/official-api/news": {
                    get: {
                        summary: "获取新闻文章列表",
                        description: "获取新闻文章列表，支持多条件查询和分页",
                        tags: ["新闻文章管理"],
                        security: [{ bearerAuth: [] }],
                        parameters: [
                            { name: "page", in: "query", schema: { type: "integer", default: 1 }, description: "页码（从1开始）" },
                            { name: "limit", in: "query", schema: { type: "integer", default: 20 }, description: "每页数量" },
                            { name: "status", in: "query", schema: { type: "integer" }, description: "状态（1=激活，2=非激活）" }
                        ],
                        responses: {
                            200: {
                                description: "获取成功",
                                content: {
                                    "application/json": {
                                        schema: {
                                            type: "object",
                                            properties: {
                                                code: { type: "integer", example: 200 },
                                                message: { type: "string", example: "success" },
                                                data: {
                                                    type: "object",
                                                    properties: {
                                                        items: { type: "array", items: { type: "object" } },
                                                        total: { type: "integer", example: 100 },
                                                        page: { type: "integer", example: 1 },
                                                        limit: { type: "integer", example: 20 }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    },
                    post: {
                        summary: "创建新闻文章",
                        description: "创建新的新闻文章",
                        tags: ["新闻文章管理"],
                        security: [{ bearerAuth: [] }],
                        requestBody: {
                            required: true,
                            content: {
                                "application/json": {
                                    schema: {
                                        type: "object",
                                        required: ["name", "cover", "content", "category"],
                                        properties: {
                                            name: { type: "string", description: "文章名称" },
                                            cover: { type: "string", description: "封面图片" },
                                            content: { type: "string", description: "文章内容" },
                                            category: { type: "string", description: "分类ID或分类编码" }
                                        }
                                    }
                                }
                            }
                        },
                        responses: {
                            201: {
                                description: "创建成功",
                                content: {
                                    "application/json": {
                                        schema: {
                                            type: "object",
                                            properties: {
                                                code: { type: "integer", example: 201 },
                                                message: { type: "string", example: "创建成功" },
                                                data: { type: "object" }
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

        const ui = SwaggerUIBundle({
            spec: apiDoc,
            dom_id: "#swagger-ui",
            deepLinking: true,
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIStandalonePreset
            ],
            plugins: [
                SwaggerUIBundle.plugins.DownloadUrl
            ],
            layout: "StandaloneLayout",
            supportedSubmitMethods: ["get", "post", "put", "delete", "patch"],
            tryItOutEnabled: true,
            requestInterceptor: function(request) {
                request.headers["Accept"] = "application/json";
                return request;
            }
        });

        async function testAPI() {
            const resultDiv = document.getElementById("testResult");
            resultDiv.style.display = "block";
            resultDiv.innerHTML = "🔄 正在测试 API 连接...";
            resultDiv.style.background = "#e3f2fd";
            resultDiv.style.color = "#1976d2";

            try {
                const response = await fetch("' . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/standalone_swagger.php/api/health");
                const data = await response.json();

                if (response.ok && data.code === 200) {
                    resultDiv.innerHTML = "✅ API 连接正常！<br><pre>" + JSON.stringify(data, null, 2) + "</pre>";
                    resultDiv.style.background = "#d4edda";
                    resultDiv.style.color = "#155724";
                } else {
                    throw new Error(data.message || "API 响应异常");
                }
            } catch (error) {
                resultDiv.innerHTML = "❌ API 连接失败！<br>错误: " + error.message;
                resultDiv.style.background = "#f8d7da";
                resultDiv.style.color = "#721c24";
            }
        }

        window.addEventListener("load", function() {
            setTimeout(testAPI, 1000);
        });
    </script>
</body>
</html>';
    exit;
}

// 处理 API 请求
if (strpos($path, '/standalone_swagger.php/api/') === 0) {
    header('Content-Type: application/json; charset=utf-8');

    // 路由处理
    $apiPath = str_replace('/standalone_swagger.php', '', $path);

    if ($apiPath === '/api/health') {
        echo json_encode([
            'code' => 200,
            'message' => 'success',
            'data' => [
                'status' => 'ok',
                'timestamp' => (new DateTime())->format('c'),
                'service' => '官方网站后台API',
                'version' => '2.0.0'
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } elseif ($apiPath === '/api/test') {
        echo json_encode([
            'code' => 200,
            'message' => 'success',
            'data' => [
                'message' => 'Hello World',
                'timestamp' => (new DateTime())->format('c')
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } elseif ($apiPath === '/official-api/news' && $method === 'GET') {
        $news = [
            [
                'id' => 1,
                'name' => '测试新闻1',
                'cover' => 'https://example.com/cover1.jpg',
                'content' => '这是测试新闻的内容',
                'status' => 1,
                'createTime' => '2025-11-25T05:00:00+00:00'
            ],
            [
                'id' => 2,
                'name' => '测试新闻2',
                'cover' => 'https://example.com/cover2.jpg',
                'content' => '这是另一篇测试新闻的内容',
                'status' => 1,
                'createTime' => '2025-11-25T04:00:00+00:00'
            ]
        ];

        echo json_encode([
            'code' => 200,
            'message' => 'success',
            'data' => [
                'items' => $news,
                'total' => count($news),
                'page' => 1,
                'limit' => 20,
                'pages' => 1
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } elseif ($apiPath === '/official-api/news' && $method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            http_response_code(400);
            echo json_encode([
                'code' => 400,
                'message' => '请求数据格式错误',
                'error' => true
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $news = [
                'id' => rand(1000, 9999),
                'name' => $input['name'] ?? '新新闻',
                'cover' => $input['cover'] ?? '',
                'content' => $input['content'] ?? '',
                'category' => $input['category'] ?? '',
                'status' => $input['status'] ?? 1,
                'createTime' => (new DateTime())->format('c')
            ];

            http_response_code(201);
            echo json_encode([
                'code' => 201,
                'message' => '创建成功',
                'data' => $news
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    } else {
        http_response_code(404);
        echo json_encode([
            'code' => 404,
            'message' => '接口不存在',
            'error' => true
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 默认响应
http_response_code(404);
echo json_encode([
    'code' => 404,
    'message' => '页面不存在',
    'error' => true
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
