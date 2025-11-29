# CORS 网络错误修复方案

## 问题确认

基于详细分析，确认了以下问题：

### 主要问题：URL 协议格式错误

-   **错误信息**: "URL scheme must be 'http' or 'https' for CORS request"
-   **根本原因**: 前端使用了缺少协议的 URL 格式（如 `//domain.com/api`）

### 次要问题：生产环境 CORS 域名限制

-   **当前配置**: `https://newsapi.arab-bee.com,https://www.newsapi.arab-bee.com`
-   **潜在问题**: 前端域名可能不在允许列表中

## 立即修复方案

### 1. 前端 URL 格式修复

#### ❌ 错误的 URL 格式

```javascript
// 这些格式会导致 "URL scheme must be http or https" 错误
const BAD_URLS = [
    "//newsapi.arab-bee.com/public-api/articles", // 缺少协议
    "newsapi.arab-bee.com/public-api/articles", // 完全缺少协议和主机
    "/public-api/articles", // 相对路径在某些环境下可能有问题
];
```

#### ✅ 正确的 URL 格式

```javascript
// 推荐的URL格式
const GOOD_URLS = [
    "https://newsapi.arab-bee.com/public-api/articles", // 完整HTTPS URL
    "http://newsapi.arab-bee.com/public-api/articles", // 完整HTTP URL（开发环境）
];

// 环境自适应配置
const API_CONFIG = {
    development: {
        baseURL: "http://localhost:8000/public-api",
        timeout: 10000,
    },
    production: {
        baseURL: "https://newsapi.arab-bee.com/public-api",
        timeout: 15000,
    },
};

const currentConfig =
    API_CONFIG[process.env.NODE_ENV] || API_CONFIG.development;
```

### 2. 前端请求示例代码

#### React/Axios 配置

```javascript
import axios from "axios";

// 创建API客户端
const apiClient = axios.create({
    baseURL:
        process.env.NODE_ENV === "production"
            ? "https://newsapi.arab-bee.com/public-api"
            : "http://localhost:8000/public-api",
    timeout: 10000,
    headers: {
        "Content-Type": "application/json",
    },
});

// 请求拦截器 - 添加调试日志
apiClient.interceptors.request.use(
    (config) => {
        console.log("🚀 API Request:", {
            url: config.url,
            fullUrl: config.baseURL + config.url,
            method: config.method,
            headers: config.headers,
        });
        return config;
    },
    (error) => {
        console.error("❌ Request Error:", error);
        return Promise.reject(error);
    }
);

// 响应拦截器 - 错误处理
apiClient.interceptors.response.use(
    (response) => {
        console.log("✅ API Response:", {
            status: response.status,
            url: response.config.url,
            data: response.data,
        });
        return response;
    },
    (error) => {
        console.error("❌ Response Error:", {
            message: error.message,
            status: error.response?.status,
            url: error.config?.url,
            data: error.response?.data,
        });

        // 特定错误处理
        if (error.message.includes("URL scheme")) {
            console.error("🔍 URL格式错误 detected - 请检查API_BASE_URL配置");
        }

        return Promise.reject(error);
    }
);

// API调用方法
export const articleApi = {
    getNewsList: (page = 1, limit = 20) => {
        return apiClient.get("/articles", {
            params: { type: "news", page, limit },
        });
    },

    getWechatList: (page = 1, limit = 20) => {
        return apiClient.get("/articles", {
            params: { type: "wechat", page, limit },
        });
    },

    getNewsDetail: (id) => {
        return apiClient.get(`/news/${id}`);
    },

    getWechatDetail: (id) => {
        return apiClient.get(`/wechat/${id}`);
    },
};
```

#### Vanilla JavaScript 配置

