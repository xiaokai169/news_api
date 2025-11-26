# Swagger UI 配置完成指南

## 🎉 配置已完成

您的 Symfony 项目现在已经成功配置了 Swagger UI！

## 📋 已完成的配置

### 1. 路由配置

-   ✅ 创建了 `config/routes/nelmio_api_doc.yaml`
-   ✅ 在 `config/routes.yaml` 中导入了 Swagger UI 路由
-   ✅ 配置了 `/api/doc` 作为 Swagger UI 访问路径

### 2. Bundle 配置

-   ✅ 优化了 `config/packages/nelmio_api_doc.yaml` 配置
-   ✅ 配置了 JWT Bearer Token 认证
-   ✅ 设置了 API 文档的标题、描述和版本信息
-   ✅ 配置了扫描路径，包含所有控制器和实体

### 3. 控制器文档

-   ✅ `NewsController` - 完整的新闻文章管理 API
-   ✅ `TestController` - 测试 API
-   ✅ `DocumentationController` - 系统状态和 API 信息

## 🚀 如何使用

### 启动开发服务器

```bash
# 方法1: 使用提供的启动脚本
php start_server.php

# 方法2: 手动启动
php -S localhost:8001 -t public public/index.php
```

### 访问 Swagger UI

1. **Swagger UI 界面**: http://localhost:8001/api/doc
2. **OpenAPI JSON**: http://localhost:8001/api/doc.json
3. **健康检查**: http://localhost:8001/api/health
4. **API 信息**: http://localhost:8001/api/info
5. **端点列表**: http://localhost:8001/api/endpoints

### 测试 API

-   **测试接口**: http://localhost:8001/api/test
-   **新闻列表**: http://localhost:8001/official-api/news
-   **创建新闻**: POST http://localhost:8001/official-api/news

## 🔐 JWT 认证配置

Swagger UI 已配置为支持 JWT Bearer Token 认证：

1. 在 Swagger UI 界面中，点击右上角的 "Authorize" 按钮
2. 在弹出框中输入您的 JWT Token
3. 格式：`Bearer your_jwt_token_here`
4. 点击 "Authorize" 完成认证

## 📝 API 文档功能

### 已配置的 API 端点

#### 系统状态

-   `GET /api/health` - 健康检查
-   `GET /api/info` - API 系统信息
-   `GET /api/endpoints` - 所有可用端点列表

#### 测试

-   `GET /api/test` - 简单测试接口

#### 新闻文章管理

-   `GET /official-api/news` - 获取新闻文章列表
-   `POST /official-api/news` - 创建新闻文章
-   `GET /official-api/news/{id}` - 获取单个新闻文章
-   `PUT /official-api/news/{id}` - 更新新闻文章
-   `DELETE /official-api/news/{id}` - 删除新闻文章
-   `PATCH /official-api/news/{id}/status` - 设置文章状态
-   `PATCH /official-api/news/{id}/restore` - 恢复已删除文章

### OpenAPI 注解示例

您的控制器已经包含了完整的 OpenAPI 注解，包括：

-   **请求参数** - 路径参数、查询参数、请求体
-   **响应格式** - 成功响应、错误响应
-   **认证要求** - JWT Bearer Token
-   **数据模型** - 实体类的自动文档生成
-   **标签分组** - 按功能模块分组

## 🛠️ 自定义配置

### 修改 API 信息

编辑 `config/packages/nelmio_api_doc.yaml`：

```yaml
documentation:
    info:
        title: 您的 API 标题
        description: 您的 API 描述
        version: 您的版本号
```

### 添加新的服务器地址

```yaml
servers:
    - url: http://localhost:8001
      description: 本地开发
    - url: https://api.yourdomain.com
      description: 生产环境
```

### 自定义扫描路径

```yaml
scan:
    paths:
        - "%kernel.project_dir%/src/Controller"
        - "%kernel.project_dir%/src/Entity"
        - "%kernel.project_dir%/src/Dto"
```

## 🔍 故障排除

### 常见问题

1. **Swagger UI 无法访问**

    - 确保开发服务器正在运行
    - 检查路由配置是否正确
    - 清除缓存：`php bin/console cache:clear`

2. **API 文档不显示**

    - 检查控制器是否有 OpenAPI 注解
    - 确认扫描路径包含您的控制器
    - 检查 PHP 错误日志

3. **JWT 认证问题**
    - 确保已正确配置 LexikJWTAuthenticationBundle
    - 检查 JWT Token 格式是否正确
    - 验证 Token 是否有效

### 调试工具

1. **测试脚本**：运行 `public/test_swagger.php` 检查配置
2. **路由调试**：访问 `/api/endpoints` 查看所有可用路由
3. **健康检查**：访问 `/api/health` 验证服务状态

## 📚 更多资源

-   [NelmioApiDocBundle 文档](https://github.com/nelmio/NelmioApiDocBundle)
-   [OpenAPI 规范](https://swagger.io/specification/)
-   [Symfony 最佳实践](https://symfony.com/doc/current/best_practices.html)

## 🎯 下一步

1. 为您的其他控制器添加 OpenAPI 注解
2. 配置生产环境的 Swagger UI
3. 设置 API 版本控制
4. 添加请求/响应验证
5. 集成自动化测试

---

**配置完成！现在您可以享受完整的 API 文档体验了！** 🎉
