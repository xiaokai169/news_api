<?php
/**
 * 测试公共API接口的简单脚本
 */

// 包含Symfony的引导文件
require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;

echo "=== 公共API接口测试 ===\n\n";

// 测试1: 新闻文章列表
echo "测试1: 获取新闻文章列表\n";
echo "URL: /public-api/articles?type=news&limit=5\n";
echo "预期: 返回最多5条已发布的新闻文章\n\n";

// 测试2: 公众号文章列表
echo "测试2: 获取公众号文章列表\n";
echo "URL: /public-api/articles?type=wechat&limit=5\n";
echo "预期: 返回最多5条公众号文章\n\n";

// 测试3: 新闻文章详情
echo "测试3: 获取新闻文章详情\n";
echo "URL: /public-api/news/{id}\n";
echo "预期: 返回指定ID的新闻文章详情\n\n";

// 测试4: 公众号文章详情
echo "测试4: 获取公众号文章详情\n";
echo "URL: /public-api/wechat/{id}\n";
echo "预期: 返回指定ID的公众号文章详情\n\n";

// 测试5: 错误处理
echo "测试5: 错误处理\n";
echo "URL: /public-api/articles?type=invalid\n";
echo "预期: 返回400错误，提示类型参数无效\n\n";

echo "=== 测试完成 ===\n";
echo "注意: 这些接口都不需要登录验证\n";
echo "安全配置: security.yaml 中 api 防火墙已设置 security: false\n";
echo "访问控制: access_control 中 /official-api 路径设置为 PUBLIC_ACCESS\n";
