#!/bin/bash
# 部署验证脚本 - deploy_verification.sh
set -e

VERIFICATION_LOG="/var/log/deploy_verification_$(date +%Y%m%d_%H%M%S).log"
API_BASE_URL="https://api.yourdomain.com"
FAILED_TESTS=0
TOTAL_TESTS=0

# 日志函数
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$VERIFICATION_LOG"
}

# 测试函数
test_endpoint() {
    local endpoint=$1
    local method=${2:-GET}
    local data=${3:-""}
    local expected_status=${4:-200}
    local test_name=$5

    TOTAL_TESTS=$((TOTAL_TESTS + 1))

    log "测试: $test_name"
    log "  端点: $method $endpoint"
    log "  期望状态: $expected_status"

    local response_status=$(curl -s -o /dev/null -w "%{http_code}" \
        -X "$method" \
        -H "Content-Type: application/json" \
        ${data:+-d "$data"} \
        "$API_BASE_URL$endpoint")

    if [ "$response_status" = "$expected_status" ]; then
        log "  结果: ✅ 通过 ($response_status)"
        return 0
    else
        log "  结果: ❌ 失败 ($response_status)"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        return 1
    fi
}

# 数据库连接测试
test_database_connection() {
    log "测试数据库连接..."

    if mysql -u root -p -e "SELECT 1 FROM official_website.users LIMIT 1;" > /dev/null 2>&1; then
        log "  结果: ✅ 数据库连接正常"
        return 0
    else
        log "  结果: ❌ 数据库连接失败"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        return 1
    fi
}

# Redis连接测试
test_redis_connection() {
    log "测试Redis连接..."

    if redis-cli ping > /dev/null 2>&1; then
        log "  结果: ✅ Redis连接正常"
        return 0
    else
        log "  结果: ❌ Redis连接失败"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        return 1
    fi
}

# JWT认证测试
test_jwt_authentication() {
    log "测试JWT认证..."

    # 获取JWT令牌
    local token=$(curl -s -X POST \
        -H "Content-Type: application/json" \
        -d '{"email":"admin@test.com","password":"test123"}' \
        "$API_BASE_URL/api/login" | jq -r '.token // empty')

    if [ -n "$token" ] && [ "$token" != "null" ]; then
        log "  结果: ✅ JWT认证正常"
        echo "$token" > /tmp/test_jwt_token
        return 0
    else
        log "  结果: ❌ JWT认证失败"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        return 1
    fi
}

# 新闻API测试
test_news_api() {
    local token=$(cat /tmp/test_jwt_token 2>/dev/null)

    # 测试获取新闻列表
    test_endpoint "/api/news" "GET" "" "200" "获取新闻列表"

    # 测试创建新闻（需要认证）
    if [ -n "$token" ]; then
        test_endpoint "/api/news" "POST" \
            '{"title":"测试新闻","content":"测试内容","category_id":1}' \
            "201" "创建新闻"
    fi
}

# 文章API测试
test_articles_api() {
    local token=$(cat /tmp/test_jwt_token 2>/dev/null)

    # 测试获取文章列表
    test_endpoint "/api/articles" "GET" "" "200" "获取文章列表"

    # 测试记录阅读（需要认证）
    if [ -n "$token" ]; then
        test_endpoint "/api/articles/read" "POST" \
            '{"article_id":1,"user_id":1}' \
            "200" "记录文章阅读"
    fi
}

# 微信API测试
test_wechat_api() {
    local token=$(cat /tmp/test_jwt_token 2>/dev/null)

    # 测试获取公众号列表
    test_endpoint "/api/wechat/accounts" "GET" "" "200" "获取公众号列表"

    # 测试同步文章（需要认证）
    if [ -n "$token" ]; then
        test_endpoint "/api/wechat/sync" "POST" \
            '{"account_id":1}' \
            "200" "同步微信文章"
    fi
}

