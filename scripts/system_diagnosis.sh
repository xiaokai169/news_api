#!/bin/bash

# 系统综合诊断脚本
# 作者: 运维团队
# 版本: v2.1.0
# 更新: 2025-11-27

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

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

# 检查是否为root用户
check_root() {
    if [[ $EUID -ne 0 ]]; then
        log_error "此脚本需要root权限运行"
        exit 1
    fi
}

# 系统信息检查
check_system_info() {
    log_info "=== 系统信息检查 ==="

    echo "操作系统: $(cat /etc/os-release | grep PRETTY_NAME | cut -d'"' -f2)"
    echo "内核版本: $(uname -r)"
    echo "系统架构: $(uname -m)"
    echo "运行时间: $(uptime -p)"
    echo "当前时间: $(date '+%Y-%m-%d %H:%M:%S %Z')"
    echo "系统负载: $(uptime | awk -F'load average:' '{print $2}')"
    echo ""
}

# 硬件资源检查
check_hardware() {
    log_info "=== 硬件资源检查 ==="

    # CPU信息
    echo "CPU信息:"
    echo "  型号: $(grep 'model name' /proc/cpuinfo | head -1 | cut -d':' -f2 | xargs)"
    echo "  核心数: $(nproc)"
    echo "  频率: $(lscpu | grep 'CPU MHz' | awk '{print $3}') MHz"

    # 内存信息
    echo "内存信息:"
    local total_mem=$(free -h | grep Mem | awk '{print $2}')
    local used_mem=$(free -h | grep Mem | awk '{print $3}')
    local mem_usage=$(free | grep Mem | awk '{printf "%.1f", $3/$2 * 100.0}')
    echo "  总内存: $total_mem"
    echo "  已使用: $used_mem ($mem_usage%)"

    if (( $(echo "$mem_usage > 80" | bc -l) )); then
        log_warning "内存使用率过高: $mem_usage%"
    else
        log_success "内存使用率正常: $mem_usage%"
    fi

    # 磁盘信息
    echo "磁盘信息:"
    df -h | grep -E '^/dev/' | while read line; do
        local mount_point=$(echo $line | awk '{print $6}')
        local usage=$(echo $line | awk '{print $5}' | tr -d '%')
        echo "  $mount_point: $(echo $line | awk '{print $3}')/$(echo $line | awk '{print $2}') ($usage%)"

        if [[ $usage -gt 80 ]]; then
            log_warning "磁盘使用率过高: $mount_point ($usage%)"
        fi
    done

    echo ""
}

# 网络连接检查
check_network() {
    log_info "=== 网络连接检查 ==="

    # 检查网络接口
    echo "网络接口:"
    ip addr show | grep -E '^[0-9]+:' | while read line; do
        local interface=$(echo $line | cut -d':' -f2 | xargs)
        local status=$(ip link show $interface | grep -o 'state [A-Z]*' | cut -d' ' -f2)
        echo "  $interface: $status"
    done

    # 检查端口监听
    echo "端口监听状态:"
    local ports=("22" "80" "443" "3306" "6379")
    for port in "${ports[@]}"; do
        if netstat -tuln | grep -q ":$port "; then
            echo "  端口 $port: 正在监听"
        else
            log_warning "端口 $port: 未监听"
        fi
    done

    # 检查外网连接
    if ping -c 1 8.8.8.8 &>/dev/null; then
        log_success "外网连接正常"
    else
        log_error "外网连接失败"
    fi

    echo ""
}

