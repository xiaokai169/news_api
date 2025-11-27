#!/bin/bash

# 交互式故障排除助手脚本
# 作者: 运维团队
# 版本: v2.1.0
# 更新: 2025-11-27

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# 日志函数
log_info() {
    echo -e "${BLUE}[信息]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[成功]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[警告]${NC} $1"
}

log_error() {
    echo -e "${RED}[错误]${NC} $1"
}

log_step() {
    echo -e "${CYAN}[步骤]${NC} $1"
}

# 等待用户确认
wait_for_confirmation() {
    echo ""
    read -p "按回车键继续..."
}

# 显示主菜单
show_main_menu() {
    clear
    echo "========================================"
    echo "    故障排除助手 v2.1.0"
    echo "========================================"
    echo ""
    echo "请选择故障类型:"
    echo "1) 应用启动问题"
    echo "2) 数据库连接问题"
    echo "3) API访问问题"
    echo "4) 性能问题"
    echo "5) 安全相关问题"
    echo "6) 网络连接问题"
    echo "7) 服务状态问题"
    echo "8) 日志分析"
    echo "9) 系统资源问题"
    echo "10) 综合系统检查"
    echo "0) 退出"
    echo ""
    read -p "请输入选项 (0-10): " choice
    echo ""

    case $choice in
        1) troubleshoot_startup ;;
        2) troubleshoot_database ;;
        3) troubleshoot_api ;;
        4) troubleshoot_performance ;;
        5) troubleshoot_security ;;
        6) troubleshoot_network ;;
        7) troubleshoot_services ;;
        8) troubleshoot_logs ;;
        9) troubleshoot_resources ;;
        10) comprehensive_check ;;
        0) exit 0 ;;
        *)
            log_error "无效选项，请重新选择"
            wait_for_confirmation
            show_main_menu
            ;;
    esac
}

# 应用启动问题排查
troubleshoot_startup() {
    log_step "开始排查应用启动问题..."
    echo ""

    # 检查应用目录
    log_info "1. 检查应用目录结构"
    local app_dir="/www/wwwroot/official_website_backend"

    if [[ ! -d "$app_dir" ]]; then
        log_error "应用目录不存在: $app_dir"
        echo "解决方案:"
        echo "- 检查应用是否正确部署"
        echo "- 确认目录路径是否正确"
        wait_for_confirmation
        return
    fi

    log_success "应用目录存在: $app_dir"
    cd "$app_dir"

    # 检查关键文件
    log_info "2. 检查关键文件"
    local critical_files=("public/index.php" "src/Kernel.php" "composer.json" ".env")
    local missing_files=()

    for file in "${critical_files[@]}"; do
        if [[ -f "$file" ]]; then
            log_success "文件存在: $file"
        else
            log_error "文件缺失: $file"
            missing_files+=("$file")
        fi
    done

    if [[ ${#missing_files[@]} -gt 0 ]]; then
        echo ""
        echo "缺失文件解决方案:"
        for file in "${missing_files[@]}"; do
            case "$file" in
                "public/index.php")
                    echo "- 恢复 public/index.php 文件"
                    echo "- 检查Git历史并恢复文件"
                    ;;
                "src/Kernel.php")
                    echo "- 恢复 src/Kernel.php 文件"
                    echo "- 重新部署应用代码"
                    ;;
                "composer.json")
                    echo "- 恢复 composer.json 文件"
                    echo "- 从版本控制系统恢复"
                    ;;
                ".env")
                    echo "- 创建 .env 文件"
                    echo "- 从 .env.example 复制并配置"
                    ;;
            esac
        done
        wait_for_confirmation
        return
    fi

    # 检查Composer依赖
    log_info "3. 检查Composer依赖"
    if [[ -d "vendor" ]]; then
        local vendor_count=$(ls vendor 2>/dev/null | wc -l)
        if [[ $vendor_count -gt 10 ]]; then
            log_success "Composer依赖存在 ($vendor_count 个包)"
        else
            log_warning "Composer依赖可能不完整"
            echo "解决方案:"
            echo "- 运行: composer install --no-dev --optimize-autoloader"
        fi
    else
        log_error "vendor目录不存在"
        echo "解决方案:"
        echo "- 运行: composer install --no-dev --optimize-autoloader"
    fi

    # 检查环境配置
    log_info "4. 检查环境配置"
    if [[ -f ".env" ]]; then
        log_success ".env文件存在"

        # 检查关键配置
        if grep -q "APP_ENV=prod" .env; then
            log_success "生产环境配置正确"
        else
            log_warning "环境配置可能有问题"
            echo "检查 .env 文件中的 APP_ENV 设置"
        fi

        if grep -q "APP_SECRET" .env; then
            log_success "APP_SECRET已配置"
        else
            log_error "APP_SECRET未配置"
            echo "解决方案: 运行 php bin/console generate:secret"
        fi
    fi

    # 检查文件权限
    log_info "5. 检查文件权限"
    local dirs=("var/cache" "var/log" "var/sessions")
    for dir in "${dirs[@]}"; do
        if [[ -d "$dir" ]]; then
            local perms=$(stat -c "%a" "$dir")
            if [[ "$perms" == "755" ]] || [[ "$perms" == "775" ]]; then
                log_success "目录权限正确: $dir ($perms)"
            else
                log_warning "目录权限异常: $dir ($perms)"
                echo "解决方案: chmod -R 755 $dir"
            fi
        else
            log_warning "目录不存在: $dir"
            echo "解决方案: mkdir -p $dir && chmod -R 755 $dir"
        fi
    done

    # 测试PHP语法
    log_info "6. 测试PHP语法"
    if php -l public/index.php &>/dev/null; then
        log_success "入口文件语法正确"
    else
        log_error "入口文件语法错误"
        php -l public/index.php
    fi

    if php -l src/Kernel.php &>/dev/null; then
        log_success "内核文件语法正确"
    else
        log_error "内核文件语法错误"
        php -l src/Kernel.php
    fi

    echo ""
    log_success "应用启动问题排查完成"
    wait_for_confirmation
    show_main_menu
}

