<?php

require_once 'vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;

// 测试当前微信文章接口的返回格式
echo "=== 测试当前微信文章接口返回格式 ===\n";

$client = HttpClient::create();
$url = 'https://127.0.0.1:8000/official-api/wechat/articles?page=1&size=10';

try {
    echo "请求URL: $url\n";
    $response = $client->request('GET', $url, [
        'verify_peer' => false,
        'verify_host' => false,
    ]);

    $statusCode = $response->getStatusCode();
    echo "响应状态码: $statusCode\n";

    if ($statusCode === 200) {
        $data = $response->toArray();
        echo "\n=== 完整响应数据 ===\n";
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        echo "\n\n=== 分页信息分析 ===\n";
        if (isset($data['data'])) {
            $paginationFields = [];
            foreach ($data['data'] as $key => $value) {
                if (in_array($key, ['page', 'size', 'total', 'pages', 'current', 'pageSize'])) {
                    $paginationFields[$key] = $value;
                }
            }

            echo "当前分页字段:\n";
            foreach ($paginationFields as $key => $value) {
                echo "  $key: $value\n";
            }

            echo "\n用户期望的分页字段:\n";
            echo "  page: " . ($paginationFields['page'] ?? 'N/A') . "\n";
            echo "  size: " . ($paginationFields['size'] ?? 'N/A') . "\n";
            echo "  total: " . ($paginationFields['total'] ?? 'N/A') . "\n";

            echo "\n需要移除的多余字段:\n";
            $extraFields = array_diff_key($paginationFields, array_flip(['page', 'size', 'total']));
            foreach ($extraFields as $key => $value) {
                echo "  - $key: $value\n";
            }
        }
    } else {
        echo "请求失败: $statusCode\n";
        echo $response->getContent(false);
    }

} catch (\Exception $e) {
    echo "请求异常: " . $e->getMessage() . "\n";
}

echo "\n=== 测试完成 ===\n";
