#!/bin/bash

# =============================================================================
# 数据库监控脚本
# =============================================================================
#
# 功能：
# - 定期检查数据库连接状态
# - 记录连接状态到日志文件
# - 发送告警通知（可选）
# - 适合设置到crontab中定期执行
#
# 使用方法：
# ./database_monitor.sh [选项]
#
# 选项：
#   --check-only     仅检查状态，不发送通知
#   --verbose        详细输出模式
#   --quiet          静默模式
#   --log-file FILE  指定日志文件路径
#   --alert-email    发送告警邮件
#   --help           显示帮助信息
#
# Crontab 设置示例：
#   # 每5分钟检查一次
#   */5 * * * * /path/to/scripts/database_monitor.sh --quiet
#   # 每小时发送一次完整报告
#   0 * * * * /path/to/scripts/database_monitor.sh --alert-email
#
# =============================================================================

# 脚本配置
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
LOG_FILE="${PROJECT_ROOT}/var/logs/database_monitor.log"
ALERT_LOG="${PROJECT_ROOT}/var/logs/database_alerts.log"
API_URL="http://localhost/api_db_status.php"
ACCESS_TOKEN="db_monitor_2024_secure"

# 默认配置
VERBOSE=false
QUIET=false
CHECK_ONLY=false
ALERT_EMAIL=false
TIMEOUT=30
MAX_RETRIES=3
RESPONSE_TIME_THRESHOLD=1000  # ms

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# =============================================================================
# 函数定义
# =============================================================================

/**
 * 显示帮助信息
 */
show_help() {
    cat << EOF
数据库监控脚本 - 使用说明

用法: $0 [选项]

选项:
    --check-only     仅检查状态，不发送通知
    --verbose        详细输出模式
    --quiet          静默模式
    --log-file FILE  指定日志文件路径
    --alert-email    发送告警邮件
    --timeout SEC    设置请求超时时间（默认30秒）
    --help           显示此帮助信息

示例:
    $0                           # 基本检查
    $0 --verbose                 # 详细输出
    $0 --quiet                   # 静默模式
    $0 --check-only              # 仅检查状态
    $0 --log-file /tmp/db.log    # 指定日志文件
    $0 --alert-email             # 发送邮件告警

Crontab 设置:
    */5 * * * * $0 --quiet
    0 * * * * $0 --alert-email

EOF
}

/**
 * 日志记录函数
 */
log_message() {
    local level="$1"
    local message="$2"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')

    # 确保日志目录存在
    mkdir -p "$(dirname "$LOG_FILE")"

    # 写入日志文件
    echo "[$timestamp] [$level] $message" >> "$LOG_FILE"

    # 如果不是静默模式，也输出到控制台
    if [[ "$QUIET" != "true" ]]; then
        case "$level" in
            "ERROR")
                echo -e "${RED}[ERROR]${NC} $message" >&2
                ;;
            "WARN")
                echo -e "${YELLOW}[WARN]${NC} $message"
                ;;
            "INFO")
                echo -e "${GREEN}[INFO]${NC} $message"
                ;;
            "DEBUG")
                if [[ "$VERBOSE" == "true" ]]; then
                    echo -e "${BLUE}[DEBUG]${NC} $message"
                fi
                ;;
            *)
                echo "[$level] $message"
                ;;
        esac
    fi
}

/**
 * 错误处理函数
 */
handle_error() {
    local exit_code=$?
    local line_number=$1
    log_message "ERROR" "脚本在第 $line_number 行发生错误，退出码: $exit_code"
    exit $exit_code
}

/**
 * 检查依赖
 */
check_dependencies() {
    local deps=("curl" "jq")

    for dep in "${deps[@]}"; do
        if ! command -v "$dep" &> /dev/null; then
            log_message "ERROR" "缺少依赖: $dep"
            exit 1
        fi
    done

    log_message "DEBUG" "依赖检查通过"
}

/**
 * 检查API可访问性
 */