# 数据库连接问题排查
troubleshoot_database() {
    log_step "开始排查数据库连接问题..."
    echo ""

    # 检查MySQL服务状态
    log_info "1. 检查MySQL服务状态"
    if systemctl is-active --quiet mysql; then
        log_success "MySQL服务运行正常"
    else
        log_error "MySQL服务未运行"
        echo "解决方案:"
        echo "- 运行: systemctl start mysql"
        echo "- 检查MySQL配置文件"
        wait_for_confirmation
        return
    fi

    # 检查MySQL端口
    log_info "2. 检查MySQL端口监听"
    if netstat -tuln | grep -q ":3306 "; then
        log_success "MySQL端口3306正在监听"
    else
        log_error "MySQL端口未监听"
        echo "解决方案:"
        echo "- 检查MySQL配置文件中的端口设置"
        echo "- 重启MySQL服务"
    fi

    # 检查数据库连接
    log_info "3. 测试数据库连接"
    if mysql -e "SELECT 1;" &>/dev/null; then
        log_success "数据库连接正常"
    else
        log_error "数据库连接失败"
        echo "可能原因:"
        echo "- 用户名或密码错误"
        echo "- 数据库服务未启动"
        echo "- 网络连接问题"
        echo ""
        echo "解决方案:"
        echo "- 检查 .env 文件中的数据库配置"
        echo "- 验证数据库用户权限"
        wait_for_confirmation
        return
    fi

    # 检查应用数据库配置
    log_info "4. 检查应用数据库配置"
    local app_dir="/www/wwwroot/official_website_backend"
    if [[ -f "$app_dir/.env" ]]; then
        local db_url=$(grep "^DATABASE_URL" "$app_dir/.env" | cut -d'=' -f2)
        if [[ -n "$db_url" ]]; then
            log_success "数据库URL已配置"
            echo "数据库URL: ${db_url:0:50}..."
        else
            log_error "数据库URL未配置"
            echo "解决方案: 在 .env 文件中配置 DATABASE_URL"
        fi
    fi

    # 检查数据库表
    log_info "5. 检查数据库表"
    local table_count=$(mysql -e "SHOW TABLES;" 2>/dev/null | wc -l)
    if [[ $table_count -gt 0 ]]; then
        log_success "数据库表存在 ($table_count 个表)"

        # 检查关键表
        local critical_tables=("users" "sys_news_articles")
        for table in "${critical_tables[@]}"; do
            if mysql -e "DESCRIBE $table;" &>/dev/null; then
                log_success "关键表存在: $table"
            else
                log_warning "关键表不存在: $table"
                echo "解决方案: 运行数据库迁移"
            fi
        done
    else
        log_error "数据库表不存在"
        echo "解决方案:"
        echo "- 运行: php bin/console doctrine:migrations:migrate"
    fi

    # 检查数据库权限
    log_info "6. 检查数据库权限"
    local current_user=$(mysql -e "SELECT USER();" 2>/dev/null | tail -1)
    echo "当前数据库用户: $current_user"

    # 测试数据库操作
    if mysql -e "CREATE TABLE IF NOT EXISTS test_table (id INT); DROP TABLE IF EXISTS test_table;" &>/dev/null; then
        log_success "数据库操作权限正常"
    else
        log_error "数据库操作权限不足"
        echo "解决方案:"
        echo "- 检查数据库用户权限"
        echo "- 联系数据库管理员"
    fi

    echo ""
    log_success "数据库连接问题排查完成"
    wait_for_confirmation
    show_main_menu
}

