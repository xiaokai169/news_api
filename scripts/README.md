# 运维脚本集合

本目录包含了用于Symfony 7.3 + PHP 8.2+ RESTful API项目的运维脚本。

## 脚本列表

### 1. 系统诊断脚本 (`system_diagnosis.sh`)

**功能**: 对系统进行全面诊断，包括硬件、服务、网络、数据库、应用、安全和性能检查。

**用法**:
```bash
sudo ./system_diagnosis.sh
```

**检查项目**:
- 系统信息和硬件资源
- 网络连接状态
- 服务运行状态
- 数据库连接
- 应用健康状态
- 安全配置
- 性能指标

**输出**: 生成详细的诊断报告文件到 `/tmp/system_diagnosis_YYYYMMDD_HHMMSS.txt`

---

### 2. 健康监控脚本 (`health_monitor.sh`)

**功能**: 作为守护进程持续监控系统健康状态，并发送告警。

**用法**:
```bash
# 启动守护进程
sudo ./health_monitor.sh start

# 停止守护进程
sudo ./health_monitor.sh stop

# 检查状态
sudo ./health_monitor.sh status

# 执行一次检查
sudo ./health_monitor.sh check

# 显示帮助
./health_monitor.sh help
```

**监控项目**:
- 系统负载
- 内存使用率
- 磁盘空间
- 服务状态
- 网络连接
- 数据库连接
- 应用响应
- 日志错误
- SSL证书有效期

**告警方式**:
- 邮件告警
- Slack通知
- 日志记录

---

### 3. 故障排除助手 (`troubleshooting_helper.sh`)

**功能**: 交互式故障排除工具，提供问题诊断和解决方案。

**用法**:
```bash
./troubleshooting_helper.sh
```

**故障类型**:
1. 应用启动问题
2. 数据库连接问题
3. API访问问题
4. 性能问题
5. 安全相关问题
6. 网络连接问题
7. 服务状态问题
8. 日志分析
9. 系统资源问题
10. 综合系统检查

**特点**:
- 交互式菜单界面
- 详细的诊断步骤
- 具体的解决方案
- 实时状态检查

---

## 使用前准备

### 1. 权限设置
确保脚本具有执行权限：
```bash
chmod +x scripts/*.sh
```

### 2. 依赖安装
部分脚本需要以下工具：
```bash
# 基础工具
sudo apt update
sudo apt install -y bc netstat curl jq iotop

# 系统监控工具
sudo apt install -y htop iotop sysstat

# 网络工具
sudo apt install -y nmap telnet nc

# 邮件发送工具
sudo apt install -y mailutils
```

### 3. 配置调整

#### 健康监控脚本配置
编辑 `health_monitor.sh` 中的配置变量：
```bash
MONITOR_INTERVAL=60          # 监控间隔（秒）
LOG_FILE="/var/log/health_monitor.log"
ALERT_EMAIL="ops@company.com"
WEBHOOK_URL="https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK"
```

#### 系统诊断脚本配置
脚本会自动检测系统环境，无需特殊配置。

#### 故障排除助手配置
脚本会自动适配当前环境，建议以root权限运行以获得完整功能。

---

## 使用场景

### 1. 日常监控
```bash
# 启动健康监控守护进程
sudo ./health_monitor.sh start

# 检查监控状态
./health_monitor.sh status
```

### 2. 故障排查
```bash
# 交互式故障排除
./troubleshooting_helper.sh

# 或执行全面诊断
sudo ./system_diagnosis.sh
```

### 3. 发布前检查
```bash
# 执行系统诊断
sudo ./system_diagnosis.sh

# 运行综合检查
./troubleshooting_helper.sh
# 选择选项 10: 综合系统检查
```

### 4. 定期维护
```bash
# 每周执行一次全面诊断
sudo ./system_diagnosis.sh

# 检查监控日志
tail -f /var/log/health_monitor.log
```

---

## 日志文件

### 健康监控日志
- 位置: `/var/log/health_monitor.log`
- 内容: 监控状态、告警信息、错误记录
- 轮转: 建议配置logrotate

### 系统诊断报告
- 位置: `/tmp/system_diagnosis_YYYYMMDD_HHMMSS.txt`
- 内容: 系统状态快照、性能指标、问题清单
- 保存: 建议定期归档

---

## 告警配置

### 邮件告警
配置邮件发送：
```bash
# 安装邮件工具
sudo apt install mailutils

# 配置邮件系统
sudo dpkg-reconfigure postfix
```

### Slack告警
在 `health_monitor.sh` 中配置Webhook URL：
```bash
WEBHOOK_URL="https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK"
```

---

## 性能优化建议

### 1. 脚本优化
- 定期清理日志文件
- 监控脚本本身的资源使用
- 根据需要调整监控间隔

### 2. 系统优化
- 定期执行系统诊断
- 监控系统资源趋势
- 及时处理告警信息

---

## 故障处理

### 脚本无法执行
1. 检查执行权限: `ls -la scripts/`
2. 检查脚本语法: `bash -n scripts/script_name.sh`
3. 检查依赖工具: `which bc jq curl`

### 权限不足
1. 使用sudo执行: `sudo ./script_name.sh`
2. 检查用户权限: `id`
3. 配置sudo免密（谨慎操作）

### 服务异常
1. 查看服务状态: `systemctl status service_name`
2. 查看服务日志: `journalctl -u service_name -f`
3. 重启服务: `sudo systemctl restart service_name`

---

## 更新维护

### 脚本更新
- 定期检查脚本版本
- 根据系统变化更新脚本
- 测试新版本脚本功能

### 配置更新
- 根据业务需求调整监控阈值
- 更新告警联系方式
- 优化监控参数

---

## 联系支持

如有问题或建议，请联系：
- 运维团队: ops@company.com
- 开发团队: dev@company.com
- 紧急联系: 138-xxxx-xxxx

---

**注意**: 
- 生产环境使用前请在测试环境验证
- 定期备份脚本和配置文件
- 遵循公司安全规范使用脚本
