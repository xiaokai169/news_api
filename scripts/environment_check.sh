#!/bin/bash

# 环境检查和修复脚本
# 专门用于诊断和修复 "symfony-cmd: command not found" 相关问题

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 日志函数
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_debug() {
    echo -e "${BLUE}[DEBUG]${NC} $1"
}

# 检查 PHP 版本和配置
check_php() {
    log_info "=== PHP 环境检查 ==="

    if ! command -v php &> /dev/null; then
        log_error "PHP 未安装或不在 PATH 中"
        return 1
    fi

    php_version=$(php -v | head -n 1)
    log_info "PHP 版本: $php_version"

    # 检查 PHP 版本是否满足要求 (>= 8.2)
    php_major=$(php -r "echo PHP_MAJOR_VERSION;")
    php_minor=$(php -r "echo PHP_MINOR_VERSION;")

    if [ "$php_major" -lt 8 ] || ([ "$php_major" -eq 8 ] && [ "$php_minor" -lt 2 ]); then
        log_error "PHP 版本过低，需要 PHP >= 8.2，当前版本: $php_major.$php_minor"
        return 1
    fi

    # 检查 PHP 配置
    memory_limit=$(php -r "echo ini_get('memory_limit');")
    max_execution_time=$(php -r "echo ini_get('max_execution_time');")

    log_info "内存限制: $memory_limit"
    log_info "最大执行时间: $max_execution_time 秒"

    # 检查必要的扩展
    log_info "检查 PHP 扩展..."
    required_extensions=("ctype" "iconv" "pdo" "pdo_mysql" "json" "tokenizer" "mbstring" "curl" "xml")
    missing_extensions=()

    for ext in "${required_extensions[@]}"; do
        if php -m | grep -q "^$ext$"; then
            log_debug "✓ $ext"
        else
            log_warn "✗ $ext (缺失)"
            missing_extensions+=("$ext")
        fi
    done

    if [ ${#missing_extensions[@]} -gt 0 ]; then
        log_error "缺失的 PHP 扩展: ${missing_extensions[*]}"
        log_info "安装命令示例:"
        log_info "  Ubuntu/Debian: sudo apt-get install php8.2-${missing_extensions[*]}"
        log_info "  CentOS/RHEL: sudo yum install php82-${missing_extensions[*]}"
        return 1
    fi

    return 0
}

# 检查 Composer
check_composer() {
    log_info "=== Composer 检查 ==="

    if ! command -v composer &> /dev/null; then
        log_error "Composer 未安装或不在 PATH 中"
        log_info "安装命令示例:"
        log_info "  curl -sS https://getcomposer.org/installer | php"
        log_info "  sudo mv composer.phar /usr/local/bin/composer"
        return 1
    fi

    composer_version=$(composer --version)
    log_info "Composer 版本: $composer_version"

    # 检查 Composer 配置
    composer_home=$(composer config --global home 2>/dev/null || echo "未设置")
    log_info "Composer 主目录: $composer_home"

    # 检查 process-timeout 设置
    process_timeout=$(composer config --global process-timeout 2>/dev/null || echo "300")
    log_info "Process timeout: $process_timeout 秒"

    return 0
}

# 检查项目文件结构
check_project_structure() {
    log_info "=== 项目结构检查 ==="

    # 检查必需文件
    required_files=("composer.json" "composer.lock" "bin/console" "src/Kernel.php")

    for file in "${required_files[@]}"; do
        if [ -f "$file" ]; then
            log_debug "✓ $file"
        else
            log_error "✗ $file (缺失)"
            return 1
        fi
    done

    # 检查目录权限
    log_info "检查目录权限..."

    if [ -d "bin" ]; then
        bin_perms=$(stat -c "%a" bin 2>/dev/null || stat -f "%A" bin 2>/dev/null)
        log_info "bin 目录权限: $bin_perms"
    fi

    if [ -f "bin/console" ]; then
        console_perms=$(stat -c "%a" bin/console 2>/dev/null || stat -f "%A" bin/console 2>/dev/null)
        log_info "bin/console 文件权限: $console_perms"

        if [ ! -x "bin/console" ]; then
            log_warn "bin/console 没有执行权限，正在修复..."
            chmod +x bin/console
            log_info "已添加执行权限"
        fi
    fi

    return 0
}

# 检查 Composer 配置
check_composer_config() {
    log_info "=== Composer 配置检查 ==="

    if [ ! -f "composer.json" ]; then
        log_error "composer.json 文件不存在"
        return 1
    fi

    # 检查 auto-scripts 配置
    log_info "检查 auto-scripts 配置..."

    if php -r "
        \$json = json_decode(file_get_contents('composer.json'), true);
        if (isset(\$json['scripts']['auto-scripts'])) {
            foreach (\$json['scripts']['auto-scripts'] as \$script => \$type) {
                if (\$type === 'symfony-cmd') {
                    echo \"WARNING: 发现 symfony-cmd 类型脚本: \$script\n\";
                }
            }
        }
    "; then
        log_warn "发现 symfony-cmd 类型脚本，这是导致问题的原因"
        log_info "建议使用 php-script 类型替代"
    fi

    return 0
}

# 测试 bin/console
test_console() {
    log_info "=== bin/console 测试 ==="

    if [ ! -f "bin/console" ]; then
        log_error "bin/console 文件不存在"
        return 1
    fi

    # 检查 shebang
    first_line=$(head -n 1 bin/console)
    log_info "Shebang: $first_line"

    # 测试基本命令
    log_info "测试 bin/console --version..."
    if php bin/console --version 2>/dev/null; then
        log_info "✓ bin/console 工作正常"
    else
        log_error "✗ bin/console 执行失败"

        # 尝试诊断问题
        log_info "诊断 bin/console 问题..."

        # 检查 vendor 目录
        if [ ! -d "vendor" ]; then
            log_warn "vendor 目录不存在，需要运行 composer install"
        fi

        # 检查 autoload_runtime.php
        if [ ! -f "vendor/autoload_runtime.php" ]; then
            log_warn "vendor/autoload_runtime.php 不存在，可能是依赖未正确安装"
        fi

        return 1
    fi

    # 测试 cache:clear 命令
    log_info "测试 cache:clear 命令..."
    APP_ENV=dev php bin/console cache:clear --no-warmup 2>/dev/null && log_info "✓ cache:clear 工作正常" || log_warn "cache:clear 可能有问题"

    return 0
}

# 检查环境变量
check_environment() {
    log_info "=== 环境变量检查 ==="

    # 检查 .env 文件
    if [ -f ".env" ]; then
        log_info "✓ .env 文件存在"
    else
        log_warn ".env 文件不存在，可能会使用默认配置"
    fi

    # 检查关键环境变量
    env_vars=("APP_ENV" "APP_DEBUG" "DATABASE_URL")

    for var in "${env_vars[@]}"; do
        if [ -f ".env" ]; then
            value=$(grep "^$var=" .env 2>/dev/null | cut -d'=' -f2- || echo "未设置")
            log_info "$var: ${value:0:50}..."
        fi
    done

    return 0
}

# 生成修复建议
generate_fix_suggestions() {
    log_info "=== 修复建议 ==="

    suggestions=()

    # 检查常见问题
    if [ ! -x "bin/console" ]; then
        suggestions+=("chmod +x bin/console")
    fi

    if [ ! -d "vendor" ]; then
        suggestions+=("composer install")
    fi

    if php -r "
        \$json = json_decode(file_get_contents('composer.json'), true);
        if (isset(\$json['scripts']['auto-scripts'])) {
            foreach (\$json['scripts']['auto-scripts'] as \$script => \$type) {
                if (\$type === 'symfony-cmd') {
                    exit(1);
                }
            }
        }
        exit(0);
    "; then
        suggestions+=("修改 composer.json 中的 symfony-cmd 为 php-script")
    fi

    if [ "$EUID" -eq 0 ]; then
        suggestions+=("export COMPOSER_ALLOW_SUPERUSER=1")
    fi

    if [ ${#suggestions[@]} -gt 0 ]; then
        log_info "建议执行的修复命令:"
        for suggestion in "${suggestions[@]}"; do
            log_info "  $suggestion"
        done
    else
        log_info "未发现需要修复的问题"
    fi
}

# 主函数
main() {
    log_info "开始环境检查..."
    log_info "当前目录: $(pwd)"
    log_info "当前用户: $(whoami)"
    log_info "操作系统: $(uname -a)"

    echo

    # 执行所有检查
    local exit_code=0

    check_php || exit_code=1
    echo
    check_composer || exit_code=1
    echo
    check_project_structure || exit_code=1
    echo
    check_composer_config || exit_code=1
    echo
    test_console || exit_code=1
    echo
    check_environment || exit_code=1
    echo
    generate_fix_suggestions

    echo
    if [ $exit_code -eq 0 ]; then
        log_info "环境检查完成，未发现严重问题"
    else
        log_error "环境检查完成，发现问题需要修复"
    fi

    return $exit_code
}

# 执行主函数
main "$@"
