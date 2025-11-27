#!/bin/bash
# 告警监控脚本 - alert_monitor.sh
set -e

ALERT_RULES_FILE="/var/www/official_website_backend/config/alerts/alert_rules.yaml"
METRICS_CACHE_FILE="/tmp/metrics_cache.json"
LOG_FILE="/var/log/alert_monitor.log"
ALERT_STATE_FILE="/tmp/alert_state.json"

# 日志函数
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# 收集系统指标
collect_metrics() {
    local metrics_file=$1

    # CPU使用率
    local cpu_usage=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d% -f1)

    # 内存使用率
    local memory_usage=$(free | grep Mem | awk '{printf("%.1f", $3/$2 * 100.0)}')

    # 磁盘使用率
    local disk_usage=$(df / | tail -1 | awk '{print $5}' | cut -d% -f1)

    # 数据库连接使用率
    local max_connections=$(mysql -u root -p -e "SHOW VARIABLES LIKE 'max_connections';" 2>/dev/null | tail -1 | awk '{print $2}' || echo 100)
    local current_connections=$(mysql -u root -p -e "SHOW STATUS LIKE 'Threads_connected';" 2>/dev/null | tail -1 | awk '{print $2}' || echo 0)
    local db_connection_usage=$((current_connections * 100 / max_connections))

    # 应用错误率（从日志中计算）
    local error_count=$(tail -1000 /var/www/official_website_backend/var/log/application.log 2>/dev/null | grep -c "ERROR" || echo 0)
    local total_requests=$(tail -1000 /var/www/official_website_backend/var/log/application.log 2>/dev/null | wc -l || echo 1)
    local error_rate=$((error_count * 100 / total_requests))

    # 平均响应时间（从日志中计算）
    local avg_response_time=0
    if [ -f "/var/www/official_website_backend/var/log/performance.log" ]; then
        avg_response_time=$(tail -100 /var/www/official_website_backend/var/log/performance.log | grep -o '"duration":[0-9]*' | cut -d: -f2 | awk '{sum+=$1; count++} END {if(count>0) print sum/count; else print 0}')
    fi

    # SSL证书过期天数
    local days_until_expiry=365
    if command -v openssl &> /dev/null; then
        local expiry_date=$(echo | openssl s_client -servername yourdomain.com -connect yourdomain.com:443 2>/dev/null | openssl x509 -noout -dates | grep "notAfter" | cut -d= -f2)
        if [ -n "$expiry_date" ]; then
            local expiry_timestamp=$(date -d "$expiry_date" +%s)
            local current_timestamp=$(date +%s)
            days_until_expiry=$(( (expiry_timestamp - current_timestamp) / 86400 ))
        fi
    fi

    # 慢查询数量
    local slow_query_count=0
    if [ -f "/var/log/mysql/slow.log" ]; then
        slow_query_count=$(tail -100 /var/log/mysql/slow.log | grep -c "# Query_time" || echo 0)
    fi

    # 失败登录次数
    local failed_login_count=0
    if [ -f "/var/www/official_website_backend/var/log/security.log" ]; then
        failed_login_count=$(tail -100 /var/www/official_website_backend/var/log/security.log | grep -c "authentication failed" || echo 0)
    fi

    # 生成JSON格式的指标数据
    cat > "$metrics_file" << EOF
{
    "timestamp": "$(date -Iseconds)",
    "cpu_usage": $cpu_usage,
    "memory_usage": $memory_usage,
    "disk_usage": $disk_usage,
    "db_connection_usage": $db_connection_usage,
    "error_rate": $error_rate,
    "avg_response_time": $avg_response_time,
    "days_until_expiry": $days_until_expiry,
    "slow_query_count": $slow_query_count,
    "failed_login_count": $failed_login_count,
    "max_connections": $max_connections,
    "current_connections": $current_connections
}
EOF

    log "指标收集完成: $metrics_file"
}