check_api_accessibility() {
    log_message "DEBUG" "检查API可访问性: $API_URL"

    if ! curl --connect-timeout 10 --max-time "$TIMEOUT" -s -o /dev/null -w "%{http_code}" "$API_URL" | grep -q "200"; then
        log_message "ERROR" "API不可访问: $API_URL"
        return 1
    fi

    log_message "DEBUG" "API可访问性检查通过"
    return 0
}

/**
 * 调用API获取数据库状态
 */
get_database_status() {
    local endpoint="$1"
    local url="$API_URL$endpoint"

    if [[ -n "$ACCESS_TOKEN" ]]; then
        url="$url?token=$ACCESS_TOKEN"
    fi

    log_message "DEBUG" "请求API: $url"

    local response
    response=$(curl -s \
        --connect-timeout 10 \
        --max-time "$TIMEOUT" \
        -H "Content-Type: application/json" \
        "$url" 2>/dev/null)

    local curl_exit_code=$?
    if [[ $curl_exit_code -ne 0 ]]; then
        log_message "ERROR" "API请求失败，curl退出码: $curl_exit_code"
        return 1
    fi

    if [[ -z "$response" ]]; then
        log_message "ERROR" "API返回空响应"
        return 1
    fi

    echo "$response"
    return 0
}

/**
 * 解析JSON响应
 */
parse_response() {
    local response="$1"

    # 检查是否为有效JSON
    if ! echo "$response" | jq . >/dev/null 2>&1; then
        log_message "ERROR" "无效的JSON响应"
        log_message "DEBUG" "响应内容: $response"
        return 1
    fi

    # 检查API返回的成功状态
    local success
    success=$(echo "$response" | jq -r '.success // false')

    if [[ "$success" != "true" ]]; then
        local error_msg
        error_msg=$(echo "$response" | jq -r '.error.message // "未知错误"')
        log_message "ERROR" "API返回错误: $error_msg"
        return 1
    fi

    return 0
}

/**
 * 检查数据库健康状态
 */
check_database_health() {
    log_message "INFO" "开始数据库健康检查"

    local response
    if ! response=$(get_database_status "?health"); then
        return 1
    fi

    if ! parse_response "$response"; then
        return 1
    fi

    local overall_status
    overall_status=$(echo "$response" | jq -r '.data.status // "unknown"')

    if [[ "$overall_status" == "healthy" ]]; then
        log_message "INFO" "数据库健康状态: 正常"
        return 0
    else
        log_message "WARN" "数据库健康状态: $overall_status"
        return 1
    fi
}

/**
 * 检查详细状态
 */
check_detailed_status() {
    log_message "INFO" "开始详细状态检查"

    local response
    if ! response=$(get_database_status ""); then
        return 1
    fi

    if ! parse_response "$response"; then
        return 1
    fi

    # 解析连接状态
    local connections
    connections=$(echo "$response" | jq -r '.data.connections // {}')

    echo "$connections" | jq -r 'to_entries[] |
        "连接: \(.key) | 状态: \(.value.status) | 响应时间: \(.value.response_time)ms | 数据库: \(.value.database)"' |
    while read -r line; do
        log_message "INFO" "$line"
    done

    # 检查告警
    local alerts
    alerts=$(echo "$response" | jq -r '.data.alerts // []')

    if [[ "$alerts" != "[]" ]]; then
        log_message "WARN" "发现告警信息:"
        echo "$alerts" | jq -r '.[] | "类型: \(.type) | 严重程度: \(.severity) | 消息: \(.message)"' |
        while read -r line; do
            log_message "WARN" "$line"
        done
        return 1
    fi

    # 检查摘要信息
    local summary
    summary=$(echo "$response" | jq -r '.data.summary // {}')

    local health_percentage
    health_percentage=$(echo "$summary" | jq -r '.health_percentage // 0')

    local avg_response_time
    avg_response_time=$(echo "$summary" | jq -r '.average_response_time // 0')

    log_message "INFO" "健康度: ${health_percentage}% | 平均响应时间: ${avg_response_time}ms"

    # 检查阈值
    if (( $(echo "$avg_response_time > $RESPONSE_TIME_THRESHOLD" | bc -l) )); then
        log_message "WARN" "平均响应时间超过阈值: ${avg_response_time}ms > ${RESPONSE_TIME_THRESHOLD}ms"
        return 1
    fi

    if (( $(echo "$health_percentage < 80" | bc -l) )); then
        log_message "WARN" "健康度低于80%: ${health_percentage}%"
        return 1
    fi

    return 0
}

