<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Service\MediaResourceProcessor;
use App\Service\ResourceExtractor;
use Symfony\Component\HttpClient\HttpClient;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;

// 创建日志记录器
$logger = new Logger('debug_media');
$logger->pushHandler(new StreamHandler(__DIR__ . '/debug_media.log', Logger::DEBUG));

// 模拟的HTML内容（从数据库中提取的实际内容）
$htmlContent = '<section style="text-align: center;" nodeleaf=""><img class="rich_pages wxw-img js_insertlocalimg" data-imgfileid="100005738" data-ratio="1.5025125628140703" data-s="300,640" data-src="https://z-arab-crm.obs.ap-southeast-1.myhuaweicloud.com:443/official_website/ba/0c/ba0c778.jpg" data-type="jpeg" data-w="398" type="block"></section>';

// 模拟的微信图片HTML
$wechatHtml = '<section style="text-align: center;" nodeleaf=""><img class="rich_pages wxw-img js_insertlocalimg" data-imgfileid="100005738" data-ratio="1.5025125628140703" data-s="300,640" data-src="https://mmbiz.qpic.cn/sz_mmbiz_jpg/test.jpg" data-type="jpeg" data-w="398" type="block"></section>';

echo "=== 调试媒体处理器 ===\n\n";

// 测试ResourceExtractor
echo "1. 测试ResourceExtractor URL提取功能:\n";
$extractor = new ResourceExtractor($logger);

// 测试OBS URL提取
echo "测试OBS URL提取:\n";
$obsUrls = $extractor->extractFromContent($htmlContent);
echo "提取到的URL: " . json_encode($obsUrls, JSON_UNESCAPED_UNICODE) . "\n\n";

// 测试微信URL提取
echo "测试微信URL提取:\n";
$wechatUrls = $extractor->extractFromContent($wechatHtml);
echo "提取到的URL: " . json_encode($wechatUrls, JSON_UNESCAPED_UNICODE) . "\n\n";

// 测试isWechatResource方法
echo "2. 测试isWechatResource方法:\n";
$testUrls = [
    'https://mmbiz.qpic.cn/sz_mmbiz_jpg/test.jpg',
    'https://z-arab-crm.obs.ap-southeast-1.myhuaweicloud.com:443/official_website/ba/0c/ba0c778.jpg',
    'https://res.wx.qq.com/test.png',
    'https://example.com/image.jpg'
];

foreach ($testUrls as $url) {
    $isWechat = $extractor->isWechatResource($url);
    echo "URL: $url -> 是否为微信资源: " . ($isWechat ? '是' : '否') . "\n";
}

echo "\n3. 测试完整的媒体处理流程:\n";

// 这里我们需要模拟MediaResourceProcessor，但由于它依赖Doctrine和MediaManager
// 我们创建一个简化版本进行测试
class SimpleMediaProcessor
{
    private const WECHAT_CDN_DOMAINS = [
        'mmbiz.qpic.cn',
        'res.wx.qq.com',
        'wx.qlogo.cn',
        'mmfb.qpic.cn'
    ];

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function extractMediaUrls(string $content): array
    {
        $urls = [];

        // 提取img标签中的src
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches);
        if (!empty($matches[1])) {
            $this->logger->debug('提取到src属性URL', ['urls' => $matches[1]]);
            $urls = array_merge($urls, $matches[1]);
        }

        // 提取img标签中的data-src（懒加载图片）
        preg_match_all('/<img[^>]+data-src=["\']([^"\']+)["\']/i', $content, $matches);
        if (!empty($matches[1])) {
            $this->logger->debug('提取到data-src属性URL', ['urls' => $matches[1]]);
            $urls = array_merge($urls, $matches[1]);
        }

        // 去重并过滤
        $urls = array_filter(array_unique($urls));

        $this->logger->debug('提取到的所有媒体URL', ['urls' => $urls]);

        return $urls;
    }

    public function isWechatResource(string $url): bool
    {
        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['host'])) {
            $this->logger->debug('URL解析失败，没有host字段', ['url' => $url]);
            return false;
        }

        $this->logger->debug('检查是否为微信资源', ['url' => $url, 'host' => $parsedUrl['host']]);

        foreach (self::WECHAT_CDN_DOMAINS as $domain) {
            if (strpos($parsedUrl['host'], $domain) !== false) {
                $this->logger->debug('确认为微信CDN资源', ['url' => $url, 'matched_domain' => $domain]);
                return true;
            }
        }

        // 检查是否为华为云OBS（已处理的资源）
        if (strpos($parsedUrl['host'], 'obs.myhuaweicloud.com') !== false) {
            $this->logger->debug('华为云OBS资源，无需再次处理', ['url' => $url]);
            return false;
        }

        $this->logger->debug('非微信CDN资源', ['url' => $url, 'host' => $parsedUrl['host']]);
        return false;
    }
}

$simpleProcessor = new SimpleMediaProcessor($logger);

// 测试OBS内容处理
echo "处理OBS内容:\n";
$obsUrls = $simpleProcessor->extractMediaUrls($htmlContent);
foreach ($obsUrls as $url) {
    $isWechat = $simpleProcessor->isWechatResource($url);
    echo "URL: $url -> 需要处理: " . ($isWechat ? '是' : '否') . "\n";
}

// 测试微信内容处理
echo "\n处理微信内容:\n";
$wechatUrls = $simpleProcessor->extractMediaUrls($wechatHtml);
foreach ($wechatUrls as $url) {
    $isWechat = $simpleProcessor->isWechatResource($url);
    echo "URL: $url -> 需要处理: " . ($isWechat ? '是' : '否') . "\n";
}

echo "\n=== 调试完成 ===\n";
echo "详细日志已保存到: " . __DIR__ . "/debug_media.log\n";
