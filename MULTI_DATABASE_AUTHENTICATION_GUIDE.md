# Symfony 多数据库用户认证配置指南

## 概述

本文档详细说明了如何在 Symfony 7.3 项目中配置多数据库用户认证系统。系统使用两个独立的数据库：

-   **默认数据库**：存储主业务数据
-   **用户数据库**：专门用于用户认证数据

## 配置完成情况

### ✅ 已完成配置

#### 1. Doctrine 多数据库配置 (`config/packages/doctrine.yaml`)

-   配置了 `default` 和 `user` 两个数据库连接
-   将默认实体管理器设置为 `user`，确保安全组件使用正确的数据库
-   为每个连接配置了独立的实体管理器

#### 2. 用户实体 (`src/Entity/User.php`)

-   实现了 `UserInterface` 和 `PasswordAuthenticatedUserInterface` 接口
-   包含完整的用户字段：`id`, `username`, `email`, `password`, `nickname`, `phone`, `roles`, `status`, `createdAt`, `updatedAt`
-   实现了所有必需的安全接口方法

#### 3. 用户仓库 (`src/Repository/UserRepository.php`)

-   实现了 `UserProviderInterface` 和 `PasswordUpgraderInterface` 接口
-   提供了完整的用户认证方法：`loadUserByIdentifier()`, `refreshUser()`, `supportsClass()`
-   支持通过用户名和邮箱进行用户查找

#### 4. 安全配置 (`config/packages/security.yaml`)

-   配置了密码哈希器，使用 `auto` 算法自动选择最佳哈希方案
-   定义了 `app_user_provider` 用户提供程序，指向用户实体并使用 `user` 实体管理器
-   配置了主防火墙，启用表单登录和注销功能
-   设置了访问控制规则，保护除登录页面外的所有路径

#### 5. 控制器 (`src/Controller/SecurityController.php`)

-   登录控制器：处理用户登录流程
-   仪表板控制器：显示用户信息和系统状态
-   注销控制器：处理用户注销

#### 6. 模板文件

-   `templates/security/login.html.twig`：登录页面模板
-   `templates/security/dashboard.html.twig`：用户仪表板模板

#### 7. 环境变量配置 (`.env`)

-   添加了 `USER_DATABASE_URL` 环境变量，指向用户数据库

#### 8. 数据库脚本 (`create_user_table.sql`)

-   提供了用户表的创建脚本
-   包含示例用户数据

## 使用说明

### 1. 数据库设置

1. 确保 `app` 数据库存在
2. 执行 `create_user_table.sql` 脚本创建用户表结构
3. 使用真实的密码哈希替换示例数据中的占位符密码

### 2. 生成真实密码哈希

```bash
# 使用 Symfony 命令生成密码哈希
bin/console security:hash-password

# 输入密码后，命令会输出哈希值
# 将哈希值更新到数据库中的 password 字段
```

### 3. 测试认证系统

1. 访问 `/login` 页面
2. 使用示例用户登录：
    - 用户名：`admin`
    - 密码：需要设置真实密码
3. 登录成功后将被重定向到 `/dashboard`
4. 测试访问控制：尝试访问其他受保护页面

## 关键配置说明

### 多数据库配置要点

```yaml
# doctrine.yaml 关键配置
doctrine:
    dbal:
        connections:
            default: # 主业务数据库
                url: "%env(resolve:DATABASE_URL)%"
            user: # 用户认证数据库
                url: "%env(resolve:USER_DATABASE_URL)%"

    orm:
        default_entity_manager: user # 重要：安全组件使用默认实体管理器
        entity_managers:
            default:
                connection: default
            user:
                connection: user
```

### 安全配置要点

```yaml
# security.yaml 关键配置
security:
    providers:
        app_user_provider:
            entity:
                class: App\Entity\User
                property: username
                manager_name: user # 指定使用用户数据库的实体管理器

    firewalls:
        main:
            provider: app_user_provider # 使用正确的用户提供程序
            form_login:
                login_path: app_login
                check_path: app_login
```

## 故障排除

### 常见问题

1. **用户认证失败**

    - 检查用户数据库连接是否正常
    - 确认用户表中存在对应的用户记录
    - 验证密码哈希是否正确

2. **数据库连接错误**

    - 检查 `.env` 文件中的数据库 URL 配置
    - 确认数据库服务器正在运行
    - 验证数据库用户权限

3. **访问控制问题**
    - 检查 `security.yaml` 中的访问控制规则
    - 确认用户角色配置正确

### 调试命令

```bash
# 检查数据库连接
bin/console doctrine:database:info --connection=user

# 检查用户表结构
bin/console doctrine:schema:validate --em=user

# 调试安全配置
bin/console debug:config security
```

## 安全注意事项

1. **密码安全**

    - 始终使用强密码哈希算法
    - 在生产环境中使用真实的、安全的密码
    - 定期更新密码策略

2. **数据库安全**

    - 为生产环境使用不同的数据库凭据
    - 定期备份用户数据
    - 实施适当的访问控制

3. **会话安全**
    - 配置安全的会话设置
    - 实施适当的 CSRF 保护
    - 设置合理的会话超时时间

## 扩展建议

1. **添加用户注册功能**
2. **实现密码重置流程**
3. **添加双因素认证**
4. **实现角色和权限管理**
5. **添加用户活动日志**

---

**配置完成时间**: 2025-11-24  
**Symfony 版本**: 7.3  
**PHP 版本**: 8.3
