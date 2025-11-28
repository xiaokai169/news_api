#!/bin/bash

# PHP 扩展自动安装脚本
# 支持多种操作系统和包管理器，包括宝塔面板环境

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

# 检测操作系统
detect_os() {
    if [[ "$OSTYPE" == "linux-gnu"* ]]; then
        if [ -f /etc/debian_version ]; then
            echo "debian"
        elif [ -f /etc/redhat-release ]; then
            echo "redhat"
        elif [ -f /etc/alpine-release ]; then
            echo "alpine"
        else
            echo "linux"
        fi
    elif [[ "$OSTYPE" == "darwin"* ]]; then
        echo "macos"
    else
        echo "unknown"
    fi
}

# 检测包管理器
detect_package_manager() {
    local os=$(detect_os)
    case $os in
        "debian")
            if command -v apt-get &> /dev/null; then
                echo "apt"
            elif command -v apt &> /dev/null; then
                echo "apt"
            else
                echo "unknown"
            fi
            ;;
        "redhat")
            if command -v yum &> /dev/null; then
                echo "yum"
            elif command -v dnf &> /dev/null; then
                echo "dnf"
            else
                echo "unknown"
            fi
            ;;
        "alpine")
            if command -v apk &> /dev/null; then
                echo "apk"
            else
                echo "unknown"
            fi
            ;;
        "macos")
            if command -v brew &> /dev/null; then
                echo "brew"
            else
                echo "unknown"
            fi
            ;;
        *)
            echo "unknown"
            ;;
    esac
}

# 检测宝塔面板
detect_baota() {
    if [ -d "/www/server/panel" ] || [ -f "/www/server/panel/class/panelPlugin.py" ]; then
        echo "true"
    else
        echo "false"
    fi
}

# 获取 PHP 版本
get_php_version() {
    if command -v php &> /dev/null; then
        php -r "echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;"
    else
        echo ""
    fi
}

# 检测宝塔面板中的 PHP 版本
get_baota_php_versions() {
    local php_versions=()
    if [ -d "/www/server/php" ]; then
        for dir in /www/server/php/*/; do
            if [ -d "$dir" ]; then
                version=$(basename "$dir")
                if [[ "$version" =~ ^[0-9]+\.[0-9]+$ ]]; then
                    php_versions+=("$version")
                fi
            fi
        done
    fi
    echo "${php_versions[@]}"
}

# 宝塔面板安装扩展
install_extension_baota() {
    local extension=$1
    local php_version=$2
    local is_baota=$(detect_baota)

    if [ "$is_baota" == "true" ]; then
        log_info "检测到宝塔面板环境，使用宝塔方式安装扩展..."

        # 检查宝塔 PHP 安装路径
        local php_path="/www/server/php/$php_version"
        if [ ! -d "$php_path" ]; then
            log_error "宝塔面板中未找到 PHP $php_version 安装路径: $php_path"
            return 1
        fi

        # 检查扩展是否已安装
        if [ -f "$php_path/lib/php/extensions/no-debug-non-zts-*/${extension}.so" ] || $php_path/bin/php -m | grep -q "^$extension$"; then
            log_info "扩展 $extension 已安装"
            return 0
        fi

        log_info "尝试通过宝塔面板安装 $extension 扩展..."

        # 方法1: 使用宝塔面板命令行工具
        if command -v /www/server/panel/install_soft.sh &> /dev/null; then
            log_info "使用宝塔安装脚本..."
            /www/server/panel/install_soft.sh install php${php_version}_${extension} 2>/dev/null || log_warn "宝塔安装脚本失败"
        fi

        # 方法2: 直接编译安装
        if ! $php_path/bin/php -m | grep -q "^$extension$"; then
            log_info "尝试编译安装 $extension 扩展..."
            compile_php_extension "$extension" "$php_version" "$php_path"
        fi

        # 验证安装
        if $php_path/bin/php -m | grep -q "^$extension$"; then
            log_info "✓ 扩展 $extension 安装成功"
            return 0
        else
            log_error "✗ 扩展 $extension 安装失败"
            return 1
        fi
    else
        log_error "未检测到宝塔面板环境"
        return 1
    fi
}