# API访问问题排查
troubleshoot_api() {
    log_step "开始排查API访问问题..."
    echo ""

    # 检查Web服务器状态
    log_info "1. 检查Web服务器状态"
    if systemctl is-active --quiet nginx; then
        log_success "Nginx服务运行正常"
    else
        log_error "Nginx服务未运行"
        echo "解决方案: systemctl start nginx"
        wait_for_confirmation
        return
    fi

    # 检查PHP-FPM状态
    log_info "2. 检查PHP-FPM状态"
    if systemctl is-active --quiet php8.2-fpm; then
        log_success "PHP-FPM服务运行正常"
    else
        log_error "PHP-FPM服务未运行"
        echo "解决方案: systemctl start php8.2-fpm"
        wait_for_confirmation
        return
    fi

    # 检查端口监听
    log_info "3. 检查端口监听"
    local ports=("80" "443")
    for port in "${ports[@]}"; do
        if netstat -tuln | grep -q ":$port "; then
            log_success "端口 $port 正在监听"
        else
            log_error "端口 $port 未监听"
            echo "检查Nginx配置中的端口设置"
        fi
    done

    # 测试HTTP响应
    log_info "4. 测试HTTP响应"
    local base_url="http://localhost"

    local response_code=$(curl -s -o /dev/null -w "%{http_code}" "$base_url" || echo "000")
    if [[ "$response_code" == "200" ]]; then
        log_success "HTTP响应正常 (200)"
    else
        log_error "HTTP响应异常 ($response_code)"
        echo "可能原因:"
        echo "- Nginx配置错误"
        echo "- PHP-FPM配置错误"
        echo "- 应用代码错误"
        echo ""
        echo "解决方案:"
        echo "- 检查Nginx错误日志: tail -f /var/log/nginx/error.log"
        echo "- 检查PHP-FPM日志: tail -f /var/log/php8.2-fpm.log"
    fi

    # 检查API路由
    log_info "5. 检查API路由"
    local api_endpoints=(
        "/api/health"
        "/api/articles"
        "/api/login"
    )

    for endpoint in "${api_endpoints[@]}"; do
        local api_response=$(curl -s "$base_url$endpoint" || echo "")
        local api_code=$(curl -s -o /dev/null -w "%{http_code}" "$base_url$endpoint" || echo "000")

        echo "测试 $endpoint: HTTP $api_code"
        if [[ "$api_code" == "200" ]] || [[ "$api_code" == "401" ]]; then
            log_success "API端点响应正常: $endpoint"
        else
            log_warning "API端点响应异常: $endpoint (HTTP $api_code)"
        fi
    done

    # 检查CORS配置
    log_info "6. 检查CORS配置"
    local cors_headers=$(curl -s -I "$base_url/api/health" | grep -i "access-control" || echo "")
    if [[ -n "$cors_headers" ]]; then
        log_success "CORS头已配置"
        echo "$cors_headers"
    else
        log_warning "CORS头未配置或配置不完整"
        echo "解决方案:"
        echo "- 检查 config/packages/nelmio_cors.yaml"
        echo "- 确认CORS配置正确"
    fi

    # 检查JWT配置
    log_info "7. 检查JWT配置"
    local app_dir="/www/wwwroot/official_website_backend"
    if [[ -f "$app_dir/config/jwt/public.pem" ]] && [[ -f "$app_dir/config/jwt/private.pem" ]]; then
        log_success "JWT密钥文件存在"

        # 检查密钥权限
        local public_perms=$(stat -c "%a" "$app_dir/config/jwt/public.pem")
        local private_perms=$(stat -c "%a" "$app_dir/config/jwt/private.pem")

        if [[ "$public_perms" == "644" ]] && [[ "$private_perms" == "600" ]]; then
            log_success "JWT密钥权限正确"
        else
            log_warning "JWT密钥权限可能不安全"
            echo "公钥权限: $public_perms, 私钥权限: $private_perms"
        fi
    else
        log_error "JWT密钥文件不存在"
        echo "解决方案: php bin/console jwt:generate-keypair"
    fi

    # 测试API认证
    log_info "8. 测试API认证"
    local login_response=$(curl -s -X POST \
        -H "Content-Type: application/json" \
        -d '{"username":"test","password":"test"}' \
        "$base_url/api/login" || echo "")

    if echo "$login_response" | jq -e '.token' &>/dev/null; then
        log_success "API认证功能正常"
    else
        log_warning "API认证可能有问题"
        echo "可能原因:"
        echo "- 用户不存在"
        echo "- 密码错误"
        echo "- JWT配置错误"
    fi

    echo ""
    log_success "API访问问题排查完成"
    wait_for_confirmation
    show_main_menu
}

