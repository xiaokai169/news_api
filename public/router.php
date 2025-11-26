<?php
/**
 * PHP内置服务器路由器
 * 处理Symfony应用的路由重写
 */

// 如果请求的是真实存在的文件，直接返回
if (preg_match('/\.(?:png|jpg|jpeg|gif|css|js|ico)$/', $_SERVER['REQUEST_URI'])) {
    return false; // 直接返回静态文件
}

// 否则将所有请求重写到index.php
include __DIR__.'/index.php';