# 评估告警规则
evaluate_alerts() {
    local metrics_file=$1

    if [ ! -f "$ALERT_RULES_FILE" ]; then
        log "错误: 告警规则文件不存在 $ALERT_RULES_FILE"
        return 1
    fi

    # 解析指标数据
    local cpu_usage=$(grep -o '"cpu_usage":[0-9.]*' "$metrics_file" | cut -d: -f2)
    local memory_usage=$(grep -o '"memory_usage":[0-9.]*' "$metrics_file" | cut -d: -f2)
    local disk_usage=$(grep -o '"disk_usage":[0-9]*' "$metrics_file" | cut -d: -f2)
    local db_connection_usage=$(grep -o '"db_connection_usage":[0-9]*' "$metrics_file" | cut -d: -f2)
    local error_rate=$(grep -o '"error_rate":[0-9]*' "$metrics_file" | cut -d: -f2)
    local avg_response_time=$(grep -o '"avg_response_time":[0-9]*' "$metrics_file" | cut -d: -f2)
    local days_until_expiry=$(grep -o '"days_until_expiry":[0-9]*' "$metrics_file" | cut -d: -f2)
    local slow_query_count=$(grep -o '"slow_query_count":[0-9]*' "$metrics_file" | cut -d: -f2)
    local failed_login_count=$(grep -o '"failed_login_count":[0-9]*' "$metrics_file" | cut -d: -f2)

    # 加载现有告警状态
    local active_alerts="{}"
    if [ -f "$ALERT_STATE_FILE" ]; then
        active_alerts=$(cat "$ALERT_STATE_FILE")
    fi

    local new_alerts="{}"

    # 评估各项告警规则
    # CPU使用率告警
    if (( $(echo "$cpu_usage > 80" | bc -l) )); then
        local alert_key="cpu_usage_high"
        local alert_message="CPU使用率过高: ${cpu_usage}%"
        trigger_alert "$alert_key" "warning" "$alert_message" "$new_alerts"
    else
        resolve_alert "$alert_key" "$new_alerts"
    fi

    # 内存使用率告警
    if (( $(echo "$memory_usage > 85" | bc -l) )); then
        local alert_key="memory_usage_high"
        local alert_message="内存使用率过高: ${memory_usage}%"
        trigger_alert "$alert_key" "critical" "$alert_message" "$new_alerts"
    else
        resolve_alert "$alert_key" "$new_alerts"
    fi

    # 磁盘使用率告警
    if [ "$disk_usage" -gt 90 ]; then
        local alert_key="disk_usage_high"
        local alert_message="磁盘使用率过高: ${disk_usage}%"
        trigger_alert "$alert_key" "critical" "$alert_message" "$new_alerts"
    else
        resolve_alert "$alert_key" "$new_alerts"
    fi

    # 数据库连接告警
    if [ "$db_connection_usage" -gt 80 ]; then
        local alert_key="db_connections_high"
        local alert_message="数据库连接使用率过高: ${db_connection_usage}%"
        trigger_alert "$alert_key" "critical" "$alert_message" "$new_alerts"
    else
        resolve_alert "$alert_key" "$new_alerts"
    fi

    # 应用错误率告警
    if [ "$error_rate" -gt 5 ]; then
        local alert_key="app_error_rate_high"
        local alert_message="应用错误率过高: ${error_rate}%"
        trigger_alert "$alert_key" "critical" "$alert_message" "$new_alerts"
    else
        resolve_alert "$alert_key" "$new_alerts"
    fi

    # 响应时间告警
    if [ "$avg_response_time" -gt 5000 ]; then
        local alert_key="response_time_high"
        local alert_message="平均响应时间过长: ${avg_response_time}ms"
        trigger_alert "$alert_key" "warning" "$alert_message" "$new_alerts"
    else
        resolve_alert "$alert_key" "$new_alerts"
    fi

    # SSL证书过期告警
    if [ "$days_until_expiry" -lt 30 ]; then
        local alert_key="ssl_cert_expiring"
        local alert_message="SSL证书将在${days_until_expiry}天后过期"
        trigger_alert "$alert_key" "warning" "$alert_message" "$new_alerts"
    else
        resolve_alert "$alert_key" "$new_alerts"
    fi

    # 慢查询告警
    if [ "$slow_query_count" -gt 10 ]; then
        local alert_key="slow_queries_high"
        local alert_message="慢查询数量过多: $slow_query_count"
        trigger_alert "$alert_key" "warning" "$alert_message" "$new_alerts"
    else
        resolve_alert "$alert_key" "$new_alerts"
    fi

    # 安全事件告警
    if [ "$failed_login_count" -gt 5 ]; then
        local alert_key="security_events"
        local alert_message="检测到$failed_login_count次失败登录"
        trigger_alert "$alert_key" "critical" "$alert_message" "$new_alerts"
    else
        resolve_alert "$alert_key" "$new_alerts"
    fi

    # 保存新的告警状态
    echo "$new_alerts" > "$ALERT_STATE_FILE"

    log "告警评估完成"
}