```javascript
class PublicApiClient {
    constructor() {
        // 确保使用完整的URL格式
        this.baseURL = this.getBaseURL();
        this.timeout = 10000;
    }

    getBaseURL() {
        const isProduction =
            window.location.hostname === "newsapi.arab-bee.com";
        return isProduction
            ? "https://newsapi.arab-bee.com/public-api"
            : "http://localhost:8000/public-api";
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;

        console.log("🚀 Making request:", { url, endpoint, options });

        const config = {
            method: "GET",
            headers: {
                "Content-Type": "application/json",
                ...options.headers,
            },
            ...options,
        };

        try {
            const response = await fetch(url, config);
            console.log("✅ Response received:", {
                url,
                status: response.status,
                ok: response.ok,
            });

            if (!response.ok) {
                throw new Error(
                    `HTTP ${response.status}: ${response.statusText}`
                );
            }

            const data = await response.json();
            console.log("✅ Response data:", data);
            return data;
        } catch (error) {
            console.error("❌ Request failed:", { url, error: error.message });

            // 特定错误处理
            if (error.message.includes("URL scheme")) {
                console.error("🔍 检测到URL格式错误");
                console.error("当前URL:", url);
                console.error("建议使用完整URL格式（包含http://或https://）");
            }

            throw error;
        }
    }

    // API方法
    async getArticles(type, page = 1, limit = 20) {
        const params = new URLSearchParams({
            type,
            page: page.toString(),
            limit: limit.toString(),
        });
        return this.request(`/articles?${params}`);
    }

    async getNewsDetail(id) {
        return this.request(`/news/${id}`);
    }

    async getWechatDetail(id) {
        return this.request(`/wechat/${id}`);
    }
}

// 使用示例
const api = new PublicApiClient();

// 测试方法
window.testAPI = {
    async testNewsList() {
        try {
            const result = await api.getArticles("news", 1, 5);
            console.log("新闻列表测试成功:", result);
            return result;
        } catch (error) {
            console.error("新闻列表测试失败:", error);
            throw error;
        }
    },

    async testUrlFormat() {
        console.log("当前配置:", {
            baseURL: api.baseURL,
            hostname: window.location.hostname,
            protocol: window.location.protocol,
        });
    },
};
```

### 3. 生产环境 CORS 配置检查

如果前端域名不在允许列表中，需要更新生产环境 CORS 配置：

#### 检查当前允许的域名

```bash
# 查看当前CORS配置
php bin/console debug:config nelmio_cors --env=prod
```

#### 更新 CORS 配置（如果需要）

在 `.env.prod` 文件中：

```bash
# 允许的前端域名列表
CORS_ALLOW_ORIGIN=https://your-frontend-domain.com,https://www.your-frontend-domain.com,https://newsapi.arab-bee.com,https://www.newsapi.arab-bee.com
```

#### 清除缓存

```bash
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

## 验证步骤

### 1. 使用测试页面验证

访问我们创建的测试页面：

```
https://newsapi.arab-bee.com/cors_comprehensive_test.html
```

### 2. 检查浏览器控制台

-   查看网络请求的 URL 格式
-   确认没有 "URL scheme" 错误
-   验证 CORS 头部是否正确返回

### 3. 使用 curl 命令测试

```bash
# 测试CORS预检请求
curl -X OPTIONS \
  -H "Origin: https://your-frontend-domain.com" \
  -H "Access-Control-Request-Method: GET" \
  -H "Access-Control-Request-Headers: Content-Type" \
  https://newsapi.arab-bee.com/public-api/articles

# 测试实际请求
curl -X GET \
  -H "Origin: https://your-frontend-domain.com" \
  "https://newsapi.arab-bee.com/public-api/articles?type=news&limit=5"
```

## 快速修复检查清单

### 前端修复

-   [ ] 确保 API 基础 URL 包含完整的协议（http://或 https://）
-   [ ] 移除所有协议相对路径（//domain.com）
-   [ ] 添加请求和响应拦截器进行调试
-   [ ] 测试不同环境下的 URL 配置

### 后端验证

-   [ ] 确认 CORS 配置包含前端域名
-   [ ] 清除生产环境缓存
-   [ ] 验证 API 端点可访问性
-   [ ] 检查错误日志

### 测试验证

-   [ ] 使用综合测试页面验证
-   [ ] 检查浏览器网络面板
-   [ ] 验证生产环境访问
-   [ ] 确认错误已解决

## 常见错误及解决方案

### 错误 1: "URL scheme must be http or https"

**原因**: 使用了协议相对路径或缺少协议
**解决**: 使用完整的 URL 格式

### 错误 2: "CORS policy: No 'Access-Control-Allow-Origin' header"

**原因**: 前端域名不在 CORS 允许列表中
**解决**: 更新 CORS_ALLOW_ORIGIN 配置

### 错误 3: "Network Error"

**原因**: URL 格式错误或网络连接问题
**解决**: 验证 URL 格式和网络连接

## 联系支持

如果问题仍然存在，请提供：

1. 浏览器控制台的完整错误信息
2. 网络请求的 URL 和响应头
3. 前端代码中的 API 配置
4. 生产环境的具体域名信息

---

**重要**: 这个修复方案主要针对 URL 格式问题，这是导致 "URL scheme must be http or https" 错误的主要原因。请首先检查和修复前端 URL 配置。
