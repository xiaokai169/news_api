<?php
// src/Service/RollbackTriggerService.php
namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;

class RollbackTriggerService
{
    private LoggerInterface $logger;
    private array $thresholds;

    public function __construct(LoggerInterface $logger, ParameterBagInterface $params)
    {
        $this->logger = $logger;
        $this->loadThresholds($params->get('kernel.project_dir') . '/config/rollback/thresholds.yaml');
    }

    private function loadThresholds(string $thresholdsFile): void
    {
        if (file_exists($thresholdsFile)) {
            $this->thresholds = yaml_parse_file($thresholdsFile);
        }
    }

    public function checkPerformanceMetrics(array $metrics): bool
    {
        $shouldRollback = false;
        $reasons = [];

        // 检查响应时间
        if (isset($metrics['avg_response_time']) &&
            $metrics['avg_response_time'] > $this->thresholds['response_time']['max']) {
            $shouldRollback = true;
            $reasons[] = "响应时间过长: {$metrics['avg_response_time']}ms";
        }

        // 检查错误率
        if (isset($metrics['error_rate']) &&
            $metrics['error_rate'] > $this->thresholds['error_rate']['max']) {
            $shouldRollback = true;
            $reasons[] = "错误率过高: {$metrics['error_rate']}%";
        }

        // 检查内存使用率
        if (isset($metrics['memory_usage']) &&
            $metrics['memory_usage'] > $this->thresholds['memory_usage']['max']) {
            $shouldRollback = true;
            $reasons[] = "内存使用率过高: {$metrics['memory_usage']}%";
        }

        // 检查数据库连接数
        if (isset($metrics['db_connections']) &&
            $metrics['db_connections'] > $this->thresholds['db_connections']['max']) {
            $shouldRollback = true;
            $reasons[] = "数据库连接数过多: {$metrics['db_connections']}";
        }

        if ($shouldRollback) {
            $this->triggerRollback(implode(', ', $reasons));
        }

        return $shouldRollback;
    }

    private function triggerRollback(string $reason): void
    {
        $this->logger->critical('触发自动回滚', ['reason' => $reason]);

        // 创建回滚触发文件
        file_put_contents('/tmp/rollback_triggered', date('Y-m-d H:i:s'));
        file_put_contents('/tmp/rollback_reason', $reason);

        // 执行回滚脚本
        exec('/scripts/rollback.sh auto >> /var/log/rollback.log 2>&1 &');
    }

    public function shouldTriggerAutoRollback(array $healthChecks): bool
    {
        $strategy = $this->thresholds['rollback_strategy'];

        if (!$strategy['auto_rollback']['enabled']) {
            return false;
        }

        $consecutiveFailures = 0;
        $timeWindow = $strategy['auto_rollback']['time_window'];
        $maxFailures = $strategy['auto_rollback']['consecutive_failures'];

        // 检查连续失败次数
        foreach ($healthChecks as $check) {
            if (!$check['passed']) {
                $consecutiveFailures++;
            } else {
                $consecutiveFailures = 0;
            }
        }

        if ($consecutiveFailures >= $maxFailures) {
            $this->triggerRollback("连续 $consecutiveFailures 次健康检查失败");
            return true;
        }

        return false;
    }

    public function isInCooldownPeriod(): bool
    {
        $cooldownFile = '/tmp/rollback_cooldown';

        if (!file_exists($cooldownFile)) {
            return false;
        }

        $lastRollback = file_get_contents($cooldownFile);
        $cooldownPeriod = $this->thresholds['rollback_strategy']['cooldown_period'];

        $nextAllowedRollback = strtotime($lastRollback . ' +' . $cooldownPeriod);

        return time() < $nextAllowedRollback;
    }

    public function setCooldownPeriod(): void
    {
        file_put_contents('/tmp/rollback_cooldown', date('Y-m-d H:i:s'));
    }

    public function canExecuteRollback(): bool
    {
        // 检查回滚次数限制
        $maxAttempts = $this->thresholds['rollback_strategy']['max_rollback_attempts'];
        $currentAttempts = $this->getCurrentRollbackAttempts();

        if ($currentAttempts >= $maxAttempts) {
            $this->logger->warning('回滚次数已达上限', [
                'current' => $currentAttempts,
                'max' => $maxAttempts
            ]);
            return false;
        }

        // 检查冷却期
        if ($this->isInCooldownPeriod()) {
            $this->logger->warning('回滚在冷却期内');
            return false;
        }

        return true;
    }

    private function getCurrentRollbackAttempts(): int
    {
        $attemptsFile = '/tmp/rollback_attempts';

        if (!file_exists($attemptsFile)) {
            return 0;
        }

        return (int) file_get_contents($attemptsFile);
    }

    public function incrementRollbackAttempts(): void
    {
        $attemptsFile = '/tmp/rollback_attempts';
        $currentAttempts = $this->getCurrentRollbackAttempts();

        file_put_contents($attemptsFile, $currentAttempts + 1);
    }

    public function resetRollbackAttempts(): void
    {
        $attemptsFile = '/tmp/rollback_attempts';
        if (file_exists($attemptsFile)) {
            unlink($attemptsFile);
        }
    }
}
