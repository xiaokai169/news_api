#!/bin/bash

# Symfony应用修复脚本集合
# 用于解决WSL环境下的权限和配置问题

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 项目路径
PROJECT_PATH="/mnt/c/Users/Administrator/Desktop/www/official_website_backend"

# 日志函数
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# 检查是否在正确的目录
check_project_path() {
    log_info "检查项目路径..."

    if [ ! -d "$PROJECT_PATH" ]; then
        log_error "项目路径不存在: $PROJECT_PATH"
        exit 1
    fi

    if [ ! -f "$PROJECT_PATH/composer.json" ]; then
        log_error "不是有效的Symfony项目路径"
        exit 1
    fi

    log_success "项目路径验证通过"
}

# 清除Symfony缓存
clear_symfony_cache() {
    log_info "清除Symfony缓存..."

    cd "$PROJECT_PATH"

    # 检查composer是否可用
    if ! command -v composer &> /dev/null; then
        log_error "Composer未安装或不在PATH中"
        return 1
    fi

    # 清除所有缓存
    try_command "php bin/console cache:clear --env=dev" "清除开发环境缓存"
    try_command "php bin/console cache:clear --env=prod" "清除生产环境缓存"

    # 手动删除缓存目录
    if [ -d "$PROJECT_PATH/var/cache" ]; then
        rm -rf "$PROJECT_PATH/var/cache"/*
        log_success "手动删除缓存目录完成"
    fi

    # 重新生成缓存
    try_command "php bin/console cache:warmup --env=prod" "重新生成生产环境缓存"

    log_success "缓存清除完成"
}

# 修复文件权限
fix_permissions() {
    log_info "修复文件权限..."

    cd "$PROJECT_PATH"

    # 设置目录权限
    find . -type d -exec chmod 755 {} \;
    log_success "目录权限设置为755"

    # 设置文件权限
    find . -type f -exec chmod 644 {} \;
    log_success "文件权限设置为644"

    # 特殊权限设置
    if [ -d "var" ]; then
        chmod -R 777 var/
        log_success "var目录权限设置为777"
    fi

    if [ -d "public" ]; then
        chmod -R 755 public/
        log_success "public目录权限设置为755"
    fi

    # 可执行文件
    if [ -f "bin/console" ]; then
        chmod +x bin/console
        log_success "bin/console设置为可执行"
    fi

    # 检查.env文件权限
    if [ -f ".env" ]; then
        chmod 600 .env
        log_success ".env文件权限设置为600"
    fi

    log_success "权限修复完成"
}

# 验证环境配置
validate_environment() {
    log_info "验证环境配置..."

    cd "$PROJECT_PATH"

    # 检查PHP版本
    PHP_VERSION=$(php -v | head -n1 | cut -d' ' -f2 | cut -d'.' -f1,2)
    log_info "PHP版本: $PHP_VERSION"

    # 检查必需的PHP扩展
    REQUIRED_EXTENSIONS=("pdo" "pdo_mysql" "curl" "json" "mbstring" "xml" "ctype" "iconv")

    for ext in "${REQUIRED_EXTENSIONS[@]}"; do
        if php -m | grep -q "$ext"; then
            log_success "PHP扩展 $ext 已安装"
        else
            log_error "PHP扩展 $ext 未安装"
        fi
    done

    # 检查Composer依赖
    if [ -f "vendor/autoload.php" ]; then
        log_success "Composer依赖已安装"
    else
        log_warning "Composer依赖未安装，正在安装..."
        composer install --no-dev --optimize-autoloader
    fi

    # 验证.env文件
    if [ ! -f ".env" ]; then
        if [ -f ".env.example" ]; then
            cp .env.example .env
            log_warning "已从.env.example复制.env文件，请手动配置"
        else
            log_error ".env文件不存在且没有.env.example模板"
        fi
    fi

    log_success "环境验证完成"
}

# 测试数据库连接
test_database_connection() {
    log_info "测试数据库连接..."

    cd "$PROJECT_PATH"

    # 使用Symfony命令测试连接
    if php bin/console doctrine:database:create --if-not-exists; then
        log_success "数据库连接成功"
    else
        log_error "数据库连接失败"
        return 1
    fi

    # 验证表是否存在
    if php bin/console doctrine:schema:validate; then
        log_success "数据库架构验证通过"
    else
        log_warning "数据库架构需要更新"
        php bin/console doctrine:schema:update --force
    fi

    log_success "数据库连接测试完成"
}

# 验证路由配置
validate_routes() {
    log_info "验证路由配置..."

    cd "$PROJECT_PATH"

    # 检查路由
    if php bin/console debug:router > /dev/null 2>&1; then
        log_success "路由配置正常"

        # 显示API相关路由
        log_info "API路由列表:"
        php bin/console debug:router | grep -E "(api|API)" || log_warning "未找到API路由"
    else
        log_error "路由配置有问题"
        return 1
    fi

    log_success "路由验证完成"
}

# 测试应用启动
test_application_startup() {
    log_info "测试应用启动..."

    cd "$PROJECT_PATH"

    # 测试Symfony命令
    if php bin/console about; then
        log_success "Symfony应用启动正常"
    else
        log_error "Symfony应用启动失败"
        return 1
    fi

    # 测试基础URL访问
    if php -S localhost:8000 -t public/ > /dev/null 2>&1 & then
        sleep 2
        if curl -s http://localhost:8000 > /dev/null; then
            log_success "内置Web服务器测试成功"
            pkill -f "php -S localhost:8000"
        else
            log_error "内置Web服务器测试失败"
            pkill -f "php -S localhost:8000"
        fi
    fi

    log_success "应用启动测试完成"
}

# 通用命令执行函数
try_command() {
    local cmd="$1"
    local desc="$2"

    log_info "执行: $desc"

    if eval "$cmd"; then
        log_success "$desc - 成功"
        return 0
    else
        log_error "$desc - 失败"
        return 1
    fi
}

# 完整修复流程
full_fix() {
    log_info "开始完整修复流程..."

    check_project_path
    validate_environment
    fix_permissions
    clear_symfony_cache
    test_database_connection
    validate_routes
    test_application_startup

    log_success "完整修复流程执行完成"
}

# 显示帮助信息
show_help() {
    echo "Symfony应用修复脚本"
    echo ""
    echo "用法: $0 [选项]"
    echo ""
    echo "选项:"
    echo "  full          执行完整修复流程"
    echo "  cache         仅清除缓存"
    echo "  perms         仅修复权限"
    echo "  env           仅验证环境"
    echo "  db            仅测试数据库"
    echo "  routes        仅验证路由"
    echo "  startup       仅测试应用启动"
    echo "  help          显示此帮助信息"
    echo ""
    echo "示例:"
    echo "  $0 full       # 执行完整修复"
    echo "  $0 cache      # 仅清除缓存"
}

# 主程序
main() {
    case "${1:-full}" in
        "full")
            full_fix
            ;;
        "cache")
            clear_symfony_cache
            ;;
        "perms")
            fix_permissions
            ;;
        "env")
            validate_environment
            ;;
        "db")
            test_database_connection
            ;;
        "routes")
            validate_routes
            ;;
        "startup")
            test_application_startup
            ;;
        "help"|"-h"|"--help")
            show_help
            ;;
        *)
            log_error "未知选项: $1"
            show_help
            exit 1
            ;;
    esac
}

# 执行主程序
main "$@"
