<?php
/**
 * 清理Symfony缓存脚本
 */

header('Content-Type: application/json; charset=utf-8');

function clearSymfonyCache() {
    $cacheDir = __DIR__ . '/../var/cache';
    $result = [
        'timestamp' => date('Y-m-d H:i:s'),
        'actions' => []
    ];

    // 清理var/cache目录
    if (is_dir($cacheDir)) {
        $result['actions'][] = 'Found cache directory: ' . $cacheDir;

        // 尝试删除缓存文件
        $files = glob($cacheDir . '/*');
        $deleted = 0;
        $errors = 0;

        foreach ($files as $file) {
            if (is_dir($file)) {
                // 递归删除目录
                if (deleteDirectory($file)) {
                    $deleted++;
                } else {
                    $errors++;
                }
            } else {
                if (unlink($file)) {
                    $deleted++;
                } else {
                    $errors++;
                }
            }
        }

        $result['actions'][] = "Deleted {$deleted} cache items";
        if ($errors > 0) {
            $result['actions'][] = "Failed to delete {$errors} items";
        }
    } else {
        $result['actions'][] = 'Cache directory not found';
    }

    // 检查.env文件
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $result['actions'][] = '.env file exists';
        $envContent = file_get_contents($envFile);
        $result['env_vars'] = [
            'APP_ENV' => strpos($envContent, 'APP_ENV=') !== false ? 'defined' : 'not defined',
            'CORS_ALLOW_ORIGIN' => strpos($envContent, 'CORS_ALLOW_ORIGIN=') !== false ? 'defined' : 'not defined'
        ];
    } else {
        $result['actions'][] = '.env file not found';
    }

    $result['success'] = $errors === 0;
    $result['message'] = $result['success'] ? 'Cache cleared successfully' : 'Some errors occurred';

    return $result;
}

function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return true;
    }

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }

    return rmdir($dir);
}

// 执行缓存清理
$clearResult = clearSymfonyCache();

echo json_encode($clearResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