# 性能测试
test_performance() {
    log "测试API性能..."

    local endpoint="/api/news"
    local max_response_time=2000  # 2秒
    local response_time=$(curl -o /dev/null -s -w '%{time_total}' \
        --max-time 10 "$API_BASE_URL$endpoint")

    local response_time_ms=$(echo "$response_time" | awk '{printf("%.0f", $1 * 1000)}')

    if [ "$response_time_ms" -le "$max_response_time" ]; then
        log "  结果: ✅ 性能测试通过 (${response_time_ms}ms)"
        return 0
    else
        log "  结果: ❌ 性能测试失败 (${response_time_ms}ms > ${max_response_time}ms)"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        return 1
    fi
}

# 安全测试
test_security() {
    log "测试安全配置..."

    # 测试HTTPS重定向
    local http_status=$(curl -s -o /dev/null -w "%{http_code}" \
        "http://api.yourdomain.com/health")

    if [ "$http_status" = "301" ] || [ "$http_status" = "302" ]; then
        log "  结果: ✅ HTTPS重定向正常"
    else
        log "  结果: ❌ HTTPS重定向失败 ($http_status)"
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi

    # 测试安全头
    local security_headers=$(curl -s -I "$API_BASE_URL/health")

    if echo "$security_headers" | grep -q "X-Content-Type-Options:" && \
       echo "$security_headers" | grep -q "X-Frame-Options:"; then
        log "  结果: ✅ 安全头配置正常"
    else
        log "  结果: ❌ 安全头配置缺失"
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
}

# 生成验证报告
generate_report() {
    local report_file="/var/log/deploy_verification_report_$(date +%Y%m%d_%H%M%S).html"
    local success_rate=$(( (TOTAL_TESTS - FAILED_TESTS) * 100 / TOTAL_TESTS ))

    cat > "$report_file" << EOF
<!DOCTYPE html>
<html>
<head>
    <title>部署验证报告</title>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        .success { color: green; }
        .failure { color: red; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>部署验证报告</h1>
    <p>生成时间: $(date)</p>

    <div class="section">
        <h2>验证结果概览</h2>
        <table>
            <tr><th>总测试数</th><td>$TOTAL_TESTS</td></tr>
            <tr><th>成功测试</th><td>$((TOTAL_TESTS - FAILED_TESTS))</td></tr>
            <tr><th>失败测试</th><td>$FAILED_TESTS</td></tr>
            <tr><th>成功率</th><td>$success_rate%</td></tr>
        </table>
    </div>

    <div class="section">
        <h2>详细测试日志</h2>
        <pre>$(cat "$VERIFICATION_LOG")</pre>
    </div>
</body>
</html>
EOF

    log "验证报告生成: $report_file"

    # 发送报告
    if [ "$FAILED_TESTS" -eq 0 ]; then
        echo "部署验证全部通过" | mail -s "部署验证成功" "admin@yourdomain.com"
    else
        echo "部署验证失败: $FAILED_TESTS/$TOTAL_TESTS 测试失败" | \
            mail -s "部署验证失败" "admin@yourdomain.com"
    fi
}

# 主验证流程
main() {
    log "开始部署验证..."

    # 基础连接测试
    test_database_connection
    test_redis_connection

    # 认证测试
    test_jwt_authentication

    # API功能测试
    test_news_api
    test_articles_api
    test_wechat_api

    # 性能测试
    test_performance

    # 安全测试
    test_security

    # 生成报告
    generate_report

    # 清理
    rm -f /tmp/test_jwt_token

    log "部署验证完成"
    log "总测试: $TOTAL_TESTS, 成功: $((TOTAL_TESTS - FAILED_TESTS)), 失败: $FAILED_TESTS"

    if [ "$FAILED_TESTS" -eq 0 ]; then
        log "结果: 所有测试通过"
        exit 0
    else
        log "结果: 存在失败的测试"
        exit 1
    fi
}

main "$@"
