
<?php

/**
 * 日志监控和查看工具
 * 用于在正式环境中查看各种日志文件
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// 创建简单的请求和响应对象
$request = Request::createFromGlobals();
$response = new Response();

// 日志文件配置
$logConfig = [
    'wechat' => [
        'file' => __DIR__ . '/../var/log/wechat.log',
        'name' => '微信API日志',
        'description' => '记录微信API调用、access_token获取、文章同步等操作'
    ],
    'api' => [
        'file' => __DIR__ . '/../var/log/api.log',
        'name' => 'API请求日志',
        'description' => '记录所有API请求和响应'
    ],
    'database' => [
        'file' => __DIR__ . '/../var/log/database.log',
        'name' => '数据库操作日志',
        'description' => '记录数据库查询、事务等操作'
    ],
    'performance' => [
        'file' => __DIR__ . '/../var/log/performance.log',
        'name' => '性能监控日志',
        'description' => '记录性能指标、响应时间等'
    ],
    'error' => [
        'file' => __DIR__ . '/../var/log/error.log',
        'name' => '错误日志',
        'description' => '记录所有错误和异常'
    ],
    'main' => [
        'file' => __DIR__ . '/../var/log/prod.log',
        'name' => '主日志',
        'description' => '应用程序主日志文件'
    ]
];

// 获取请求参数
$action = $request->get('action', 'list');
$logType = $request->get('type', 'wechat');
$lines = (int) $request->get('lines', 100);
$search = $request->get('search', '');

/**
 * 安全的文件读取函数
 */
function safeReadFile($filePath, $lines = 100, $search = '')
{
    if (!file_exists($filePath)) {
        return "日志文件不存在: " . basename($filePath);
    }

    if (!is_readable($filePath)) {
        return "日志文件不可读: " . basename($filePath);
    }

    try {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return "无法读取日志文件: " . basename($filePath);
        }

        // 按行分割
        $allLines = explode("\n", $content);

        // 如果有搜索条件，过滤行
        if (!empty($search)) {
            $allLines = array_filter($allLines, function($line) use ($search) {
                return stripos($line, $search) !== false;
            });
            $allLines = array_values($allLines); // 重新索引
        }

        // 获取最后N行
        $totalLines = count($allLines);
        $startLine = max(0, $totalLines - $lines);
        $selectedLines = array_slice($allLines, $startLine);

        return [
            'total_lines' => $totalLines,
            'showing_lines' => count($selectedLines),
            'content' => implode("\n", $selectedLines)
        ];

    } catch (Exception $e) {
        return "读取日志文件时出错: " . $e->getMessage();
    }
}

/**
 * 获取日志文件信息
 */
function getLogFileInfo($filePath)
{
    if (!file_exists($filePath)) {
        return [
            'exists' => false,
            'size' => 0,
            'modified' => '未知'
        ];
    }

    return [
        'exists' => true,
        'size' => filesize($filePath),
        'modified' => date('Y-m-d H:i:s', filemtime($filePath)),
        'readable' => is_readable($filePath)
    ];
}

