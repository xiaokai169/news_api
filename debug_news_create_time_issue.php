<?php
require_once 'vendor/autoload.php';

use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use App\Kernel as AppKernel;

// 初始化 Symfony 内核
$kernel = new AppKernel('dev', true);
$kernel->boot();

$container = $kernel->getContainer();
$entityManager = $container->get('doctrine.orm.entity_manager');
$connection = $entityManager->getConnection();

echo "=== 新闻文章创建时间问题调试报告 ===\n\n";

// 1. 检查实际的表结构
echo "1. 检查 sys_news_article 表结构:\n";
echo str_repeat("-", 60) . "\n";

try {
    $tableStructure = $connection->fetchAllAssociative("DESCRIBE sys_news_article");

    foreach ($tableStructure as $column) {
        echo sprintf(
            "字段: %-20s 类型: %-20s NULL: %-8s 默认值: %s\n",
            $column['Field'],
            $column['Type'],
            $column['Null'],
            $column['Default'] ?? 'NULL'
        );
    }
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}

echo "\n";

// 2. 检查时间字段的详细定义
echo "2. 检查时间字段定义:\n";
echo str_repeat("-", 60) . "\n";

try {
    $timeFields = $connection->fetchAllAssociative("
        SELECT
            COLUMN_NAME,
            COLUMN_TYPE,
            IS_NULLABLE,
            COLUMN_DEFAULT,
            EXTRA
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'sys_news_article'
        AND COLUMN_NAME IN ('create_time', 'update_time', 'create_at', 'update_at', 'release_time')
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
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. 分析数据问题
echo "3. 数据分析:\n";
echo str_repeat("-", 60) . "\n";

try {
    // 检查总记录数
    $totalRecords = $connection->fetchOne("SELECT COUNT(*) FROM sys_news_article");
    echo "总记录数: " . $totalRecords . "\n";

    // 检查 create_time 为 NULL 的记录
    $nullCreateTime = $connection->fetchOne("SELECT COUNT(*) FROM sys_news_article WHERE create_time IS NULL");
    echo "create_time 为 NULL 的记录数: " . $nullCreateTime . "\n";

    // 检查 create_at 为 NULL 的记录（如果字段存在）
    try {
        $nullCreateAt = $connection->fetchOne("SELECT COUNT(*) FROM sys_news_article WHERE create_at IS NULL");
        echo "create_at 为 NULL 的记录数: " . $nullCreateAt . "\n";
    } catch (Exception $e) {
        echo "create_at 字段不存在或无法查询\n";
    }

    // 检查 update_time 为 NULL 的记录
    $nullUpdateTime = $connection->fetchOne("SELECT COUNT(*) FROM sys_news_article WHERE update_time IS NULL");
    echo "update_time 为 NULL 的记录数: " . $nullUpdateTime . "\n";

    // 检查 release_time 为 NULL 的记录
    $nullReleaseTime = $connection->fetchOne("SELECT COUNT(*) FROM sys_news_article WHERE release_time IS NULL");
    echo "release_time 为 NULL 的记录数: " . $nullReleaseTime . "\n";

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. 查看有问题的记录样本
echo "4. 有问题记录样本:\n";
echo str_repeat("-", 60) . "\n";

try {
    // 查看 create_time 为 NULL 的记录
    $problemRecords = $connection->fetchAllAssociative("
        SELECT
            id,
            name,
            create_time,
            update_time,
            release_time,
            status,
            create_at,
            update_at
        FROM sys_news_article
        WHERE create_time IS NULL OR create_time = '0000-00-00 00:00:00'
        LIMIT 5
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

// 5. 检查数据创建时间分布
echo "5. 数据创建时间分布:\n";
echo str_repeat("-", 60) . "\n";

try {
    $timeDistribution = $connection->fetchAllAssociative("
        SELECT
            DATE(create_time) as create_date,
            COUNT(*) as count
        FROM sys_news_article
        WHERE create_time IS NOT NULL
        GROUP BY DATE(create_time)
        ORDER BY create_date DESC
        LIMIT 10
    ");

    foreach ($timeDistribution as $dist) {
        echo sprintf(
            "日期: %s, 记录数: %d\n",
            $dist['create_date'],
            $dist['count']
        );
    }

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}

echo "\n";

// 6. 检查实体生命周期回调是否工作
echo "6. 检查最近的创建记录:\n";
echo str_repeat("-", 60) . "\n";

try {
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

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}

echo "\n";

// 7. 测试创建新记录
echo "7. 测试创建新记录:\n";
echo str_repeat("-", 60) . "\n";

try {
    // 获取分类ID
    $categoryId = $connection->fetchOne("SELECT id FROM sys_news_article_category LIMIT 1");

    if ($categoryId) {
        echo "测试创建新记录...\n";

        // 插入测试记录
        $testSql = "
            INSERT INTO sys_news_article (
                name, cover, content, status, category_id,
                merchant_id, user_id, is_recommend, perfect, original_url, view_count
            ) VALUES (
                '测试文章-" . date('Y-m-d H:i:s') . "',
                '/test/cover.jpg',
                '测试内容',
                1,
                :category_id,
                0,
                0,
                0,
                '',
                '',
                0
            )
        ";

        $stmt = $connection->prepare($testSql);
        $stmt->bindValue('category_id', $categoryId);
        $stmt->executeStatement();

        $newId = $connection->lastInsertId();
        echo "新记录ID: " . $newId . "\n";

        // 查询新创建的记录
        $newRecord = $connection->fetchAssociative("
            SELECT
                id,
                name,
                create_time,
                update_time,
                release_time,
                status
            FROM sys_news_article
            WHERE id = :id
        ", ['id' => $newId]);

        if ($newRecord) {
            echo sprintf(
                "新记录 - ID: %d, CreateTime: %s, UpdateTime: %s, ReleaseTime: %s, Status: %d\n",
                $newRecord['id'],
                $newRecord['create_time'] ?? 'NULL',
                $newRecord['update_time'] ?? 'NULL',
                $newRecord['release_time'] ?? 'NULL',
                $newRecord['status']
            );

            // 删除测试记录
            $connection->executeStatement("DELETE FROM sys_news_article WHERE id = :id", ['id' => $newId]);
            echo "测试记录已清理\n";
        }
    } else {
        echo "未找到分类，无法测试创建记录\n";
    }

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}

echo "\n=== 调试报告完成 ===\n";
