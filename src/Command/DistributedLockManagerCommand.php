<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:distributed-lock:manage',
    description: '管理分布式锁'
)]
class DistributedLockManagerCommand extends Command
{
    public function __construct(
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, '操作类型: status, clean, release, create-table')
            ->addOption('lock-key', 'k', InputOption::VALUE_OPTIONAL, '指定锁键（用于release操作）')
            ->addOption('force', 'f', InputOption::VALUE_NONE, '强制执行操作')
            ->setHelp(<<<'EOF'
分布式锁管理命令：

查看所有锁状态:
  <info>php %command.full_name% status</info>

清理过期锁:
  <info>php %command.full_name% clean</info>

释放指定锁:
  <info>php %command.full_name% release --lock-key=wechat_sync_gh_xxx</info>

强制释放所有锁:
  <info>php %command.full_name% clean --force</info>

创建分布式锁表:
  <info>php %command.full_name% create-table</info>
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        switch ($action) {
            case 'status':
                return $this->showStatus($io);
            case 'clean':
                return $this->cleanLocks($io, $input->getOption('force'));
            case 'release':
                return $this->releaseLock($io, $input->getOption('lock-key'));
            case 'create-table':
                return $this->createTable($io);
            default:
                $io->error("未知操作: {$action}");
                return Command::FAILURE;
        }
    }

    private function showStatus(SymfonyStyle $io): int
    {
        $io->title('分布式锁状态');

        try {
            // 检查表是否存在
            $result = $this->connection->executeQuery("SHOW TABLES LIKE 'distributed_locks'");
            $tableExists = $result->fetchAssociative();

            if (!$tableExists) {
                $io->warning('distributed_locks 表不存在');
                return Command::FAILURE;
            }

            // 获取所有锁
            $result = $this->connection->executeQuery("SELECT * FROM distributed_locks ORDER BY created_at DESC");
            $locks = $result->fetchAllAssociative();

            if (empty($locks)) {
                $io->success('当前没有锁记录');
                return Command::SUCCESS;
            }

            $tableData = [];
            $activeCount = 0;
            $expiredCount = 0;

            foreach ($locks as $lock) {
                $isExpired = new \DateTime($lock['expire_time']) < new \DateTime();
                $status = $isExpired ? "已过期" : "活跃";
                $statusIcon = $isExpired ? "⏰" : "🔒";

                if ($isExpired) {
                    $expiredCount++;
                } else {
                    $activeCount++;
                }

                $tableData[] = [
                    '锁键' => $lock['lock_key'],
                    '锁ID' => $lock['lock_id'],
                    '过期时间' => $lock['expire_time'],
                    '创建时间' => $lock['created_at'],
                    '状态' => $statusIcon . ' ' . $status,
                ];
            }

            $io->table(['锁键', '锁ID', '过期时间', '创建时间', '状态'], $tableData);
            $io->info("总计: " . count($locks) . " 个锁 (活跃: {$activeCount}, 过期: {$expiredCount})");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('查看锁状态时发生错误: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function cleanLocks(SymfonyStyle $io, bool $force): int
    {
        $io->title('清理分布式锁');

        try {
            if ($force) {
                // 强制删除所有锁
                $result = $this->connection->executeStatement("DELETE FROM distributed_locks");
                $io->success("已强制删除 {$result} 个锁记录");
            } else {
                // 只删除过期锁
                $result = $this->connection->executeStatement("DELETE FROM distributed_locks WHERE expire_time < NOW()");
                $io->success("已清理 {$result} 个过期锁");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('清理锁时发生错误: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function releaseLock(SymfonyStyle $io, ?string $lockKey): int
    {
        $io->title('释放指定锁');

        if (!$lockKey) {
            $io->error('请指定要释放的锁键: --lock-key=<lock-key>');
            return Command::FAILURE;
        }

        try {
            $result = $this->connection->executeStatement(
                "DELETE FROM distributed_locks WHERE lock_key = ?",
                [$lockKey]
            );

            if ($result > 0) {
                $io->success("已释放锁: {$lockKey}");
            } else {
                $io->warning("未找到锁: {$lockKey}");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('释放锁时发生错误: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function createTable(SymfonyStyle $io): int
    {
        $io->title('创建分布式锁表');

        try {
            // 检查表是否已存在
            $result = $this->connection->executeQuery("SHOW TABLES LIKE 'distributed_locks'");
            $tableExists = $result->fetchAssociative();

            if ($tableExists) {
                $io->warning('distributed_locks 表已存在');
                return Command::SUCCESS;
            }

            // 创建表
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

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('创建表时发生错误: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