// HTML 输出
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>日志监控面板</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .nav {
            background: #34495e;
            padding: 10px 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .nav a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .nav a:hover, .nav a.active {
            background: #3498db;
        }
        .content {
            padding: 20px;
        }
        .log-info {
            background: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .info-item {
            background: white;
            padding: 10px;
            border-radius: 4px;
            border-left: 4px solid #3498db;
        }
        .info-item strong {
            display: block;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .controls {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .controls input, .controls select, .controls button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .controls button {
            background: #3498db;
            color: white;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .controls button:hover {
            background: #2980b9;
        }
        .log-content {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 20px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            overflow-x: auto;
            white-space: pre-wrap;
            max-height: 600px;
            overflow-y: auto;
        }
        .error {
            background: #e74c3c;
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .success {
            background: #27ae60;
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .stat-item {
            background: #3498db;
            color: white;
            padding: 10px;
            border-radius: 4px;
            text-align: center;
        }
        @media (max-width: 768px) {
            .controls {
                flex-direction: column;
                align-items: stretch;
            }
            .nav {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 日志监控面板</h1>
            <p>正式环境日志查看工具</p>
        </div>

        <div class="nav">
            <a href="?action=list&type=wechat" class="<?php echo $logType === 'wechat' ? 'active' : ''; ?>">微信API日志</a>
            <a href="?action=list&type=api" class="<?php echo $logType === 'api' ? 'active' : ''; ?>">API请求日志</a>
            <a href="?action=list&type=database" class="<?php echo $logType === 'database' ? 'active' : ''; ?>">数据库日志</a>
            <a href="?action=list&type=performance" class="<?php echo $logType === 'performance' ? 'active' : ''; ?>">性能日志</a>
            <a href="?action=list&type=error" class="<?php echo $logType === 'error' ? 'active' : ''; ?>">错误日志</a>
            <a href="?action=list&type=main" class="<?php echo $logType === 'main' ? 'active' : ''; ?>">主日志</a>
            <a href="?action=overview">📈 总览</a>
        </div>

        <div class="content">
            <?php if ($action === 'overview'): ?>
                <h2>📈 日志文件总览</h2>
                <div class="log-info">
                    <?php foreach ($logConfig as $type => $config): ?>
                        <?php $info = getLogFileInfo($config['file']); ?>
                        <div class="info-item">
                            <strong><?php echo $config['name']; ?></strong>
                            <?php if ($info['exists']): ?>
                                <div>状态: ✅ 存在</div>
                                <div>大小: <?php echo number_format($info['size'] / 1024, 2); ?> KB</div>
                                <div>修改时间: <?php echo $info['modified']; ?></div>
                                <div>可读: <?php echo $info['readable'] ? '✅' : '❌'; ?></div>
                            <?php else: ?>
                                <div>状态: ❌ 不存在</div>
                                <div>路径: <?php echo basename($config['file']); ?></div>
                            <?php endif; ?>
                            <div style="margin-top: 5px;">
                                <a href="?action=list&type=<?php echo $type; ?>" style="color: #3498db;">查看日志 →</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="background: #f39c12; color: white; padding: 15px; border-radius: 5px; margin-top: 20px;">
                    <h3>🔧 使用说明</h3>
                    <ul>
                        <li>点击上方导航栏查看不同类型的日志</li>
                        <li>使用搜索框过滤特定内容</li>
                        <li>可以调整显示的行数</li>
                        <li>微信API日志记录了所有微信相关的操作</li>
                        <li>错误日志记录了所有异常和错误信息</li>
                    </ul>
                </div>

            <?php else: ?>
                <?php
                $config = $logConfig[$logType] ?? null;
                if (!$config):
                ?>
                    <div class="error">
                        <strong>错误:</strong> 未知的日志类型 "<?php echo htmlspecialchars($logType); ?>"
                    </div>
                <?php else: ?>
                    <h2>📋 <?php echo $config['name']; ?></h2>
                    <p style="color: #7f8c8d; margin-bottom: 20px;"><?php echo $config['description']; ?></p>

                    <div class="controls">
                        <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; width: 100%;">
                            <input type="hidden" name="action" value="list">
                            <input type="hidden" name="type" value="<?php echo $logType; ?>">

                            <label>显示行数:</label>
                            <select name="lines">
                                <option value="50" <?php echo $lines === 50 ? 'selected' : ''; ?>>50行</option>
                                <option value="100" <?php echo $lines === 100 ? 'selected' : ''; ?>>100行</option>
                                <option value="200" <?php echo $lines === 200 ? 'selected' : ''; ?>>200行</option>
                                <option value="500" <?php echo $lines === 500 ? 'selected' : ''; ?>>500行</option>
                                <option value="1000" <?php echo $lines === 1000 ? 'selected' : ''; ?>>1000行</option>
                            </select>

                            <label>搜索:</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="关键词搜索...">

                            <button type="submit">🔍 刷新</button>
                            <button type="button" onclick="window.location.href='?action=list&type=<?php echo $logType; ?>'">🔄 清空搜索</button>
                        </form>
                    </div>

                    <?php
                    $result = safeReadFile($config['file'], $lines, $search);

                    if (is_string($result)):
                    ?>
                        <div class="error">
                            <strong>读取错误:</strong> <?php echo htmlspecialchars($result); ?>
                        </div>
                    <?php else: ?>
                        <div class="stats">
                            <div class="stat-item">
                                <div>总行数</div>
                                <div style="font-size: 18px; font-weight: bold;"><?php echo $result['total_lines']; ?></div>
                            </div>
                            <div class="stat-item">
                                <div>显示行数</div>
                                <div style="font-size: 18px; font-weight: bold;"><?php echo $result['showing_lines']; ?></div>
                            </div>
                            <?php if (!empty($search)): ?>
                            <div class="stat-item" style="background: #e74c3c;">
                                <div>搜索关键词</div>
                                <div style="font-size: 14px;"><?php echo htmlspecialchars($search); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="log-content"><?php echo htmlspecialchars($result['content']); ?></div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // 自动刷新功能（可选）
        let autoRefresh = false;
        let refreshInterval;

        function toggleAutoRefresh() {
            autoRefresh = !autoRefresh;
            if (autoRefresh) {
                refreshInterval = setInterval(() => {
                    window.location.reload();
                }, 30000); // 30秒刷新一次
                console.log('自动刷新已启用');
            } else {
                clearInterval(refreshInterval);
                console.log('自动刷新已禁用');
            }
        }

        // 键盘快捷键
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                window.location.reload();
            }
        });
    </script>
</body>
</html>
