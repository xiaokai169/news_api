# 生产环境 "symfony-cmd: command not found" 错误修复文档

## 问题概述

在生产环境中执行 `composer install` 时出现 "symfony-cmd: command not found" 错误，错误码 127。这个问题的核心原因是：

1. `symfony-cmd` 不是系统命令，而是 Symfony Flex 的内部脚本类型标识符
2. 在某些环境中（特别是 root 用户或受限环境），Symfony Flex 无法正确解析这种脚本类型
3. PHP 执行过程中可能遇到权限或环境配置问题

## 修复方案

### 1. Composer 配置优化

**问题**: `composer.json` 中的 `auto-scripts` 使用了 `symfony-cmd` 类型

```json
"auto-scripts": {
    "cache:clear": "symfony-cmd",
    "assets:install %PUBLIC_DIR%": "symfony-cmd"
}
```

**修复**: 改为更稳定的 `php-script` 类型

```json
"auto-scripts": {
    "cache:clear": "php-script",
    "assets:install %PUBLIC_DIR%": "php-script"
}
```

**优势**:

-   `php-script` 是 Composer 原生支持的脚本类型
-   不依赖 Symfony Flex 的特殊解析
-   在各种环境中都能稳定工作

### 2. 新增生产环境脚本

在 `composer.json` 中添加了专门的生产环境脚本：

```json
"production-install": [
    "composer install --no-dev --optimize-autoloader --no-progress --no-interaction",
    "@auto-scripts"
],
"production-cache-clear": [
    "php bin/console cache:clear --no-warmup --env=prod",
    "php bin/console cache:warmup --env=prod"
],
"check-environment": [
    "php -v",
    "php bin/console about --env=prod"
]
```

### 3. 生产环境部署脚本

创建了 `scripts/production_deploy.sh` 脚本，包含以下功能：

#### 主要特性

-   **环境检查**: 自动检测 PHP 版本、扩展、Composer 配置
-   **权限处理**: 自动处理 root 用户权限问题
-   **备份机制**: 自动备份现有的 vendor 和缓存目录
-   **错误处理**: 完整的错误捕获和日志记录
-   **生产优化**: 使用生产环境优化参数安装依赖

#### 使用方法

```bash
# 基本部署
./scripts/production_deploy.sh

# 或者直接使用 Composer 命令
composer run production-install
```

### 4. 环境检查脚本

创建了 `scripts/environment_check.sh` 脚本，用于诊断环境问题：

#### 检查项目

-   PHP 版本和必需扩展
-   Composer 安装和配置
-   项目文件结构和权限
-   bin/console 文件状态
-   环境变量配置

#### 使用方法

```bash
./scripts/environment_check.sh
```

## 详细修复步骤

### 步骤 1: 检查 bin/console 文件

**验证结果**: ✅ 文件存在且有正确权限

-   文件路径: `bin/console`
-   权限: `-rwxr-xr-x` (755)
-   测试结果: `Symfony 7.3.7 (env: dev, debug: true)`

### 步骤 2: 优化 composer.json 配置

**已完成的修改**:

-   将 `symfony-cmd` 改为 `php-script`
-   添加生产环境专用脚本
-   添加环境检查脚本

### 步骤 3: 创建生产环境部署脚本

**脚本功能**:

-   自动检测和设置 root 用户环境
-   完整的 PHP 环境验证
-   安全的备份机制
-   生产环境优化安装
-   自动权限设置

### 步骤 4: 创建环境检查脚本

**诊断能力**:

-   PHP 版本和扩展检查
-   Composer 配置验证
-   项目结构完整性检查
-   bin/console 功能测试
-   自动修复建议生成

## 使用指南

### 快速修复（推荐）

```bash
# 1. 运行环境检查
./scripts/environment_check.sh

# 2. 执行生产环境部署
./scripts/production_deploy.sh
```

### 手动修复步骤

如果需要手动执行修复：

```bash
# 1. 设置 root 用户环境变量（如果需要）
export COMPOSER_ALLOW_SUPERUSER=1

# 2. 设置生产环境变量
export APP_ENV=prod
export APP_DEBUG=0

# 3. 执行生产环境安装
composer install --no-dev --optimize-autoloader --no-progress --no-interaction

# 4. 清理和预热缓存
php bin/console cache:clear --no-warmup --env=prod
php bin/console cache:warmup --env=prod

# 5. 设置文件权限
chmod -R 755 var
chown -R www-data:www-data var  # 如果是 root 用户
```

## 常见问题解决

### Q1: 仍然出现 "command not found" 错误

**解决方案**:

1. 检查是否使用了修复后的 composer.json
2. 运行环境检查脚本诊断具体问题
3. 确保使用 `php-script` 而不是 `symfony-cmd`

### Q2: 权限问题

**解决方案**:

1. 确保 bin/console 有执行权限: `chmod +x bin/console`
2. 如果是 root 用户，设置: `export COMPOSER_ALLOW_SUPERUSER=1`
3. 检查 var 目录权限: `chmod -R 755 var`

### Q3: 依赖安装失败

**解决方案**:

1. 检查 PHP 版本是否 >= 8.2
2. 验证必需的 PHP 扩展是否安装
3. 检查网络连接和 Composer 配置

### Q4: 缓存问题

**解决方案**:

1. 完全删除缓存: `rm -rf var/cache/*`
2. 使用生产环境命令清理缓存
3. 确保有足够的磁盘空间

## 验证修复结果

### 成功指标

1. ✅ `composer install` 无错误完成
2. ✅ `php bin/console --version` 正常显示版本
3. ✅ `php bin/console cache:clear --env=prod` 执行成功
4. ✅ 生产环境变量正确设置
5. ✅ 文件权限正确配置

### 测试命令

```bash
# 测试基本功能
php bin/console --version

# 测试缓存清理
php bin/console cache:clear --env=prod

# 测试路由
php bin/console router:debug --env=prod

# 测试应用状态
php bin/console about --env=prod
```

## 生产环境部署建议

### 部署前检查清单

-   [ ] PHP 版本 >= 8.2
-   [ ] 必需的 PHP 扩展已安装
-   [ ] Composer 已安装并配置
-   [ ] 环境变量已正确设置
-   [ ] 文件权限已配置
-   [ ] 数据库连接已测试

### 部署后验证

-   [ ] 应用可以正常启动
-   [ ] 缓存清理和预热成功
-   [ ] 数据库迁移完成（如需要）
-   [ ] API 端点响应正常
-   [ ] 日志记录正常

## 维护建议

### 定期维护

1. 定期运行环境检查脚本
2. 监控日志文件中的错误
3. 定期清理和重建缓存
4. 保持依赖包更新

### 监控要点

-   PHP 错误日志
-   应用日志
-   系统资源使用情况
-   网络连接状态

## 总结

通过以上修复方案，我们解决了生产环境中 "symfony-cmd: command not found" 错误：

1. **根本原因**: Symfony Flex 的 `symfony-cmd` 类型在生产环境中不稳定
2. **解决方案**: 改用 Composer 原生的 `php-script` 类型
3. **工具支持**: 提供了完整的部署和诊断脚本
4. **安全保障**: 包含备份、错误处理和验证机制

这些修复确保了生产环境的稳定性和可维护性，同时提供了完整的错误诊断和修复工具。
