<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

class JwtService
{
    private string $secretKey;

    public function __construct()
    {
        // 从环境变量获取密钥，如果没有则使用默认值
        $this->secretKey = $_ENV['JWT_SECRET_KEY'] ?? 'your-secret-key-change-in-production';
    }

    /**
     * 解析JWT token并返回payload
     */
    public function decodeToken(string $token): ?array
    {
        try {
            // 移除Bearer前缀（如果存在）
            $token = str_replace('Bearer ', '', $token);

            // 简单的JWT解析（仅用于演示，生产环境应使用专业的JWT库）
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }

            // 解析payload部分
            $payload = json_decode(base64_decode($parts[1]), true);
            if (!$payload) {
                return null;
            }

            // 检查是否过期
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return null;
            }

            return $payload;
        } catch (\Exception $e) {
            // 其他错误
            return null;
        }
    }

    /**
     * 从token中提取userId
     */
    public function getUserIdFromToken(string $token): ?int
    {
        $payload = $this->decodeToken($token);

        if (!$payload || !isset($payload['userId'])) {
            return null;
        }

        return (int) $payload['userId'];
    }

    /**
     * 从HTTP请求头中获取token
     */
    public function getTokenFromRequest(Request $request): ?string
    {
        // 从Authorization header获取token
        $authHeader = $request->headers->get('Authorization');

        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * 从请求中直接获取userId
     */
    public function getUserIdFromRequest(Request $request): ?int
    {
        $token = $this->getTokenFromRequest($request);

        if (!$token) {
            return null;
        }

        return $this->getUserIdFromToken($token);
    }

    /**
     * 生成JWT token（用于测试）
     */
    public function generateToken(array $payload, int $expiresIn = 3600): string
    {
        $payload['iat'] = time();
        $payload['exp'] = time() + $expiresIn;

        // 简单的JWT生成（仅用于演示，生产环境应使用专业的JWT库）
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($payload);

        $headerEncoded = base64_encode($header);
        $payloadEncoded = base64_encode($payload);

        $signature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, $this->secretKey, true);
        $signatureEncoded = base64_encode($signature);

        return $headerEncoded . "." . $payloadEncoded . "." . $signatureEncoded;
    }
}
