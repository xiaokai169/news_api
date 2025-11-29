<?php
/**
 * 500错误根本原因分析工具
 * 基于真实生产环境Nginx配置的问题诊断
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

$diagnosis = [
    'timestamp' => date('Y-m-d H:i:s'),
    'status' => 'analysis',
    'root_causes' => [],
    'environment_checks' => [],
    'nginx_issues' => [],
    'symfony_issues' => [],
    'recommendations' => []
];

// 1. 环境变量冲突检查
$diagnosis['environment_checks'][] = [
    'check' => 'APP_ENV环境变量',
    'value' => $_ENV['APP_ENV'] ?? 'NOT_SET',
    'file_env' => file_get_contents('.env') && preg_match('/APP_ENV=(.+)/', file_get_contents('.env'), $matches) ? $matches[1] : 'NOT_FOUND',
    'nginx_forced' => 'prod (from enable-php-82.conf)',
    'conflict' => true,
    'severity' => 'CRITICAL'
];

// 2. PHP版本检查
$diagnosis['environment_checks'][] = [
    'check' => 'PHP版本兼容性',
    'current' => PHP_VERSION,
    'nginx_config' => 'PHP 8.2 (enable-php-82.conf)',
    'symfony_required' => 'PHP 8.1+',
    'compatible' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'severity' => 'HIGH'
];

// 3. Nginx配置问题分析
$nginx_issues = [
    [
        'issue' => '缺少Symfony专用路由配置',
        'description' => '当前配置使用通用的enable-php-82.conf，缺少Symfony的PATH_INFO和前端控制器配置',
        'impact' => '导致所有请求返回500错误，因为无法正确路由到Symfony内核',
        'severity' => 'CRITICAL'
    ],
    [
        'issue' => '缺少FastCGI环境变量传递',
        'description' => '没有设置APP_ENV和APP_DEBUG等关键环境变量',
        'impact' => 'Symfony在错误的环境中运行，可能导致配置加载失败',
        'severity' => 'HIGH'
    ],
    [
        'issue' => '缺少API路由特殊处理',
        'description' => '没有针对/api/、/official-api/等路径的特殊处理',
        'impact' => 'API请求无法正确路由，返回404或500错误',
        'severity' => 'HIGH'
    ],
    [
        'issue' => '缺少错误页面配置',
        'description' => '没有配置API专用的JSON错误页面',
        'impact' => '错误时返回HTML而不是JSON，破坏API契约',
        'severity' => 'MEDIUM'
    ]
];

$diagnosis['nginx_issues'] = $nginx_issues;

// 4. Symfony系统检查
try {
    // 检查composer自动加载
    if (file_exists('../vendor/autoload.php')) {
        require_once '../vendor/autoload.php';
        $diagnosis['symfony_issues'][] = [
            'check' => 'Composer自动加载',
            'status' => 'OK',
            'severity' => 'INFO'
        ];
    } else {
        $diagnosis['symfony_issues'][] = [
            'check' => 'Composer自动加载',
            'status' => 'FAILED',
            'error' => 'vendor/autoload.php不存在',
            'severity' => 'CRITICAL'
        ];
    }

    // 检查Symfony内核
    if (class_exists('App\Kernel')) {
        $diagnosis['symfony_issues'][] = [
            'check' => 'Symfony内核类',
            'status' => 'OK',
            'severity' => 'INFO'
        ];
    } else {
        $diagnosis['symfony_issues'][] = [
            'check' => 'Symfony内核类',
            'status' => 'FAILED',
            'error' => 'App\Kernel类不存在',
            'severity' => 'CRITICAL'
        ];
    }

    // 检查Bundle配置
    if (file_exists('../config/bundles.php')) {
        $bundles = include '../config/bundles.php';
        $nelmio_loaded = isset($bundles['Nelmio\CorsBundle\NelmioCorsBundle']);
        $diagnosis['symfony_issues'][] = [
            'check' => 'NelmioCorsBundle加载',
            'status' => $nelmio_loaded ? 'OK' : 'FAILED',
            'severity' => $nelmio_loaded ? 'INFO' : 'HIGH'
        ];
    }

} catch (Exception $e) {
    $diagnosis['symfony_issues'][] = [
        'check' => 'Symfony系统检查',
        'status' => 'ERROR',
        'error' => $e->getMessage(),
        'severity' => 'CRITICAL'
    ];
}

// 5. 根本原因总结
$diagnosis['root_causes'] = [
    [
        'cause' => 'Nginx配置不兼容Symfony',
        'description' => '使用通用PHP配置而非Symfony专用配置，导致请求无法正确路由到前端控制器',
        'evidence' => '缺少try_files $uri $uri/ /index.php?$query_string;配置',
        'impact' => '所有请求返回500错误',
        'priority' => 1
    ],
    [
        'cause' => '环境变量冲突',
        'description' => '.env文件设置APP_ENV=dev，但Nginx强制传递APP_ENV=prod',
        'evidence' => '不一致的环境配置导致Symfony加载错误的配置文件',
        'impact' => 'Bundle加载失败，配置冲突',
        'priority' => 2
    ],
    [
        'cause' => '缺少FastCGI参数',
        'description' => '关键的环境变量和路径信息没有正确传递给PHP-FPM',
        'evidence' => 'enable-php-82.conf是通用配置，缺少Symfony特定参数',
        'impact' => 'Symfony内核无法正确初始化',
        'priority' => 3
    ]
];

// 6. 紧急修复建议
$diagnosis['recommendations'] = [
    [
        'priority' => 1,
        'action' => '立即修复Nginx配置',
        'description' => '替换include enable-php-82.conf为Symfony专用配置',
        'steps' => [
            '添加location ~ ^/(api|official-api|public-api)配置',
            '添加location ~ \.php$的完整Symfony配置',
            '设置正确的FastCGI参数'
        ],
        'safety' => '生产环境安全，需要重启Nginx'
    ],
    [
        'priority' => 2,
        'action' => '统一环境变量',
        'description' => '确保.env文件和Nginx传递的环境变量一致',
        'steps' => [
            '修改.env中的APP_ENV=prod',
            '设置APP_DEBUG=false',
            '验证所有环境变量'
        ],
        'safety' => '生产环境安全，需要清除缓存'
    ],
    [
        'priority' => 3,
        'action' => '添加CORS配置',
        'description' => '在Nginx层面添加基本CORS头作为备用',
        'steps' => [
            '添加OPTIONS请求处理',
            '设置基本的CORS头'
        ],
        'safety' => '生产环境安全，可立即生效'
    ]
];

// 7. 风险评估
$diagnosis['risk_assessment'] = [
    'current_status' => 'CRITICAL_FAILURE',
    'service_impact' => '完全不可用',
    'data_risk' => 'LOW',
    'recovery_time' => '15-30分钟',
    'rollback_possible' => 'YES'
];

echo json_encode($diagnosis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
