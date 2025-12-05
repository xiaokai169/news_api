<?php
echo "=== 快速诊断微信同步错误 ===\n\n";

// 1. 检查服务器IP
echo "1. 服务器IP检查:\n";
$externalIp = @file_get_contents('https://api.ipify.org');
if ($externalIp) {
    echo "   外网IP: $externalIp\n";
} else {
    echo "   无法获取外网IP\n";
}

// 2. 测试微信API连接
echo "\n2. 微信API连接测试:\n";
$context = stream_context_create([
    'http' => [
        'timeout' => 5
    ]
]);
$response = @file_get_contents('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=test&secret=test', false, $context);
if ($response) {
    $data = json_decode($response, true);
    if (isset($data['errcode']) && $data['errcode'] === 40013) {
        echo "   ✅ 微信API连接正常\n";
    } elseif (isset($data['errmsg'])) {
        echo "   ⚠️ 微信API响应: " . $data['errmsg'] . "\n";
    }
} else {
    echo "   ❌ 无法连接微信API\n";
}

// 3. 检查数据库连接
echo "\n3. 数据库连接测试:\n";
try {
    require_once __DIR__ . '/../vendor/autoload.php';
    $kernel = new App\Kernel('dev', true);
    $kernel->boot();
    $connection = $kernel->getContainer()->get('doctrine.orm.entity_manager')->getConnection();
    $connection->connect();
    echo "   ✅ 数据库连接正常\n";

    // 检查分布式锁表
    $sql = "SELECT COUNT(*) as cnt FROM distributed_locks";
    $stmt = $connection->prepare($sql);
    $result = $stmt->executeQuery();
    $count = $result->fetchAssociative()['cnt'];
    echo "   分布式锁表存在，当前锁数量: $count\n";

    $kernel->shutdown();
} catch (Exception $e) {
    echo "   ❌ 数据库连接失败: " . $e->getMessage() . "\n";
}

// 4. 基于错误信息的诊断
echo "\n4. 错误分析:\n";
echo "   原始错误: 'invalid ip 1.92.157.200 ipv6 ::ffff:1.92.157.200, not in whitelist'\n";
echo "   诊断: 这表明服务器IP (1.92.157.200) 不在微信公众号的IP白名单中\n\n";

echo "=== 解决方案 ===\n";
echo "1. 登录微信公众平台 (mp.weixin.qq.com)\n";
echo "2. 进入: 开发 -> 基本配置 -> IP白名单\n";
echo "3. 添加以下IP到白名单:\n";
echo "   - 1.92.157.200\n";
if ($externalIp && $externalIp !== '1.92.157.200') {
    echo "   - $externalIp\n";
}
echo "\n4. 添加完成后等待5-10分钟生效\n";
echo "5. 重新运行同步任务\n\n";

echo "=== 分布式锁问题 ===\n";
echo "如果IP白名单配置后仍有锁问题，运行:\n";
echo "curl http://127.0.0.1:8084/cleanup_expired_locks.php\n\n";

echo "=== 诊断完成 ===\n";
