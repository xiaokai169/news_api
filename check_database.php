<?php
// 简单数据库连接检查脚本
$host = '127.0.0.1';
$dbname = 'official_website';
$username = 'root';
$password = 'qwe147258..';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 查询表结构
    echo "正在查询 official 表结构...\n";
    $stmt = $pdo->query("DESCRIBE official");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "official 表结构:\n";
    foreach ($columns as $column) {
        echo "字段: {$column['Field']}, 类型: {$column['Type']}, 是否允许NULL: {$column['Null']}, 默认值: {$column['Default']}\n";
    }

    // 检查是否存在 is_deleted 字段
    $hasIsDeleted = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'is_deleted') {
            $hasIsDeleted = true;
            break;
        }
    }

    echo $hasIsDeleted ? "\n✓ 存在 is_deleted 字段\n" : "\n✗ 不存在 is_deleted 字段\n";

    // 检查软删除记录
    echo "\n检查软删除记录统计:\n";
    $stmt = $pdo->query("SELECT is_deleted, COUNT(*) as count FROM official GROUP BY is_deleted");
    $deletedStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($deletedStats as $stat) {
        $status = $stat['is_deleted'] ? '已删除' : '未删除';
        echo "$status: {$stat['count']} 条记录\n";
    }

} catch (PDOException $e) {
    echo "数据库连接错误: " . $e->getMessage() . "\n";
    exit(1);
}
