<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-distributed-locks-table',
    description: '创建分布式锁表'
)]
class CreateDistributedLocksTableCommand extends Command
{
    public function __construct(
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('创建分布式锁表');

        try {
            // 检查表是否已存在
            $result = $this->connection->executeQuery("SHOW TABLES LIKE 'distributed_locks'");
            $tableExists = $result->fetchAssociative();

            if ($tableExists) {
                $io->warning('distributed_locks 表已存在');
                return Command::SUCCESS;
            }

            // 创建表的SQL
            $sql = "
            CREATE TABLE `distributed_locks` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `lock_key` varchar(255) NOT NULL,
              `lock_id` varchar(255) NOT NULL,
              `expire_time` datetime NOT NULL,
              `created_at` datetime NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `idx_lock_key` (`lock_key`),
              KEY `idx_expire_time` (`expire_time`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";

            $this->connection->executeStatement($sql);

            $io->success('✅ distributed_locks 表创建成功！');

            // 验证表是否创建成功
            $result = $this->connection->executeQuery("SHOW TABLES LIKE 'distributed_locks'");
            $tableExists = $result->fetchAssociative();

            if ($tableExists) {
                $io->success('✅ 表验证成功，distributed_locks 表已存在');

                // 显示表结构
                $io->section('表结构信息');
                $structure = $this->connection->executeQuery("DESCRIBE distributed_locks");
                $tableData = [];
                while ($row = $structure->fetchAssociative()) {
                    $tableData[] = [
                        '字段' => $row['Field'],
                        '类型' => $row['Type'],
                        '允许空值' => $row['Null'],
                        '键' => $row['Key'],
                        '默认值' => $row['Default']
                    ];
                }
                $io->table(['字段', '类型', '允许空值', '键', '默认值'], $tableData);
            } else {
                $io->error('❌ 表验证失败');
                return Command::FAILURE;
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('❌ 创建表时发生错误: ' . $e->getMessage());
            $io->text('错误详情: ' . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
