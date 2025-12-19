<?php

require_once 'vendor/autoload.php';

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use App\Kernel;

/**
 * 综合的 Doctrine 缓存清理脚本
 * 彻底清理所有类型的缓存并重新生成代理类
 */

class ComprehensiveDoctrineCacheClear
{
    private $kernel;
    private $application;
    private $output;

    public function __construct()
    {
        $this->kernel = new Kernel('dev', true);
        $this->kernel->boot();
        $this->application = new Application($this->kernel);
        $this->output = new BufferedOutput();
    }

    /**
     * 执行控制台命令
     */
    private function runCommand(string $command, array $options = []): string
    {
        try {
            $input = new ArrayInput(['command' => $command] + $options);
            $exitCode = $this->application->doRun($input, $this->output);
            $result = $this->output->fetch();

            echo "=== 执行命令: $command ===\n";
            echo $result;
            echo "退出代码: $exitCode\n\n";

            return $result;
        } catch (Exception $e) {
            echo "错误: " . $e->getMessage() . "\n\n";
            return "ERROR: " . $e->getMessage();
        }
    }

    /**
     * 清理应用缓存
     */
    public function clearApplicationCache(): void
    {
        echo "🧹 清理应用缓存 (dev 环境)...\n";
        $this->runCommand('cache:clear', ['--no-warmup' => true]);

        echo "🧹 清理应用缓存 (prod 环境)...\n";
        $this->runCommand('cache:clear', ['--env' => 'prod', '--no-warmup' => true]);
    }

    /**
     * 清理 Doctrine 元数据缓存
     */
    public function clearDoctrineMetadataCache(): void
    {
        echo "🗂️  清理 Doctrine 元数据缓存...\n";
        $this->runCommand('doctrine:cache:clear-metadata');
    }

    /**
     * 清理 Doctrine 查询缓存
     */
    public function clearDoctrineQueryCache(): void
    {
        echo "🔍 清理 Doctrine 查询缓存...\n";
        $this->runCommand('doctrine:cache:clear-query');
    }

    /**
     * 清理 Doctrine 结果缓存
     */
    public function clearDoctrineResultCache(): void
    {
        echo "📊 清理 Doctrine 结果缓存...\n";
        $this->runCommand('doctrine:cache:clear-result');
    }

    /**
     * 重新生成 Doctrine 代理类
     */
    public function regenerateDoctrineProxies(): void
    {
        echo "🔄 重新生成 Doctrine 代理类...\n";
        $this->runCommand('doctrine:generate:proxies', ['--regenerate' => true]);
    }

    /**
     * 验证数据库架构
     */
    public function validateDatabaseSchema(): void
    {
        echo "✅ 验证数据库架构...\n";
        $this->runCommand('doctrine:schema:validate');
    }

    /**
     * 手动清理缓存目录
     */
    public function manualCacheCleanup(): void
    {
        echo "🗑️  手动清理缓存目录...\n";

        $cacheDirs = [
            'var/cache/dev',
            'var/cache/prod',
            'var/cache/test'
        ];

        foreach ($cacheDirs as $dir) {
            if (is_dir($dir)) {
                echo "清理目录: $dir\n";
                $this->recursiveDelete($dir);
                echo "已清理: $dir\n";
            } else {
                echo "目录不存在: $dir\n";
            }
        }

        echo "\n";
    }

    /**
     * 递归删除目录
     */
    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->recursiveDelete($path);
                rmdir($path);
            } else {
                unlink($path);
            }
        }
    }

    /**
     * 检查实体元数据
     */
    public function checkEntityMetadata(): void
    {
        echo "🔍 检查实体元数据...\n";

        try {
            $entityManager = $this->kernel->getContainer()->get('doctrine.orm.entity_manager');
            $metadataFactory = $entityManager->getMetadataFactory();

            // 获取所有实体元数据
            $allMetadata = $metadataFactory->getAllMetadata();

            foreach ($allMetadata as $metadata) {
                $className = $metadata->getName();
                if (strpos($className, 'News') !== false || strpos($className, 'Article') !== false) {
                    echo "实体: $className\n";
                    $fieldNames = $metadata->getFieldNames();
                    echo "字段: " . implode(', ', $fieldNames) . "\n";

                    // 检查是否有 update_at 字段
                    if (in_array('update_at', $fieldNames)) {
                        echo "⚠️  发现 update_at 字段！\n";
                    }

                    // 检查是否有 updated_at 字段
                    if (in_array('updated_at', $fieldNames)) {
                        echo "✅ 发现 updated_at 字段\n";
                    }

                    echo "\n";
                }
            }
        } catch (Exception $e) {
            echo "检查元数据时出错: " . $e->getMessage() . "\n";
        }
    }

    /**
     * 执行完整的缓存清理流程
     */
    public function executeFullCleanup(): void
    {
        echo "🚀 开始执行完整的 Doctrine 缓存清理流程...\n\n";

        // 1. 手动清理缓存目录
        $this->manualCacheCleanup();

        // 2. 清理应用缓存
        $this->clearApplicationCache();

        // 3. 清理 Doctrine 元数据缓存
        $this->clearDoctrineMetadataCache();

        // 4. 清理 Doctrine 查询缓存
        $this->clearDoctrineQueryCache();

        // 5. 清理 Doctrine 结果缓存
        $this->clearDoctrineResultCache();

        // 6. 重新生成 Doctrine 代理类
        $this->regenerateDoctrineProxies();

        // 7. 检查实体元数据
        $this->checkEntityMetadata();

        // 8. 验证数据库架构
        $this->validateDatabaseSchema();

        echo "✅ Doctrine 缓存清理流程完成！\n";
    }
}

// 执行清理
try {
    $cleaner = new ComprehensiveDoctrineCacheClear();
    $cleaner->executeFullCleanup();
} catch (Exception $e) {
    echo "执行过程中发生错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
