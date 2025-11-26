<?php
require_once 'vendor/autoload.php';

use App\Kernel;

$kernel = new Kernel('dev', true);
$kernel->boot();

$entityManager = $kernel->getContainer()->get('doctrine.orm.entity_manager');
$connection = $entityManager->getConnection();

try {
    echo "正在更新 sys_news_article 表的 name 字段长度...\n";

    // 执行SQL更新
    $sql = "ALTER TABLE sys_news_article MODIFY COLUMN name VARCHAR(50) NOT NULL";
    $connection->executeStatement($sql);

    echo "✅ 成功更新 name 字段长度为 50 个字符\n";

    // 验证更新
    $schemaManager = $connection->createSchemaManager();
    $table = $schemaManager->introspectTable('sys_news_article');
    $nameColumn = $table->getColumn('name');

    echo "验证更新结果:\n";
    echo "字段名: " . $nameColumn->getName() . "\n";
    echo "类型: " . $nameColumn->getType()->getName() . "\n";
    echo "长度: " . $nameColumn->getLength() . "\n";
    echo "是否可为空: " . ($nameColumn->getNotnull() ? '否' : '是') . "\n";

} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
}
