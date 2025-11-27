#!/bin/bash

# 健康监控守护进程脚本
# 作者: 运维团队
# 版本: v2.1.0
# 更新: 2025-11-27

set -e

# 配置参数
MONITOR_INTERVAL=60
LOG_FILE="/var/log/health_monitor.log"
ALERT_EMAIL="ops@company.com"
WEBHOOK_URL="https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK"

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# 全局变量
PID_FILE="/var/run/health_monitor.pid"
ALERT_COUNT_FILE="/tmp/alert_count.tmp"
MAX_ALERTS_PER_HOUR=10

# 日志函数
log_info() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') [INFO] $1" | tee -a "$LOG_FILE"
}

log_warning() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') [WARNING] $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') [ERROR] $1" | tee -a "$LOG_FILE"
}

log_success() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') [SUCCESS] $1" | tee -a "$LOG_FILE"
}

# 检查告警频率
check_alert_frequency() {
    local current_time=$(date +%s)
    local last_alert_time=0

    if [[ -f "$ALERT_COUNT_FILE" ]]; then
        last_alert_time=$(cat "$ALERT_COUNT_FILE")
    fi

    local time_diff=$((current_time - last_alert_time))

    # 如果距离上次告警超过1小时，重置计数
    if [[ $time_diff -gt 3600 ]]; then
        echo "$current_time" > "$ALERT_COUNT_FILE"
        return 0
    fi

    # 检查1小时内告警次数
    local alert_count=$(find /var/log -name "health_monitor.log" -mmin -60 | xargs grep -c "\[ERROR\]\|\[WARNING\]" 2>/dev/null || echo "0")

    if [[ $alert_count -gt $MAX_ALERTS_PER_HOUR ]]; then
        log_warning "告警频率过高，暂停发送告警 ($alert_count/$MAX_ALERTS_PER_HOUR)"
        return 1
    fi

    return 0
}

# 发送告警
send_alert() {
    local level="$1"
    local message="$2"

    if ! check_alert_frequency; then
        return 1
    fi

    # 发送邮件告警
    if command -v mail &> /dev/null; then
        echo "$message" | mail -s "[$level] 系统健康监控告警" "$ALERT_EMAIL"
    fi

    # 发送Slack告警
    if [[ -n "$WEBHOOK_URL" ]] && command -v curl &> /dev/null; then
        local color="good"
        case "$level" in
            "ERROR") color="danger" ;;
            "WARNING") color="warning" ;;
            "INFO") color="good" ;;
        esac

        curl -X POST -H 'Content-type: application/json' \
            --data "{\"text\":\"$message\", \"attachments\":[{\"color\":\"$color\",\"text\":\"$(date '+%Y-%m-%d %H:%M:%S')\"}]}" \
            "$WEBHOOK_URL" &>/dev/null
    fi

    log_error "告警已发送: [$level] $message"
}

# 检查系统负载
check_system_load() {
    local load_avg=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | tr -d ',')
    local cpu_cores=$(nproc)
    local threshold=$(echo "$cpu_cores * 2.0" | bc)

    if (( $(echo "$load_avg > $threshold" | bc -l) )); then
        send_alert "ERROR" "系统负载过高: $load_avg (阈值: $threshold)"
        return 1
    fi

    return 0
}

# 检查内存使用
check_memory_usage() {
    local mem_usage=$(free | grep Mem | awk '{printf "%.1f", $3/$2 * 100.0}')

    if (( $(echo "$mem_usage > 90" | bc -l) )); then
        send_alert "ERROR" "内存使用率过高: ${mem_usage}%"
        return 1
    elif (( $(echo "$mem_usage > 80" | bc -l) )); then
        send_alert "WARNING" "内存使用率较高: ${mem_usage}%"
        return 1
    fi

    return 0
}

# 检查磁盘空间
check_disk_space() {
    local alert_sent=0

    df | grep -E '^/dev/' | while read line; do
        local mount_point=$(echo $line | awk '{print $6}')
        local usage=$(echo $line | awk '{print $5}' | tr -d '%')

        if [[ $usage -gt 90 ]]; then
            send_alert "ERROR" "磁盘空间不足: $mount_point 使用率 ${usage}%"
            alert_sent=1
        elif [[ $usage -gt 80 ]]; then
            send_alert "WARNING" "磁盘空间警告: $mount_point 使用率 ${usage}%"
            alert_sent=1
        fi
    done

    return $alert_sent
}

