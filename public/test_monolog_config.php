<?php
// 简单的配置验证脚本
require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

try {
    $configPath = __DIR__ . '/../config/packages/monolog.yaml';

    if (!file_exists($configPath)) {
        throw new Exception("配置文件不存在: $configPath");
    }

    $content = file_get_contents($configPath);
    $config = Yaml::parse($content);

    echo "✓ YAML 语法正确\n";

    // 检查基本结构
    if (isset($config['monolog'])) {
        echo "✓ 找到 monolog 配置块\n";

        if (isset($config['monolog']['channels'])) {
            echo "✓ 找到 channels 配置\n";
            echo "  - 频道: " . implode(', ', $config['monolog']['channels']) . "\n";
        }

        if (isset($config['monolog']['handlers'])) {
            echo "✓ 找到 handlers 配置\n";
            echo "  - 处理器数量: " . count($config['monolog']['handlers']) . "\n";
        }
    }

    // 检查环境配置
    if (isset($config['when@prod'])) {
        echo "✓ 找到生产环境配置\n";
    }

    if (isset($config['when@dev'])) {
        echo "✓ 找到开发环境配置\n";
    }

    echo "\n配置验证完成！\n";

} catch (Exception $e) {
    echo "✗ 配置验证失败: " . $e->getMessage() . "\n";
    exit(1);
}
