@echo off
setlocal enabledelayedexpansion

REM Symfony应用修复脚本 - Windows版本
REM 用于解决WSL环境下的权限和配置问题

REM 项目路径
set "PROJECT_PATH=C:\Users\Administrator\Desktop\www\official_website_backend"

REM 颜色定义（Windows CMD限制）
set "INFO=[INFO]"
set "SUCCESS=[SUCCESS]"
set "WARNING=[WARNING]"
set "ERROR=[ERROR]"

REM 检查是否在正确的目录
:check_project_path
echo %INFO% 检查项目路径...

if not exist "%PROJECT_PATH%" (
    echo %ERROR% 项目路径不存在: %PROJECT_PATH%
    exit /b 1
)

if not exist "%PROJECT_PATH%\composer.json" (
    echo %ERROR% 不是有效的Symfony项目路径
    exit /b 1
)

echo %SUCCESS% 项目路径验证通过
goto :eof

REM 清除Symfony缓存
:clear_symfony_cache
echo %INFO% 清除Symfony缓存...

cd /d "%PROJECT_PATH%"

REM 检查composer是否可用
composer --version >nul 2>&1
if errorlevel 1 (
    echo %ERROR% Composer未安装或不在PATH中
    exit /b 1
)

REM 清除所有缓存
call :try_command "php bin/console cache:clear --env=dev" "清除开发环境缓存"
call :try_command "php bin/console cache:clear --env=prod" "清除生产环境缓存"

REM 手动删除缓存目录
if exist "var\cache" (
    rd /s /q "var\cache" >nul 2>&1
    echo %SUCCESS% 手动删除缓存目录完成
)

REM 重新生成缓存
call :try_command "php bin/console cache:warmup --env=prod" "重新生成生产环境缓存"

echo %SUCCESS% 缓存清除完成
goto :eof

REM 修复文件权限（Windows版本）
:fix_permissions
echo %INFO% 修复文件权限...

cd /d "%PROJECT_PATH%"

REM 设置目录权限（Windows使用icacls）
echo %INFO% 设置目录权限...
for /d %%d in (*) do (
    icacls "%%d" /grant Everyone:(OI)(CI)F >nul 2>&1
)

REM 设置文件权限
echo %INFO% 设置文件权限...
for %%f in (*) do (
    icacls "%%f" /grant Everyone:F >nul 2>&1
)

REM 特殊权限设置
if exist "var" (
    icacls "var" /grant Everyone:(OI)(CI)F /T >nul 2>&1
    echo %SUCCESS% var目录权限设置完成
)

if exist "public" (
    icacls "public" /grant Everyone:(OI)(CI)F /T >nul 2>&1
    echo %SUCCESS% public目录权限设置完成
)

REM 检查.env文件权限
if exist ".env" (
    icacls ".env" /grant Everyone:F >nul 2>&1
    echo %SUCCESS% .env文件权限设置完成
)

echo %SUCCESS% 权限修复完成
goto :eof

REM 验证环境配置
:validate_environment
echo %INFO% 验证环境配置...

cd /d "%PROJECT_PATH%"

REM 检查PHP版本
for /f "tokens=*" %%i in ('php -v ^| findstr "PHP"') do set "PHP_VERSION_LINE=%%i"
echo %INFO% %PHP_VERSION_LINE%

REM 检查必需的PHP扩展
set "REQUIRED_EXTENSIONS=pdo pdo_mysql curl json mbstring xml ctype iconv"

for %%e in (%REQUIRED_EXTENSIONS%) do (
    php -m | findstr "%%e" >nul 2>&1
    if errorlevel 1 (
        echo %ERROR% PHP扩展 %%e 未安装
    ) else (
        echo %SUCCESS% PHP扩展 %%e 已安装
    )
)

REM 检查Composer依赖
if exist "vendor\autoload.php" (
    echo %SUCCESS% Composer依赖已安装
) else (
    echo %WARNING% Composer依赖未安装，正在安装...
    composer install --no-dev --optimize-autoloader
)

REM 验证.env文件
if not exist ".env" (
    if exist ".env.example" (
        copy ".env.example" ".env" >nul 2>&1
        echo %WARNING% 已从.env.example复制.env文件，请手动配置
    ) else (
        echo %ERROR% .env文件不存在且没有.env.example模板
    )
)

echo %SUCCESS% 环境验证完成
goto :eof

REM 测试数据库连接
:test_database_connection
echo %INFO% 测试数据库连接...

cd /d "%PROJECT_PATH%"