# 性能问题排查
troubleshoot_performance() {
    log_step "开始排查性能问题..."
    echo ""

    # 检查系统资源使用
    log_info "1. 检查系统资源使用"

    # CPU使用率
    local cpu_usage=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | tr -d '%us')
    echo "CPU使用率: ${cpu_usage}%"
    if (( $(echo "$cpu_usage > 80" | bc -l) 2>/dev/null )); then
        log_warning "CPU使用率过高"
        echo "解决方案:"
        echo "- 检查CPU密集型进程: top"
        echo "- 优化应用代码"
        echo "- 增加服务器资源"
    else
        log_success "CPU使用率正常"
    fi

    # 内存使用率
    local mem_usage=$(free | grep Mem | awk '{printf "%.1f", $3/$2 * 100.0}')
    echo "内存使用率: ${mem_usage}%"
    if (( $(echo "$mem_usage > 85" | bc -l) )); then
        log_warning "内存使用率过高"
        echo "解决方案:"
        echo "- 检查内存泄漏: ps aux --sort=-%mem | head"
        echo "- 调整PHP memory_limit"
        echo "- 重启相关服务"
    else
        log_success "内存使用率正常"
    fi

    # 磁盘I/O
    local io_wait=$(iostat -c 1 2 | tail -2 | head -1 | awk '{print $4}' 2>/dev/null || echo "0")
    echo "I/O等待时间: ${io_wait}%"
    if (( $(echo "$io_wait > 20" | bc -l) 2>/dev/null )); then
        log_warning "I/O等待时间过长"
        echo "解决方案:"
        echo "- 检查磁盘性能: iostat -x 1"
        echo "- 优化数据库查询"
        echo "- 考虑使用SSD"
    else
        log_success "I/O等待时间正常"
    fi

    # 检查应用响应时间
    log_info "2. 检查应用响应时间"
    local base_url="http://localhost"
    local response_times=()

    for i in {1..5}; do
        local response_time=$(curl -s -o /dev/null -w "%{time_total}" "$base_url" || echo "10")
        response_times+=("$response_time")
        sleep 1
    done

    local avg_time=$(printf '%s\n' "${response_times[@]}" | awk '{sum+=$1} END {print sum/NR}')
    local max_time=$(printf '%s\n' "${response_times[@]}" | sort -nr | head -1)

    echo "平均响应时间: ${avg_time}s"
    echo "最大响应时间: ${max_time}s"

    if (( $(echo "$avg_time > 2.0" | bc -l) )); then
        log_warning "应用响应时间过长"
        echo "解决方案:"
        echo "- 启用OPcache"
        echo "- 优化数据库查询"
        echo "- 使用缓存"
        echo "- 优化前端资源"
    else
        log_success "应用响应时间正常"
    fi

    # 检查数据库性能
    log_info "3. 检查数据库性能"

    # 慢查询
    local slow_queries=$(mysql -e "SHOW GLOBAL STATUS LIKE 'Slow_queries';" 2>/dev/null | tail -1 | awk '{print $2}')
    echo "慢查询数量: $slow_queries"

    if [[ $slow_queries -gt 0 ]]; then
        log_warning "发现慢查询"
        echo "解决方案:"
        echo "- 启用慢查询日志"
        echo "- 分析慢查询: SHOW PROCESSLIST"
        echo "- 优化SQL语句"
        echo "- 添加适当索引"
    else
        log_success "无慢查询"
    fi

    # 连接数
    local connections=$(mysql -e "SHOW STATUS LIKE 'Threads_connected';" 2>/dev/null | tail -1 | awk '{print $2}')
    local max_connections=$(mysql -e "SHOW VARIABLES LIKE 'max_connections';" 2>/dev/null | tail -1 | awk '{print $2}')
    echo "当前连接数: $connections / $max_connections"

    if [[ $connections -gt $((max_connections * 80 / 100)) ]]; then
        log_warning "数据库连接数过高"
        echo "解决方案:"
        echo "- 增加max_connections"
        echo "- 优化应用连接池"
        echo "- 检查连接泄漏"
    fi

    # 检查PHP配置
    log_info "4. 检查PHP配置"

    local memory_limit=$(php -r "echo ini_get('memory_limit');")
    local max_execution_time=$(php -r "echo ini_get('max_execution_time');")
    local opcache_enabled=$(php -r "echo ini_get('opcache.enable');")

    echo "PHP内存限制: $memory_limit"
    echo "最大执行时间: ${max_execution_time}s"
    echo "OPcache启用: $opcache_enabled"

    if [[ "$opcache_enabled" != "1" ]]; then
        log_warning "OPcache未启用"
        echo "解决方案: 在php.ini中启用opcache"
    fi

    # 检查缓存使用
    log_info "5. 检查缓存使用"

    if command -v redis-cli &> /dev/null; then
        if redis-cli ping &>/dev/null; then
            local redis_memory=$(redis-cli info memory | grep used_memory_human | cut -d':' -f2 | tr -d '\r')
            local redis_keys=$(redis-cli dbsize 2>/dev/null || echo "0")

            echo "Redis内存使用: $redis_memory"
            echo "Redis键数量: $redis_keys"

            log_success "Redis缓存正常"
        else
            log_warning "Redis连接失败"
        fi
    else
        log_warning "Redis未安装"
    fi

    echo ""
    log_success "性能问题排查完成"
    wait_for_confirmation
    show_main_menu
}

