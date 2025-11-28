#!/bin/bash

# 生产环境部署脚本
# 修复 "symfony-cmd: command not found" 错误

set -e  # 遇到错误立即退出

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
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

# 检查是否为 root 用户
check_root_user() {
    if [ "$EUID" -eq 0 ]; then
        log_warn "检测到 root 用户，正在设置环境变量..."
        export COMPOSER_ALLOW_SUPERUSER=1
        export APP_ENV=prod
        export APP_DEBUG=0
    fi
}

# 检查 PHP 环境
check_php_environment() {
    log_info "检查 PHP 环境..."

    if ! command -v php &> /dev/null; then
        log_error "PHP 未安装或不在 PATH 中"
        exit 1
    fi

    php_version=$(php -v | head -n 1)
    log_info "PHP 版本: $php_version"

    # 检查必要的 PHP 扩展
    required_extensions=("ctype" "iconv" "pdo" "pdo_mysql" "json" "tokenizer")
    for ext in "${required_extensions[@]}"; do
        if ! php -m | grep -q "^$ext$"; then
            log_error "缺少必需的 PHP 扩展: $ext"
            exit 1
        fi
    done

    log_info "PHP 环境检查通过"
}

# 检查 Composer
check_composer() {
    log_info "检查 Composer..."

    if ! command -v composer &> /dev/null; then
        log_error "Composer 未安装或不在 PATH 中"
        exit 1
    fi

    composer_version=$(composer --version)
    log_info "Composer 版本: $composer_version"
}

# 检查 bin/console 文件
check_console_file() {
    log_info "检查 bin/console 文件..."

    if [ ! -f "bin/console" ]; then
        log_error "bin/console 文件不存在"
        exit 1
    fi

    if [ ! -x "bin/console" ]; then
        log_warn "bin/console 文件没有执行权限，正在添加..."
        chmod +x bin/console
    fi

    # 测试 console 是否可用
    if ! php bin/console --version &> /dev/null; then
        log_error "bin/console 无法正常执行"
        exit 1
    fi

    log_info "bin/console 检查通过"
}

# 备份当前环境
backup_environment() {
    log_info "备份当前环境..."

    if [ -d "vendor" ]; then
        backup_dir="vendor_backup_$(date +%Y%m%d_%H%M%S)"
        mv vendor "$backup_dir"
        log_info "已备份 vendor 目录到: $backup_dir"
    fi

    if [ -d "var/cache" ]; then
        backup_cache_dir="var_cache_backup_$(date +%Y%m%d_%H%M%S)"
        cp -r var/cache "$backup_cache_dir"
        log_info "已备份缓存目录到: $backup_cache_dir"
    fi
}

# 生产环境安装
production_install() {
    log_info "开始生产环境安装..."

    # 设置生产环境变量
    export APP_ENV=prod
    export APP_DEBUG=0

    # 执行生产环境安装
    log_info "执行 composer install (生产环境模式)..."
    composer install --no-dev --optimize-autoloader --no-progress --no-interaction

    log_info "生产环境依赖安装完成"
}

# 清理和缓存
setup_cache() {
    log_info "设置生产环境缓存..."

    export APP_ENV=prod
    export APP_DEBUG=0

    # 清理旧缓存
    if [ -d "var/cache" ]; then
        rm -rf var/cache/*
    fi

    # 清理缓存
    php bin/console cache:clear --no-warmup --env=prod

    # 预热缓存
    php bin/console cache:warmup --env=prod

    log_info "生产环境缓存设置完成"
}

# 设置文件权限
set_permissions() {
    log_info "设置文件权限..."

    # 设置 var 目录权限
    if [ -d "var" ]; then
        chmod -R 755 var
        if [ "$EUID" -eq 0 ]; then
            chown -R www-data:www-data var 2>/dev/null || true
        fi
    fi

    # 设置 storage 目录权限（如果存在）
    if [ -d "public/storage" ]; then
        chmod -R 755 public/storage
        if [ "$EUID" -eq 0 ]; then
            chown -R www-data:www-data public/storage 2>/dev/null || true
        fi
    fi

    log_info "文件权限设置完成"
}

# 验证部署
verify_deployment() {
    log_info "验证部署..."

    export APP_ENV=prod
    export APP_DEBUG=0

    # 检查 Symfony 是否正常工作
    if php bin/console about --env=prod &> /dev/null; then
        log_info "Symfony 应用状态正常"
    else
        log_error "Symfony 应用状态异常"
        exit 1
    fi

    # 检查路由
    if php bin/console router:debug --env=prod &> /dev/null; then
        log_info "路由配置正常"
    else
        log_warn "路由配置可能有问题"
    fi

    log_info "部署验证完成"
}

# 主函数
main() {
    log_info "开始生产环境部署..."
    log_info "当前目录: $(pwd)"
    log_info "当前用户: $(whoami)"

    # 执行所有步骤
    check_root_user
    check_php_environment
    check_composer
    check_console_file
    backup_environment
    production_install
    setup_cache
    set_permissions
    verify_deployment

    log_info "生产环境部署完成！"
    log_info "如需启动服务，请使用: php bin/console server:run --env=prod"
}

# 错误处理
trap 'log_error "部署过程中发生错误，请检查日志"; exit 1' ERR

# 执行主函数
main "$@"
