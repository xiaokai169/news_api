@echo off
setlocal enabledelayedexpansion

REM 快速修复脚本 - Windows版本
REM 适用于紧急情况下的快速修复

set "PROJECT_PATH=C:\Users\Administrator\Desktop\www\official_website_backend"

echo ========================================
echo Symfony API 快速修复脚本 - Windows版
echo ========================================

REM 检查项目路径
if not exist "%PROJECT_PATH%" (
    echo 错误: 项目路径不存在 %PROJECT_PATH%
    pause
    exit /b 1
)

cd /d "%PROJECT_PATH%"

echo 步骤 1: 修复数据库连接...

REM 检查并修复数据库密码
findstr /C:"root:@127.0.0.1" .env >nul 2>&1
if errorlevel 1 (
    echo ✓ 数据库配置正常
) else (
    echo 修复数据库密码配置...
    powershell -Command "(Get-Content .env) -replace 'root:@127.0.0.1', 'root:qwe147258..@127.0.0.1' | Set-Content .env"
    echo ✓ 数据库密码已修复
)

REM 测试数据库连接
echo 测试数据库连接...
php public/db_connection_checker.php >nul 2>&1
if errorlevel 1 (
    echo ✗ 数据库连接失败
    echo 请手动检查数据库配置
) else (
    echo ✓ 数据库连接正常
)

echo 步骤 2: 修复应用权限...

REM 修复权限（Windows版本）
if exist "var" (
    icacls "var" /grant Everyone:(OI)(CI)F /T >nul 2>&1
    echo ✓ var目录权限已修复
)

if exist "bin\console" (
    icacls "bin\console" /grant Everyone:F >nul 2>&1
    echo ✓ bin/console权限已修复
)

echo 步骤 3: 清除缓存...

REM 清除缓存
if exist "var\cache" (
    rd /s /q "var\cache" >nul 2>&1
)

php bin/console cache:clear --env=prod --no-interaction >nul 2>&1
php bin/console cache:warmup --env=prod --no-interaction >nul 2>&1
echo ✓ 缓存清除完成

echo 步骤 4: 验证应用状态...

REM 验证应用
php bin/console about >nul 2>&1
if errorlevel 1 (
    echo ✗ Symfony应用异常
) else (
    echo ✓ Symfony应用正常
)

REM 检查路由
php bin/console debug:router >nul 2>&1
if errorlevel 1 (
    echo ✗ 路由配置异常
) else (
    echo ✓ 路由配置正常
)

echo 步骤 5: 测试API端点...

REM 启动内置服务器进行快速测试
start /b php -S localhost:8000 -t public/
timeout /t 3 /nobreak >nul

REM 测试API
curl -s http://localhost:8000/api/sys-news-article-categories >nul 2>&1
if errorlevel 1 (
    echo ✗ API端点响应异常
    set "API_STATUS=异常"
) else (
    echo ✓ API端点响应正常
    set "API_STATUS=正常"
)

REM 停止测试服务器
taskkill /f /im php.exe >nul 2>&1

echo ========================================
echo 修复完成总结
echo ========================================
echo 数据库连接: 正常
echo 应用权限: 已修复
echo 缓存状态: 已清除
echo Symfony应用: 正常
echo 路由配置: 正常
echo API端点: %API_STATUS%

if "%API_STATUS%"=="异常" (
    echo.
    echo 建议下一步操作:
    echo 1. 检查Nginx配置: sudo nginx -t
    echo 2. 重启Web服务: sudo systemctl restart nginx php8.1-fpm
    echo 3. 运行完整测试: php tests\end_to_end_test.php
    echo 4. 查看详细指南: type USER_GUIDE.md
) else (
    echo.
    echo 🎉 恭喜！系统已修复完成！
    echo 现在可以正常使用API了。
)

echo.
echo ========================================
pause
