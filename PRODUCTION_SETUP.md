# 生产环境配置指南

## 必需的环境变量配置

### 1. 创建 .env.prod 文件

在项目根目录创建 `.env.prod` 文件（不要提交到版本控制），包含以下配置：

```bash
###> symfony/framework-bundle ###
APP_ENV=prod
APP_DEBUG=false
APP_SECRET=你的生产环境密钥（使用下面的命令生成）
###< symfony/framework-bundle ###

CORS_ALLOW_ORIGIN=https://yourdomain.com

###> symfony/routing ###
DEFAULT_URI=https://yourdomain.com
###< symfony/routing ###

###> doctrine/doctrine-bundle ###
# MySQL 示例
DATABASE_URL="mysql://username:password@127.0.0.1:3306/database_name?serverVersion=8.0.32&charset=utf8mb4"

# PostgreSQL 示例
# DATABASE_URL="postgresql://username:password@127.0.0.1:5432/database_name?serverVersion=16&charset=utf8"
###< doctrine/doctrine-bundle ###
```

### 2. 生成 APP_SECRET

运行以下命令生成安全的 APP_SECRET：

```bash
php -r "echo bin2hex(random_bytes(32));"
```

将生成的密钥复制到 `.env.prod` 文件中的 `APP_SECRET` 变量。

### 3. 配置数据库连接

根据您使用的数据库类型，在 `.env.prod` 中配置 `DATABASE_URL`：
- MySQL: `mysql://用户名:密码@主机:端口/数据库名?serverVersion=版本号&charset=utf8mb4`
- PostgreSQL: `postgresql://用户名:密码@主机:端口/数据库名?serverVersion=版本号&charset=utf8`

### 4. 配置 CORS

在 `.env.prod` 中设置 `CORS_ALLOW_ORIGIN` 为您的实际前端域名，例如：
```
CORS_ALLOW_ORIGIN=https://yourdomain.com
```

如果需要允许多个域名，可以在 `config/packages/nelmio_cors.yaml` 中配置数组。

## 部署步骤

1. **安装依赖**（生产环境）
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

2. **配置环境变量**
   - 创建 `.env.prod` 文件
   - 配置所有必需的环境变量

3. **运行数据库迁移**
   ```bash
   php bin/console doctrine:migrations:migrate --no-interaction
   ```

4. **清除缓存**
   ```bash
   php bin/console cache:clear --env=prod --no-debug
   ```

5. **设置权限**
   ```bash
   chmod -R 755 var/
   chmod -R 755 public/
   ```

6. **优化自动加载器**
   ```bash
   composer dump-autoload --optimize --no-dev
   ```

## 必需的 PHP 扩展

确保以下 PHP 扩展已安装并启用：
- ✅ ext-ctype
- ✅ ext-iconv
- ✅ ext-pdo
- ✅ ext-pdo_mysql (如果使用 MySQL)
- ✅ ext-pdo_pgsql (如果使用 PostgreSQL)
- ✅ ext-json
- ✅ ext-mbstring
- ✅ ext-xml
- ✅ ext-tokenizer

## 生产环境检查清单

- [ ] APP_SECRET 已设置并安全
- [ ] DATABASE_URL 已配置
- [ ] APP_DEBUG=false
- [ ] APP_ENV=prod
- [ ] CORS_ALLOW_ORIGIN 已配置
- [ ] 数据库迁移已运行
- [ ] 缓存已清除
- [ ] 文件权限已正确设置
- [ ] 所有依赖已安装（使用 --no-dev）
- [ ] Web 服务器配置正确（Nginx/Apache）

