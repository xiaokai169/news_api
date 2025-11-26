<?php

namespace App\Command;

use App\Service\NewsPublishService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:news:publish',
    description: '执行文章定时发布任务，扫描并自动发布符合条件的文章'
)]
class NewsPublishCommand extends Command
{
    public function __construct(
        private NewsPublishService $newsPublishService,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                '强制执行发布任务，忽略分布式锁'
            )
            ->addOption(
                'check-delayed',
                'd',
                InputOption::VALUE_NONE,
                '检查延迟发布的文章'
            )
            ->addOption(
                'stats',
                's',
                InputOption::VALUE_NONE,
                '显示发布统计信息'
            )
            ->addOption(
                'article-id',
                'i',
                InputOption::VALUE_REQUIRED,
                '手动强制发布指定ID的文章'
            )
            ->setHelp(<<<'EOF'
<info>%command.name%</info> 命令用于执行文章定时发布任务：

  <info>php %command.full_name%</info>

可选选项：
  <info>--force, -f</info>        强制执行发布任务，忽略分布式锁
  <info>--check-delayed, -d</info> 检查延迟发布的文章
  <info>--stats, -s</info>        显示发布统计信息
  <info>--article-id, -i ID</info> 手动强制发布指定ID的文章

示例用法：
  <info>php %command.full_name%</info>           # 执行常规发布任务
  <info>php %command.full_name% --force</info>   # 强制执行发布任务
  <info>php %command.full_name% --stats</info>   # 查看发布统计
  <info>php %command.full_name% -i 123</info>    # 强制发布ID为123的文章

定时任务配置（crontab）：
  * * * * * cd /path/to/project && php bin/console app:news:publish >> /var/log/news-publish.log 2>&1
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');
        $checkDelayed = $input->getOption('check-delayed');
        $showStats = $input->getOption('stats');
        $articleId = $input->getOption('article-id');

        $this->logger->info('文章发布任务开始执行', [
            'force' => $force,
            'checkDelayed' => $checkDelayed,
            'showStats' => $showStats,
            'articleId' => $articleId
        ]);

        try {
            // 处理手动强制发布单个文章
            if ($articleId) {
                return $this->handleForcePublish($io, $articleId);
            }

            // 显示统计信息
            if ($showStats) {
                return $this->handleShowStats($io);
            }

            // 检查延迟发布的文章
            if ($checkDelayed) {
                return $this->handleCheckDelayed($io);
            }

            // 执行常规发布任务
            return $this->handleRegularPublish($io, $force);

        } catch (\Exception $e) {
            $this->logger->error('文章发布任务执行失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $io->error('任务执行失败: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function handleForcePublish(SymfonyStyle $io, string $articleId): int
    {
        $io->section('手动强制发布文章');

        if (!is_numeric($articleId) || $articleId <= 0) {
            $io->error('文章ID必须为正整数');
            return Command::FAILURE;
        }

        $result = $this->newsPublishService->forcePublishArticle((int)$articleId);

        if ($result['success']) {
            $io->success(sprintf('文章 ID %d 强制发布成功', $articleId));
            $io->text([
                '文章标题: ' . $result['article']->getName(),
                '发布时间: ' . $result['article']->getReleaseTime()->format('Y-m-d H:i:s'),
                '当前状态: ' . $result['article']->getStatusDescription()
            ]);
        } else {
            $io->error(sprintf('文章 ID %d 强制发布失败: %s', $articleId, $result['message']));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function handleShowStats(SymfonyStyle $io): int
    {
        $io->section('文章发布统计信息');

        $stats = $this->newsPublishService->getPublishStats();

        $io->table(
            ['统计项', '数量'],
            [
                ['待发布文章', $stats['scheduled_count']],
                ['已发布文章', $stats['published_count']],
                ['延迟发布文章', $stats['delayed_count']],
                ['今日发布成功', $stats['today_success_count']],
                ['今日发布失败', $stats['today_failure_count']],
                ['发布成功率', $stats['success_rate'] . '%']
            ]
        );

        if ($stats['delayed_count'] > 0) {
            $io->warning(sprintf('发现 %d 篇延迟发布的文章，建议检查系统状态', $stats['delayed_count']));
        }

        return Command::SUCCESS;
    }

    private function handleCheckDelayed(SymfonyStyle $io): int
    {
        $io->section('检查延迟发布的文章');

        $delayedArticles = $this->newsPublishService->checkDelayedPublish();

        if (empty($delayedArticles)) {
            $io->success('未发现延迟发布的文章');
            return Command::SUCCESS;
        }

        $io->warning(sprintf('发现 %d 篇延迟发布的文章', count($delayedArticles)));

        $tableData = [];
        foreach ($delayedArticles as $article) {
            $tableData[] = [
                $article->getId(),
                $article->getName(),
                $article->getReleaseTime()->format('Y-m-d H:i:s'),
                $article->getStatusDescription(),
                $article->getMerchantId()
            ];
        }

        $io->table(
            ['ID', '标题', '计划发布时间', '状态', '商户ID'],
            $tableData
        );

        return Command::SUCCESS;
    }

    private function handleRegularPublish(SymfonyStyle $io, bool $force): int
    {
        $io->section('执行常规发布任务');

        $result = $this->newsPublishService->executePublishTask($force);

        if ($result['success']) {
            $io->success(sprintf('发布任务执行成功，共发布 %d 篇文章', $result['published_count']));

            if ($result['published_count'] > 0) {
                $io->text('发布的文章ID: ' . implode(', ', $result['published_ids']));
            }

            if ($result['skipped_locked']) {
                $io->note('任务被跳过（其他节点正在执行）');
            }

            // 检查延迟发布的文章
            $delayedArticles = $this->newsPublishService->checkDelayedPublish();
            if (!empty($delayedArticles)) {
                $io->warning(sprintf('发现 %d 篇延迟发布的文章', count($delayedArticles)));
            }

        } else {
            $io->error('发布任务执行失败: ' . $result['message']);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
