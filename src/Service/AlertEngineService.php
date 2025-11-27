<?php
// src/Service/AlertEngineService.php
namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;

class AlertEngineService
{
    private LoggerInterface $logger;
    private array $rules = [];
    private array $activeAlerts = [];

    public function __construct(LoggerInterface $logger, ParameterBagInterface $params)
    {
        $this->logger = $logger;
        $this->loadRules($params->get('kernel.project_dir') . '/config/alerts/alert_rules.yaml');
    }

    private function loadRules(string $rulesFile): void
    {
        if (file_exists($rulesFile)) {
            $this->rules = yaml_parse_file($rulesFile);
            $this->logger->info('告警规则加载完成', ['rules_count' => count($this->rules['alerts'])]);
        }
    }

    public function evaluateMetrics(array $metrics): void
    {
        foreach ($this->rules['alerts'] as $alertName => $rule) {
            if (!$rule['enabled']) {
                continue;
            }

            $alertKey = $this->generateAlertKey($alertName, $metrics);

            if ($this->shouldTriggerAlert($alertName, $rule, $metrics)) {
                $this->triggerAlert($alertName, $rule, $metrics, $alertKey);
            } else {
                $this->resolveAlert($alertKey);
            }
        }
    }

    private function shouldTriggerAlert(string $alertName, array $rule, array $metrics): bool
    {
        switch ($alertName) {
            case 'application_error_rate':
                return $this->evaluateCondition($rule['condition'], [
                    'error_rate' => $metrics['error_rate'] ?? 0
                ]);

            case 'response_time':
                return $this->evaluateCondition($rule['condition'], [
                    'avg_response_time' => $metrics['avg_response_time'] ?? 0
                ]);

            case 'database_connections':
                return $this->evaluateCondition($rule['condition'], [
                    'connection_usage' => $metrics['db_connection_usage'] ?? 0
                ]);

            case 'cpu_usage':
                return $this->evaluateCondition($rule['condition'], [
                    'cpu_usage' => $metrics['cpu_usage'] ?? 0
                ]);

            case 'memory_usage':
                return $this->evaluateCondition($rule['condition'], [
                    'memory_usage' => $metrics['memory_usage'] ?? 0
                ]);

            case 'disk_usage':
                return $this->evaluateCondition($rule['condition'], [
                    'disk_usage' => $metrics['disk_usage'] ?? 0
                ]);

            default:
                return false;
        }
    }

    private function evaluateCondition(string $condition, array $variables): bool
    {
        // 简单的条件评估器
        // 在生产环境中应该使用更安全的表达式评估器
        extract($variables);

        try {
            return eval("return $condition;");
        } catch (\Throwable $e) {
            $this->logger->error('告警条件评估失败', [
                'condition' => $condition,
                'variables' => $variables,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function triggerAlert(string $alertName, array $rule, array $metrics, string $alertKey): void
    {
        if (isset($this->activeAlerts[$alertKey])) {
            // 告警已经激活，检查是否需要升级
            $this->checkEscalation($alertKey, $rule);
            return;
        }

        $this->activeAlerts[$alertKey] = [
            'name' => $alertName,
            'severity' => $rule['severity'],
            'description' => $rule['description'],
            'triggered_at' => new \DateTime(),
            'metrics' => $metrics,
            'escalation_level' => 1,
            'last_notification' => null
        ];

        $this->logger->warning('告警触发', [
            'alert_name' => $alertName,
            'severity' => $rule['severity'],
            'description' => $rule['description'],
            'metrics' => $metrics
        ]);

        $this->sendNotification($alertKey, $rule, 1);
    }

    private function resolveAlert(string $alertKey): void
    {
        if (isset($this->activeAlerts[$alertKey])) {
            $this->logger->info('告警解除', [
                'alert_key' => $alertKey,
                'alert_name' => $this->activeAlerts[$alertKey]['name'],
                'duration' => $this->activeAlerts[$alertKey]['triggered_at']->diff(new \DateTime())->format('%i分钟')
            ]);

            unset($this->activeAlerts[$alertKey]);
        }
    }

    private function checkEscalation(string $alertKey, array $rule): void
    {
        $alert = &$this->activeAlerts[$alertKey];
        $currentLevel = $alert['escalation_level'];

        if (!isset($this->rules['escalation']['levels'][$currentLevel])) {
            return;
        }

        $nextLevel = $this->rules['escalation']['levels'][$currentLevel];
        $delay = new \DateInterval('PT' . str_replace('m', 'M', $nextLevel['delay']));

        if ($alert['triggered_at']->add($delay) <= new \DateTime() &&
            $alert['last_notification'] === null) {

            $alert['escalation_level'] = $currentLevel + 1;
            $this->sendNotification($alertKey, $rule, $alert['escalation_level']);
        }
    }

    private function sendNotification(string $alertKey, array $rule, int $escalationLevel): void
    {
        $alert = $this->activeAlerts[$alertKey];
        $escalationConfig = $this->rules['escalation']['levels'][$escalationLevel - 1] ?? null;

        if (!$escalationConfig) {
            return;
        }

        foreach ($escalationConfig['channels'] as $channel) {
            switch ($channel) {
                case 'email':
                    $this->sendEmailNotification($alert);
                    break;
                case 'webhook':
                    $this->sendWebhookNotification($alert);
                    break;
                case 'sms':
                    $this->sendSmsNotification($alert);
                    break;
            }
        }

        $alert['last_notification'] = new \DateTime();
    }

    private function sendEmailNotification(array $alert): void
    {
        $subject = "[{$alert['severity']}] {$alert['name']} - 系统告警";
        $message = $this->formatEmailMessage($alert);

        // 发送邮件逻辑
        $this->logger->info('发送邮件告警', [
            'subject' => $subject,
            'to' => 'admin@yourdomain.com'
        ]);

        // 实际邮件发送代码
        // mail('admin@yourdomain.com', $subject, $message);
    }

    private function sendWebhookNotification(array $alert): void
    {
        $payload = [
            'text' => "🚨 系统告警\n",
            'alert_name' => $alert['name'],
            'severity' => $alert['severity'],
            'description' => $alert['description'],
            'triggered_at' => $alert['triggered_at']->format('Y-m-d H:i:s'),
            'escalation_level' => $alert['escalation_level']
        ];

        // 发送Webhook
        $this->logger->info('发送Webhook告警', ['payload' => $payload]);

        // 实际Webhook发送代码
        // curl -X POST -H 'Content-type: application/json' --data json_encode($payload) $webhookUrl;
    }

    private function sendSmsNotification(array $alert): void
    {
        // SMS发送逻辑
        $this->logger->info('发送短信告警', [
            'alert_name' => $alert['name'],
            'severity' => $alert['severity']
        ]);
    }

    private function formatEmailMessage(array $alert): string
    {
        return "系统告警通知\n\n" .
               "告警名称: {$alert['name']}\n" .
               "严重程度: {$alert['severity']}\n" .
               "描述: {$alert['description']}\n" .
               "触发时间: {$alert['triggered_at']->format('Y-m-d H:i:s')}\n" .
               "升级级别: {$alert['escalation_level']}\n\n" .
               "当前指标:\n" .
               json_encode($alert['metrics'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private function generateAlertKey(string $alertName, array $metrics): string
    {
        return md5($alertName . serialize($metrics));
    }

    public function getActiveAlerts(): array
    {
        return $this->activeAlerts;
    }
}
