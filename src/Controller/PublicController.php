<?php

namespace App\Controller;

use App\Entity\SysNewsArticle;
use App\Entity\Official;
use App\Http\ApiResponse;
use App\Repository\SysNewsArticleRepository;
use App\Repository\OfficialRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/public-api')]
class PublicController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SysNewsArticleRepository $newsRepository,
        private readonly OfficialRepository $wechatRepository,
        private readonly ValidatorInterface $validator,
        private readonly ApiResponse $apiResponse
    ) {
    }

    /**
     * 获取文章列表（支持新闻和公众号两种类型）
     */
    #[Route('/articles', name: 'public_articles_list', methods: ['GET'])]
    #[OA\Get(
        path: '/public-api/articles',
        summary: '获取公共文章列表',
        tags: ['公共接口'],
        parameters: [
            new OA\Parameter(
                name: 'type',
                description: '文章类型：news（新闻）或 wechat（公众号）',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string', enum: ['news', 'wechat'])
            ),
            new OA\Parameter(
                name: 'limit',
                description: '每页数量，默认20，最大100',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)
            ),
            new OA\Parameter(
                name: 'page',
                description: '页码，默认1',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1)
            )
        ]
    )]
    #[OA\Response(
        response: 200,
        description: '成功返回文章列表',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'items', type: 'array', items: new OA\Items()),
                    new OA\Property(property: 'total', type: 'integer'),
                    new OA\Property(property: 'page', type: 'integer'),
                    new OA\Property(property: 'limit', type: 'integer'),
                    new OA\Property(property: 'pages', type: 'integer')
                ])
            ]
        )
    )]
    public function getArticles(Request $request): JsonResponse
    {
        try {
            // 添加调试日志
            error_log('[DEBUG] PublicController::getArticles - 访问公共文章列表接口');
            error_log('[DEBUG] PublicController::getArticles - 请求路径: ' . $request->getPathInfo());
            error_log('[DEBUG] PublicController::getArticles - 完整URL: ' . $request->getUri());

            // 获取并验证参数
            $type = $request->query->get('type');
            $limit = max(1, min(100, (int)$request->query->get('limit', 20)));
            $page = max(1, (int)$request->query->get('page', 1));

            // 验证类型参数
            if (!in_array($type, ['news', 'wechat'])) {
                return $this->apiResponse->error('文章类型必须是 news 或 wechat', Response::HTTP_BAD_REQUEST);
            }

            // 创建参数约束
            $constraints = new Assert\Collection([
                'type' => [new Assert\NotBlank(), new Assert\Choice(['choices' => ['news', 'wechat']])],
                'limit' => [new Assert\Type(['type' => 'integer']), new Assert\Range(['min' => 1, 'max' => 100])],
                'page' => [new Assert\Type(['type' => 'integer']), new Assert\Range(['min' => 1])]
            ]);

            $input = [
                'type' => $type,
                'limit' => $limit,
                'page' => $page
            ];

            // 验证输入参数
            $violations = $this->validator->validate($input, $constraints);
            if (count($violations) > 0) {
                $errorMessages = [];
                foreach ($violations as $violation) {
                    $errorMessages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
                }
                return $this->apiResponse->error(implode(', ', $errorMessages), Response::HTTP_BAD_REQUEST);
            }

            $offset = ($page - 1) * $limit;

            if ($type === 'news') {
                // 获取新闻文章列表
                $articles = $this->newsRepository->findActivePublicArticles($limit, $offset);
                $total = $this->newsRepository->countActivePublicArticles();

                // 序列化新闻文章数据
                $items = [];
                foreach ($articles as $article) {
                    $items[] = [
                        'id' => $article->getId(),
                        'title' => $article->getName(),
                        'cover' => $article->getCover(),
                        'summary' => $article->getContent(),
                        'releaseTime' => $article->getReleaseTime()?->format('Y-m-d H:i:s'),
                        'category' => [
                            'id' => $article->getCategory()?->getId(),
                            'name' => $article->getCategory()?->getName()
                        ],
                        'isRecommend' => $article->isRecommend(),
                        'perfect' => $article->getPerfect(),
                        'createTime' => $article->getCreateTime()?->format('Y-m-d H:i:s')
                    ];
                }
            } else {
                // 获取公众号文章列表
                $articles = $this->wechatRepository->findActiveArticles($limit, $offset);
                $total = $this->wechatRepository->countActiveArticles();

                // 序列化公众号文章数据
                $items = [];
                foreach ($articles as $article) {
                    $items[] = [
                        'id' => $article->getId(),
                        'title' => $article->getTitle(),
                        'content' => $article->getContent(),
                        'releaseTime' => $article->getReleaseTime(),
                        'originalUrl' => $article->getOriginalUrl(),
                        'articleId' => $article->getArticleId(),
                        'createTime' => $article->getCreateAt()->format('Y-m-d H:i:s'),
                        'category' => [
                            'id' => $article->getCategory()?->getId(),
                            'name' => $article->getCategory()?->getName()
                        ]
                    ];
                }
            }

            $pages = (int)ceil($total / $limit);

            return $this->apiResponse->success([
                'items' => $items,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => $pages,
                'type' => $type
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            error_log('[ERROR] PublicController::getArticles - ' . $e->getMessage());
            return $this->apiResponse->error('获取文章列表失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 获取新闻文章详情（公共接口）
     */
    #[Route('/news/{id}', name: 'public_news_detail', methods: ['GET'])]
    #[OA\Get(
        path: '/public-api/news/{id}',
        summary: '获取新闻文章详情',
        tags: ['公共接口'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: '文章ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ]
    )]
    public function getNewsDetail(Request $request, int $id): JsonResponse
    {
        try {
            error_log('[DEBUG] PublicController::getNewsDetail - 访问公共新闻详情接口，ID: ' . $id);
            error_log('[DEBUG] PublicController::getNewsDetail - 请求路径: ' . $request->getPathInfo());

            $article = $this->newsRepository->find($id);

            if (!$article) {
                return $this->apiResponse->error('文章不存在', Response::HTTP_NOT_FOUND);
            }

            if ($article->isDeleted()) {
                return $this->apiResponse->error('文章已被删除', Response::HTTP_NOT_FOUND);
            }

            if (!$article->isPublished()) {
                return $this->apiResponse->error('文章尚未发布', Response::HTTP_NOT_FOUND);
            }

            $detail = [
                'id' => $article->getId(),
                'title' => $article->getName(),
                'cover' => $article->getCover(),
                'content' => $article->getContent(),
                'releaseTime' => $article->getReleaseTime()?->format('Y-m-d H:i:s'),
                'originalUrl' => $article->getOriginalUrl(),
                'category' => [
                    'id' => $article->getCategory()?->getId(),
                    'name' => $article->getCategory()?->getName()
                ],
                'isRecommend' => $article->isRecommend(),
                'perfect' => $article->getPerfect(),
                'createTime' => $article->getCreateTime()?->format('Y-m-d H:i:s'),
                'updateTime' => $article->getUpdateTime()?->format('Y-m-d H:i:s')
            ];

            return $this->apiResponse->success($detail, Response::HTTP_OK);

        } catch (\Exception $e) {
            error_log('[ERROR] PublicController::getNewsDetail - ' . $e->getMessage());
            return $this->apiResponse->error('获取文章详情失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 获取公众号文章详情（公共接口）
     */
    #[Route('/wechat/{id}', name: 'public_wechat_detail', methods: ['GET'])]
    #[OA\Get(
        path: '/public-api/wechat/{id}',
        summary: '获取公众号文章详情',
        tags: ['公共接口'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: '文章ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ]
    )]
    public function getWechatDetail(Request $request, int $id): JsonResponse
    {
        try {
            error_log('[DEBUG] PublicController::getWechatDetail - 访问公共微信文章详情接口，ID: ' . $id);
            error_log('[DEBUG] PublicController::getWechatDetail - 请求路径: ' . $request->getPathInfo());

            $article = $this->wechatRepository->find($id);

            if (!$article) {
                return $this->apiResponse->error('文章不存在', Response::HTTP_NOT_FOUND);
            }

            $detail = [
                'id' => $article->getId(),
                'title' => $article->getTitle(),
                'content' => $article->getContent(),
                'releaseTime' => $article->getReleaseTime(),
                'originalUrl' => $article->getOriginalUrl(),
                'articleId' => $article->getArticleId(),
                'createTime' => $article->getCreateAt()->format('Y-m-d H:i:s'),
                'updateTime' => $article->getUpdatedAt()->format('Y-m-d H:i:s'),
                'category' => [
                    'id' => $article->getCategory()?->getId(),
                    'name' => $article->getCategory()?->getName()
                ]
            ];

            return $this->apiResponse->success($detail, Response::HTTP_OK);

        } catch (\Exception $e) {
            error_log('[ERROR] PublicController::getWechatDetail - ' . $e->getMessage());
            return $this->apiResponse->error('获取文章详情失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
