# 微信公众号表错误诊断与修复报告

## 🚨 错误描述

**错误信息**: `SQLSTATE[42S02]: Base table or view not found: 1146 Table 'official_website.wechat_public_account' doesn't exist`

**错误类型**: 数据库表不存在错误

## 🔍 诊断过程

### 1. 错误上下文分析

-   **触发操作**: 访问微信公众号相关的 API 端点
-   **涉及组件**:
    -   Entity: `App\Entity\WechatPublicAccount`
    -   Controller: `App\Controller\WechatPublicAccountController`
    -   Repository: `App\Repository\WechatPublicAccountRepository`
-   **表名定义**: Entity 中定义了 `#[ORM\Table(name: 'wechat_public_account')]`

### 2. 数据库结构检查

通过诊断脚本发现：

-   ✅ 数据库连接正常
-   ✅ 数据库 `official_website` 存在
-   ❌ 表 `wechat_public_account` 不存在
-   📊 现有表: `article_read_logs`, `article_read_statistics`, `sys_news_article`, `sys_news_article_category`

### 3. 代码依赖分析

-   ✅ Entity 文件存在且配置正确
-   ✅ Controller 文件存在且路由配置正确
-   ✅ Repository 文件存在且继承正确
-   ✅ SQL 创建脚本存在 (`create_table.sql`)

### 4. 根本原因确定

**主要原因**: 数据库迁移未执行，表创建 SQL 脚本存在但未在数据库中执行

## 🔧 修复过程

### 第一步: 创建表结构

执行 SQL 脚本创建表：

```sql
CREATE TABLE IF NOT EXISTS wechat_public_account (
    id VARCHAR(100) NOT NULL PRIMARY KEY,
    name VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    avatarUrl VARCHAR(500) DEFAULT NULL,
    appId VARCHAR(128) DEFAULT NULL UNIQUE,
    appSecret VARCHAR(128) DEFAULT NULL UNIQUE,
    createdAt DATETIME NOT NULL,
    updatedAt DATETIME NOT NULL,
    isActive TINYINT(1) NOT NULL DEFAULT 1,
    token VARCHAR(32) DEFAULT NULL,
    encodingAESKey VARCHAR(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 第二步: 修复字段名不匹配

发现 Entity 定义与数据库字段名不一致，进行修复：

| Entity 字段名   | 原数据库字段名 | 修复后数据库字段名 |
| --------------- | -------------- | ------------------ |
| avatar_url      | avatarUrl      | avatar_url ✅      |
| app_id          | appId          | app_id ✅          |
| app_secret      | appSecret      | app_secret ✅      |
| created_at      | createdAt      | created_at ✅      |
| updated_at      | updatedAt      | updated_at ✅      |
| is_active       | isActive       | is_active ✅       |
| encoding_aeskey | encodingAESKey | encoding_aeskey ✅ |

### 第三步: 验证修复结果

-   ✅ 表创建成功
-   ✅ 字段名匹配正确
-   ✅ Entity 连接测试成功
-   ✅ Repository 查询测试成功
-   ✅ 测试数据创建成功
-   ✅ API 路由存在且可访问

## 📊 最终表结构

```sql
mysql> DESCRIBE wechat_public_account;
+-----------------+--------------+------+-----+---------+-------+
| Field           | Type         | Null | Key | Default | Extra |
+-----------------+--------------+------+-----+---------+-------+
| id              | varchar(100) | NO   | PRI | NULL    |       |
| name            | varchar(255) | YES  |     | NULL    |       |
| description     | text         | YES  |     | NULL    |       |
| avatar_url      | varchar(500) | YES  |     | NULL    |       |
| app_id          | varchar(128) | YES  | UNI | NULL    |       |
| app_secret      | varchar(128) | YES  | UNI | NULL    |       |
| created_at      | datetime     | NO   |     | NULL    |       |
| updated_at      | datetime     | NO   |     | NULL    |       |
| is_active       | tinyint(1)   | NO   |     | 1       |       |
| token           | varchar(32)  | YES  |     | NULL    |       |
| encoding_aeskey | varchar(128) | YES  |     | NULL    |       |
+-----------------+--------------+------+-----+---------+-------+
```

## 🎯 修复验证结果

### ✅ 成功项目

1. **数据库表创建**: 表 `wechat_public_account` 已成功创建
2. **字段名匹配**: 所有字段名与 Entity 定义一致
3. **Entity 连接**: Doctrine Entity Manager 正常加载
4. **Repository 功能**: 数据查询操作正常
5. **数据插入**: 测试数据创建成功
6. **API 路由**: Controller 路由配置正确

### 🌐 可用 API 端点

-   `GET /official-api/wechatpublicaccount` - 获取公众号列表
-   `GET /official-api/wechatpublicaccount/{id}` - 获取单个公众号详情
-   `POST /official-api/wechatpublicaccount` - 创建新公众号
-   `PUT /official-api/wechatpublicaccount/{id}` - 全量更新公众号
-   `PATCH /official-api/wechatpublicaccount/{id}` - 部分更新公众号
-   `DELETE /official-api/wechatpublicaccount/{id}` - 删除公众号

## 📝 预防措施

### 1. 数据库迁移管理

-   确保所有数据库变更都通过 Doctrine Migration 执行
-   在部署前运行 `php bin/console doctrine:migrations:migrate`
-   定期检查数据库结构与 Entity 定义的一致性

### 2. 命名规范统一

-   建议统一数据库字段命名规范（建议使用下划线命名）
-   在 Entity 中使用 `#[ORM\Column(name: 'field_name')]` 明确指定字段名
-   定期运行 `php bin/console doctrine:schema:validate` 检查一致性

### 3. 部署检查清单

-   [ ] 数据库迁移已执行
-   [ ] 表结构验证通过
-   [ ] Entity 与数据库字段映射正确
-   [ ] 相关 API 端点测试通过

## 🎉 修复完成

**状态**: ✅ 已完全修复

**修复时间**: 2025-12-04

**影响范围**: 微信公众号管理功能

**验证状态**: 所有核心功能测试通过

---

## 📋 相关文件

### 诊断脚本

-   `public/debug_wechat_table.php` - 数据库表诊断脚本
-   `public/fix_wechat_table.php` - 表修复脚本
-   `public/test_wechat_api_fix.php` - API 功能验证脚本

### 核心文件

-   `src/Entity/WechatPublicAccount.php` - Entity 定义
-   `src/Controller/WechatPublicAccountController.php` - API 控制器
-   `src/Repository/WechatPublicAccountRepository.php` - 数据访问层
-   `create_table.sql` - 表创建 SQL 脚本

**备注**: 此修复解决了原始的 `SQLSTATE[42S02]` 错误，微信公众号功能现已恢复正常。