REM 使用Symfony命令测试连接
php bin/console doctrine:database:create --if-not-exists >nul 2>&1
if errorlevel 1 (
    echo %ERROR% 数据库连接失败
    exit /b 1
) else (
    echo %SUCCESS% 数据库连接成功
)

REM 验证表是否存在
php bin/console doctrine:schema:validate >nul 2>&1
if errorlevel 1 (
    echo %WARNING% 数据库架构需要更新
    php bin/console doctrine:schema:update --force
) else (
    echo %SUCCESS% 数据库架构验证通过
)

echo %SUCCESS% 数据库连接测试完成
goto :eof

REM 验证路由配置
:validate_routes
echo %INFO% 验证路由配置...

cd /d "%PROJECT_PATH%"

REM 检查路由
php bin/console debug:router >nul 2>&1
if errorlevel 1 (
    echo %ERROR% 路由配置有问题
    exit /b 1
) else (
    echo %SUCCESS% 路由配置正常
)

REM 显示API相关路由
echo %INFO% API路由列表:
php bin/console debug:router | findstr /i "api" 2>nul || echo %WARNING% 未找到API路由

echo %SUCCESS% 路由验证完成
goto :eof

REM 测试应用启动
:test_application_startup
echo %INFO% 测试应用启动...

cd /d "%PROJECT_PATH%"

REM 测试Symfony命令
php bin/console about >nul 2>&1
if errorlevel 1 (
    echo %ERROR% Symfony应用启动失败
    exit /b 1
) else (
    echo %SUCCESS% Symfony应用启动正常
)

REM 测试基础URL访问（简单测试）
echo %INFO% 测试内置Web服务器...
start /b php -S localhost:8000 -t public/
timeout /t 3 /nobreak >nul

curl -s http://localhost:8000 >nul 2>&1
if errorlevel 1 (
    echo %ERROR% 内置Web服务器测试失败
) else (
    echo %SUCCESS% 内置Web服务器测试成功
)

REM 停止内置服务器
taskkill /f /im php.exe >nul 2>&1

echo %SUCCESS% 应用启动测试完成
goto :eof

REM 通用命令执行函数
:try_command
set "cmd=%~1"
set "desc=%~2"

echo %INFO% 执行: %desc%

%cmd% >nul 2>&1
if errorlevel 1 (
    echo %ERROR% %desc% - 失败
    exit /b 1
) else (
    echo %SUCCESS% %desc% - 成功
    exit /b 0
)
goto :eof

REM 完整修复流程
:full_fix
echo %INFO% 开始完整修复流程...

call :check_project_path
if errorlevel 1 exit /b 1

call :validate_environment
if errorlevel 1 exit /b 1

call :fix_permissions
if errorlevel 1 exit /b 1

call :clear_symfony_cache
if errorlevel 1 exit /b 1

call :test_database_connection
if errorlevel 1 exit /b 1

call :validate_routes
if errorlevel 1 exit /b 1

call :test_application_startup
if errorlevel 1 exit /b 1

echo %SUCCESS% 完整修复流程执行完成
goto :eof

REM 显示帮助信息
:show_help
echo Symfony应用修复脚本 - Windows版本
echo.
echo 用法: %~nx0 [选项]
echo.
echo 选项:
echo   full          执行完整修复流程
echo   cache         仅清除缓存
echo   perms         仅修复权限
echo   env           仅验证环境
echo   db            仅测试数据库
echo   routes        仅验证路由
echo   startup       仅测试应用启动
echo   help          显示此帮助信息
echo.
echo 示例:
echo   %~nx0 full       # 执行完整修复
echo   %~nx0 cache      # 仅清除缓存
goto :eof

REM 主程序
:main
set "ARG=%~1"

if "%ARG%"=="" set "ARG=full"

if "%ARG%"=="full" (
    call :full_fix
) else if "%ARG%"=="cache" (
    call :clear_symfony_cache
) else if "%ARG%"=="perms" (
    call :fix_permissions
) else if "%ARG%"=="env" (
    call :validate_environment
) else if "%ARG%"=="db" (
    call :test_database_connection
) else if "%ARG%"=="routes" (
    call :validate_routes
) else if "%ARG%"=="startup" (
    call :test_application_startup
) else if "%ARG%"=="help" (
    call :show_help
) else (
    echo %ERROR% 未知选项: %ARG%
    call :show_help
    exit /b 1
)

if errorlevel 1 (
    echo %ERROR% 脚本执行失败
    exit /b 1
)

goto :eof

REM 执行主程序
call :main %*
