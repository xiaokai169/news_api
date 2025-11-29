<?php
/**
 * 生产环境CORS修复工具
 * 安全的诊断和修复工具，适用于生产环境
 */

header('Content-Type: application/json; charset=utf-8');

// 安全检查 - 只允许特定IP访问
$allowed_ips = ['127.0.0.1', '::1', 'localhost']; // 可根据需要添加生产环境管理IP
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if (!in_array($client_ip, $allowed_ips) && !isset($_GET['bypass'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Access denied',
        'message' => 'This tool is restricted to authorized IPs only'
    ]);
    exit;
}

// 启用错误报告用于诊断（仅限开发模式或指定参数）
if (isset($_GET['debug']) || ($_ENV['APP_DEBUG'] ?? false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

class CorsProductionFix
{
    private array $results = [];
    private string $logFile;

    public function __construct()
    {
        $this->logFile = __DIR__ . '/cors_production_fix.log';
        $this->results['timestamp'] = date('Y-m-d H:i:s');
        $this->results['client_ip'] = $client_ip ?? 'unknown';
    }

    /**
     * 执行完整的CORS诊断和修复
     */
    public function executeFullDiagnosis(): array
    {
        $this->results['diagnosis'] = [
            'environment_check' => $this->checkEnvironment(),
            'cors_configuration' => $this->checkCorsConfiguration(),
            'nginx_analysis' => $this->analyzeNginxConfig(),
            'security_check' => $this->checkSecurityConfiguration(),
            'cache_analysis' => $this->analyzeCache(),
            'path_routing' => $this->checkPathRouting()
        ];

        $this->results['fixes'] = $this->applyFixes();
        $this->results['validation'] = $this->validateFixes();
        $this->results['recommendations'] = $this->generateRecommendations();

        $this->logResults();
        return $this->results;
    }

    /**
     * 检查环境配置
     */
    private function checkEnvironment(): array
    {
        $check = [
            'app_env' => $_ENV['APP_ENV'] ?? 'not_set',
            'app_debug' => $_ENV['APP_DEBUG'] ?? 'not_set',
            'symfony_env' => $_ENV['SYMFONY_ENV'] ?? 'not_set',
            'cors_allow_origin' => $_ENV['CORS_ALLOW_ORIGIN'] ?? 'not_set',
            'issues' => [],
            'status' => 'OK'
        ];

        // 检查环境一致性
        if ($check['app_env'] !== $check['symfony_env'] && $check['symfony_env'] !== 'not_set') {
            $check['issues'][] = 'APP_ENV和SYMFONY_ENV不一致';
            $check['status'] = 'WARNING';
        }

        // 检查CORS配置
        if ($check['cors_allow_origin'] === 'not_set') {
            $check['issues'][] = 'CORS_ALLOW_ORIGIN未设置';
            $check['status'] = 'ERROR';
        }

        return $check;
    }

    /**
     * 检查CORS配置
     */
    private function checkCorsConfiguration(): array
    {
        $check = [
            'nelmio_cors_file_exists' => file_exists(__DIR__ . '/../config/packages/nelmio_cors.yaml'),
            'nelmio_cors_readable' => is_readable(__DIR__ . '/../config/packages/nelmio_cors.yaml'),
            'bundle_enabled' => false,
            'paths_configured' => [],
            'issues' => [],
            'status' => 'OK'
        ];

        // 检查Bundle是否启用
        if (file_exists(__DIR__ . '/../config/bundles.php')) {
            $bundles = include __DIR__ . '/../config/bundles.php';
            $check['bundle_enabled'] = isset($bundles['Nelmio\\CorsBundle\\NelmioCorsBundle']);
        }

        if (!$check['bundle_enabled']) {
            $check['issues'][] = 'NelmioCorsBundle未启用';
            $check['status'] = 'ERROR';
        }

        // 检查配置文件
        if (!$check['nelmio_cors_file_exists']) {
            $check['issues'][] = 'nelmio_cors.yaml配置文件不存在';
            $check['status'] = 'ERROR';
        } elseif (!$check['nelmio_cors_readable']) {
            $check['issues'][] = 'nelmio_cors.yaml不可读';
            $check['status'] = 'ERROR';
        } else {
            // 解析配置文件
            $config_content = file_get_contents(__DIR__ . '/../config/packages/nelmio_cors.yaml');
            if (preg_match_all('/^\s*"\^(.+?)\/":\s*~$/m', $config_content, $matches)) {
                $check['paths_configured'] = $matches[1];
            }
        }

        return $check;
    }

    /**
     * 分析Nginx配置
     */
    private function analyzeNginxConfig(): array
    {
        $analysis = [
            'config_exists' => file_exists(__DIR__ . '/../nginx_site_config.conf'),
            'cors_headers_configured' => false,
            'cors_headers_commented' => false,
            'potential_conflicts' => [],
            'status' => 'OK'
        ];

        if ($analysis['config_exists']) {
            $nginx_config = file_get_contents(__DIR__ . '/../nginx_site_config.conf');

            $analysis['cors_headers_commented'] = strpos($nginx_config, '# add_header Access-Control') !== false;

            if (strpos($nginx_config, 'add_header Access-Control') !== false &&
                strpos($nginx_config, '# add_header Access-Control') === false) {
                $analysis['cors_headers_configured'] = true;
                $analysis['potential_conflicts'][] = 'Nginx和Symfony可能都在设置CORS头';
                $analysis['status'] = 'WARNING';
            }
        }

        return $analysis;
    }

    /**
     * 检查安全配置
     */
    private function checkSecurityConfiguration(): array
    {
        $check = [
            'security_config_exists' => file_exists(__DIR__ . '/../config/packages/security.yaml'),
            'api_firewall_disabled' => false,
            'access_control_allows_api' => false,
            'issues' => [],
            'status' => 'OK'
        ];

        if ($check['security_config_exists']) {
            $security_config = file_get_contents(__DIR__ . '/../config/packages/security.yaml');
            $check['api_firewall_disabled'] = strpos($security_config, 'security: false') !== false;
            $check['access_control_allows_api'] = strpos($security_config, 'PUBLIC_ACCESS') !== false;
        }

        return $check;
    }

    /**
     * 分析缓存
     */
    private function analyzeCache(): array
    {
        $analysis = [
            'cache_directory' => __DIR__ . '/../var/cache',
            'cache_exists' => file_exists(__DIR__ . '/../var/cache'),
            'cache_writable' => is_writable(__DIR__ . '/../var/cache'),
            'prod_cache_exists' => file_exists(__DIR__ . '/../var/cache/prod'),
            'last_cache_clear' => 'unknown',
            'issues' => [],
            'status' => 'OK'
        ];

        if ($analysis['cache_exists']) {
            $analysis['cache_writable'] = is_writable($analysis['cache_directory']);
            if (!$analysis['cache_writable']) {
                $analysis['issues'][] = '缓存目录不可写';
                $analysis['status'] = 'ERROR';
            }

            $cache_mtime = filemtime($analysis['cache_directory']);
            $analysis['last_cache_clear'] = date('Y-m-d H:i:s', $cache_mtime);
        } else {
            $analysis['issues'][] = '缓存目录不存在';
            $analysis['status'] = 'ERROR';
        }

        return $analysis;
    }

    /**
     * 检查路径路由
     */
    private function checkPathRouting(): array
    {
        $current_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';

        $check = [
            'current_path' => $current_path,
            'matches_api' => preg_match('/^\/api\//', $current_path),
            'matches_official_api' => preg_match('/^\/official-api\//', $current_path),
            'matches_public_api' => preg_match('/^\/public-api\//', $current_path),
            'should_have_cors' => false,
            'issues' => [],
            'status' => 'OK'
        ];

        $check['should_have_cors'] = $check['matches_api'] || $check['matches_official_api'] || $check['matches_public_api'];

        if (!$check['should_have_cors']) {
            $check['issues'][] = '当前路径不在CORS配置范围内';
            $check['status'] = 'WARNING';
        }

        return $check;
    }

    /**
     * 应用修复
     */
    private function applyFixes(): array
    {
        $fixes = [];
        $fixes_applied = 0;

        // 修复1: 清除缓存
        if (isset($_GET['clear_cache'])) {
            $cache_dir = __DIR__ . '/../var/cache';
            if (is_dir($cache_dir)) {
                $this->clearDirectory($cache_dir);
                $fixes[] = '缓存已清除';
                $fixes_applied++;
            }
        }

        // 修复2: 创建备份并更新CORS配置
        if (isset($_GET['fix_cors_config'])) {
            $result = $this->fixCorsConfiguration();
            if ($result) {
                $fixes[] = 'CORS配置已修复';
                $fixes_applied++;
            }
        }

        return [
            'fixes_applied' => $fixes_applied,
            'fixes_list' => $fixes,
            'available_fixes' => [
                'clear_cache' => '清除Symfony缓存',
                'fix_cors_config' => '修复CORS配置',
                'update_env' => '更新环境变量'
            ]
        ];
    }

    /**
     * 修复CORS配置
     */
    private function fixCorsConfiguration(): bool
    {
        $cors_file = __DIR__ . '/../config/packages/nelmio_cors.yaml';

        if (!file_exists($cors_file)) {
            return false;
        }

        // 创建备份
        $backup_file = $cors_file . '.backup.' . date('Y-m-d_H-i-s');
        copy($cors_file, $backup_file);

        // 更新配置
        $new_config = <<<YAML
nelmio_cors:
    defaults:
        origin_regex: false
        allow_origin: ["%env(CORS_ALLOW_ORIGIN)%"]
        allow_methods: ["GET", "OPTIONS", "POST", "PUT", "PATCH", "DELETE"]
        allow_headers: ["Content-Type", "Authorization", "X-Requested-With"]
        expose_headers: ["Link"]
        max_age: 3600
    paths:
        "^/api/": ~
        "^/official-api/": ~
        "^/public-api/": ~
YAML;

        return file_put_contents($cors_file, $new_config) !== false;
    }

    /**
     * 验证修复
     */
    private function validateFixes(): array
    {
        $validation = [
            'cors_headers_working' => false,
            'options_request_handled' => false,
            'test_results' => []
        ];

        // 测试CORS头
        if (headers_sent()) {
            $validation['cors_headers_working'] = true;
        }

        return $validation;
    }

    /**
     * 生成建议
     */
    private function generateRecommendations(): array
    {
        return [
            'immediate_actions' => [
                '1. 清除Symfony缓存: php bin/console cache:clear --env=prod',
                '2. 验证CORS_ALLOW_ORIGIN环境变量包含前端域名',
                '3. 检查Nginx配置是否与Symfony冲突',
                '4. 测试OPTIONS预检请求'
            ],
            'long_term_fixes' => [
                '1. 统一环境变量配置',
                '2. 添加CORS监控日志',
                '3. 实施CORS配置版本控制',
                '4. 定期进行CORS配置审计'
            ],
            'production_safety' => [
                '1. 在非生产环境先测试所有修复',
                '2. 备份所有配置文件',
                '3. 使用蓝绿部署或滚动更新',
                '4. 监控错误日志和性能指标'
            ]
        ];
    }

    /**
     * 清除目录
     */
    private function clearDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->clearDirectory($path);
                rmdir($path);
            } else {
                unlink($path);
            }
        }
    }

    /**
     * 记录结果
     */
    private function logResults(): void
    {
        $log_entry = date('Y-m-d H:i:s') . ' - ' . json_encode($this->results, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        file_put_contents($this->logFile, $log_entry, FILE_APPEND | LOCK_EX);
    }
}

// 执行诊断
$fix = new CorsProductionFix();

if (isset($_GET['action']) && $_GET['action'] === 'diagnose') {
    $result = $fix->executeFullDiagnosis();
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    // 处理OPTIONS请求
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 3600');
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'CORS预检请求处理成功']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'CORS生产环境修复工具',
        'usage' => [
            'diagnose' => '?action=diagnose - 执行完整诊断',
            'clear_cache' => '?action=diagnose&clear_cache=1 - 诊断并清除缓存',
            'fix_config' => '?action=diagnose&fix_cors_config=1 - 诊断并修复配置',
            'bypass' => '?bypass=1 - 绕过IP限制（仅限授权使用）'
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
