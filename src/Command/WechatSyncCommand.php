<?php

namespace App\Command;

use App\Service\WechatArticleSyncService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:wechat:sync',
    description: '同步公众号文章到数据库'
)]
class WechatSyncCommand extends Command
{
    public function __construct(
        private readonly WechatArticleSyncService $syncService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('account-id', InputArgument::REQUIRED, '公众号账户ID')
            ->addOption('force', 'f', InputOption::VALUE_NONE, '强制同步（即使文章已存在也更新）')
            ->addOption('bypass-lock', null, InputOption::VALUE_NONE, '绕过锁检查（用于解决锁卡住的问题）')
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, '每批次获取的文章数量', 20)
            ->addOption('max-articles', 'm', InputOption::VALUE_REQUIRED, '最大获取文章数量（0表示无限制）', 0)
            ->setHelp(<<<'EOF'
<info>%command.name%</info> 命令用于同步指定公众号的文章到数据库：

  <info>php %command.full_name% gh_xxxxxxxxxxxxxxxx</info>

使用 <comment>--force</comment> 选项强制同步所有文章：
  <info>php %command.full_name% gh_xxxxxxxxxxxxxxxx --force</info>

绕过锁检查（用于解决锁卡住的问题）：
  <info>php %command.full_name% gh_xxxxxxxxxxxxxxxx --bypass-lock</info>

限制同步文章数量：
  <info>php %command.full_name% gh_xxxxxxxxxxxxxxxx --max-articles=50</info>

设置批次大小：
  <info>php %command.full_name% gh_xxxxxxxxxxxxxxxx --batch-size=10</info>
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $accountId = $input->getArgument('account-id');
        $forceSync = $input->getOption('force');
        $bypassLock = $input->getOption('bypass-lock');

        $io->title('公众号文章同步任务');
        $io->writeln(sprintf('公众号ID: <info>%s</info>', $accountId));
        $io->writeln(sprintf('强制同步: <info>%s</info>', $forceSync ? '是' : '否'));
        $io->writeln(sprintf('绕过锁检查: <info>%s</info>', $bypassLock ? '是' : '否'));

        // 检查同步状态（除非使用绕过锁检查选项）
        if (!$bypassLock) {
            $status = $this->syncService->getSyncStatus($accountId);
            if (isset($status['error'])) {
                $io->error($status['error']);
                return Command::FAILURE;
            }

            if ($status['is_syncing']) {
                $io->warning('同步任务正在进行中，请稍后再试');
                return Command::FAILURE;
            }
        } else {
            $io->note('已启用绕过锁检查模式，跳过同步状态检查');
        }

        $io->section('开始同步');

        try {
            $result = $this->syncService->syncArticles($accountId, $forceSync, $bypassLock);

            if ($result['success']) {
                $io->success($result['message']);

                // 显示详细统计
                $stats = $result['stats'];
                $io->table(
                    ['统计项', '数量'],
                    [
                        ['总计文章', $stats['total']],
                        ['新增文章', $stats['created']],
                        ['更新文章', $stats['updated']],
                        ['跳过文章', $stats['skipped']],
                        ['失败文章', $stats['failed']],
                    ]
                );

                // 显示错误信息（如果有）
                if (!empty($result['errors'])) {
                    $io->section('错误信息');
                    foreach ($result['errors'] as $error) {
                        $io->writeln(sprintf('❌ <error>%s</error>', $error));
                    }
                }

                // 记录日志
                $this->logger->info('CLI同步任务完成: ' . $result['message']);

                return Command::SUCCESS;
            } else {
                $io->error($result['message']);

                if (!empty($result['errors'])) {
                    foreach ($result['errors'] as $error) {
                        $io->writeln(sprintf('❌ <error>%s</error>', $error));
                    }
                }

                $this->logger->error('CLI同步任务失败: ' . $result['message']);

                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $errorMessage = sprintf('同步过程中发生异常: %s', $e->getMessage());
            $io->error($errorMessage);
            $this->logger->error('CLI同步任务异常: ' . $errorMessage);

            return Command::FAILURE;
        }
    }
}
