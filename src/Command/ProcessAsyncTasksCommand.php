<?php

namespace App\Command;

use App\Service\TaskQueueService;
use App\Service\AsyncTaskManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 处理异步任务命令
 */
#[AsCommand(
    name: 'app:process-async-tasks',
    description: 'Process async tasks from queue'
)]
class ProcessAsyncTasksCommand extends Command
{
    public function __construct(
        private TaskQueueService $taskQueueService,
        private AsyncTaskManager $asyncTaskManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('queue_name', InputArgument::OPTIONAL, 'Queue name to process', 'default')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Maximum number of tasks to process', 10)
            ->addOption('timeout', 't', InputOption::VALUE_OPTIONAL, 'Script timeout in seconds', 300)
            ->addOption('memory-limit', 'm', InputOption::VALUE_OPTIONAL, 'Memory limit in MB', 256)
            ->addOption('cleanup', 'c', InputOption::VALUE_NONE, 'Cleanup expired tasks before processing')
            ->addOption('retry-failed', 'r', InputOption::VALUE_NONE, 'Retry failed tasks before processing')
            ->setHelp('
This command allows you to process async tasks from the queue.

Examples:
  # Process default queue
  php bin/console app:process-async-tasks

  # Process specific queue
  php bin/console app:process-async-tasks wechat_sync

  # Process with custom limits
  php bin/console app:process-async-tasks --limit=20 --timeout=600

  # Process with cleanup and retry
  php bin/console app:process-async-tasks --cleanup --retry-failed

  # Process with memory limit
  php bin/console app:process-async-tasks --memory-limit=512
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $queueName = $input->getArgument('queue_name');
        $limit = (int)$input->getOption('limit');
        $timeout = (int)$input->getOption('timeout');
        $memoryLimit = (int)$input->getOption('memory-limit');
        $cleanup = $input->getOption('cleanup');
        $retryFailed = $input->getOption('retry-failed');

        $io->title('Processing Async Tasks');
        $io->writeln([
            sprintf('Queue: <info>%s</info>', $queueName),
            sprintf('Limit: <info>%d</info>', $limit),
            sprintf('Timeout: <info>%d seconds</info>', $timeout),
            sprintf('Memory Limit: <info>%d MB</info>', $memoryLimit),
        ]);

        // 设置内存限制
        $this->setMemoryLimit($memoryLimit);

        // 设置超时
        $this->setTimeLimit($timeout);

        $startTime = time();
        $processedCount = 0;
        $errorCount = 0;

        try {
            // 清理过期任务
            if ($cleanup) {
                $io->section('Cleaning Up Expired Tasks');
                $cleanupResult = $this->taskQueueService->cleanupExpiredTasks();
                $io->writeln(sprintf('Cleaned up <info>%d</info> expired tasks', $cleanupResult['cleaned']));

                if (!empty($cleanupResult['errors'])) {
                    $io->warning(sprintf('Encountered <error>%d</error> cleanup errors', count($cleanupResult['errors'])));
                }
            }

            // 重试失败任务
            if ($retryFailed) {
                $io->section('Retrying Failed Tasks');
                $retryResult = $this->taskQueueService->retryFailedTasks($queueName);
                $io->writeln(sprintf('Retried <info>%d</info> failed tasks', $retryResult['retried']));

                if (!empty($retryResult['errors'])) {
                    $io->warning(sprintf('Encountered <error>%d</error> retry errors', count($retryResult['errors'])));
                }
            }

            // 获取队列健康状态
            $io->section('Queue Health Check');
            $health = $this->taskQueueService->getQueueHealth($queueName);
            $io->writeln([
                sprintf('Status: <info>%s</info>', $health['status']),
                sprintf('Pending Tasks: <info>%d</info>', $health['pending_tasks']),
                sprintf('Running Tasks: <info>%d</info>', $health['running_tasks']),
                sprintf('Failed Tasks: <info>%d</info>', $health['failed_tasks']),
            ]);

            if ($health['status'] === 'critical') {
                if (!$io->confirm('Queue health is critical. Continue processing?', false)) {
                    return Command::SUCCESS;
                }
            }

            // 处理任务
            $io->section('Processing Tasks');
            $io->progressStart($limit);

            while ($processedCount < $limit && (time() - $startTime) < $timeout) {
                // 检查内存使用
                if ($this->isMemoryLimitExceeded($memoryLimit)) {
                    $io->warning('Memory limit exceeded, stopping processing');
                    break;
                }

                // 处理队列
                $result = $this->taskQueueService->processQueue($queueName);

                $processedCount += $result['processed'];
                $errorCount += $result['failed'];

                // 更新进度
                $io->progressAdvance($result['processed'] + $result['failed']);

                // 如果没有任务处理，等待一段时间
                if ($result['processed'] === 0 && $result['failed'] === 0) {
                    $io->writeln('No tasks to process, waiting...');
                    sleep(5);

                    // 检查是否超时
                    if ((time() - $startTime) >= $timeout) {
                        break;
                    }

                    continue;
                }

                // 显示错误
                if (!empty($result['errors'])) {
                    foreach ($result['errors'] as $error) {
                        $io->error(sprintf('Task Error: %s', $error['error'] ?? 'Unknown error'));
                    }
                }

                // 短暂休息，避免过度占用CPU
                usleep(100000); // 0.1秒
            }

            $io->progressFinish();

        } catch (\Exception $e) {
            $io->error(sprintf('Processing failed: %s', $e->getMessage()));
            $io->text($e->getTraceAsString());
            return Command::FAILURE;
        }

        // 显示最终结果
        $io->section('Processing Summary');
        $duration = time() - $startTime;
        $io->writeln([
            sprintf('Processed Tasks: <info>%d</info>', $processedCount),
            sprintf('Failed Tasks: <error>%d</error>', $errorCount),
            sprintf('Duration: <info>%d seconds</info>', $duration),
            sprintf('Memory Peak: <info>%s</info>', $this->formatBytes(memory_get_peak_usage(true))),
        ]);

        // 获取最终队列状态
        $finalStats = $this->taskQueueService->getQueueStats($queueName);
        $io->section('Final Queue Statistics');
        $io->writeln([
            sprintf('Pending: <info>%d</info>', $finalStats[AsyncTask::STATUS_PENDING] ?? 0),
            sprintf('Running: <info>%d</info>', $finalStats[AsyncTask::STATUS_RUNNING] ?? 0),
            sprintf('Completed: <info>%d</info>', $finalStats[AsyncTask::STATUS_COMPLETED] ?? 0),
            sprintf('Failed: <error>%d</error>', $finalStats[AsyncTask::STATUS_FAILED] ?? 0),
        ]);

        return Command::SUCCESS;
    }

    /**
     * 设置内存限制
     */
    private function setMemoryLimit(int $limitMB): void
    {
        $limitBytes = $limitMB * 1024 * 1024;
        ini_set('memory_limit', $limitBytes);
    }

    /**
     * 设置超时
     */
    private function setTimeLimit(int $seconds): void
    {
        set_time_limit($seconds);
    }

    /**
     * 检查内存限制是否超过
     */
    private function isMemoryLimitExceeded(int $limitMB): bool
    {
        $currentUsage = memory_get_usage(true);
        $limitBytes = $limitMB * 1024 * 1024;

        return $currentUsage > ($limitBytes * 0.9); // 使用90%作为阈值
    }

    /**
     * 格式化字节数
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return sprintf('%.2f %s', $bytes, $units[$unitIndex]);
    }
}