# 编译安装 PHP 扩展
compile_php_extension() {
    local extension=$1
    local php_version=$2
    local php_path=$3

    log_info "编译安装 PHP 扩展: $extension"

    # 检查 phpize
    if [ ! -f "$php_path/bin/phpize" ]; then
        log_error "phpize 不存在，需要安装 PHP 开发包"
        return 1
    fi

    # 创建临时目录
    local temp_dir=$(mktemp -d)
    cd "$temp_dir"

    # 下载扩展源码
    case $extension in
        "pdo")
            log_info "PDO 扩展通常随 PHP 一起安装，检查配置..."
            # PDO 是内置扩展，只需要在配置中启用
            if [ -f "$php_path/etc/php.ini" ]; then
                if ! grep -q "^extension=pdo.so" "$php_path/etc/php.ini"; then
                    echo "extension=pdo.so" >> "$php_path/etc/php.ini"
                fi
            fi
            ;;
        "pdo_mysql")
            log_info "下载 PDO_MYSQL 源码..."
            wget -q "https://pecl.php.net/get/PDO_MYSQL" -O PDO_MYSQL.tgz || {
                log_error "下载 PDO_MYSQL 源码失败"
                cd - && rm -rf "$temp_dir"
                return 1
            }
            tar -xzf PDO_MYSQL.tgz
            cd PDO_MYSQL-*/
            ;;
        *)
            log_error "不支持的扩展编译: $extension"
            cd - && rm -rf "$temp_dir"
            return 1
            ;;
    esac

    # 编译安装
    if [ "$extension" != "pdo" ]; then
        log_info "开始编译 $extension..."
        "$php_path/bin/phpize"
        ./configure --with-php-config="$php_path/bin/php-config" --with-pdo-mysql=mysqlnd
        make -j$(nproc)
        make install

        # 添加到 php.ini
        local extension_dir=$("$php_path/bin/php-config" --extension-dir)
        if [ -f "$php_path/etc/php.ini" ]; then
            if ! grep -q "^extension=${extension}.so" "$php_path/etc/php.ini"; then
                echo "extension=${extension}.so" >> "$php_path/etc/php.ini"
            fi
        fi
    fi

    cd - && rm -rf "$temp_dir"
}

# APT 安装扩展
install_extension_apt() {
    local extension=$1
    local php_version=$2

    log_info "使用 APT 安装 $extension 扩展..."

    # 更新包列表
    apt-get update -qq

    # 映射扩展名到包名
    case $extension in
        "pdo")
            package="php${php_version}-pdo"
            ;;
        "pdo_mysql")
            package="php${php_version}-mysql"
            ;;
        "mbstring")
            package="php${php_version}-mbstring"
            ;;
        "curl")
            package="php${php_version}-curl"
            ;;
        "xml")
            package="php${php_version}-xml"
            ;;
        "tokenizer")
            package="php${php_version}-tokenizer"
            ;;
        "ctype")
            package="php${php_version}-ctype"
            ;;
        "iconv")
            package="php${php_version}-iconv"
            ;;
        "json")
            package="php${php_version}-json"
            ;;
        *)
            package="php${php_version}-${extension}"
            ;;
    esac

    log_info "安装包: $package"
    if apt-get install -y "$package"; then
        log_info "✓ $extension 安装成功"
        return 0
    else
        log_error "✗ $extension 安装失败"
        return 1
    fi
}

# YUM/DNF 安装扩展
install_extension_yum() {
    local extension=$1
    local php_version=$2

    log_info "使用 YUM/DNF 安装 $extension 扩展..."

    # 检测使用 yum 还是 dnf
    local pkg_manager="yum"
    if command -v dnf &> /dev/null; then
        pkg_manager="dnf"
    fi

    # 映射扩展名到包名
    case $extension in
        "pdo")
            package="php${php_version//./}-pdo"
            ;;
        "pdo_mysql")
            package="php${php_version//./}-mysqlnd"
            ;;
        "mbstring")
            package="php${php_version//./}-mbstring"
            ;;
        "curl")
            package="php${php_version//./}-curl"
            ;;
        "xml")
            package="php${php_version//./}-xml"
            ;;
        "tokenizer")
            package="php${php_version//./}-tokenizer"
            ;;
        "ctype")
            package="php${php_version//./}-ctype"
            ;;
        "iconv")
            package="php${php_version//./}-iconv"
            ;;
        "json")
            package="php${php_version//./}-json"
            ;;
        *)
            package="php${php_version//./}-${extension}"
            ;;
    esac

    log_info "安装包: $package"
    if $pkg_manager install -y "$package"; then
        log_info "✓ $extension 安装成功"
        return 0
    else
        log_error "✗ $extension 安装失败"
        return 1
    fi
}

# 验证扩展安装
verify_extension() {
    local extension=$1

    if php -m | grep -q "^$extension$"; then
        log_info "✓ 扩展 $extension 验证成功"
        return 0
    else
        log_error "✗ 扩展 $extension 验证失败"
        return 1
    fi
}

# 安装单个扩展
install_extension() {
    local extension=$1
    local php_version=$2
    local os=$(detect_os)
    local pkg_manager=$(detect_package_manager)
    local is_baota=$(detect_baota)

    log_info "开始安装扩展: $extension (PHP $php_version)"

    # 检查扩展是否已安装
    if verify_extension "$extension"; then
        log_info "扩展 $extension 已安装，跳过"
        return 0
    fi

    # 根据环境选择安装方法
    if [ "$is_baota" == "true" ]; then
        install_extension_baota "$extension" "$php_version"
    elif [ "$pkg_manager" == "apt" ]; then
        install_extension_apt "$extension" "$php_version"
    elif [ "$pkg_manager" == "yum" ] || [ "$pkg_manager" == "dnf" ]; then
        install_extension_yum "$extension" "$php_version"
    else
        log_error "不支持的操作系统或包管理器"
        return 1
    fi

    # 验证安装
    if verify_extension "$extension"; then
        return 0
    else
        return 1
    fi
}

