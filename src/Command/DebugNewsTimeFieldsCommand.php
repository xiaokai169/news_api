<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug-news-time-fields',
    description: '调试新闻文章创建时间为空的问题'
)]
class DebugNewsTimeFieldsCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private Connection $connection;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->connection = $entityManager->getConnection();
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('fix', 'f', InputOption::VALUE_NONE, '尝试修复问题')
            ->addOption('test-create', 't', InputOption::VALUE_NONE, '测试创建新记录')
            ->setHelp('
此命令用于调试新闻文章创建时间为空的问题。

使用示例:
  php bin/console app:debug-news-time-fields              # 检查问题
  php bin/console app:debug-news-time-fields --fix        # 尝试修复问题
  php bin/console app:debug-news-time-fields --test-create # 测试创建新记录
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $fix = $input->getOption('fix');
        $testCreate = $input->getOption('test-create');

        $io->title('🔍 新闻文章创建时间问题调试');

        try {
            // 1. 检查表结构
            $this->checkTableStructure($io);

            // 2. 分析数据问题
            $this->analyzeDataProblems($io);

            // 3. 检查实体映射
            $this->checkEntityMapping($io);

            // 4. 测试创建新记录
            if ($testCreate) {
                $this->testCreateRecord($io);
            }

            // 5. 尝试修复问题
            if ($fix) {
                $this->fixProblems($io);
            }

            $io->success('调试完成！');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('调试过程中发生错误: ' . $e->getMessage());
            $io->text('文件: ' . $e->getFile());
            $io->text('行号: ' . $e->getLine());
            return Command::FAILURE;
        }
    }

    private function checkTableStructure(SymfonyStyle $io): void
    {
        $io->section('📋 检查表结构');

        try {
            $tableStructure = $this->connection->fetchAllAssociative("DESCRIBE sys_news_article");

            $rows = [];
            foreach ($tableStructure as $column) {
                $rows[] = [
                    $column['Field'],
                    $column['Type'],
                    $column['Null'],
                    $column['Default'] ?? 'NULL',
                    $column['Extra'] ?? ''
                ];
            }

            $io->table(['字段名', '类型', '允许NULL', '默认值', '额外信息'], $rows);

            // 专门检查时间字段
            $io->text('<info>时间字段详细检查:</info>');
            $timeFields = $this->connection->fetchAllAssociative("
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
                $io->text(sprintf(
                    "• %s: %s, NULL=%s, Default=%s, Extra=%s",
                    $field['COLUMN_NAME'],
                    $field['COLUMN_TYPE'],
                    $field['IS_NULLABLE'],
                    $field['COLUMN_DEFAULT'] ?? 'NULL',
                    $field['EXTRA'] ?? ''
                ));
            }

        } catch (\Exception $e) {
            $io->error('检查表结构失败: ' . $e->getMessage());
        }
    }

    private function analyzeDataProblems(SymfonyStyle $io): void
    {
        $io->section('📊 分析数据问题');

        try {
            // 总记录数
            $totalRecords = $this->connection->fetchOne("SELECT COUNT(*) FROM sys_news_article");
            $io->text("总记录数: <info>{$totalRecords}</info>");

            // 检查各个时间字段的 NULL 值
            $fieldsToCheck = ['create_time', 'update_time', 'release_time'];

            foreach ($fieldsToCheck as $field) {
                try {
                    $nullCount = $this->connection->fetchOne("SELECT COUNT(*) FROM sys_news_article WHERE `{$field}` IS NULL");
                    $zeroCount = $this->connection->fetchOne("SELECT COUNT(*) FROM sys_news_article WHERE `{$field}` = '0000-00-00 00:00:00'");
                    $totalNull = $nullCount + $zeroCount;

                    $io->text("{$field} 为 NULL 或零值: <comment>{$totalNull}</comment> 记录");

                    if ($totalNull > 0) {
                        $io->text("  - NULL: {$nullCount}");
                        $io->text("  - 零值: {$zeroCount}");
                    }
                } catch (\Exception $e) {
                    $io->text("{$field} 字段检查失败: " . $e->getMessage());
                }
            }

            // 查看问题记录样本
            $problemRecords = $this->connection->fetchAllAssociative("
                SELECT
                    id,
                    name,
                    create_time,
                    update_time,
                    release_time,
                    status
                FROM sys_news_article
                WHERE create_time IS NULL OR create_time = '0000-00-00 00:00:00'
                LIMIT 5
            ");

            if (!empty($problemRecords)) {
                $io->warning('发现 create_time 为 NULL 或零值的记录:');
                $rows = [];
                foreach ($problemRecords as $record) {
                    $rows[] = [
                        $record['id'],
                        substr($record['name'], 0, 30),
                        $record['create_time'] ?? 'NULL',
                        $record['update_time'] ?? 'NULL',
                        $record['release_time'] ?? 'NULL',
                        $record['status']
                    ];
                }
                $io->table(['ID', '名称', 'CreateTime', 'UpdateTime', 'ReleaseTime', 'Status'], $rows);
            } else {
                $io->success('未发现 create_time 为 NULL 或零值的记录');
            }

            // 数据创建时间分布
            $timeDistribution = $this->connection->fetchAllAssociative("
                SELECT
                    DATE(create_time) as create_date,
                    COUNT(*) as count
                FROM sys_news_article
                WHERE create_time IS NOT NULL AND create_time != '0000-00-00 00:00:00'
                GROUP BY DATE(create_time)
                ORDER BY create_date DESC
                LIMIT 10
            ");

            if (!empty($timeDistribution)) {
                $io->text('<info>数据创建时间分布 (最近10天):</info>');
                $rows = [];
                foreach ($timeDistribution as $dist) {
                    $rows[] = [$dist['create_date'], $dist['count']];
                }
                $io->table(['日期', '记录数'], $rows);
            }

        } catch (\Exception $e) {
            $io->error('分析数据问题失败: ' . $e->getMessage());
        }
    }

    private function checkEntityMapping(SymfonyStyle $io): void
    {
        $io->section('🔍 检查实体映射');

        try {
            $metadata = $this->entityManager->getClassMetadata('App\Entity\SysNewsArticle');

            $io->text('<info>实体字段映射:</info>');
            foreach ($metadata->fieldNames as $column => $field) {
                $type = $metadata->getTypeOfField($field);
                $nullable = $metadata->isNullable($field) ? 'YES' : 'NO';
                $io->text("• {$field} -> {$column} ({$type}, NULL={$nullable})");
            }

            // 检查生命周期回调
            $lifecycleCallbacks = $metadata->lifecycleCallbacks;
            $io->text('<info>生命周期回调:</info>');
            foreach ($lifecycleCallbacks as $event => $callbacks) {
                $io->text("• {$event}: " . implode(', ', $callbacks));
            }

        } catch (\Exception $e) {
            $io->error('检查实体映射失败: ' . $e->getMessage());
        }
    }

    private function testCreateRecord(SymfonyStyle $io): void
    {
        $io->section('🧪 测试创建新记录');

        try {
            // 获取分类ID
            $categoryId = $this->connection->fetchOne("SELECT id FROM sys_news_article_category LIMIT 1");

            if (!$categoryId) {
                $io->warning('未找到分类，无法测试创建记录');
                return;
            }

            $io->text('测试创建新记录...');

            // 使用实体管理器创建记录（测试生命周期回调）
            $entity = new \App\Entity\SysNewsArticle();
            $entity->setName('测试文章-' . date('Y-m-d H:i:s'));
            $entity->setCover('/test/cover.jpg');
            $entity->setContent('测试内容');
            $entity->setMerchantId(0);
            $entity->setUserId(0);

            // 设置分类
            $category = $this->entityManager->find('App\Entity\SysNewsArticleCategory', $categoryId);
            if ($category) {
                $entity->setCategory($category);
            }

            $this->entityManager->persist($entity);
            $this->entityManager->flush();

            $newId = $entity->getId();
            $io->text("新记录ID: <info>{$newId}</info>");

            // 查询新创建的记录
            $newRecord = $this->connection->fetchAssociative("
                SELECT
                    id,
                    name,
                    create_time,
                    update_time,
                    release_time,
                    status
                FROM sys_news_article
                WHERE id = ?
            ", [$newId]);

            if ($newRecord) {
                $io->text('<info>新创建的记录详情:</info>');
                $io->table(['字段', '值'], [
                    ['ID', $newRecord['id']],
                    ['Name', substr($newRecord['name'], 0, 50)],
                    ['CreateTime', $newRecord['create_time'] ?? 'NULL'],
                    ['UpdateTime', $newRecord['update_time'] ?? 'NULL'],
                    ['ReleaseTime', $newRecord['release_time'] ?? 'NULL'],
                    ['Status', $newRecord['status']]
                ]);

                // 检查时间字段是否正确设置
                if ($newRecord['create_time'] === null || $newRecord['create_time'] === '0000-00-00 00:00:00') {
                    $io->error('❌ create_time 未正确设置！');
                } else {
                    $io->success('✅ create_time 正确设置');
                }

                if ($newRecord['update_time'] === null || $newRecord['update_time'] === '0000-00-00 00:00:00') {
                    $io->error('❌ update_time 未正确设置！');
                } else {
                    $io->success('✅ update_time 正确设置');
                }

                // 删除测试记录
                $this->entityManager->remove($entity);
                $this->entityManager->flush();
                $io->text('测试记录已清理');
            }

        } catch (\Exception $e) {
            $io->error('测试创建记录失败: ' . $e->getMessage());
        }
    }

    private function fixProblems(SymfonyStyle $io): void
    {
        $io->section('🔧 尝试修复问题');

        try {
            // 修复 create_time 为 NULL 的记录
            $nullCreateTimeCount = $this->connection->fetchOne("SELECT COUNT(*) FROM sys_news_article WHERE create_time IS NULL OR create_time = '0000-00-00 00:00:00'");

            if ($nullCreateTimeCount > 0) {
                $io->text("发现 {$nullCreateTimeCount} 条 create_time 为 NULL 或零值的记录");

                $io->text('尝试修复 create_time...');
                $updateResult = $this->connection->executeStatement("
                    UPDATE sys_news_article
                    SET create_time = NOW()
                    WHERE create_time IS NULL OR create_time = '0000-00-00 00:00:00'
                ");
                $io->success("已修复 {$updateResult} 条记录的 create_time");
            }

            // 修复 update_time 为 NULL 的记录
            $nullUpdateTimeCount = $this->connection->fetchOne("SELECT COUNT(*) FROM sys_news_article WHERE update_time IS NULL OR update_time = '0000-00-00 00:00:00'");

            if ($nullUpdateTimeCount > 0) {
                $io->text("发现 {$nullUpdateTimeCount} 条 update_time 为 NULL 或零值的记录");

                $io->text('尝试修复 update_time...');
                $updateResult = $this->connection->executeStatement("
                    UPDATE sys_news_article
                    SET update_time = NOW()
                    WHERE update_time IS NULL OR update_time = '0000-00-00 00:00:00'
                ");
                $io->success("已修复 {$updateResult} 条记录的 update_time");
            }

            // 检查修复结果
            $remainingNullCreateTime = $this->connection->fetchOne("SELECT COUNT(*) FROM sys_news_article WHERE create_time IS NULL OR create_time = '0000-00-00 00:00:00'");
            $remainingNullUpdateTime = $this->connection->fetchOne("SELECT COUNT(*) FROM sys_news_article WHERE update_time IS NULL OR update_time = '0000-00-00 00:00:00'");

            $io->text('<info>修复后状态:</info>');
            $io->text("• create_time 仍有问题: {$remainingNullCreateTime} 条记录");
            $io->text("• update_time 仍有问题: {$remainingNullUpdateTime} 条记录");

            if ($remainingNullCreateTime === 0 && $remainingNullUpdateTime === 0) {
                $io->success('🎉 所有问题已修复！');
            } else {
                $io->warning('仍有部分问题未解决，可能需要进一步调查');
            }

        } catch (\Exception $e) {
            $io->error('修复问题失败: ' . $e->getMessage());
        }
    }
}
