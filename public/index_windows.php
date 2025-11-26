<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload.php';

// 为Windows环境创建临时缓存目录
$cacheDir = dirname(__DIR__).'/cache_temp';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}

// 设置环境变量来使用临时缓存目录
putenv('SYMFONY_CACHE_DIR='.$cacheDir);

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