# 触发告警
trigger_alert() {
    local alert_key=$1
    local severity=$2
    local message=$3
    local alerts_json=$4

    # 检查告警是否已经激活
    if echo "$alerts_json" | jq -e ".has(\"$alert_key\")" > /dev/null 2>&1; then
        # 告警已激活，检查是否需要升级
        local current_level=$(echo "$alerts_json" | jq -r ".\"$alert_key\".level // 1")
        local triggered_at=$(echo "$alerts_json" | jq -r ".\"$alert_key\".triggered_at")
        local current_time=$(date +%s)
        local triggered_timestamp=$(date -d "$triggered_at" +%s)
        local duration=$((current_time - triggered_timestamp))

        # 15分钟后升级到level 2
        if [ "$current_level" -eq 1 ] && [ "$duration" -gt 900 ]; then
            echo "$alerts_json" | jq ".\"$alert_key\".level = 2" > /tmp/alerts_temp.json
            mv /tmp/alerts_temp.json "$ALERT_STATE_FILE"
            send_alert "$alert_key" "$severity" "$message (Level 2)" "escalated"
        # 30分钟后升级到level 3
        elif [ "$current_level" -eq 2 ] && [ "$duration" -gt 1800 ]; then
            echo "$alerts_json" | jq ".\"$alert_key\".level = 3" > /tmp/alerts_temp.json
            mv /tmp/alerts_temp.json "$ALERT_STATE_FILE"
            send_alert "$alert_key" "$severity" "$message (Level 3 - Critical)" "escalated"
        fi
    else
        # 新告警
        local current_time=$(date -Iseconds)
        alerts_json=$(echo "$alerts_json" | jq ".\"$alert_key\" = {severity: \"$severity\", message: \"$message\", triggered_at: \"$current_time\", level: 1}")
        echo "$alerts_json" > "$ALERT_STATE_FILE"
        send_alert "$alert_key" "$severity" "$message" "new"
    fi
}

# 解除告警
resolve_alert() {
    local alert_key=$1
    local alerts_json=$2

    if echo "$alerts_json" | jq -e ".has(\"$alert_key\")" > /dev/null 2>&1; then
        local message=$(echo "$alerts_json" | jq -r ".\"$alert_key\".message")
        send_alert "$alert_key" "resolved" "$message - 已解除" "resolved"
        alerts_json=$(echo "$alerts_json" | jq "del(.\"$alert_key\")")
        echo "$alerts_json" > "$ALERT_STATE_FILE"
    fi
}

# 发送告警通知
send_alert() {
    local alert_key=$1
    local severity=$2
    local message=$3
    local status=$4
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')

    log "告警通知: $alert_key [$severity] $message ($status)"

    # 发送邮件通知
    if [ "$severity" = "critical" ] || [ "$status" = "new" ]; then
        echo "[$timestamp] 告警通知: $message" | mail -s "系统告警: $alert_key" "admin@yourdomain.com"
    fi

    # 发送Webhook通知
    if [ -n "$WEBHOOK_URL" ]; then
        local emoji="🚨"
        if [ "$severity" = "resolved" ]; then
            emoji="✅"
        elif [ "$severity" = "warning" ]; then
            emoji="⚠️"
        fi

        curl -X POST -H 'Content-type: application/json' \
            --data "{\"text\":\"$emoji 告警通知\\n类型: $alert_key\\n严重程度: $severity\\n时间: $timestamp\\n消息: $message\\n状态: $status\"}" \
            "$WEBHOOK_URL" 2>/dev/null || true
    fi
}

# 主监控循环
main() {
    log "开始告警监控..."

    while true; do
        # 收集指标
        collect_metrics "$METRICS_CACHE_FILE"

        # 评估告警
        evaluate_alerts "$METRICS_CACHE_FILE"

        # 等待下次检查
        sleep 60
    done
}

# 信号处理
trap 'echo "告警监控停止"; exit 0' SIGINT SIGTERM

# 启动监控
main "$@"