# 安全相关问题排查
troubleshoot_security() {
    log_step "开始排查安全相关问题..."
    echo ""

    # 检查文件权限
    log_info "1. 检查文件权限"
    local app_dir="/www/wwwroot/official_website_backend"

    if [[ -d "$app_dir" ]]; then
        cd "$app_dir"

        # 检查敏感文件权限
        local sensitive_files=(".env" "config/packages/security.yaml" "config/jwt/private.pem")
        for file in "${sensitive_files[@]}"; do
            if [[ -f "$file" ]]; then
                local perms=$(stat -c "%a" "$file")
                if [[ "$file" == *"private.pem" ]] && [[ "$perms" != "600" ]]; then
                    log_warning "私钥权限不安全: $file ($perms)"
                    echo "解决方案: chmod 600 $file"
                elif [[ "$file" == ".env" ]] && [[ "$perms" != "600" ]] && [[ "$perms" != "640" ]]; then
                    log_warning "环境文件权限不安全: $file ($perms)"
                    echo "解决方案: chmod 600 $file"
                else
                    log_success "文件权限安全: $file ($perms)"
                fi
            fi
        done

        # 检查目录权限
        local dirs=("var/cache" "var/log")
        for dir in "${dirs[@]}"; do
            if [[ -d "$dir" ]]; then
                local perms=$(stat -c "%a" "$dir")
                if [[ "$perms" == "755" ]] || [[ "$perms" == "775" ]]; then
                    log_success "目录权限安全: $dir ($perms)"
                else
                    log_warning "目录权限可能不安全: $dir ($perms)"
                fi
            fi
        done
    fi

    # 检查SSL证书
    log_info "2. 检查SSL证书"
    local ssl_cert="/etc/nginx/ssl/cert.pem"

    if [[ -f "$ssl_cert" ]]; then
        local cert_info=$(openssl x509 -in "$ssl_cert" -noout -dates 2>/dev/null)
        if [[ -n "$cert_info" ]]; then
            echo "SSL证书信息:"
            echo "$cert_info"

            local expiry_date=$(echo "$cert_info" | grep "notAfter" | cut -d'=' -f2)
            local expiry_timestamp=$(date -d "$expiry_date" +%s)
            local current_timestamp=$(date +%s)
            local days_until_expiry=$(( (expiry_timestamp - current_timestamp) / 86400 ))

            if [[ $days_until_expiry -lt 30 ]]; then
                log_warning "SSL证书即将过期 ($days_until_expiry 天)"
                echo "解决方案: 更新SSL证书"
            else
                log_success "SSL证书有效 ($days_until_expiry 天后过期)"
            fi
        else
            log_error "SSL证书格式错误"
        fi
    else
        log_warning "SSL证书不存在"
        echo "解决方案: 配置SSL证书"
    fi

    # 检查防火墙状态
    log_info "3. 检查防火墙状态"
    if command -v ufw &> /dev/null; then
        local ufw_status=$(ufw status | head -1)
        echo "防火墙状态: $ufw_status"

        if echo "$ufw_status" | grep -q "active"; then
            log_success "防火墙已启用"

            # 检查开放端口
            echo "开放端口:"
            ufw status | grep "ALLOW" | head -10
        else
            log_warning "防火墙未启用"
            echo "解决方案: ufw enable"
        fi
    else
        log_warning "UFW未安装"
    fi

    # 检查SSH安全配置
    log_info "4. 检查SSH安全配置"
    local ssh_config="/etc/ssh/sshd_config"

    if [[ -f "$ssh_config" ]]; then
        local root_login=$(grep "^PermitRootLogin" "$ssh_config" | awk '{print $2}')
        local password_auth=$(grep "^PasswordAuthentication" "$ssh_config" | awk '{print $2}')
        local port=$(grep "^Port" "$ssh_config" | awk '{print $2}')

        echo "SSH端口: ${port:-22}"
        echo "Root登录: $root_login"
        echo "密码认证: $password_auth"

        if [[ "$root_login" == "yes" ]]; then
            log_warning "SSH root登录未禁用"
            echo "解决方案: 在 $ssh_config 中设置 PermitRootLogin no"
        else
            log_success "SSH root登录已禁用"
        fi

        if [[ "$password_auth" == "yes" ]]; then
            log_warning "SSH密码认证未禁用"
            echo "建议: 使用密钥认证，禁用密码认证"
        fi
    fi

    # 检查最近登录
    log_info "5. 检查最近登录记录"
    echo "最近登录记录:"
    last -n 5 | head -5

    # 检查异常进程
    log_info "6. 检查异常进程"
    echo "可疑进程检查:"
    ps aux | grep -E "(sh|bash|python|perl|nc|ncat)" | grep -v grep | head -5

    # 检查网络连接
    log_info "7. 检查网络连接"
    echo "活跃网络连接:"
    netstat -tuln | head -10

    # 检查系统更新
    log_info "8. 检查系统安全更新"
    apt update &>/dev/null
    local security_updates=$(apt list --upgradable 2>/dev/null | grep -i "security" | wc -l)

    if [[ $security_updates -gt 0 ]]; then
        log_warning "发现 $security_updates 个安全更新"
        echo "解决方案: apt upgrade"
    else
        log_success "系统安全更新为最新"
    fi

    echo ""
    log_success "安全问题排查完成"
    wait_for_confirmation
    show_main_menu
}

