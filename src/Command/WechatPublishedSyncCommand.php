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
    name: 'app:wechat:sync-published',
    description: '同步公众号已发布消息到数据库'
)]
class WechatPublishedSyncCommand extends Command
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
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, '每批次获取的消息数量', 20)
            ->addOption('max-articles', 'm', InputOption::VALUE_REQUIRED, '最大获取消息数量（0表示无限制）', 0)
            ->addOption('begin-date', null, InputOption::VALUE_REQUIRED, '开始日期（格式：YYYYMMDD）', 0)
            ->addOption('end-date', null, InputOption::VALUE_REQUIRED, '结束日期（格式：YYYYMMDD）', 0)
            ->setHelp(<<<'EOF'
<info>%command.name%</info> 命令用于同步指定公众号的已发布消息到数据库：

  <info>php %command.full_name% gh_xxxxxxxxxxxxxxxx</info>

使用 <comment>--force</comment> 选项强制同步所有消息：
  <info>php %command.full_name% gh_xxxxxxxxxxxxxxxx --force</info>

绕过锁检查（用于解决锁卡住的问题）：
  <info>php %command.full_name% gh_xxxxxxxxxxxxxxxx --bypass-lock</info>

限制同步消息数量：
  <info>php %command.full_name% gh_xxxxxxxxxxxxxxxx --max-articles=50</info>

设置批次大小：
  <info>php %command.full_name% gh_xxxxxxxxxxxxxxxx --batch-size=10</info>

设置日期范围：
  <info>php %command.full_name% gh_xxxxxxxxxxxxxxxx --begin-date=20250101 --end-date=20251231</info>
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
        $beginDate = (int) $input->getOption('begin-date');
        $endDate = (int) $input->getOption('end-date');

        $io->title('公众号已发布消息同步任务');
        $io->writeln(sprintf('公众号ID: <info>%s</info>', $accountId));
        $io->writeln(sprintf('强制同步: <info>%s</info>', $forceSync ? '是' : '否'));
        $io->writeln(sprintf('绕过锁检查: <info>%s</info>', $bypassLock ? '是' : '否'));

        if ($beginDate > 0) {
            $io->writeln(sprintf('开始日期: <info>%s</info>', $beginDate));
        }
        if ($endDate > 0) {
            $io->writeln(sprintf('结束日期: <info>%s</info>', $endDate));
        }

        $io->section('开始同步已发布消息');

        try {
            $result = $this->syncService->syncPublishedArticles($accountId, $forceSync, $bypassLock, $beginDate, $endDate);

            if ($result['success']) {
                $io->success($result['message']);

                // 显示详细统计
                $stats = $result['stats'];
                $io->table(
                    ['统计项', '数量'],
                    [
                        ['总计消息', $stats['total']],
                        ['新增消息', $stats['created']],
                        ['更新消息', $stats['updated']],
                        ['跳过消息', $stats['skipped']],
                        ['失败消息', $stats['failed']],
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
                $this->logger->info('CLI已发布消息同步任务完成: ' . $result['message']);

                return Command::SUCCESS;
            } else {
                $io->error($result['message']);

                if (!empty($result['errors'])) {
                    foreach ($result['errors'] as $error) {
                        $io->writeln(sprintf('❌ <error>%s</error>', $error));
                    }
                }

                $this->logger->error('CLI已发布消息同步任务失败: ' . $result['message']);

                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $errorMessage = sprintf('已发布消息同步过程中发生异常: %s', $e->getMessage());
            $io->error($errorMessage);
            $this->logger->error('CLI已发布消息同步任务异常: ' . $errorMessage);

            return Command::FAILURE;
        }
    }
}
