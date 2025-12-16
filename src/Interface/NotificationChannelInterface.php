<?php

namespace App\Interface;

use App\DTO\NotificationMessage;

/**
 * 通知渠道接口
 */
interface NotificationChannelInterface
{
    /**
     * 发送通知
     */
    public function send(NotificationMessage $message): bool;

    /**
     * 检查渠道可用性
     */
    public function isAvailable(): bool;

    /**
     * 获取渠道名称
     */
    public function getChannelName(): string;

    /**
     * 获取渠道优先级
     */
    public function getPriority(): int;

    /**
     * 支持的消息类型
     */
    public function getSupportedMessageTypes(): array;

    /**
     * 获取渠道配置
     */
    public function getConfig(): array;

    /**
     * 设置渠道配置
     */
    public function setConfig(array $config): void;

    /**
     * 获取最后一次错误信息
     */
    public function getLastError(): ?string;

    /**
     * 重试发送失败的通知
     */
    public function retry(NotificationMessage $message, int $attempt): bool;

    /**
     * 批量发送通知
     */
    public function sendBatch(array $messages): array;

    /**
     * 验证消息格式
     */
    public function validateMessage(NotificationMessage $message): bool;

    /**
     * 获取发送统计信息
     */
    public function getStats(): array;
}
