<?php

namespace App\Controller;

use App\DTO\Shared\PaginationDto;
use App\DTO\Shared\SortDto;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * 用于API文档生成的控制器
 * 确保PaginationDto和SortDto被包含在API文档中
 */
#[Route('/api/documentation')]
class DocumentationController extends AbstractController
{
    /**
     * 获取分页信息
     *
     * @return JsonResponse
     */
    #[Route('/pagination', methods: ['GET'], name: 'api_documentation_pagination')]
    #[OA\Get(
        tags: ['Documentation'],
        summary: '获取分页信息',
        description: '返回分页相关的信息结构',
        responses: [
            new OA\Response(
                response: 200,
                description: '成功返回分页信息',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'pagination',
                            properties: [
                                new OA\Property(property: 'page', type: 'integer', description: '当前页码'),
                                new OA\Property(property: 'limit', type: 'integer', description: '每页数量'),
                                new OA\Property(property: 'total', type: 'integer', description: '总记录数'),
                                new OA\Property(property: 'totalPages', type: 'integer', description: '总页数')
                            ],
                            type: 'object'
                        )
                    ]
                )
            )
        ]
    )]
    public function pagination(): JsonResponse
    {
        return $this->json([
            'pagination' => new PaginationDto()
        ]);
    }

    /**
     * 获取排序信息
     *
     * @return JsonResponse
     */
    #[Route('/sort', methods: ['GET'], name: 'api_documentation_sort')]
    #[OA\Get(
        tags: ['Documentation'],
        summary: '获取排序信息',
        description: '返回排序相关的信息结构',
        responses: [
            new OA\Response(
                response: 200,
                description: '成功返回排序信息',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'sort',
                            properties: [
                                new OA\Property(property: 'field', type: 'string', description: '排序字段'),
                                new OA\Property(property: 'direction', type: 'string', description: '排序方向 (asc/desc)')
                            ],
                            type: 'object'
                        )
                    ]
                )
            )
        ]
    )]
    public function sort(): JsonResponse
    {
        return $this->json([
            'sort' => new SortDto()
        ]);
    }

    /**
     * 获取分页和排序信息
     *
     * @return JsonResponse
     */
    #[Route('/pagination-sort', methods: ['GET'], name: 'api_documentation_pagination_sort')]
    #[OA\Get(
        tags: ['Documentation'],
        summary: '获取分页和排序信息',
        description: '返回分页和排序相关的信息结构',
        responses: [
            new OA\Response(
                response: 200,
                description: '成功返回分页和排序信息',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'pagination',
                            properties: [
                                new OA\Property(property: 'page', type: 'integer', description: '当前页码'),
                                new OA\Property(property: 'limit', type: 'integer', description: '每页数量'),
                                new OA\Property(property: 'total', type: 'integer', description: '总记录数'),
                                new OA\Property(property: 'totalPages', type: 'integer', description: '总页数')
                            ],
                            type: 'object'
                        ),
                        new OA\Property(
                            property: 'sort',
                            properties: [
                                new OA\Property(property: 'field', type: 'string', description: '排序字段'),
                                new OA\Property(property: 'direction', type: 'string', description: '排序方向 (asc/desc)')
                            ],
                            type: 'object'
                        )
                    ]
                )
            )
        ]
    )]
    public function paginationAndSort(): JsonResponse
    {
        return $this->json([
            'pagination' => new PaginationDto(),
            'sort' => new SortDto()
        ]);
    }
}
