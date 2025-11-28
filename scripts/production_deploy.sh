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

        # 检查是否为宝塔面板环境
        if [ -d "/www/server/panel" ]; then
            log_info "宝塔面板环境检测，设置相关变量..."
            # 宝塔面板可能需要的特殊设置
            export PATH="/www/server/php/$(php -r 'echo PHP_MAJOR_VERSION . \".\" . PHP_MINOR_VERSION;')/bin:$PATH"
        fi
    fi
}

# 预部署环境检查
pre_deploy_check() {
    log_info "执行预部署环境检查..."

    # 检查磁盘空间
    local available_space=$(df . | tail -1 | awk '{print $4}')
    if [ "$available_space" -lt 1048576 ]; then  # 1GB in KB
        log_warn "可用磁盘空间不足 1GB，当前可用: ${available_space}KB"
    fi

    # 检查内存使用情况
    if command -v free &> /dev/null; then
        local available_mem=$(free -m | awk 'NR==2{printf "%.0f", $7}')
        if [ "$available_mem" -lt 512 ]; then
            log_warn "可用内存不足 512MB，当前可用: ${available_mem}MB"
        fi
    fi

    # 检查网络连接
    if ping -c 1 google.com &> /dev/null || ping -c 1 baidu.com &> /dev/null; then
        log_info "网络连接正常"
    else
        log_warn "网络连接可能有问题，可能会影响依赖下载"
    fi

    log_info "预部署环境检查完成"
}

# 检查和安装 PHP 扩展
install_missing_extensions() {
    local required_extensions=("ctype" "iconv" "pdo" "pdo_mysql" "json" "tokenizer" "mbstring" "curl" "xml")
    local missing_extensions=()

    log_info "检查必需的 PHP 扩展..."

    for ext in "${required_extensions[@]}"; do
        if ! php -m | grep -q "^$ext$"; then
            missing_extensions+=("$ext")
        fi
    done

    if [ ${#missing_extensions[@]} -gt 0 ]; then
        log_error "发现缺失的 PHP 扩展: ${missing_extensions[*]}"

        # 检查是否有自动安装脚本
        local install_script="$(dirname "$0")/install_php_extensions.sh"
        if [ -f "$install_script" ]; then
            log_info "尝试自动安装缺失的扩展..."

            if bash "$install_script" --auto; then
                log_info "扩展自动安装成功，重新验证..."

                # 重新检查扩展
                local still_missing=()
                for ext in "${missing_extensions[@]}"; do
                    if ! php -m | grep -q "^$ext$"; then
                        still_missing+=("$ext")
                    fi
                done

                if [ ${#still_missing[@]} -gt 0 ]; then
                    log_error "自动安装后仍有缺失的扩展: ${still_missing[*]}"
                    log_info "请手动安装或运行: bash $install_script ${still_missing[*]}"
                    return 1
                else
                    log_info "所有缺失的扩展已成功安装！"
                    return 0
                fi
            else
                log_error "自动安装扩展失败"
                log_info "请手动安装扩展: ${missing_extensions[*]}"
                log_info "或运行: bash $install_script ${missing_extensions[*]}"
                return 1
            fi
        else
            log_error "未找到自动安装脚本"
            log_info "请手动安装缺失的扩展: ${missing_extensions[*]}"
            return 1
        fi
    else
        log_info "所有必需的 PHP 扩展都已安装"
        return 0
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

    # 检查 PHP 版本是否满足要求 (>= 8.2)
    php_major=$(php -r "echo PHP_MAJOR_VERSION;")
    php_minor=$(php -r "echo PHP_MINOR_VERSION;")

    if [ "$php_major" -lt 8 ] || ([ "$php_major" -eq 8 ] && [ "$php_minor" -lt 2 ]); then
        log_error "PHP 版本过低，需要 PHP >= 8.2，当前版本: $php_major.$php_minor"
        exit 1
    fi

    # 检查宝塔面板环境
    if [ -d "/www/server/panel" ]; then
        log_info "检测到宝塔面板环境"
        local php_path=$(which php 2>/dev/null)
        if [[ "$php_path" == /www/server/php/* ]]; then
            log_info "使用宝塔面板的 PHP: $php_path"
        else
            log_warn "当前 PHP 不是宝塔面板版本，可能存在扩展不匹配问题"
        fi
    fi

    # 检查和安装扩展
    if ! install_missing_extensions; then
        log_error "PHP 扩展检查失败，部署终止"
        exit 1
    fi

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

# 显示部署摘要
show_deployment_summary() {
    log_info "=== 部署摘要 ==="
    log_info "部署时间: $(date)"
    log_info "部署目录: $(pwd)"
    log_info "部署用户: $(whoami)"
    log_info "PHP 版本: $(php -v | head -n 1)"
    log_info "Composer 版本: $(composer --version 2>/dev/null | head -n 1)"

    if [ -d "/www/server/panel" ]; then
        log_info "环境类型: 宝塔面板"
    else
        log_info "环境类型: 标准服务器"
    fi

    # 显示项目信息
    if [ -f "composer.json" ]; then
        local project_name=$(php -r "echo json_decode(file_get_contents('composer.json'), true)['name'] ?? 'Unknown';" 2>/dev/null)
        local project_version=$(php -r "echo json_decode(file_get_contents('composer.json'), true)['version'] ?? 'Unknown';" 2>/dev/null)
        log_info "项目名称: $project_name"
        log_info "项目版本: $project_version"
    fi
}

# 主函数
main() {
    log_info "开始生产环境部署..."

    # 显示部署摘要
    show_deployment_summary
    echo

    # 执行所有步骤
    check_root_user
    pre_deploy_check
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

    # 显示后续操作建议
    echo
    log_info "=== 后续操作建议 ==="
    log_info "1. 检查应用状态: php bin/console about --env=prod"
    log_info "2. 运行数据库迁移: php bin/console doctrine:migrations:migrate --env=prod"
    log_info "3. 清理旧日志: find var/log -name '*.log' -mtime +7 -delete"
    log_info "4. 监控应用性能: php bin/console cache:pool:clear cache.app --env=prod"

    # 宝塔面板特殊建议
    if [ -d "/www/server/panel" ]; then
        log_info "5. 宝塔面板用户："
        log_info "   - 在宝塔面板中设置定时任务进行缓存清理"
        log_info "   - 配置 SSL 证书（如果需要）"
        log_info "   - 设置防火墙规则"
        log_info "   - 配置监控和告警"
    fi
}

# 错误处理
trap 'log_error "部署过程中发生错误，请检查日志";
      log_error "错误发生在第 $LINENO 行";
      log_error "命令: $BASH_COMMAND";
      exit 1' ERR

# 执行主函数
main "$@"
