<?php

use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

function dbConnectionChecker() {
    $startTime = microtime(true);

    // 安全检查：只允许特定IP访问或在开发环境
    $allowedIps = ['127.0.0.1', '::1', 'localhost'];
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $isProd = getenv('APP_ENV') === 'prod';

    if ($isProd && !in_array($clientIp, $allowedIps) && !isset($_GET['token'])) {
        http_response_code(403);
        echo '<h1>403 - 禁止访问</h1>';
        echo '<p>此工具仅允许本地访问或需要有效的访问令牌</p>';
        return;
    }

    // 简单的令牌验证
    if (isset($_GET['token']) && $_GET['token'] !== 'db_check_2024_secure') {
        http_response_code(403);
        echo '<h1>403 - 无效令牌</h1>';
        return;
    }

    try {
        $kernel = new Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
        $kernel->boot();

        $container = $kernel->getContainer();
        $doctrine = $container->get('doctrine');
        $entityManager = $container->get(EntityManagerInterface::class);

        // 获取所有连接信息
        $allConnections = $doctrine->getConnections();
        $allManagers = $doctrine->getManagers();
        $defaultConnection = $doctrine->getDefaultConnectionName();
        $defaultManager = $doctrine->getDefaultManagerName();

        $connectionStatus = [];
        $errors = [];

        foreach ($allConnections as $name => $connection) {
            try {
                $status = [
                    'name' => $name,
                    'is_default' => $name === $defaultConnection,
                    'database' => null,
                    'host' => null,
                    'port' => null,
                    'driver' => null,
                    'status' => 'connected',
                    'response_time' => 0,
                    'error' => null
                ];

                // 获取连接参数
                $params = $connection->getParams();
                $status['database'] = $params['dbname'] ?? 'unknown';
                $status['host'] = $params['host'] ?? 'unknown';
                $status['port'] = $params['port'] ?? 'default';
                $status['driver'] = $params['driver'] ?? 'unknown';

                // 测试连接
                $testStart = microtime(true);
                $connection->executeQuery('SELECT 1');
                $status['response_time'] = round((microtime(true) - $testStart) * 1000, 2);

                // 获取数据库版本信息
                try {
                    $versionQuery = $connection->executeQuery('SELECT VERSION() as version');
                    $version = $versionQuery->fetchOne();
                    $status['mysql_version'] = $version;
                } catch (\Exception $e) {
                    $status['mysql_version'] = 'unknown';
                }

                $connectionStatus[$name] = $status;

            } catch (\Exception $e) {
                $connectionStatus[$name] = [
                    'name' => $name,
                    'is_default' => $name === $defaultConnection,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'database' => 'unknown',
                    'host' => 'unknown',
                    'port' => 'unknown',
                    'driver' => 'unknown',
                    'response_time' => 0,
                    'mysql_version' => 'unknown'
                ];
                $errors[] = "连接 '{$name}': " . $e->getMessage();
            }
        }

        // 获取实体管理器信息
        $managerInfo = [];
        foreach ($allManagers as $name => $manager) {
            try {
                $managerInfo[$name] = [
                    'name' => $name,
                    'is_default' => $name === $defaultManager,
                    'connection_name' => $manager->getConnection()->getDatabasePlatform()->getName(),
                    'entity_paths' => $manager->getConfiguration()->getMetadataDriverImpl()->getPaths()
                ];
            } catch (\Exception $e) {
                $managerInfo[$name] = [
                    'name' => $name,
                    'is_default' => $name === $defaultManager,
                    'error' => $e->getMessage()
                ];
            }
        }

        // 输出HTML页面
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        echo '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据库连接状态检测</title>
    <style>
        body { font-family: "Segoe UI", Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        .status-connected { color: #28a745; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
        .default-badge { background: #007bff; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px; margin-left: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; font-weight: bold; }
        tr:hover { background-color: #f5f5f5; }
        .error-section { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .success-section { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .info-section { background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 14px; }
        .test-button { background: #007bff; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .test-button:hover { background: #0056b3; }
        .response-time { font-size: 12px; color: #666; }
    </style>
    <script>
        function testConnection(connectionName) {
            fetch("?test=" + connectionName + "&token=' . ($_GET['token'] ?? '') . '")
                .then(response => response.text())
                .then(data => {
                    alert("连接测试结果:\n" + data);
                })
                .catch(error => {
                    alert("测试失败: " + error);
                });
        }

        function refreshPage() {
            window.location.reload();
        }
    </script>
</head>
<body>
    <div class="container">
        <h1>🔍 数据库连接状态检测</h1>

        <div class="info-section">
            <strong>环境信息:</strong><br>
            • 环境: ' . htmlspecialchars(getenv('APP_ENV')) . '<br>
            • 默认连接: <span class="default-badge">' . htmlspecialchars($defaultConnection) . '</span><br>
            • 默认实体管理器: <span class="default-badge">' . htmlspecialchars($defaultManager) . '</span><br>
            • 检测时间: ' . date('Y-m-d H:i:s') . '<br>
            • 执行时间: ' . $executionTime . 'ms<br>
            • 客户端IP: ' . htmlspecialchars($clientIp) . '
        </div>';

        if (!empty($errors)) {
            echo '<div class="error-section">
                <h3>⚠️ 连接错误</h3>
                <ul>';
            foreach ($errors as $error) {
                echo '<li>' . htmlspecialchars($error) . '</li>';
            }
            echo '</ul></div>';
        } else {
            echo '<div class="success-section">
                <h3>✅ 所有连接正常</h3>
                <p>所有数据库连接都成功建立。</p>
            </div>';
        }

        echo '<h2>📊 连接状态详情</h2>
        <table>
            <thead>
                <tr>
                    <th>连接名称</th>
                    <th>状态</th>
                    <th>数据库</th>
                    <th>主机:端口</th>
                    <th>驱动</th>
                    <th>MySQL版本</th>
                    <th>响应时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($connectionStatus as $conn) {
            $statusClass = $conn['status'] === 'connected' ? 'status-connected' : 'status-error';
            $statusText = $conn['status'] === 'connected' ? '✅ 连接' : '❌ 错误';
            $defaultBadge = $conn['is_default'] ? '<span class="default-badge">默认</span>' : '';

            echo '<tr>
                <td>' . htmlspecialchars($conn['name']) . $defaultBadge . '</td>
                <td class="' . $statusClass . '">' . $statusText . '</td>
                <td>' . htmlspecialchars($conn['database']) . '</td>
                <td>' . htmlspecialchars($conn['host']) . ':' . htmlspecialchars($conn['port']) . '</td>
                <td>' . htmlspecialchars($conn['driver']) . '</td>
                <td>' . htmlspecialchars($conn['mysql_version']) . '</td>
                <td><span class="response-time">' . $conn['response_time'] . 'ms</span></td>
                <td><button class="test-button" onclick="testConnection(\'' . htmlspecialchars($conn['name']) . '\')">测试连接</button></td>
            </tr>';

            if ($conn['error']) {
                echo '<tr><td colspan="8" style="background-color: #f8d7da; color: #721c24; font-size: 12px;">
                    <strong>错误详情:</strong> ' . htmlspecialchars($conn['error']) . '
                </td></tr>';
            }
        }

        echo '</tbody></table>';

        echo '<h2>🗂️ 实体管理器信息</h2>
        <table>
            <thead>
                <tr>
                    <th>管理器名称</th>
                    <th>状态</th>
                    <th>连接名称</th>
                    <th>实体路径</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($managerInfo as $manager) {
            $defaultBadge = $manager['is_default'] ? '<span class="default-badge">默认</span>' : '';
            $paths = is_array($manager['entity_paths'] ?? []) ? implode(', ', $manager['entity_paths']) : 'unknown';

            echo '<tr>
                <td>' . htmlspecialchars($manager['name']) . $defaultBadge . '</td>
                <td>' . (isset($manager['error']) ? '<span class="status-error">错误</span>' : '<span class="status-connected">正常</span>') . '</td>
                <td>' . htmlspecialchars($manager['connection_name'] ?? 'unknown') . '</td>
                <td>' . htmlspecialchars($paths) . '</td>
            </tr>';

            if (isset($manager['error'])) {
                echo '<tr><td colspan="4" style="background-color: #f8d7da; color: #721c24; font-size: 12px;">
                    <strong>错误详情:</strong> ' . htmlspecialchars($manager['error']) . '
                </td></tr>';
            }
        }

        echo '</tbody></table>';

        echo '<div style="margin-top: 20px;">
            <button class="test-button" onclick="refreshPage()">🔄 刷新页面</button>
            <button class="test-button" onclick="window.print()">🖨️ 打印报告</button>
        </div>';

        echo '<div class="footer">
            <p><strong>使用说明:</strong></p>
            <ul>
                <li>此工具用于检测数据库连接状态和配置信息</li>
                <li>在生产环境中，请使用访问令牌或限制IP访问</li>
                <li>默认连接: <code>default</code> → <code>official_website</code> 数据库</li>
                <li>用户连接: <code>user</code> → <code>official_website_user</code> 数据库</li>
                <li>默认实体管理器: <code>user</code> (用于安全组件)</li>
            </ul>
        </div>';

    } catch (\Exception $e) {
        echo '<div class="error-section">
            <h1>❌ 系统错误</h1>
            <p><strong>错误信息:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>
            <p><strong>错误位置:</strong> ' . htmlspecialchars($e->getFile() . ':' . $e->getLine()) . '</p>
            <pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; overflow: auto;">' . htmlspecialchars($e->getTraceAsString()) . '</pre>
        </div>';
    }
}

// 处理单独的连接测试请求
if (isset($_GET['test'])) {
    try {
        $kernel = new Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
        $kernel->boot();
        $doctrine = $kernel->getContainer()->get('doctrine');
        $connection = $doctrine->getConnection($_GET['test']);

        $start = microtime(true);
        $result = $connection->executeQuery('SELECT 1 as test, NOW() as current_time')->fetch();
        $time = round((microtime(true) - $start) * 1000, 2);

        echo "✅ 连接成功\n";
        echo "响应时间: {$time}ms\n";
        echo "测试结果: " . json_encode($result) . "\n";
        echo "数据库名: " . $connection->getDatabase() . "\n";

    } catch (\Exception $e) {
        echo "❌ 连接失败\n";
        echo "错误信息: " . $e->getMessage() . "\n";
    }
} else {
    dbConnectionChecker();
}