# 网络连接问题排查
troubleshoot_network() {
    log_step "开始排查网络连接问题..."
    echo ""

    # 检查网络接口
    log_info "1. 检查网络接口"
    ip addr show | grep -E "^[0-9]+:" | while read line; do
        local interface=$(echo $line | cut -d':' -f2 | xargs)
        local status=$(ip link show "$interface" | grep -o "state [A-Z]*" | cut -d' ' -f2)
        echo "接口 $interface: $status"

        if [[ "$status" == "UP" ]]; then
            local ip_addr=$(ip addr show "$interface" | grep "inet " | head -1 | awk '{print $2}')
            echo "  IP地址: $ip_addr"
        fi
    done

    # 检查外网连接
    log_info "2. 检查外网连接"
    local test_hosts=("8.8.8.8" "1.1.1.1" "baidu.com")

    for host in "${test_hosts[@]}"; do
        if ping -c 1 -W 3 "$host" &>/dev/null; then
            log_success "外网连接正常: $host"
        else
            log_warning "外网连接失败: $host"
        fi
    done

    # 检查DNS解析
    log_info "3. 检查DNS解析"
    local test_domains=("www.baidu.com" "www.google.com")

    for domain in "${test_domains[@]}"; do
        if nslookup "$domain" &>/dev/null; then
            log_success "DNS解析正常: $domain"
        else
            log_warning "DNS解析失败: $domain"
        fi
    done

    # 检查端口监听
    log_info "4. 检查端口监听"
    local expected_ports=("22" "80" "443" "3306" "6379")

    for port in "${expected_ports[@]}"; do
        if netstat -tuln | grep -q ":$port "; then
            log_success "端口 $port 正在监听"
        else
            log_warning "端口 $port 未监听"
        fi
    done

    # 检查防火墙规则
    log_info "5. 检查防火墙规则"
    if command -v iptables &> /dev/null; then
        echo "当前iptables规则:"
        iptables -L -n | head -10
    fi

    # 检查路由表
    log_info "6. 检查路由表"
    echo "默认路由:"
    ip route | grep default

    echo ""
    log_success "网络连接问题排查完成"
    wait_for_confirmation
    show_main_menu
}

