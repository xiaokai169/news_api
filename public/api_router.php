<?php

// 启用错误显示
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 获取请求信息
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// 移除查询字符串
$path = explode('?', $path)[0];

// 路由定义
$routes = [
    'GET /api/health' => 'handleHealth',
    'GET /api/test' => 'handleTest',
    'GET /api/info' => 'handleInfo',
    'GET /official-api/news' => 'handleNewsList',
    'POST /official-api/news' => 'handleNewsCreate',
    'GET /official-api/news/{id}' => 'handleNewsShow',
    'PUT /official-api/news/{id}' => 'handleNewsUpdate',
    'DELETE /official-api/news/{id}' => 'handleNewsDelete',
];

// 路由匹配
$routeKey = "$method $path";
$matchedRoute = null;
$params = [];

// 精确匹配
if (isset($routes[$routeKey])) {
    $matchedRoute = $routes[$routeKey];
} else {
    // 参数路由匹配
    foreach ($routes as $routePattern => $handler) {
        list($routeMethod, $routePath) = explode(' ', $routePattern, 2);
        if ($routeMethod !== $method) continue;

        // 转换路径模式为正则表达式
        $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $path, $matches)) {
            $matchedRoute = $handler;
            // 提取参数
            $paramNames = [];
            preg_match_all('/\{([^}]+)\}/', $routePath, $paramNames);
            for ($i = 1; $i < count($matches); $i++) {
                $params[$paramNames[1][$i-1]] = $matches[$i];
            }
            break;
        }
    }
}

// 处理请求
if ($matchedRoute) {
    try {
        $response = call_user_func($matchedRoute, $params);
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'code' => 500,
            'message' => '服务器错误: ' . $e->getMessage(),
            'error' => true
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(404);
    echo json_encode([
        'code' => 404,
        'message' => '接口不存在',
        'error' => true,
        'path' => $path,
        'method' => $method
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

// 路由处理函数
function handleHealth($params = []) {
    return [
        'code' => 200,
        'message' => 'success',
        'data' => [
            'status' => 'ok',
            'timestamp' => (new DateTime())->format('c'),
            'service' => '官方网站后台API',
            'version' => '2.0.0'
        ]
    ];
}

function handleTest($params = []) {
    return [
        'code' => 200,
        'message' => 'success',
        'data' => [
            'message' => 'Hello World',
            'timestamp' => (new DateTime())->format('c')
        ]
    ];
}

function handleInfo($params = []) {
    return [
        'code' => 200,
        'message' => 'success',
        'data' => [
            'name' => '官方网站后台API',
            'version' => '2.0.0',
            'description' => '提供新闻文章管理、用户认证等功能的RESTful API',
            'features' => [
                '新闻文章管理',
                '用户认证',
                'JWT Token管理',
                '微信公众号管理',
                '文件上传'
            ],
            'authentication' => 'JWT Bearer Token',
            'documentation' => '/api/doc'
        ]
    ];
}

function handleNewsList($params = []) {
    // 模拟新闻列表数据
    $news = [
        [
            'id' => 1,
            'name' => '测试新闻1',
            'cover' => 'https://example.com/cover1.jpg',
            'content' => '这是测试新闻的内容',
            'status' => 1,
            'createTime' => '2025-11-25T05:00:00+00:00',
            'updateTime' => '2025-11-25T05:00:00+00:00'
        ],
        [
            'id' => 2,
            'name' => '测试新闻2',
            'cover' => 'https://example.com/cover2.jpg',
            'content' => '这是另一篇测试新闻的内容',
            'status' => 1,
            'createTime' => '2025-11-25T04:00:00+00:00',
            'updateTime' => '2025-11-25T04:00:00+00:00'
        ]
    ];

    return [
        'code' => 200,
        'message' => 'success',
        'data' => [
            'items' => $news,
            'total' => count($news),
            'page' => 1,
            'limit' => 20,
            'pages' => 1
        ]
    ];
}

function handleNewsShow($params) {
    $id = $params['id'] ?? 0;

    // 模拟新闻数据
    $news = [
        'id' => $id,
        'name' => "测试新闻{$id}",
        'cover' => "https://example.com/cover{$id}.jpg",
        'content' => "这是测试新闻{$id}的详细内容",
        'status' => 1,
        'createTime' => '2025-11-25T05:00:00+00:00',
        'updateTime' => '2025-11-25T05:00:00+00:00'
    ];

    return [
        'code' => 200,
        'message' => 'success',
        'data' => $news
    ];
}

function handleNewsCreate($params = []) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        return [
            'code' => 400,
            'message' => '请求数据格式错误',
            'error' => true
        ];
    }

    // 验证必填字段
    $required = ['name', 'cover', 'content', 'category'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            return [
                'code' => 400,
                'message' => "字段 {$field} 不能为空",
                'error' => true
            ];
        }
    }

    // 模拟创建成功
    $news = [
        'id' => rand(1000, 9999),
        'name' => $input['name'],
        'cover' => $input['cover'],
        'content' => $input['content'],
        'category' => $input['category'],
        'status' => $input['status'] ?? 1,
        'createTime' => (new DateTime())->format('c'),
        'updateTime' => (new DateTime())->format('c')
    ];

    http_response_code(201);
    return [
        'code' => 201,
        'message' => '创建成功',
        'data' => $news
    ];
}

function handleNewsUpdate($params) {
    $id = $params['id'] ?? 0;
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        return [
            'code' => 400,
            'message' => '请求数据格式错误',
            'error' => true
        ];
    }

    // 模拟更新成功
    $news = [
        'id' => $id,
        'name' => $input['name'] ?? "更新后的新闻{$id}",
        'cover' => $input['cover'] ?? "https://example.com/cover{$id}.jpg",
        'content' => $input['content'] ?? "更新后的内容{$id}",
        'status' => $input['status'] ?? 1,
        'updateTime' => (new DateTime())->format('c')
    ];

    return [
        'code' => 200,
        'message' => '更新成功',
        'data' => $news
    ];
}

function handleNewsDelete($params) {
    $id = $params['id'] ?? 0;

    return [
        'code' => 200,
        'message' => '删除成功',
        'data' => ['id' => (int)$id]
    ];
}