# 服务状态检查
check_services() {
    log_info "=== 服务状态检查 ==="

    local services=("nginx" "php8.2-fpm" "mysql" "redis-server")
    local failed_services=()

    for service in "${services[@]}"; do
        if systemctl is-active --quiet "$service"; then
            log_success "$service: 运行中"

            # 检查服务资源使用
            local memory_usage=$(ps aux | grep "$service" | grep -v grep | awk '{sum+=$6} END {print sum/1024}')
            echo "  内存使用: ${memory_usage}MB"
        else
            log_error "$service: 未运行"
            failed_services+=("$service")
        fi
    done

    if [[ ${#failed_services[@]} -gt 0 ]]; then
        log_error "异常服务: ${failed_services[*]}"
    fi

    echo ""
}

# 数据库连接检查
check_database() {
    log_info "=== 数据库连接检查 ==="

    # 检查MySQL连接
    if mysql -e "SELECT 1;" &>/dev/null; then
        log_success "MySQL连接正常"

        # 检查数据库状态
        local db_version=$(mysql --version | cut -d' ' -f3 | cut -d',' -f1)
        echo "MySQL版本: $db_version"

        # 检查连接数
        local connections=$(mysql -e "SHOW STATUS LIKE 'Threads_connected';" 2>/dev/null | tail -1 | awk '{print $2}')
        local max_connections=$(mysql -e "SHOW VARIABLES LIKE 'max_connections';" 2>/dev/null | tail -1 | awk '{print $2}')
        echo "当前连接数: $connections / $max_connections"

        if [[ $connections -gt $((max_connections * 80 / 100)) ]]; then
            log_warning "数据库连接数过高"
        fi

        # 检查慢查询
        local slow_queries=$(mysql -e "SHOW GLOBAL STATUS LIKE 'Slow_queries';" 2>/dev/null | tail -1 | awk '{print $2}')
        if [[ $slow_queries -gt 0 ]]; then
            log_warning "发现慢查询: $slow_queries 个"
        fi
    else
        log_error "MySQL连接失败"
    fi

    echo ""
}

# 应用健康检查
check_application() {
    log_info "=== 应用健康检查 ==="

    # 检查应用目录
    if [[ -d "/www/wwwroot/official_website_backend" ]]; then
        log_success "应用目录存在"

        cd /www/wwwroot/official_website_backend

        # 检查关键文件
        local critical_files=("public/index.php" "src/Kernel.php" "composer.json" ".env")
        for file in "${critical_files[@]}"; do
            if [[ -f "$file" ]]; then
                log_success "关键文件存在: $file"
            else
                log_error "关键文件缺失: $file"
            fi
        done

        # 检查Composer依赖
        if [[ -d "vendor" ]]; then
            local vendor_count=$(ls vendor | wc -l)
            if [[ $vendor_count -gt 10 ]]; then
                log_success "Composer依赖完整 ($vendor_count 个包)"
            else
                log_warning "Composer依赖可能不完整"
            fi
        else
            log_error "vendor目录不存在"
        fi

        # 检查缓存目录
        if [[ -d "var/cache" ]]; then
            local cache_size=$(du -sh var/cache 2>/dev/null | cut -f1)
            echo "缓存目录大小: $cache_size"
        fi

        # 检查日志目录
        if [[ -f "var/log/prod.log" ]]; then
            local log_size=$(stat -c%s "var/log/prod.log" 2>/dev/null)
            if [[ $log_size -gt 0 ]]; then
                log_success "应用日志正常"
            else
                log_warning "应用日志为空"
            fi
        fi
    else
        log_error "应用目录不存在"
    fi

    echo ""
}

# 安全检查
check_security() {
    log_info "=== 安全检查 ==="

    # 检查防火墙状态
    if command -v ufw &> /dev/null; then
        local ufw_status=$(ufw status | head -1)
        echo "防火墙状态: $ufw_status"
    fi

    # 检查SSH配置
    local ssh_root_login=$(grep "^PermitRootLogin" /etc/ssh/sshd_config | awk '{print $2}')
    if [[ "$ssh_root_login" == "no" ]]; then
        log_success "SSH root登录已禁用"
    else
        log_warning "SSH root登录未禁用"
    fi

    # 检查文件权限
    cd /www/wwwroot/official_website_backend 2>/dev/null || return

    local sensitive_files=(".env" "config/packages/security.yaml")
    for file in "${sensitive_files[@]}"; do
        if [[ -f "$file" ]]; then
            local file_perms=$(stat -c "%a" "$file")
            if [[ "$file_perms" == "600" ]] || [[ "$file_perms" == "640" ]]; then
                log_success "敏感文件权限正确: $file ($file_perms)"
            else
                log_warning "敏感文件权限异常: $file ($file_perms)"
            fi
        fi
    done

    # 检查最近登录
    echo "最近登录记录:"
    last -n 5 | head -5

    echo ""
}

# 性能检查
check_performance() {
    log_info "=== 性能检查 ==="

    # 检查系统负载
    local load_avg=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | tr -d ',')
    if (( $(echo "$load_avg < 2.0" | bc -l) )); then
        log_success "系统负载正常: $load_avg"
    else
        log_warning "系统负载较高: $load_avg"
    fi

    # 检查I/O等待
    local io_wait=$(iostat -c 1 2 | tail -2 | head -1 | awk '{print $4}')
    if (( $(echo "$io_wait < 10" | bc -l) )); then
        log_success "I/O等待正常: ${io_wait}%"
    else
        log_warning "I/O等待较高: ${io_wait}%"
    fi

    # 检查磁盘I/O
    echo "磁盘I/O统计:"
    iostat -d 1 1 | grep -E "Device|sd"

    echo ""
}

# 生成诊断报告
generate_report() {
    log_info "=== 生成诊断报告 ==="

    local report_file="/tmp/system_diagnosis_$(date +%Y%m%d_%H%M%S).txt"

    {
        echo "系统诊断报告"
        echo "============="
        echo "生成时间: $(date '+%Y-%m-%d %H:%M:%S')"
        echo ""
        echo "系统信息:"
        uname -a
        echo ""
        echo "内存使用:"
        free -h
        echo ""
        echo "磁盘使用:"
        df -h
        echo ""
        echo "网络连接:"
        netstat -tuln | head -10
        echo ""
        echo "服务状态:"
        systemctl list-units --type=service --state=running | head -10
    } > "$report_file"

    log_success "诊断报告已生成: $report_file"
}

# 主函数
main() {
    echo "========================================"
    echo "    系统综合诊断脚本 v2.1.0"
    echo "========================================"
    echo ""

    check_root

    check_system_info
    check_hardware
    check_network
    check_services
    check_database
    check_application
    check_security
    check_performance
    generate_report

    echo "========================================"
    log_success "系统诊断完成！"
    echo "========================================"
}

# 执行主函数
main "$@"