# 显示帮助信息
show_help() {
    echo "PHP 扩展自动安装脚本"
    echo ""
    echo "用法: $0 [选项] [扩展名...]"
    echo ""
    echo "选项:"
    echo "  -h, --help              显示此帮助信息"
    echo "  -v, --version           显示 PHP 版本"
    echo "  -l, --list              列出已安装的扩展"
    echo "  -c, --check             检查必需的扩展"
    echo "  -a, --auto              自动安装所有必需扩展"
    echo "  -p, --php-version VER   指定 PHP 版本 (用于宝塔面板)"
    echo ""
    echo "示例:"
    echo "  $0 pdo pdo_mysql          # 安装 pdo 和 pdo_mysql 扩展"
    echo "  $0 --check                # 检查必需扩展"
    echo "  $0 --auto                 # 自动安装所有必需扩展"
    echo "  $0 --php-version 8.2 pdo  # 为 PHP 8.2 安装 pdo 扩展"
}

# 列出已安装的扩展
list_extensions() {
    log_info "已安装的 PHP 扩展:"
    php -m | sort | while read ext; do
        echo "  ✓ $ext"
    done
}

# 检查必需扩展
check_required_extensions() {
    local required_extensions=("ctype" "iconv" "pdo" "pdo_mysql" "json" "tokenizer" "mbstring" "curl" "xml")
    local missing_extensions=()

    log_info "检查必需的 PHP 扩展..."

    for ext in "${required_extensions[@]}"; do
        if php -m | grep -q "^$ext$"; then
            log_info "  ✓ $ext"
        else
            log_warn "  ✗ $ext (缺失)"
            missing_extensions+=("$ext")
        fi
    done

    if [ ${#missing_extensions[@]} -gt 0 ]; then
        log_error "缺失的扩展: ${missing_extensions[*]}"
        return 1
    else
        log_info "所有必需扩展都已安装"
        return 0
    fi
}

# 自动安装所有必需扩展
auto_install_extensions() {
    local required_extensions=("ctype" "iconv" "pdo" "pdo_mysql" "json" "tokenizer" "mbstring" "curl" "xml")
    local php_version=$(get_php_version)
    local failed_extensions=()

    log_info "自动安装所有必需扩展..."

    if [ -z "$php_version" ]; then
        log_error "无法检测 PHP 版本"
        return 1
    fi

    log_info "检测到 PHP 版本: $php_version"

    for ext in "${required_extensions[@]}"; do
        if ! install_extension "$ext" "$php_version"; then
            failed_extensions+=("$ext")
        fi
        echo
    done

    if [ ${#failed_extensions[@]} -gt 0 ]; then
        log_error "安装失败的扩展: ${failed_extensions[*]}"
        return 1
    else
        log_info "所有必需扩展安装成功！"
        return 0
    fi
}

# 主函数
main() {
    log_info "PHP 扩展安装脚本启动..."
    log_info "操作系统: $(detect_os)"
    log_info "包管理器: $(detect_package_manager)"
    log_info "宝塔面板: $(detect_baota)"

    local php_version=$(get_php_version)
    if [ -n "$php_version" ]; then
        log_info "PHP 版本: $php_version"
    fi

    echo

    # 解析命令行参数
    case "${1:-}" in
        -h|--help)
            show_help
            exit 0
            ;;
        -v|--version)
            if [ -n "$php_version" ]; then
                echo "PHP 版本: $php_version"
            else
                log_error "无法检测 PHP 版本"
                exit 1
            fi
            exit 0
            ;;
        -l|--list)
            list_extensions
            exit 0
            ;;
        -c|--check)
            if check_required_extensions; then
                exit 0
            else
                exit 1
            fi
            ;;
        -a|--auto)
            if auto_install_extensions; then
                exit 0
            else
                exit 1
            fi
            ;;
        -p|--php-version)
            shift
            php_version="$1"
            shift
            if [ -z "$php_version" ]; then
                log_error "请指定 PHP 版本"
                exit 1
            fi
            ;;
        "")
            # 没有参数，显示帮助
            show_help
            exit 0
            ;;
        *)
            # 安装指定的扩展
            local failed_extensions=()

            if [ -z "$php_version" ]; then
                log_error "无法检测 PHP 版本，请使用 --php-version 指定"
                exit 1
            fi

            for ext in "$@"; do
                if ! install_extension "$ext" "$php_version"; then
                    failed_extensions+=("$ext")
                fi
                echo
            done

            if [ ${#failed_extensions[@]} -gt 0 ]; then
                log_error "安装失败的扩展: ${failed_extensions[*]}"
                exit 1
            else
                log_info "所有扩展安装成功！"
                exit 0
            fi
            ;;
    esac
}

# 检查是否有 root 权限
if [ "$EUID" -ne 0 ]; then
    log_warn "建议使用 root 权限运行此脚本以确保安装成功"
    log_warn "继续执行可能会遇到权限问题..."
    echo
fi

# 执行主函数
main "$@"
