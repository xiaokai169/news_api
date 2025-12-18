<?php
// TLS调试脚本
echo "=== TLS证书信息检查 ===\n";

// 检查证书文件
$certFile = __DIR__ . '/server.crt';
$keyFile = __DIR__ . '/server.key';

if (!file_exists($certFile)) {
    echo "错误: 证书文件 $certFile 不存在\n";
    exit(1);
}

if (!file_exists($keyFile)) {
    echo "错误: 私钥文件 $keyFile 不存在\n";
    exit(1);
}

// 加载证书
$certContent = file_get_contents($certFile);
$cert = openssl_x509_read($certContent);

if (!$cert) {
    echo "错误: 无法读取证书文件\n";
    exit(1);
}

// 解析证书信息
$certData = openssl_x509_parse($cert, false);

echo "证书主题:\n";
print_r($certData['subject']);

echo "\n证书颁发者:\n";
print_r($certData['issuer']);

echo "\n证书有效期:\n";
echo "生效时间: " . date('Y-m-d H:i:s', $certData['validFrom_time_t']) . "\n";
echo "过期时间: " . date('Y-m-d H:i:s', $certData['validTo_time_t']) . "\n";

echo "\n当前时间: " . date('Y-m-d H:i:s') . "\n";

// 检查是否过期
$now = time();
if ($now < $certData['validFrom_time_t']) {
    echo "⚠️  证书尚未生效\n";
} elseif ($now > $certData['validTo_time_t']) {
    echo "❌ 证书已过期\n";
} else {
    echo "✅ 证书在有效期内\n";
}

// 检查SAN (Subject Alternative Name)
if (isset($certData['extensions']['subjectAltName'])) {
    echo "\nSubject Alternative Name:\n";
    echo $certData['extensions']['subjectAltName'] . "\n";
} else {
    echo "\n⚠️  没有Subject Alternative Name扩展\n";
}

// 检查是否为自签名证书
$isSelfSigned = ($certData['subject'] == $certData['issuer']);
if ($isSelfSigned) {
    echo "⚠️  这是自签名证书\n";
} else {
    echo "✅ 这是CA签发的证书\n";
}

// 验证私钥匹配
$keyContent = file_get_contents($keyFile);
$privateKey = openssl_pkey_get_private($keyContent);

if (!$privateKey) {
    echo "❌ 私钥格式错误或无法读取\n";
} else {
    // 尝试用私钥签名并验证
    $testData = "test";
    $signature = '';
    if (openssl_sign($testData, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        $publicKey = openssl_pkey_get_public($cert);
        $verification = openssl_verify($testData, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($verification === 1) {
            echo "✅ 证书和私钥匹配\n";
        } else {
            echo "❌ 证书和私钥不匹配\n";
        }
    }
}

echo "\n=== 建议的解决方案 ===\n";
if ($isSelfSigned) {
    echo "1. 对于自签名证书，客户端需要添加证书到信任存储\n";
    echo "2. 或者在客户端禁用证书验证（仅用于开发环境）\n";
    echo "3. 或者为127.0.0.1/localhost生成本地可信证书\n";
}

if (!isset($certData['extensions']['subjectAltName'])) {
    echo "4. 重新生成证书时添加SAN扩展，包含127.0.0.1和localhost\n";
}

echo "\n=== 测试连接 ===\n";
$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    ]
]);

echo "尝试连接到 https://127.0.0.1:8000...\n";
$fp = @stream_socket_client('ssl://127.0.0.1:8000', $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context);
if ($fp) {
    echo "✅ 连接成功（禁用验证）\n";
    fclose($fp);
} else {
    echo "❌ 连接失败: $errstr\n";
}

echo "尝试连接到 https://localhost:8000...\n";
$fp = @stream_socket_client('ssl://localhost:8000', $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context);
if ($fp) {
    echo "✅ 连接成功（禁用验证）\n";
    fclose($fp);
} else {
    echo "❌ 连接失败: $errstr\n";
}
