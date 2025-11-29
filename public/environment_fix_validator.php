<?php
/**
 * 环境变量修复和验证工具
 * 解决.env文件与Nginx配置的环境变量冲突
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

$validator = [
    'timestamp' => date('Y-m-d H:i:s'),
    'status' => 'validation',
    'current_env' => [],
    'recommended_env' => [],
    'conflicts' => [],
    'fix_actions' => [],
    'validation_results' => []
];

// 1. 检查当前环境变量
$current_env_vars = [
    'APP_ENV' => $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? 'NOT_SET',
    'APP_DEBUG' => $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? 'NOT_SET',
    'APP_SECRET' => $_ENV['APP_SECRET'] ?? getenv('APP_SECRET') ?? 'NOT_SET',
    'CORS_ALLOW_ORIGIN' => $_ENV['CORS_ALLOW_ORIGIN'] ?? getenv('CORS_ALLOW_ORIGIN') ?? 'NOT_SET',
    'DATABASE_URL' => $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?? 'NOT_SET',
    'JWT_SECRET_KEY' => $_ENV['JWT_SECRET_KEY'] ?? getenv('JWT_SECRET_KEY') ?? 'NOT_SET'
];

$validator['current_env'] = $current_env_vars;

// 2. 读取.env文件内容
$env_file_path = '../.env';
$env_file_exists = file_exists($env_file_path);
$env_file_content = $env_file_exists ? file_get_contents($env_file_path) : '';

// 解析.env文件
$parsed_env = [];
if ($env_file_exists) {
    $lines = explode("\n", $env_file_content);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0 || empty(trim($line))) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $parsed_env[trim($key)] = trim($value);
        }
    }
}

// 3. 检测冲突
$conflicts = [];
foreach ($current_env_vars as $key => $current_value) {
    $file_value = $parsed_env[$key] ?? 'NOT_SET';
    $nginx_value = '';

    // 模拟Nginx传递的值
    if ($key === 'APP_ENV') {
        $nginx_value = 'prod';
    } elseif ($key === 'APP_DEBUG') {
        $nginx_value = '0';
    }

    if ($current_value !== $file_value || $file_value !== $nginx_value) {
        $conflicts[] = [
            'variable' => $key,
            'current_runtime' => $current_value,
            'env_file' => $file_value,
            'nginx_forced' => $nginx_value,
            'conflict_detected' => true,
            'severity' => in_array($key, ['APP_ENV', 'APP_DEBUG']) ? 'CRITICAL' : 'HIGH'
        ];
    }
}

$validator['conflicts'] = $conflicts;

// 4. 推荐的生产环境配置
$recommended_env = [
    'APP_ENV' => 'prod',
    'APP_DEBUG' => 'false',
    'APP_SECRET' => $parsed_env['APP_SECRET'] ?: 'GENERATE_NEW_SECRET',
    'CORS_ALLOW_ORIGIN' => '*',
    'DATABASE_URL' => $parsed_env['DATABASE_URL'] ?: 'CONFIGURE_DATABASE_URL',
    'JWT_SECRET_KEY' => $parsed_env['JWT_SECRET_KEY'] ?: '%kernel.project_dir%/config/jwt/private.pem',
    'JWT_PUBLIC_KEY' => $parsed_env['JWT_PUBLIC_KEY'] ?: '%kernel.project_dir%/config/jwt/public.pem',
    'JWT_PASSPHRASE' => $parsed_env['JWT_PASSPHRASE'] ?: 'GENERATE_NEW_PASSPHRASE'
];

$validator['recommended_env'] = $recommended_env;

// 5. 生成修复后的.env文件内容
$fixed_env_content = "# 生产环境配置文件\n";
$fixed_env_content .= "# 修复时间: " . date('Y-m-d H:i:s') . "\n";
$fixed_env_content .= "# 解决环境变量冲突问题\n\n";

$fixed_env_content .= "###> symfony/framework-bundle ###\n";
foreach ($recommended_env as $key => $value) {
    if (in_array($key, ['APP_ENV', 'APP_DEBUG', 'APP_SECRET'])) {
        $fixed_env_content .= "{$key}={$value}\n";
    }
}
$fixed_env_content .= "###< symfony/framework-bundle ###\n\n";

$fixed_env_content .= "CORS_ALLOW_ORIGIN={$recommended_env['CORS_ALLOW_ORIGIN']}\n\n";

$fixed_env_content .= "###> symfony/routing ###\n";
$fixed_env_content .= "DEFAULT_URI=https://newsapi.arab-bee.com\n";
$fixed_env_content .= "###< symfony/routing ###\n\n";

$fixed_env_content .= "###> doctrine/doctrine-bundle ###\n";
$fixed_env_content .= "DATABASE_URL={$recommended_env['DATABASE_URL']}\n";
$fixed_env_content .= "###< doctrine/doctrine-bundle ###\n\n";

$fixed_env_content .= "###> lexik/jwt-authentication-bundle ###\n";
$fixed_env_content .= "JWT_SECRET_KEY={$recommended_env['JWT_SECRET_KEY']}\n";
$fixed_env_content .= "JWT_PUBLIC_KEY={$recommended_env['JWT_PUBLIC_KEY']}\n";
$fixed_env_content .= "JWT_PASSPHRASE={$recommended_env['JWT_PASSPHRASE']}\n";
$fixed_env_content .= "###< lexik/jwt-authentication-bundle ###\n";

$validator['fix_actions'][] = [
    'action' => 'backup_current_env',
    'command' => 'cp .env .env.backup.' . date('YmdHis'),
    'description' => '备份当前.env文件',
    'safety' => 'SAFE'
];

$validator['fix_actions'][] = [
    'action' => 'update_env_file',
    'content' => $fixed_env_content,
    'description' => '更新.env文件为生产环境配置',
    'safety' => 'SAFE - 需要重启服务'
];

$validator['fix_actions'][] = [
    'action' => 'clear_symfony_cache',
    'command' => 'php bin/console cache:clear --env=prod --no-warmup',
    'description' => '清除Symfony生产环境缓存',
    'safety' => 'SAFE - 生产环境必要操作'
];

$validator['fix_actions'][] = [
    'action' => 'warmup_symfony_cache',
    'command' => 'php bin/console cache:warmup --env=prod',
    'description' => '预热Symfony生产环境缓存',
    'safety' => 'SAFE - 性能优化'
];

// 6. 验证步骤
$validation_steps = [
    [
        'step' => 1,
        'name' => '检查PHP版本兼容性',
        'check' => 'php -v',
        'expected' => 'PHP 8.1+',
        'critical' => true
    ],
    [
        'step' => 2,
        'name' => '验证Composer依赖',
        'check' => 'composer install --no-dev --optimize-autoloader',
        'expected' => '无错误完成',
        'critical' => true
    ],
    [
        'step' => 3,
        'name' => '检查Symfony环境',
        'check' => 'php bin/console env:check',
        'expected' => '所有检查通过',
        'critical' => true
    ],
    [
        'step' => 4,
        'name' => '验证路由配置',
        'check' => 'php bin/console debug:router',
        'expected' => '路由列表正常显示',
        'critical' => true
    ],
    [
        'step' => 5,
        'name' => '测试数据库连接',
        'check' => 'php bin/console doctrine:database:check',
        'expected' => '数据库连接正常',
        'critical' => true
    ]
];

$validator['validation_results'] = $validation_steps;

// 7. 风险评估和回滚计划
$validator['risk_assessment'] = [
    'current_risk_level' => 'CRITICAL',
    'after_fix_risk_level' => 'LOW',
    'downtime_expected' => '5-10分钟',
    'rollback_possible' => true,
    'rollback_steps' => [
        '恢复备份的.env文件',
        '重启Nginx服务',
        '清除Symfony缓存'
    ]
];

// 8. 监控建议
$validator['monitoring_recommendations'] = [
    'immediate_checks' => [
        'HTTP状态码监控',
        '响应时间监控',
        '错误日志监控'
    ],
    'ongoing_monitoring' => [
        'API端点可用性',
        'CORS头验证',
        '数据库连接池状态',
        '内存使用情况'
    ]
];

echo json_encode($validator, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
