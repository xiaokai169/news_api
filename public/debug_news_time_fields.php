<?php
// 简化的数据库检查脚本
require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use App\Kernel as AppKernel;

try {
    // 初始化 Symfony 内核
    $kernel = new AppKernel('dev', true);
    $kernel->boot();

    $container = $kernel->getContainer();
    $entityManager = $container->get('doctrine.orm.entity_manager');
    $connection = $entityManager->getConnection();

    echo "=== 新闻文章创建时间问题调试报告 ===\n\n";

    // 1. 检查表结构
    echo "1. 检查 sys_news_article 表结构:\n";
    echo str_repeat("-", 60) . "\n";

    $tableStructure = $connection->fetchAllAssociative("DESCRIBE sys_news_article");

    foreach ($tableStructure as $column) {
        echo sprintf(
            "字段: %-20s 类型: %-25s NULL: %-8s 默认值: %s\n",
            $column['Field'],
            $column['Type'],
            $column['Null'],
            $column['Default'] ?? 'NULL'
        );
    }

    echo "\n";

    // 2. 检查时间字段
    echo "2. 检查时间字段定义:\n";
    echo str_repeat("-", 60) . "\n";

    $timeFields = $connection->fetchAllAssociative("
        SELECT
            COLUMN_NAME,
            COLUMN_TYPE,
            IS_NULLABLE,
            COLUMN_DEFAULT,
            EXTRA
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'sys_news_article'
        AND COLUMN_NAME LIKE '%time%'
        ORDER BY COLUMN_NAME
    ");

    foreach ($timeFields as $field) {
        echo sprintf(
            "字段: %-15s 类型: %-20s NULL: %-8s 默认值: %-15s 额外: %s\n",
            $field['COLUMN_NAME'],
            $field['COLUMN_TYPE'],
            $field['IS_NULLABLE'],
            $field['COLUMN_DEFAULT'] ?? 'NULL',
            $field['EXTRA'] ?? ''
        );
    }

    echo "\n";

    // 3. 数据分析
    echo "3. 数据分析:\n";
    echo str_repeat("-", 60) . "\n";

    $totalRecords = $connection->fetchOne("SELECT COUNT(*) FROM sys_news_article");
    echo "总记录数: " . $totalRecords . "\n";

    // 检查各个时间字段的 NULL 值
    $fieldsToCheck = ['create_time', 'update_time', 'release_time', 'create_at', 'update_at'];

    foreach ($fieldsToCheck as $field) {
        try {
            $nullCount = $connection->fetchOne("SELECT COUNT(*) FROM sys_news_article WHERE `$field` IS NULL");
            echo "$field 为 NULL 的记录数: " . $nullCount . "\n";
        } catch (Exception $e) {
            echo "$field 字段不存在或无法查询\n";
        }
    }

    echo "\n";

    // 4. 查看问题记录
    echo "4. 有问题记录样本 (create_time IS NULL):\n";
    echo str_repeat("-", 60) . "\n";

    try {
        $problemRecords = $connection->fetchAllAssociative("
            SELECT
                id,
                name,
                create_time,
                update_time,
                release_time,
                status
            FROM sys_news_article
            WHERE create_time IS NULL OR create_time = '0000-00-00 00:00:00'
            LIMIT 3
        ");

        if (empty($problemRecords)) {
            echo "未发现 create_time 为 NULL 的记录\n";
        } else {
            foreach ($problemRecords as $record) {
                echo sprintf(
                    "ID: %d, Name: %s, CreateTime: %s, UpdateTime: %s, ReleaseTime: %s, Status: %d\n",
                    $record['id'],
                    substr($record['name'], 0, 30),
                    $record['create_time'] ?? 'NULL',
                    $record['update_time'] ?? 'NULL',
                    $record['release_time'] ?? 'NULL',
                    $record['status']
                );
            }
        }
    } catch (Exception $e) {
        echo "错误: " . $e->getMessage() . "\n";
    }

    echo "\n";

    // 5. 最近的记录
    echo "5. 最近创建的记录:\n";
    echo str_repeat("-", 60) . "\n";

    $recentRecords = $connection->fetchAllAssociative("
        SELECT
            id,
            name,
            create_time,
            update_time,
            release_time,
            status
        FROM sys_news_article
        ORDER BY id DESC
        LIMIT 5
    ");

    foreach ($recentRecords as $record) {
        echo sprintf(
            "ID: %d, Name: %s, CreateTime: %s, UpdateTime: %s, ReleaseTime: %s, Status: %d\n",
            $record['id'],
            substr($record['name'], 0, 30),
            $record['create_time'] ?? 'NULL',
            $record['update_time'] ?? 'NULL',
            $record['release_time'] ?? 'NULL',
            $record['status']
        );
    }

    echo "\n=== 调试报告完成 ===\n";

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪: " . $e->getTraceAsString() . "\n";
}
