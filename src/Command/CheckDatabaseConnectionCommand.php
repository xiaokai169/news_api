<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-db-connection',
    description: '检查数据库连接状态和配置信息'
)]
class CheckDatabaseConnectionCommand extends Command
{
    private ManagerRegistry $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('connection', InputArgument::OPTIONAL, '指定要测试的连接名称')
            ->addOption('detailed', 'd', InputOption::VALUE_NONE, '显示详细信息')
            ->addOption('test-query', 't', InputOption::VALUE_NONE, '执行测试查询')
            ->addOption('json', 'j', InputOption::VALUE_NONE, '以JSON格式输出')
            ->setHelp('
此命令用于检查所有或指定的数据库连接状态。

使用示例:
  php bin/console app:check-db-connection                    # 检查所有连接
  php bin/console app:check-db-connection default           # 检查默认连接
  php bin/console app:check-db-connection user --detailed   # 详细检查用户连接
  php bin/console app:check-db-connection --test-query      # 执行测试查询
  php bin/console app:check-db-connection --json            # JSON格式输出

连接说明:
  - default: official_website 数据库 (业务数据)
  - user: official_website_user 数据库 (用户数据，默认实体管理器)
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connectionName = $input->getArgument('connection');
        $detailed = $input->getOption('detailed');
        $testQuery = $input->getOption('test-query');
        $jsonOutput = $input->getOption('json');

        $startTime = microtime(true);
        $results = [];

        try {
            $allConnections = $this->doctrine->getConnections();
            $allManagers = $this->doctrine->getManagers();
            $defaultConnection = $this->doctrine->getDefaultConnectionName();
            $defaultManager = $this->doctrine->getDefaultManagerName();

            if ($connectionName && !isset($allConnections[$connectionName])) {
                $io->error("连接 '{$connectionName}' 不存在。可用连接: " . implode(', ', array_keys($allConnections)));
                return Command::FAILURE;
            }

            $connectionsToCheck = $connectionName ? [$connectionName => $allConnections[$connectionName]] : $allConnections;

            foreach ($connectionsToCheck as $name => $connection) {
                $result = $this->checkConnection($name, $connection, $detailed, $testQuery);
                $results['connections'][$name] = $result;
            }

            // 获取实体管理器信息
            foreach ($allManagers as $name => $manager) {
                $results['managers'][$name] = $this->getManagerInfo($name, $manager);
            }

            $results['summary'] = [
                'default_connection' => $defaultConnection,
                'default_manager' => $defaultManager,
                'total_connections' => count($allConnections),
                'total_managers' => count($allManagers),
                'execution_time' => round((microtime(true) - $startTime) * 1000, 2),
                'timestamp' => date('Y-m-d H:i:s')
            ];

            if ($jsonOutput) {
                $io->writeln(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $this->displayResults($io, $results, $detailed);
            }

            // 检查是否有错误
            $hasErrors = false;
            foreach ($results['connections'] as $conn) {
                if ($conn['status'] === 'error') {
                    $hasErrors = true;
                    break;
                }
            }

            return $hasErrors ? Command::FAILURE : Command::SUCCESS;

        } catch (\Exception $e) {
            if ($jsonOutput) {
                $io->writeln(json_encode([
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $io->error('检查过程中发生错误: ' . $e->getMessage());
                if ($detailed) {
                    $io->text('文件: ' . $e->getFile());
                    $io->text('行号: ' . $e->getLine());
                }
            }
            return Command::FAILURE;
        }
    }

    private function checkConnection(string $name, Connection $connection, bool $detailed, bool $testQuery): array
    {
        $result = [
            'name' => $name,
            'status' => 'unknown',
            'database' => null,
            'host' => null,
            'port' => null,
            'driver' => null,
            'charset' => null,
            'response_time' => 0,
            'error' => null,
            'mysql_version' => null,
            'test_query_result' => null
        ];

        try {
            // 获取连接参数
            $params = $connection->getParams();
            $result['database'] = $params['dbname'] ?? 'unknown';
            $result['host'] = $params['host'] ?? 'unknown';
            $result['port'] = $params['port'] ?? 'default';
            $result['driver'] = $params['driver'] ?? 'unknown';
            $result['charset'] = $params['charset'] ?? 'unknown';

            // 测试基本连接
            $testStart = microtime(true);
            $connection->executeQuery('SELECT 1');
            $result['response_time'] = round((microtime(true) - $testStart) * 1000, 2);
            $result['status'] = 'connected';

            // 获取MySQL版本
            try {
                $versionQuery = $connection->executeQuery('SELECT VERSION() as version');
                $result['mysql_version'] = $versionQuery->fetchOne();
            } catch (\Exception $e) {
                $result['mysql_version'] = 'unknown';
            }

            // 详细信息
            if ($detailed) {
                try {
                    // 获取数据库大小
                    $sizeQuery = $connection->executeQuery("
                        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'db_size_mb'
                        FROM information_schema.tables
                        WHERE table_schema = ?
                    ", [$result['database']]);
                    $result['database_size_mb'] = $sizeQuery->fetchOne();

                    // 获取表数量
                    $tableQuery = $connection->executeQuery("
                        SELECT COUNT(*) as table_count
                        FROM information_schema.tables
                        WHERE table_schema = ?
                    ", [$result['database']]);
                    $result['table_count'] = $tableQuery->fetchOne();

                    // 获取字符集和排序规则
                    $charsetQuery = $connection->executeQuery("
                        SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
                        FROM information_schema.SCHEMATA
                        WHERE SCHEMA_NAME = ?
                    ", [$result['database']]);
                    $charsetInfo = $charsetQuery->fetchAssociative();
                    $result['database_charset'] = $charsetInfo['DEFAULT_CHARACTER_SET_NAME'] ?? 'unknown';
                    $result['database_collation'] = $charsetInfo['DEFAULT_COLLATION_NAME'] ?? 'unknown';

                } catch (\Exception $e) {
                    $result['detailed_info_error'] = $e->getMessage();
                }
            }

            // 执行测试查询
            if ($testQuery) {
                try {
                    $testStart = microtime(true);
                    $testResult = $connection->executeQuery('
                        SELECT
                            1 as test_value,
                            NOW() as `current_time`,
                            CONNECTION_ID() as connection_id,
                            USER() as `current_user`
                    ')->fetchAssociative();
                    $result['test_query_result'] = [
                        'data' => $testResult,
                        'execution_time' => round((microtime(true) - $testStart) * 1000, 2) . 'ms'
                    ];
                } catch (\Exception $e) {
                    $result['test_query_result'] = [
                        'error' => $e->getMessage()
                    ];
                }
            }

        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    private function getManagerInfo(string $name, EntityManagerInterface $manager): array
    {
        $info = [
            'name' => $name,
            'status' => 'unknown',
            'connection_name' => null,
            'entity_paths' => [],
            'error' => null
        ];

        try {
            $info['connection_name'] = $manager->getConnection()->getDatabasePlatform()->getName();

            // 获取实体路径 - 使用更安全的方法
            try {
                $metadataDriver = $manager->getConfiguration()->getMetadataDriverImpl();
                if ($metadataDriver && method_exists($metadataDriver, 'getPaths')) {
                    $info['entity_paths'] = $metadataDriver->getPaths();
                } elseif ($metadataDriver && method_exists($metadataDriver, 'getNamespace')) {
                    // 如果是 XmlDriver 或其他驱动，尝试获取命名空间
                    $info['entity_paths'] = [$metadataDriver->getNamespace()];
                } else {
                    // 从实体管理器的元数据中获取实体路径
                    $metadataFactory = $manager->getMetadataFactory();
                    $entityNames = $metadataFactory->getAllMetadata();
                    $paths = [];
                    foreach ($entityNames as $metadata) {
                        if ($metadata->reflClass) {
                            $paths[] = dirname($metadata->reflClass->getFileName());
                        }
                    }
                    $info['entity_paths'] = array_unique($paths);
                }
            } catch (\Exception $e) {
                $info['entity_paths'] = ['Unable to determine paths: ' . $e->getMessage()];
            }

            $info['status'] = 'connected';

        } catch (\Exception $e) {
            $info['status'] = 'error';
            $info['error'] = $e->getMessage();
        }

        return $info;
    }

    private function displayResults(SymfonyStyle $io, array $results, bool $detailed): void
    {
        $summary = $results['summary'];

        $io->title('🔍 数据库连接状态检测');

        // 显示摘要信息
        $io->section('📊 摘要信息');
        $io->definitionList(
            ['环境' => getenv('APP_ENV') ?: 'unknown'],
            ['默认连接' => $summary['default_connection']],
            ['默认实体管理器' => $summary['default_manager']],
            ['总连接数' => $summary['total_connections']],
            ['总实体管理器数' => $summary['total_managers']],
            ['检测时间' => $summary['timestamp']],
            ['执行时间' => $summary['execution_time'] . 'ms']
        );

        // 显示连接状态
        $io->section('🔗 连接状态');
        $connectionRows = [];
        $hasErrors = false;

        foreach ($results['connections'] as $conn) {
            $status = $conn['status'] === 'connected' ? '✅ 连接' : '❌ 错误';
            $defaultBadge = $conn['name'] === $summary['default_connection'] ? ' (默认)' : '';

            $row = [
                $conn['name'] . $defaultBadge,
                $status,
                $conn['database'],
                $conn['host'] . ':' . $conn['port'],
                $conn['driver'],
                $conn['response_time'] . 'ms'
            ];

            if ($detailed) {
                $row[] = $conn['mysql_version'] ?? 'unknown';
                $row[] = ($conn['database_size_mb'] ?? 'unknown') . 'MB';
                $row[] = $conn['table_count'] ?? 'unknown';
            }

            $connectionRows[] = $row;

            if ($conn['status'] === 'error') {
                $hasErrors = true;
                $io->error("连接 '{$conn['name']}' 失败: " . $conn['error']);
            }
        }

        $headers = ['连接名称', '状态', '数据库', '主机:端口', '驱动', '响应时间'];
        if ($detailed) {
            $headers = array_merge($headers, ['MySQL版本', '数据库大小', '表数量']);
        }

        $io->table($headers, $connectionRows);

        // 显示实体管理器信息
        $io->section('🗂️ 实体管理器信息');
        $managerRows = [];

        foreach ($results['managers'] as $manager) {
            $defaultBadge = $manager['name'] === $summary['default_manager'] ? ' (默认)' : '';
            $status = $manager['status'] === 'connected' ? '✅ 正常' : '❌ 错误';
            $paths = is_array($manager['entity_paths']) ? implode(', ', $manager['entity_paths']) : 'unknown';

            $managerRows[] = [
                $manager['name'] . $defaultBadge,
                $status,
                $manager['connection_name'] ?? 'unknown',
                $paths
            ];

            if ($manager['status'] === 'error') {
                $io->error("实体管理器 '{$manager['name']}' 错误: " . $manager['error']);
            }
        }

        $io->table(['管理器名称', '状态', '连接名称', '实体路径'], $managerRows);

        // 显示测试查询结果（如果有）
        foreach ($results['connections'] as $conn) {
            if (isset($conn['test_query_result'])) {
                $io->section("🧪 测试查询结果 - {$conn['name']}");

                if (isset($conn['test_query_result']['error'])) {
                    $io->error('测试查询失败: ' . $conn['test_query_result']['error']);
                } else {
                    $io->success('测试查询成功');
                    $io->text('执行时间: ' . $conn['test_query_result']['execution_time']);
                    $io->table(['字段', '值'], [
                        ['test_value', $conn['test_query_result']['data']['test_value']],
                        ['current_time', $conn['test_query_result']['data']['current_time']],
                        ['connection_id', $conn['test_query_result']['data']['connection_id']],
                        ['current_user', $conn['test_query_result']['data']['current_user']]
                    ]);
                }
            }
        }

        // 显示连接说明
        $io->section('📋 连接说明');
        $io->text([
            '• <info>default</info> 连接 → <info>official_website</info> 数据库 (业务数据)',
            '• <info>user</info> 连接 → <info>official_website_user</info> 数据库 (用户数据)',
            '• 默认实体管理器: <info>user</info> (用于安全组件)',
            '• 使用 --detailed 选项查看更多详细信息',
            '• 使用 --test-query 选项执行测试查询',
            '• 使用 --json 选项以JSON格式输出'
        ]);

        if ($hasErrors) {
            $io->warning('检测到连接错误，请检查数据库配置和网络连接。');
        } else {
            $io->success('所有连接状态正常！');
        }
    }
}