/**
 * 发送告警通知
 */
send_alert() {
    local message="$1"
    local severity="$2"

    if [[ "$CHECK_ONLY" == "true" ]]; then
        return 0
    fi

    # 记录告警日志
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [$severity] $message" >> "$ALERT_LOG"

    # 如果启用了邮件告警（这里只是示例，需要配置实际的邮件发送）
    if [[ "$ALERT_EMAIL" == "true" ]]; then
        log_message "INFO" "发送邮件告警: $message"
        # 这里可以集成实际的邮件发送逻辑
        # mail -s "数据库监控告警" admin@example.com <<< "$message"
    fi

    # 可以在这里添加其他告警方式，如短信、钉钉、企业微信等
}

/**
 * 生成监控报告
 */
generate_report() {
    local report_file="${PROJECT_ROOT}/var/logs/database_report_$(date '+%Y%m%d_%H%M%S').json"

    log_message "INFO" "生成监控报告: $report_file"

    local response
    if response=$(get_database_status ""); then
        echo "$response" > "$report_file"
        log_message "INFO" "监控报告已生成: $report_file"
    else
        log_message "ERROR" "生成监控报告失败"
    fi
}

/**
 * 清理旧日志
 */
cleanup_logs() {
    local days=7

    # 清理7天前的监控日志
    find "$(dirname "$LOG_FILE")" -name "database_monitor.log*" -mtime +$days -delete 2>/dev/null
    find "$(dirname "$ALERT_LOG")" -name "database_alerts.log*" -mtime +$days -delete 2>/dev/null
    find "$(dirname "$report_file")" -name "database_report_*.json" -mtime +$days -delete 2>/dev/null

    log_message "DEBUG" "旧日志清理完成"
}

# =============================================================================
# 主程序
# =============================================================================

# 设置错误处理
trap 'handle_error $LINENO' ERR

# 解析命令行参数
while [[ $# -gt 0 ]]; do
    case $1 in
        --check-only)
            CHECK_ONLY=true
            shift
            ;;
        --verbose)
            VERBOSE=true
            shift
            ;;
        --quiet)
            QUIET=true
            shift
            ;;
        --log-file)
            LOG_FILE="$2"
            shift 2
            ;;
        --alert-email)
            ALERT_EMAIL=true
            shift
            ;;
        --timeout)
            TIMEOUT="$2"
            shift 2
            ;;
        --help)
            show_help
            exit 0
            ;;
        *)
            log_message "ERROR" "未知选项: $1"
            show_help
            exit 1
            ;;
    esac
done

# 主逻辑
main() {
    log_message "INFO" "开始数据库监控检查"

    # 检查依赖
    check_dependencies

    # 检查API可访问性
    if ! check_api_accessibility; then
        send_alert "数据库监控API不可访问: $API_URL" "CRITICAL"
        exit 1
    fi

    # 执行健康检查
    local health_status=0
    if ! check_database_health; then
        health_status=1
        send_alert "数据库健康检查失败" "WARNING"
    fi

    # 执行详细状态检查
    local detailed_status=0
    if ! check_detailed_status; then
        detailed_status=1
        send_alert "数据库详细状态检查发现问题" "WARNING"
    fi

    # 生成报告（每小时整点）
    local current_minute=$(date '+%M')
    if [[ "$current_minute" == "00" ]]; then
        generate_report
    fi

    # 清理旧日志（每天凌晨2点）
    local current_hour=$(date '+%H')
    if [[ "$current_hour" == "02" && "$current_minute" == "00" ]]; then
        cleanup_logs
    fi

    # 总结
    if [[ $health_status -eq 0 && $detailed_status -eq 0 ]]; then
        log_message "INFO" "数据库监控检查完成 - 状态正常"
        exit 0
    else
        log_message "WARN" "数据库监控检查完成 - 发现问题"
        exit 1
    fi
}

# 执行主程序
main "$@"