# 检查服务状态
check_service_status() {
    local services=("nginx" "php8.2-fpm" "mysql" "redis-server")
    local failed_services=()

    for service in "${services[@]}"; do
        if ! systemctl is-active --quiet "$service"; then
            failed_services+=("$service")
        fi
    done

    if [[ ${#failed_services[@]} -gt 0 ]]; then
        send_alert "ERROR" "服务异常: ${failed_services[*]}"
        return 1
    fi

    return 0
}

# 检查网络连接
check_network_connectivity() {
    # 检查外网连接
    if ! ping -c 1 8.8.8.8 &>/dev/null; then
        send_alert "ERROR" "外网连接失败"
        return 1
    fi

    # 检查关键端口
    local ports=("80" "443")
    for port in "${ports[@]}"; do
        if ! nc -z localhost "$port" &>/dev/null; then
            send_alert "ERROR" "端口 $port 未监听"
            return 1
        fi
    done

    return 0
}

# 检查数据库连接
check_database_connection() {
    if ! mysql -e "SELECT 1;" &>/dev/null; then
        send_alert "ERROR" "数据库连接失败"
        return 1
    fi

    # 检查数据库连接数
    local connections=$(mysql -e "SHOW STATUS LIKE 'Threads_connected';" 2>/dev/null | tail -1 | awk '{print $2}')
    local max_connections=$(mysql -e "SHOW VARIABLES LIKE 'max_connections';" 2>/dev/null | tail -1 | awk '{print $2}')

    if [[ $connections -gt $((max_connections * 80 / 100)) ]]; then
        send_alert "WARNING" "数据库连接数过高: $connections/$max_connections"
        return 1
    fi

    return 0
}

# 检查应用健康状态
check_application_health() {
    local app_url="http://localhost"

    # 检查HTTP响应
    local response_code=$(curl -s -o /dev/null -w "%{http_code}" "$app_url" || echo "000")

    if [[ "$response_code" != "200" ]]; then
        send_alert "ERROR" "应用HTTP响应异常: $response_code"
        return 1
    fi

    # 检查响应时间
    local response_time=$(curl -s -o /dev/null -w "%{time_total}" "$app_url" || echo "10")

    if (( $(echo "$response_time > 3.0" | bc -l) )); then
        send_alert "WARNING" "应用响应时间过长: ${response_time}s"
        return 1
    fi

    # 检查API健康接口
    local api_response=$(curl -s "http://localhost/api/health" || echo "")

    if ! echo "$api_response" | jq -e '.status' &>/dev/null; then
        send_alert "WARNING" "API健康检查接口异常"
        return 1
    fi

    return 0
}

# 检查日志错误
check_log_errors() {
    local log_files=("/var/log/nginx/error.log" "/www/wwwroot/official_website_backend/var/log/prod.log")
    local current_time=$(date +%s)
    local error_count=0

    for log_file in "${log_files[@]}"; do
        if [[ -f "$log_file" ]]; then
            # 检查最近5分钟的错误
            local recent_errors=$(find "$log_file" -mmin -5 -exec grep -l "error\|Error\|ERROR" {} \; 2>/dev/null | wc -l)
            error_count=$((error_count + recent_errors))
        fi
    done

    if [[ $error_count -gt 10 ]]; then
        send_alert "WARNING" "发现大量日志错误: $error_count 个"
        return 1
    fi

    return 0
}

# 检查SSL证书
check_ssl_certificate() {
    local ssl_cert="/etc/nginx/ssl/cert.pem"

    if [[ -f "$ssl_cert" ]]; then
        local cert_expiry=$(openssl x509 -in "$ssl_cert" -noout -enddate | cut -d'=' -f2)
        local expiry_timestamp=$(date -d "$cert_expiry" +%s)
        local current_timestamp=$(date +%s)
        local days_until_expiry=$(( (expiry_timestamp - current_timestamp) / 86400 ))

        if [[ $days_until_expiry -lt 7 ]]; then
            send_alert "ERROR" "SSL证书即将过期: $days_until_expiry 天"
            return 1
        elif [[ $days_until_expiry -lt 30 ]]; then
            send_alert "WARNING" "SSL证书即将过期: $days_until_expiry 天"
            return 1
        fi
    fi

    return 0
}

# 执行健康检查
run_health_checks() {
    log_info "开始健康检查..."

    local checks_failed=0

    check_system_load || ((checks_failed++))
    check_memory_usage || ((checks_failed++))
    check_disk_space || ((checks_failed++))
    check_service_status || ((checks_failed++))
    check_network_connectivity || ((checks_failed++))
    check_database_connection || ((checks_failed++))
    check_application_health || ((checks_failed++))
    check_log_errors || ((checks_failed++))
    check_ssl_certificate || ((checks_failed++))

    if [[ $checks_failed -eq 0 ]]; then
        log_success "所有健康检查通过"
    else
        log_warning "$checks_failed 项健康检查失败"
    fi

    return $checks_failed
}

# 守护进程主循环
daemon_loop() {
    log_info "健康监控守护进程启动 (PID: $$)"

    while true; do
        run_health_checks
        sleep "$MONITOR_INTERVAL"
    done
}

# 启动守护进程
start_daemon() {
    if [[ -f "$PID_FILE" ]]; then
        local pid=$(cat "$PID_FILE")
        if kill -0 "$pid" 2>/dev/null; then
            echo "健康监控守护进程已在运行 (PID: $pid)"
            exit 1
        else
            rm -f "$PID_FILE"
        fi
    fi

    # 后台运行守护进程
    nohup bash "$0" --daemon &>/dev/null &
    local daemon_pid=$!
    echo "$daemon_pid" > "$PID_FILE"

    echo "健康监控守护进程已启动 (PID: $daemon_pid)"
    log_info "健康监控守护进程启动 (PID: $daemon_pid)"
}

# 停止守护进程
stop_daemon() {
    if [[ -f "$PID_FILE" ]]; then
        local pid=$(cat "$PID_FILE")
        if kill -0 "$pid" 2>/dev/null; then
            kill "$pid"
            rm -f "$PID_FILE"
            echo "健康监控守护进程已停止 (PID: $pid)"
            log_info "健康监控守护进程停止 (PID: $pid)"
        else
            echo "守护进程不存在"
            rm -f "$PID_FILE"
        fi
    else
        echo "PID文件不存在，守护进程可能未运行"
    fi
}

# 检查守护进程状态
check_daemon_status() {
    if [[ -f "$PID_FILE" ]]; then
        local pid=$(cat "$PID_FILE")
        if kill -0 "$pid" 2>/dev/null; then
            echo "健康监控守护进程正在运行 (PID: $pid)"
            return 0
        else
            echo "PID文件存在但进程不存在"
            rm -f "$PID_FILE"
            return 1
        fi
    else
        echo "健康监控守护进程未运行"
        return 1
    fi
}

# 显示帮助信息
show_help() {
    echo "健康监控脚本使用说明:"
    echo ""
    echo "用法: $0 [选项]"
    echo ""
    echo "选项:"
    echo "  start     启动健康监控守护进程"
    echo "  stop      停止健康监控守护进程"
    echo "  status    检查守护进程状态"
    echo "  check     执行一次健康检查"
    echo "  daemon    运行守护进程 (内部使用)"
    echo "  help      显示此帮助信息"
    echo ""
    echo "配置文件: $0"
    echo "日志文件: $LOG_FILE"
    echo "PID文件: $PID_FILE"
}

# 主函数
main() {
    case "${1:-help}" in
        "start")
            start_daemon
            ;;
        "stop")
            stop_daemon
            ;;
        "status")
            check_daemon_status
            ;;
        "check")
            run_health_checks
            ;;
        "daemon")
            daemon_loop
            ;;
        "help"|*)
            show_help
            ;;
    esac
}

# 执行主函数
main "$@"
