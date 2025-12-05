<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;

echo "=== 简单微信API测试 ===<br>\n";

try {
    // 直接测试微信API
    $client = HttpClient::create();

    // 1. 获取access_token
    $appId = 'wx9248416064fab130';
    $appSecret = '60401298c80bcd3cfd8745f117e01b14';

    echo "1. 测试获取access_token...<br>\n";
    $tokenResponse = $client->request('GET', 'https://api.weixin.qq.com/cgi-bin/token', [
        'query' => [
            'grant_type' => 'client_credential',
            'appid' => $appId,
            'secret' => $appSecret,
        ]
    ]);

    $tokenData = $tokenResponse->toArray();
    echo "Token响应: <pre>" . json_encode($tokenData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre><br>\n";

    if (!isset($tokenData['access_token'])) {
        echo "❌ 获取access_token失败<br>\n";
        exit;
    }

    $accessToken = $tokenData['access_token'];
    echo "✅ 获取access_token成功<br>\n";

    // 2. 测试获取已发布消息
    echo "<br>2. 测试获取已发布消息...<br>\n";
    $publishResponse = $client->request('POST', 'https://api.weixin.qq.com/cgi-bin/freepublish/batchget', [
        'query' => ['access_token' => $accessToken],
        'json' => [
            'offset' => 0,
            'count' => 5,
            'no_content' => 0
        ]
    ]);

    $publishData = $publishResponse->toArray();
    echo "已发布消息响应: <pre>" . json_encode($publishData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre><br>\n";

    if (isset($publishData['errcode']) && $publishData['errcode'] !== 0) {
        echo "❌ 获取已发布消息失败: " . $publishData['errmsg'] . "<br>\n";
    } else {
        echo "✅ 获取已发布消息成功<br>\n";
        if (isset($publishData['item']) && !empty($publishData['item'])) {
            echo "📊 获取到 " . count($publishData['item']) . " 条消息<br>\n";
        } else {
            echo "📊 没有获取到消息<br>\n";
        }
    }

    // 3. 测试获取素材库
    echo "<br>3. 测试获取素材库...<br>\n";
    $materialResponse = $client->request('POST', 'https://api.weixin.qq.com/cgi-bin/material/batchget_material', [
        'query' => ['access_token' => $accessToken],
        'json' => [
            'type' => 'news',
            'offset' => 0,
            'count' => 5
        ]
    ]);

    $materialData = $materialResponse->toArray();
    echo "素材库响应: <pre>" . json_encode($materialData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre><br>\n";

    if (isset($materialData['errcode']) && $materialData['errcode'] !== 0) {
        echo "❌ 获取素材库失败: " . $materialData['errmsg'] . "<br>\n";
    } else {
        echo "✅ 获取素材库成功<br>\n";
        if (isset($materialData['item']) && !empty($materialData['item'])) {
            echo "📊 获取到 " . count($materialData['item']) . " 个素材<br>\n";
        } else {
            echo "📊 没有获取到素材<br>\n";
        }
    }

} catch (Exception $e) {
    echo "❌ 测试过程中发生异常: " . $e->getMessage() . "<br>\n";
    echo "堆栈跟踪: <pre>" . $e->getTraceAsString() . "</pre><br>\n";
}

echo "<br>=== 测试完成 ===<br>\n";