# 服务状态问题排查
troubleshoot_services() {
    log_step "开始排查服务状态问题..."
    echo ""

    local services=("nginx" "php8.2-fpm" "mysql" "redis-server")
    local failed_services=()

    for service in "${services[@]}"; do
        log_info "检查服务: $service"

        # 检查服务状态
        if systemctl is-active --quiet "$service"; then
            log_success "$service 运行正常"

            # 检查服务资源使用
            local memory_usage=$(ps aux | grep "$service" | grep -v grep | awk '{sum+=$6} END {print sum/1024}')
            echo "  内存使用: ${memory_usage}MB"

            # 检查服务启动时间
            local start_time=$(systemctl show "$service" --property=ActiveEnterTimestamp | cut -d'=' -f2)
            echo "  启动时间: $start_time"

        else
            log_error "$service 未运行"
            failed_services+=("$service")

            # 检查服务错误信息
            echo "  错误信息:"
            systemctl status "$service" --no-pager -l | tail -5
        fi
        echo ""
    done

    if [[ ${#failed_services[@]} -gt 0 ]]; then
        log_info "失败服务处理建议:"
        for service in "${failed_services[@]}"; do
            echo "- 启动服务: systemctl start $service"
            echo "- 查看日志: journalctl -u $service -f"
            echo "- 检查配置: systemctl status $service"
            echo ""
        done
    fi

    # 检查系统资源限制
    log_info "检查系统资源限制"
    echo "文件描述符限制: $(ulimit -n)"
    echo "进程限制: $(ulimit -u)"
    echo "内存限制: $(ulimit -v)"

    echo ""
    log_success "服务状态问题排查完成"
    wait_for_confirmation
    show_main_menu
}

# 日志分析
troubleshoot_logs() {
    log_step "开始分析日志..."
    echo ""

    # 应用日志
    log_info "1. 分析应用日志"
    local app_log="/www/wwwroot/official_website_backend/var/log/prod.log"

    if [[ -f "$app_log" ]]; then
        echo "应用日志统计:"
        echo "  文件大小: $(du -sh "$app_log" | cut -f1)"
        echo "  总行数: $(wc -l < "$app_log")"

        echo ""
        echo "最近错误日志:"
        tail -20 "$app_log" | grep -i "error\|critical" | tail -5

        echo ""
        echo "最近警告日志:"
        tail -20 "$app_log" | grep -i "warning" | tail -5

    else
        log_warning "应用日志文件不存在: $app_log"
    fi

    # Nginx日志
    log_info "2. 分析Nginx日志"
    local nginx_access_log="/var/log/nginx/access.log"
    local nginx_error_log="/var/log/nginx/error.log"

    if [[ -f "$nginx_error_log" ]]; then
        echo ""
        echo "Nginx错误日志 (最近10条):"
        tail -10 "$nginx_error_log"
    fi

    if [[ -f "$nginx_access_log" ]]; then
        echo ""
        echo "Nginx访问统计:"
        echo "  总访问量: $(wc -l < "$nginx_access_log")"
        echo "  4xx错误: $(grep " 4[0-9][0-9] " "$nginx_access_log" | wc -l)"
        echo "  5xx错误: $(grep " 5[0-9][0-9] " "$nginx_access_log" | wc -l)"

        echo ""
        echo "热门IP (前5):"
        awk '{print $1}' "$nginx_access_log" | sort | uniq -c | sort -nr | head -5
    fi

    # 系统日志
    log_info "3. 分析系统日志"
    echo ""
    echo "系统日志 (最近10条):"
    tail -10 /var/log/syslog | grep -E "(error|Error|ERROR)" | tail -5

    # 数据库日志
    log_info "4. 分析数据库日志"
    local mysql_log="/var/log/mysql/error.log"

    if [[ -f "$mysql_log" ]]; then
        echo ""
        echo "MySQL错误日志 (最近10条):"
        tail -10 "$mysql_log"
    fi

    echo ""
    log_success "日志分析完成"
    wait_for_confirmation
    show_main_menu
}

# 系统资源问题排查
troubleshoot_resources() {
    log_step "开始排查系统资源问题..."
    echo ""

    # CPU使用分析
    log_info "1. CPU使用分析"
    echo "CPU信息:"
    lscpu | grep -E "(Model name|CPU\(s\)|Thread)"

    echo ""
    echo "CPU使用率 (前5进程):"
    top -bn1 | head -17 | tail -5

    # 内存使用分析
    log_info "2. 内存使用分析"
    echo "内存使用情况:"
    free -h

    echo ""
    echo "内存使用 (前10进程):"
    ps aux --sort=-%mem | head -11

    # 磁盘使用分析
    log_info "3. 磁盘使用分析"
    echo "磁盘使用情况:"
    df -h

    echo ""
    echo "磁盘I/O统计:"
    iostat -x 1 1 | head -10

    # 网络使用分析
    log_info "4. 网络使用分析"
    echo "网络连接统计:"
    netstat -an | awk '/^tcp/ {print $6}' | sort | uniq -c | sort -nr

    echo ""
    echo "活跃网络连接:"
    netstat -tuln | head -10

    # 进程分析
    log_info "5. 进程分析"
    echo "总进程数: $(ps aux | wc -l)"
    echo "运行中进程: $(ps aux | awk '$8 ~ /R/ {count++} END {print count+0}')"

    echo ""
    echo "僵尸进程:"
    ps aux | awk '$8 ~ /Z/ {print $2, $11}' | head -5

    echo ""
    log_success "系统资源问题排查完成"
    wait_for_confirmation
    show_main_menu
}

# 综合系统检查
comprehensive_check() {
    log_step "开始综合系统检查..."
    echo ""

    local issues_found=0

    # 系统基础检查
    log_info "1. 系统基础检查"
    echo "操作系统: $(cat /etc/os-release | grep PRETTY_NAME | cut -d'"' -f2)"
    echo "内核版本: $(uname -r)"
    echo "系统负载: $(uptime | awk -F'load average:' '{print $2}')"
    echo "运行时间: $(uptime -p)"
    echo ""

    # 服务状态检查
    log_info "2. 服务状态检查"
    local services=("nginx" "php8.2-fpm" "mysql" "redis-server")
    for service in "${services[@]}"; do
        if systemctl is-active --quiet "$service"; then
            echo "✅ $service: 运行中"
        else
            echo "❌ $service: 未运行"
            ((issues_found++))
        fi
    done
    echo ""

    # 网络连接检查
    log_info "3. 网络连接检查"
    if ping -c 1 8.8.8.8 &>/dev/null; then
        echo "✅ 外网连接正常"
    else
        echo "❌ 外网连接失败"
        ((issues_found++))
    fi

    local ports=("80" "443")
    for port in "${ports[@]}"; do
        if netstat -tuln | grep -q ":$port "; then
            echo "✅ 端口 $port: 监听中"
        else
            echo "❌ 端口 $port: 未监听"
            ((issues_found++))
        fi
    done
    echo ""

    # 资源使用检查
    log_info "4. 资源使用检查"
    local mem_usage=$(free | grep Mem | awk '{printf "%.1f", $3/$2 * 100.0}')
    local disk_usage=$(df / | tail -1 | awk '{print $5}' | tr -d '%')

    echo "内存使用率: ${mem_usage}%"
    echo "磁盘使用率: ${disk_usage}%"

    if (( $(echo "$mem_usage > 80" | bc -l) )); then
        echo "⚠️  内存使用率过高"
        ((issues_found++))
    fi

    if [[ $disk_usage -gt 80 ]]; then
        echo "⚠️  磁盘使用率过高"
        ((issues_found++))
    fi
    echo ""

    # 应用状态检查
    log_info "5. 应用状态检查"
    local response_code=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost" || echo "000")

    if [[ "$response_code" == "200" ]]; then
        echo "✅ 应用响应正常"
    else
        echo "❌ 应用响应异常 (HTTP $response_code)"
        ((issues_found++))
    fi
    echo ""

    # 安全检查
    log_info "6. 安全检查"
    local app_dir="/www/wwwroot/official_website_backend"

    if [[ -f "$app_dir/.env" ]]; then
        local env_perms=$(stat -c "%a" "$app_dir/.env")
        if [[ "$env_perms" == "600" ]] || [[ "$env_perms" == "640" ]]; then
            echo "✅ 环境文件权限安全"
        else
            echo "⚠️  环境文件权限不安全"
            ((issues_found++))
        fi
    fi
    echo ""

    # 总结
    echo "========================================"
    if [[ $issues_found -eq 0 ]]; then
        log_success "综合检查通过！系统运行正常。"
    else
        log_warning "发现 $issues_found 个问题，请及时处理。"
    fi
    echo "========================================"

    wait_for_confirmation
    show_main_menu
}

# 主函数入口
main() {
    # 检查是否为root用户（部分功能需要）
    if [[ $EUID -ne 0 ]]; then
        log_warning "建议以root权限运行此脚本以获得完整功能"
        echo ""
        wait_for_confirmation
    fi

    show_main_menu
}

# 启动脚本
main "$@"
