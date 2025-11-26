<?php

echo "启动 Symfony 开发服务器...\n";
echo "服务器地址: http://localhost:8001\n";
echo "Swagger UI: http://localhost:8001/api/doc\n";
echo "按 Ctrl+C 停止服务器\n\n";

// 启动 Symfony 内置服务器
$command = 'php -S localhost:8001 -t public public/index.php';

// 在 Windows 上执行
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    pclose(popen('start /B ' . $command, 'r'));
} else {
    shell_exec($command . ' > /dev/null 2>&1 &');
}

echo "服务器已启动，请在浏览器中访问:\n";
echo "- http://localhost:8001/api/doc (Swagger UI)\n";
echo "- http://localhost:8001/api/doc.json (OpenAPI JSON)\n";
echo "- http://localhost:8001/api/test (测试API)\n";
echo "- http://localhost:8001/official-api/news (新闻API)\n";
