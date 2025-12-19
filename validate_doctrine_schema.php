<?php

echo "=== Doctrine 模式验证 ===\n\n";

require_once 'vendor/autoload.php';

use Doctrine\ORM\Tools\SchemaValidator;
use App\Kernel;

try {
    // 创建 Symfony 内核
    $kernel = new Kernel('dev', true);
    $kernel->boot();

    // 获取 EntityManager
    $entityManager = $kernel->getContainer()->get('doctrine.orm.entity_manager');

    echo "1. EntityManager 创建成功\n";

    // 创建 SchemaValidator
    $validator = new SchemaValidator($entityManager);

    // 验证数据库模式
    $errors = $validator->validateMapping();

    if (empty($errors)) {
        echo "   ✓ 数据库模式验证通过 - 没有发现映射错误\n";
    } else {
        echo "   ✗ 发现数据库模式映射错误:\n";
        foreach ($errors as $className => $classErrors) {
            echo "     类: $className\n";
            foreach ($classErrors as $error) {
                echo "       - $error\n";
            }
        }
    }

    // 验证数据库与 Entity 的同步性
    $syncErrors = $validator->validateDatabase();

    if (empty($syncErrors)) {
        echo "   ✓ 数据库与 Entity 同步验证通过\n";
    } else {
        echo "   ✗ 发现数据库同步错误:\n";
        foreach ($syncErrors as $error) {
            echo "     - $error\n";
        }
    }

    $kernel->shutdown();

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== 验证完成 ===\n";
