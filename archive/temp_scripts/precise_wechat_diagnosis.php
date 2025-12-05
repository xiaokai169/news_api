
<?php
echo "=== 精确微信同步诊断 ===\n\n";

// 1. 获取真实出口IP
echo "1. 检查真实出口IP:\n";
$ips = [
    'https://api.ipify.org',
    'https://ipinfo.io/json',
    'https://httpbin.org/ip',
    'https
