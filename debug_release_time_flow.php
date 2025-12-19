<?php

require_once 'vendor/autoload.php';

use App\Service\WechatApiService;
use App\Service\WechatArticleSyncService;
use App\Entity\Official;

/**
 * 调试 release_time 字段数据流的脚本
 *
 * 此脚本用于追踪 release_time 从微信API到数据库的完整流程
 */

echo "=== release_time 字段调试分析 ===\n\n";

// 1. 分析数据流路径
echo "1. 数据流路径分析:\n";
echo "   微信API -> WechatApiService.extractAllPublishedArticles() -> \n";
echo "   WechatArticleSyncService.processArticleData() -> Official实体 -> 数据库\n\n";

// 2. 检查微信API响应结构
echo "2. 微信API响应结构分析:\n";
echo "   - 已发布消息API: /freepublish/batchget\n";
echo "   - 关键字段: publish_time (Unix时间戳), update_time (Unix时间戳)\n";
echo "   - 数据提取: extractArticlesFromPublishedItem() 方法\n\n";

// 3. 检查数据转换逻辑
echo "3. 数据转换逻辑分析:\n";
echo "   WechatApiService.extractArticlesFromPublishedItem():\n";
echo "   - 第413行: \$publishTime = \$item['publish_time'] ?? \$item['update_time'] ?? time();\n";
echo "   - 第422行: 'publish_time' => \$publishTime,\n\n";

echo "   WechatArticleSyncService.processArticleData():\n";
echo "   - 第270-289行: 处理 publish_time 字段\n";
echo "   - 第271行: \$releaseTime = \\DateTime::createFromFormat('U', \$articleData['publish_time']);\n";
echo "   - 第273行: \$article->setReleaseTime(\$releaseTime->format('Y-m-d H:i:s'));\n\n";

// 4. 检查数据库字段定义
echo "4. 数据库字段定义分析:\n";
echo "   表: official\n";
echo "   字段: release_time varchar(255) NOT NULL DEFAULT ''\n";
echo "   Entity: Official.php\n";
echo "   - 第54行: #[ORM\Column(name: 'release_time', type: Types::STRING, length: 255, options: ['default' => ''])]\n";
echo "   - 第56行: private string \$releaseTime = '';\n\n";

// 5. 识别潜在问题点
echo "5. 潜在问题点分析:\n\n";

echo "   问题1: 微信API数据缺失\n";
echo "   - 如果微信API返回的数据中没有 publish_time 或 update_time\n";
echo "   - extractArticlesFromPublishedItem() 会使用 time() 作为默认值\n";
echo "   - 但这应该不会导致空值\n\n";

echo "   问题2: DateTime转换失败\n";
echo "   - WechatArticleSyncService 第271行: createFromFormat('U', \$articleData['publish_time'])\n";
echo "   - 如果 \$articleData['publish_time'] 不是有效的Unix时间戳，会返回false\n";
echo "   - 第272-277行有错误处理，但只是记录警告\n\n";

echo "   问题3: 数据类型不匹配\n";
echo "   - 数据库字段是 varchar(255)\n";
echo "   - Entity定义是 string\n";
echo "   - 但设置的是格式化的日期时间字符串\n\n";

echo "   问题4: 备选逻辑问题\n";
echo "   - 第278-286行: 如果没有 publish_time，尝试使用 update_time\n";
echo "   - 但如果两者都没有，只记录调试日志，不设置任何值\n\n";

echo "   问题5: 初始值问题\n";
echo "   - Official实体中 releaseTime 默认为空字符串 ''\n";
echo "   - 如果同步过程中没有成功设置，会保持为空\n\n";

// 6. 验证假设
echo "6. 需要验证的假设:\n\n";

echo "   假设1: 微信API响应中缺少时间字段\n";
echo "   - 需要检查实际的API响应数据\n";
echo "   - 查看 WechatApiService 第321行的完整响应日志\n\n";

echo "   假设2: DateTime格式转换失败\n";
echo "   - Unix时间戳可能不是整数格式\n";
echo "   - 可能是字符串格式的数字\n\n";

echo "   假设3: 数据提取逻辑问题\n";
echo "   - extractArticlesFromPublishedItem() 可能没有正确提取时间字段\n";
echo "   - 需要检查 \$item 数组的实际结构\n\n";

// 7. 建议的调试步骤
echo "7. 建议的调试步骤:\n\n";

echo "   步骤1: 添加详细日志\n";
echo "   - 在 WechatApiService.extractArticlesFromPublishedItem() 第413行前后添加日志\n";
echo "   - 记录 \$item 数组的完整结构\n\n";

echo "   步骤2: 验证数据类型\n";
echo "   - 在 WechatArticleSyncService.processArticleData() 第270行添加类型检查\n";
echo "   - 验证 \$articleData['publish_time'] 的数据类型和值\n\n";

echo "   步骤3: 检查DateTime转换\n";
echo "   - 在第271行前后添加调试日志\n";
echo "   - 记录转换前后的值\n\n";

echo "   步骤4: 验证数据库存储\n";
echo "   - 在保存前检查 \$article->getReleaseTime() 的值\n";
echo "   - 在保存后查询数据库验证实际存储的值\n\n";

echo "8. 最可能的原因分析:\n\n";

echo "   基于代码分析，最可能的原因是:\n\n";
echo "   1. 微信API返回的数据中 publish_time 字段缺失或格式不正确\n";
echo "   2. DateTime::createFromFormat('U', \$timestamp) 转换失败\n";
echo "   3. 备选的 update_time 字段也有同样问题\n";
echo "   4. 最终导致 releaseTime 保持为初始的空字符串值\n\n";

echo "=== 调试完成 ===\n";
