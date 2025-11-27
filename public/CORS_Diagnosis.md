# CORS 问题诊断和解决方案

## 问题分析

你遇到的错误：

```
CORS Network Failure
URL scheme must be "http" or "https" for CORS request.
```

这个错误通常由以下原因引起：

### 1. CORS 配置问题

**问题**: 当前的 `nelmio_cors.yaml` 只配置了 `/api/` 路径，但新的公共接口是 `/public-api/`

**解决方案**: 已更新配置文件，添加了 `/public-api/` 路径支持

### 2. 请求 URL 格式问题

**问题**: 前端请求的 URL 可能格式不正确
**常见错误**:

-   缺少协议头: `//example.com/public-api/articles`
-   相对路径问题: `/public-api/articles`

**正确格式**:

```javascript
// 使用绝对路径
const BASE_URL = "http://localhost:8000/public-api";
// 或者使用相对路径
const BASE_URL = "/public-api";
```

## 已实施的修复

### 1. 更新 CORS 配置

文件: [`config/packages/nelmio_cors.yaml`](config/packages/nelmio_cors.yaml:1)

```yaml
nelmio_cors:
    defaults:
        origin_regex: true
        allow_origin: ["%env(CORS_ALLOW_ORIGIN)%"] # 允许所有来源
        allow_methods: ["GET", "OPTIONS", "POST", "PUT", "PATCH", "DELETE"]
        allow_headers: ["Content-Type", "Authorization", "X-Requested-With"]
        expose_headers: ["Link"]
        max_age: 3600
    paths:
        "^/api/": ~ # 原有的API路径
        "^/public-api/": ~ # 新增的公共API路径
```

### 2. 环境变量配置

文件: [`.env`](.env:1)

```bash
CORS_ALLOW_ORIGIN=*  # 允许所有域名跨域访问
```

## 测试验证

### 1. CORS 测试页面

创建了 [`public/test_cors.html`](public/test_cors.html:1) 用于测试跨域访问

**使用方法**:

1. 将项目部署到服务器
2. 在浏览器中访问 `http://your-domain.com/test_cors.html`
3. 点击各个测试按钮
4. 查看浏览器控制台的 CORS 相关错误

### 2. 手动测试命令

```bash
# 测试OPTIONS请求（CORS预检）
curl -X OPTIONS \
  -H "Origin: http://localhost:3000" \
  -H "Access-Control-Request-Method: GET" \
  -H "Access-Control-Request-Headers: Content-Type" \
  http://localhost:8000/public-api/articles?type=news

# 测试GET请求
curl -X GET \
  -H "Origin: http://localhost:3000" \
  http://localhost:8000/public-api/articles?type=news&limit=5
```

## 常见 CORS 错误和解决方案

### 错误 1: "No 'Access-Control-Allow-Origin' header is present"

**原因**: 服务器没有返回 CORS 头
**解决方案**:

1. 确保 `nelmio_cors.yaml` 配置正确
2. 清除 Symfony 缓存: `php bin/console cache:clear`

### 错误 2: "URL scheme must be 'http' or 'https'"

**原因**: 请求 URL 格式错误
**解决方案**:

```javascript
// 错误
fetch("//example.com/public-api/articles"); // 缺少协议

// 正确
fetch("http://example.com/public-api/articles"); // 包含协议
fetch("/public-api/articles"); // 相对路径
```

### 错误 3: "Response to preflight request doesn't pass access control check"

**原因**: 预检请求失败
**解决方案**:

1. 确保 `allow_methods` 包含请求的 HTTP 方法
2. 确保 `allow_headers` 包含请求的自定义头

## 前端集成示例

### React/Axios 示例

