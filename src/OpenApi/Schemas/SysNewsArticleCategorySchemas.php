<?php

namespace App\OpenApi\Schemas;

use App\Entity\SysNewsArticleCategory;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

class SysNewsArticleCategorySchemas
{
    /**
     * 获取分类列表响应模型
     */
    public static function listResponse(): OA\JsonContent
    {
        return new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'success'),
                new OA\Property(
                    property: 'data',
                    properties: [
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(ref: new Model(type: SysNewsArticleCategory::class))
                        ),
                        new OA\Property(property: 'total', description: '总记录数', type: 'integer', example: 100),
                        new OA\Property(property: 'page', description: '当前页码', type: 'integer', example: 1),
                        new OA\Property(property: 'limit', description: '每页数量', type: 'integer', example: 20),
                        new OA\Property(property: 'pages', description: '总页数', type: 'integer', example: 5)
                    ],
                    type: 'object'
                )
            ],
            type: 'object'
        );
    }

    /**
     * 获取单个分类响应模型
     */
    public static function showResponse(): OA\JsonContent
    {
        return new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: SysNewsArticleCategory::class))
            ],
            type: 'object'
        );
    }

    /**
     * 创建分类请求体模型
     */
    public static function createRequestBody(): OA\JsonContent
    {
        return new OA\JsonContent(
            required: ['code', 'name'],
            properties: [
                new OA\Property(property: 'code', description: '分类编码', type: 'string', example: 'NEWS'),
                new OA\Property(property: 'name', description: '分类名称', type: 'string', example: '新闻'),
                new OA\Property(property: 'creator', description: '添加人', type: 'string', example: 'admin')
            ],
            type: 'object'
        );
    }

    /**
     * 创建分类响应模型
     */
    public static function createResponse(): OA\JsonContent
    {
        return new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'integer', example: 201),
                new OA\Property(property: 'message', type: 'string', example: '创建成功'),
                new OA\Property(property: 'data', ref: new Model(type: SysNewsArticleCategory::class))
            ],
            type: 'object'
        );
    }

    /**
     * 更新分类请求体模型
     */
    public static function updateRequestBody(): OA\JsonContent
    {
        return new OA\JsonContent(
            required: ['code', 'name'],
            properties: [
                new OA\Property(property: 'code', description: '分类编码', type: 'string'),
                new OA\Property(property: 'name', description: '分类名称', type: 'string'),
                new OA\Property(property: 'creator', description: '添加人', type: 'string')
            ],
            type: 'object'
        );
    }

    /**
     * 更新分类响应模型
     */
    public static function updateResponse(): OA\JsonContent
    {
        return new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: '更新成功'),
                new OA\Property(property: 'data', ref: new Model(type: SysNewsArticleCategory::class))
            ],
            type: 'object'
        );
    }

    /**
     * 删除分类响应模型
     */
    public static function deleteResponse(): OA\JsonContent
    {
        return new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: '删除成功')
            ],
            type: 'object'
        );
    }
}
