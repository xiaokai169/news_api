<?php

use App\Kernel;

require_once dirname(__DIR__)."/vendor/autoload_runtime.php";

// 临时修复：手动加载 Doctrine\Persistence\Proxy 接口
if (!interface_exists('Doctrine\Persistence\Proxy')) {
    $proxyFile = dirname(__DIR__).'/vendor/doctrine/persistence/src/Proxy.php';
    if (file_exists($proxyFile)) {
        require_once $proxyFile;
    }
}

return function (array $context) {
    return new Kernel($context["APP_ENV"], (bool) $context["APP_DEBUG"]);
};