```javascript
import axios from "axios";

const API_BASE_URL =
    process.env.NODE_ENV === "production"
        ? "https://your-api-domain.com/public-api"
        : "http://localhost:8000/public-api";

// 创建axios实例
const apiClient = axios.create({
    baseURL: API_BASE_URL,
    timeout: 10000,
    headers: {
        "Content-Type": "application/json",
    },
});

// 请求拦截器
apiClient.interceptors.request.use(
    (config) => {
        console.log("API Request:", config);
        return config;
    },
    (error) => {
        console.error("Request Error:", error);
        return Promise.reject(error);
    }
);

// 响应拦截器
apiClient.interceptors.response.use(
    (response) => {
        console.log("API Response:", response);
        return response;
    },
    (error) => {
        console.error("Response Error:", error);

        if (error.response) {
            // 服务器返回了错误状态码
            console.error("Status:", error.response.status);
            console.error("Data:", error.response.data);
        } else if (error.request) {
            // 请求已发出但没有收到响应
            console.error("No response received:", error.request);
        } else {
            // 请求配置出错
            console.error("Request config error:", error.message);
        }

        return Promise.reject(error);
    }
);

// API调用示例
export const articleApi = {
    // 获取新闻列表
    getNewsList: (page = 1, limit = 20) => {
        return apiClient.get("/articles", {
            params: { type: "news", page, limit },
        });
    },

    // 获取公众号列表
    getWechatList: (page = 1, limit = 20) => {
        return apiClient.get("/articles", {
            params: { type: "wechat", page, limit },
        });
    },

    // 获取新闻详情
    getNewsDetail: (id) => {
        return apiClient.get(`/news/${id}`);
    },

    // 获取公众号详情
    getWechatDetail: (id) => {
        return apiClient.get(`/wechat/${id}`);
    },
};
```

### Vanilla JavaScript 示例

```javascript
class PublicApi {
    constructor(baseUrl = "/public-api") {
        this.baseUrl = baseUrl;
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        const config = {
            method: "GET",
            headers: {
                "Content-Type": "application/json",
                ...options.headers,
            },
            ...options,
        };

        try {
            console.log(`Making request to: ${url}`);
            const response = await fetch(url, config);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            console.log("Response data:", data);
            return data;
        } catch (error) {
            console.error("API request failed:", error);
            throw error;
        }
    }

    // 获取文章列表
    async getArticles(type, page = 1, limit = 20) {
        return this.request("/articles", {
            params: new URLSearchParams({
                type,
                page: page.toString(),
                limit: limit.toString(),
            }),
        });
    }

    // 获取新闻详情
    async getNewsDetail(id) {
        return this.request(`/news/${id}`);
    }

    // 获取公众号详情
    async getWechatDetail(id) {
        return this.request(`/wechat/${id}`);
    }
}

// 使用示例
const api = new PublicApi();

// 获取新闻列表
api.getArticles("news", 1, 10)
    .then((data) => console.log("News list:", data))
    .catch((error) => console.error("Error:", error));
```

## 验证步骤

1. **清除缓存**:

    ```bash
    php bin/console cache:clear
    php bin/console cache:clear --env=prod
    ```

2. **检查 CORS 配置**:

    ```bash
    php bin/console debug:config nelmio_cors
    ```

3. **测试接口**:

    - 访问 `http://your-domain.com/test_cors.html`
    - 检查浏览器控制台的网络请求
    - 验证响应头包含 `Access-Control-Allow-Origin`

4. **生产环境部署**:
    - 确保环境变量 `CORS_ALLOW_ORIGIN` 设置为正确的前端域名
    - 重启 Web 服务器

## 故障排除

如果仍然遇到 CORS 问题，请检查：

1. **Web 服务器配置**: 确保 Nginx/Apache 没有覆盖 CORS 头
2. **浏览器缓存**: 清除浏览器缓存或使用无痕模式
3. **网络环境**: 检查是否有代理或防火墙干扰
4. **Symfony 版本**: 确保使用支持 CORS 的 Symfony 版本

## 联系支持

如果问题仍然存在，请提供：

1. 浏览器控制台的完整错误信息
2. 网络请求的请求头和响应头
3. 使用的具体 URL 和参数
4. 服务器环境信息（开发/生产）
