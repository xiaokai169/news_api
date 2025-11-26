<?php

namespace App\OpenApi\Operations;

use App\Entity\SysNewsArticle;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

class NewsOperations
{
    /**
     * 创建新闻文章操作配置
     */
    public static function create(): array
    {
        return [
            'description' => '创建新的新闻文章，支持预约发布',
            'summary' => '创建新闻文章',
            'tags' => ['新闻文章'],
            'requestBody' => [
                'description' => '新闻文章数据',
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string', 'description' => '文章名称（必填，最大10字符）'],
                                'cover' => ['type' => 'string', 'description' => '封面图片（必填）'],
                                'content' => ['type' => 'string', 'description' => '文章内容（必填，最大255字符）'],
                                'category' => ['type' => 'integer', 'description' => '分类ID（必填）'],
                                'perfect' => ['type' => 'string', 'description' => '完善内容（最大255字符）'],
                                'status' => ['type' => 'integer', 'description' => '状态（1=激活，2=非激活）'],
                                'isRecommend' => ['type' => 'boolean', 'description' => '是否推荐'],
                                'releaseTime' => ['type' => 'string', 'description' => '发布时间（支持预约发布）'],
                                'originalUrl' => ['type' => 'string', 'description' => '原文链接'],
                                'merchantId' => ['type' => 'integer', 'description' => '商户ID'],
                                'userId' => ['type' => 'integer', 'description' => '用户ID']
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                '201' => [
                    'description' => '创建成功',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'code' => ['type' => 'integer', 'example' => 201],
                                    'message' => ['type' => 'string', 'example' => '创建成功'],
                                    'data' => ['$ref' => '#/components/schemas/SysNewsArticle']
                                ]
                            ]
                        ]
                    ]
                ],
                '400' => ['description' => '验证失败'],
                '404' => ['description' => '分类不存在'],
                '500' => ['description' => '服务器错误']
            ]
        ];
    }

    /**
     * 获取新闻文章列表操作
     */
    public static function list(): array
    {
        return [
            new OA\Get(
                description: '获取新闻文章列表，支持多条件查询和分页',
                summary: '获取新闻文章列表',
                tags: ['新闻文章']
            ),
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
            ),
            new OA\Parameter(
                name: 'merchantId',
                description: '商户ID',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'userId',
                description: '用户ID',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'status',
                description: '状态（1=激活，2=非激活）',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'isRecommend',
                description: '是否推荐',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean')
            ),
            new OA\Parameter(
                name: 'categoryCode',
                description: '分类ID',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'name',
                description: '文章名称搜索',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'publishStatus',
                description: '发布状态（published=已发布，scheduled=待发布）',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'sortBy',
                description: '排序字段（createTime, updateTime, releaseTime, id）',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', default: 'createTime')
            ),
            new OA\Parameter(
                name: 'sortOrder',
                description: '排序方向（asc, desc）',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', default: 'desc')
            ),
            new OA\Response(
                response: 200,
                description: '获取新闻文章列表成功',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'success'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'items',
                                    type: 'array',
                                    items: new OA\Items(ref: new Model(type: SysNewsArticle::class))
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
                )
            )
        ];
    }

    /**
     * 获取单个新闻文章操作
     */
    public static function show(): array
    {
        return [
            new OA\Get(
                description: '根据ID获取单个新闻文章的详细信息',
                summary: '获取单个新闻文章',
                tags: ['新闻文章']
            ),
            new OA\Parameter(
                name: 'id',
                description: '新闻文章ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Response(
                response: 200,
                description: '获取新闻文章成功',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'success'),
                        new OA\Property(property: 'data', ref: new Model(type: SysNewsArticle::class))
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: '新闻文章不存在')
        ];
    }

    /**
     * 更新新闻文章操作
     */
    public static function update(): array
    {
        return [
            new OA\Put(
                description: '更新新闻文章，支持发布时间特殊逻辑',
                summary: '更新新闻文章',
                tags: ['新闻文章']
            ),
            new OA\Parameter(
                name: 'id',
                description: '新闻文章ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\RequestBody(
                description: '新闻文章更新数据',
                required: true,
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'name', description: '文章名称（最大10字符）', type: 'string'),
                        new OA\Property(property: 'cover', description: '封面图片', type: 'string'),
                        new OA\Property(property: 'content', description: '文章内容（最大255字符）', type: 'string'),
                        new OA\Property(property: 'category', description: '分类ID', type: 'integer'),
                        new OA\Property(property: 'perfect', description: '完善内容（最大255字符）', type: 'string'),
                        new OA\Property(property: 'status', description: '状态（1=激活，2=非激活）', type: 'integer'),
                        new OA\Property(property: 'isRecommend', description: '是否推荐', type: 'boolean'),
                        new OA\Property(property: 'releaseTime', description: '发布时间', type: 'string'),
                        new OA\Property(property: 'originalUrl', description: '原文链接', type: 'string')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 200,
                description: '更新成功',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: '更新成功'),
                        new OA\Property(property: 'data', ref: new Model(type: SysNewsArticle::class))
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: '验证失败'),
            new OA\Response(response: 404, description: '新闻文章不存在'),
            new OA\Response(response: 500, description: '服务器错误')
        ];
    }

    /**
     * 删除新闻文章操作
     */
    public static function delete(): array
    {
        return [
            new OA\Delete(
                description: '逻辑删除新闻文章',
                summary: '删除新闻文章',
                tags: ['新闻文章']
            ),
            new OA\Parameter(
                name: 'id',
                description: '新闻文章ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Response(
                response: 200,
                description: '删除成功',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: '删除成功')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: '新闻文章不存在'),
            new OA\Response(response: 500, description: '服务器错误')
        ];
    }

    /**
     * 设置文章状态操作
     */
    public static function setStatus(): array
    {
        return [
            new OA\Patch(
                description: '设置新闻文章状态',
                summary: '设置新闻文章状态',
                tags: ['新闻文章']
            ),
            new OA\Parameter(
                name: 'id',
                description: '新闻文章ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\RequestBody(
                description: '状态数据',
                required: true,
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', description: '状态（1=激活，2=非激活）', type: 'integer')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 200,
                description: '状态设置成功',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: '状态设置成功'),
                        new OA\Property(property: 'data', ref: new Model(type: SysNewsArticle::class))
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: '状态值无效'),
            new OA\Response(response: 404, description: '新闻文章不存在'),
            new OA\Response(response: 500, description: '服务器错误')
        ];
    }

    /**
     * 恢复已删除的文章操作
     */
    public static function restore(): array
    {
        return [
            new OA\Patch(
                description: '恢复已删除的新闻文章',
                summary: '恢复新闻文章',
                tags: ['新闻文章']
            ),
            new OA\Parameter(
                name: 'id',
                description: '新闻文章ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Response(
                response: 200,
                description: '恢复成功',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: '恢复成功'),
                        new OA\Property(property: 'data', ref: new Model(type: SysNewsArticle::class))
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: '新闻文章不存在'),
            new OA\Response(response: 500, description: '服务器错误')
        ];
    }
}
