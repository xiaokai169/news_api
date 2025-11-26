<?php

namespace App\OpenApi\Operations;

use App\OpenApi\Schemas\SysNewsArticleCategorySchemas;
use OpenApi\Attributes as OA;

class SysNewsArticleCategoryOperations
{
    /**
     * 获取分类列表操作
     */
    public static function list(): OA\Get
    {
        return new OA\Get(
            description: '获取系统文章分类列表（支持分页）',
            summary: '获取文章分类列表',
            tags: ['系统文章分类管理']
        );
    }

    /**
     * 获取分类列表参数
     */
    public static function listParameters(): array
    {
        return [
            new OA\Parameter(
                name: 'page',
                description: '页码（从1开始）',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 1)
            ),
            new OA\Parameter(
                name: 'limit',
                description: '每页数量',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 20, maximum: 100, minimum: 1)
            )
        ];
    }

    /**
     * 获取分类列表响应
     */
    public static function listResponses(): array
    {
        return [
            new OA\Response(
                response: 200,
                description: '获取成功',
                content: SysNewsArticleCategorySchemas::listResponse()
            )
        ];
    }

    /**
     * 获取单个分类操作
     */
    public static function show(): OA\Get
    {
        return new OA\Get(
            description: '根据ID获取单个文章分类的详细信息',
            summary: '获取单个分类',
            tags: ['系统文章分类管理']
        );
    }

    /**
     * 获取单个分类参数
     */
    public static function showParameters(): array
    {
        return [
            new OA\Parameter(
                name: 'id',
                description: '分类ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ];
    }

    /**
     * 获取单个分类响应
     */
    public static function showResponses(): array
    {
        return [
            new OA\Response(
                response: 200,
                description: '获取成功',
                content: SysNewsArticleCategorySchemas::showResponse()
            ),
            new OA\Response(response: 404, description: '分类不存在')
        ];
    }

    /**
     * 创建分类操作
     */
    public static function create(): OA\Post
    {
        return new OA\Post(
            description: '创建新的文章分类',
            summary: '创建文章分类',
            tags: ['系统文章分类管理']
        );
    }

    /**
     * 创建分类请求体
     */
    public static function createRequestBody(): OA\RequestBody
    {
        return new OA\RequestBody(
            description: '分类数据',
            required: true,
            content: SysNewsArticleCategorySchemas::createRequestBody()
        );
    }

    /**
     * 创建分类响应
     */
    public static function createResponses(): array
    {
        return [
            new OA\Response(
                response: 201,
                description: '创建成功',
                content: SysNewsArticleCategorySchemas::createResponse()
            ),
            new OA\Response(response: 400, description: '验证失败或分类已存在'),
            new OA\Response(response: 500, description: '服务器错误')
        ];
    }

    /**
     * 更新分类操作
     */
    public static function update(): OA\Put
    {
        return new OA\Put(
            description: '更新文章分类信息',
            summary: '更新文章分类',
            tags: ['系统文章分类管理']
        );
    }

    /**
     * 更新分类参数
     */
    public static function updateParameters(): array
    {
        return [
            new OA\Parameter(
                name: 'id',
                description: '分类ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ];
    }

    /**
     * 更新分类请求体
     */
    public static function updateRequestBody(): OA\RequestBody
    {
        return new OA\RequestBody(
            description: '分类数据',
            content: SysNewsArticleCategorySchemas::updateRequestBody()
        );
    }

    /**
     * 更新分类响应
     */
    public static function updateResponses(): array
    {
        return [
            new OA\Response(
                response: 200,
                description: '更新成功',
                content: SysNewsArticleCategorySchemas::updateResponse()
            ),
            new OA\Response(response: 400, description: '验证失败或分类已存在'),
            new OA\Response(response: 404, description: '分类不存在'),
            new OA\Response(response: 500, description: '服务器错误')
        ];
    }

    /**
     * 删除分类操作
     */
    public static function delete(): OA\Delete
    {
        return new OA\Delete(
            description: '删除指定的文章分类',
            summary: '删除文章分类',
            tags: ['系统文章分类管理']
        );
    }

    /**
     * 删除分类参数
     */
    public static function deleteParameters(): array
    {
        return [
            new OA\Parameter(
                name: 'id',
                description: '分类ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ];
    }

    /**
     * 删除分类响应
     */
    public static function deleteResponses(): array
    {
        return [
            new OA\Response(
                response: 200,
                description: '删除成功',
                content: SysNewsArticleCategorySchemas::deleteResponse()
            ),
            new OA\Response(response: 404, description: '分类不存在'),
            new OA\Response(response: 500, description: '服务器错误')
        ];
    }
}
