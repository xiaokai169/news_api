<?php

namespace App\Controller;

use App\Entity\SysNewsArticle;
use App\Entity\SysNewsArticleCategory;
use App\Exception\BusinessException;
use App\Http\ApiResponse;
use App\OpenApi\Operations\NewsOperations;
use App\Repository\SysNewsArticleRepository;
use App\Repository\SysNewsArticleCategoryRepository;
use App\DTO\Request\News\CreateNewsArticleDto;
use App\DTO\Request\News\UpdateNewsArticleDto;
use App\DTO\Request\News\SetNewsStatusDto;
use App\DTO\Filter\NewsFilterDto;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use App\Service\JwtService;
use App\Service\UserReadOnlyService;

#[Route('/official-api/news')]
class NewsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SysNewsArticleRepository $sysNewsArticleRepository,
        private readonly SysNewsArticleCategoryRepository $categoryRepository,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly ApiResponse $apiResponse,
        private readonly JwtService $jwtService,
        private readonly UserReadOnlyService $userReadOnlyService
    ) {
    }

    /**
     * 获取正确的数据库连接（用于新闻文章操作）
     * 根据调试发现，Web 服务器使用 'user' 实体管理器，这是正确的配置
     */
    private function getNewsEntityManager(): EntityManagerInterface
    {
        // Web 服务器使用 'user' 实体管理器（通过 services.yaml 配置）
        // 这是正确的配置，应该继续使用
        return $this->entityManager;
    }

    /**
     * 获取新闻文章仓库（使用正确的数据库连接）
     */
    private function getNewsRepository(): SysNewsArticleRepository
    {
        return $this->sysNewsArticleRepository;
    }

    /**
     * 创建新闻文章
     */
    #[Route('', name: 'api_news_create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateNewsArticleDto $createDto,
        Request $request
    ): JsonResponse {
        try {
            // 从token中解析userId并设置到DTO
            $userId = $this->jwtService->getUserIdFromRequest($request);
            if ($userId) {
                $createDto->userId = $userId;
            }

            // 验证DTO
            $validationErrors = $this->validator->validate($createDto);
            if (count($validationErrors) > 0) {
                $errorMessages = [];
                foreach ($validationErrors as $error) {
                    $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                }
                return $this->apiResponse->error(implode(', ', $errorMessages), Response::HTTP_BAD_REQUEST);
            }

            // 验证DTO的业务规则
            $businessErrors = $createDto->validateBusinessRules();
            if (!empty($businessErrors)) {
                return $this->apiResponse->error(implode(', ', $businessErrors), Response::HTTP_BAD_REQUEST);
            }

            $category = null;
            $categoryValue = null;

            // 优先使用category字段，如果为空则使用categoryCode
            if ($createDto->category !== null) {
                $categoryValue = $createDto->category;
            } elseif (!empty($createDto->categoryCode)) {
                $categoryValue = $createDto->categoryCode;
            }

            if ($categoryValue === null) {
                return $this->apiResponse->error('文章分类不能为空', Response::HTTP_BAD_REQUEST);
            }

            // 处理不同类型的分类输入
            if (is_array($categoryValue)) {
                // 如果是数组，尝试从数组中提取ID
                if (isset($categoryValue['id'])) {
                    $category = $this->categoryRepository->find((int)$categoryValue['id']);
                }
            } elseif (is_numeric($categoryValue)) {
                // 如果是数字，直接作为ID查找
                $category = $this->categoryRepository->find((int)$categoryValue);
            } elseif (is_string($categoryValue)) {
                // 如果是字符串，先尝试作为ID查找，再尝试作为code查找
                if (is_numeric($categoryValue)) {
                    $category = $this->categoryRepository->find((int)$categoryValue);
                }

                if (!$category) {
                    $category = $this->categoryRepository->findOneBy(['code' => $categoryValue]);
                }
            }

            if (!$category) {
                return $this->apiResponse->error('指定的分类不存在', Response::HTTP_NOT_FOUND);
            }

            // 创建文章实体
            $article = new SysNewsArticle();
            $article->setName($createDto->name);
            $article->setCover($createDto->cover);
            $article->setContent($createDto->content);
            $article->setCategory($category);

            // 设置可选字段
            if (!empty($createDto->perfect)) {
                $article->setPerfect($createDto->perfect);
            }
            $article->setIsRecommend($createDto->isRecommend);
            if (!empty($createDto->originalUrl)) {
                $article->setOriginalUrl($createDto->originalUrl);
            }
            if ($createDto->merchantId > 0) {
                $article->setMerchantId($createDto->merchantId);
            }

            // 设置userId
            if ($createDto->userId > 0) {
                $article->setUserId($createDto->userId);
            }

            // 处理发布时间逻辑
            if ($createDto->releaseTime) {
                $releaseTime = $createDto->getReleaseTimeDateTime();
                if (!$releaseTime) {
                    return $this->apiResponse->error('发布时间格式不正确', Response::HTTP_BAD_REQUEST);
                }
                $article->setReleaseTime($releaseTime);
            } else {
                // 当 releaseTime 为空时，设置当前时间作为默认值
                $article->setReleaseTime(new \DateTime());
            }

            // 设置状态
            $article->setStatus($createDto->status);

            // 验证实体
            $errors = $this->validator->validate($article);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $propertyPath = $error->getPropertyPath();
                    $message = $error->getMessage();
                    $errorMessages[] = $propertyPath . ': ' . $message;
                }
                return $this->apiResponse->error(implode(', ', $errorMessages), Response::HTTP_BAD_REQUEST);
            }

            // 保存到数据库
            $this->entityManager->persist($article);
            $this->entityManager->flush();

            return $this->apiResponse->success($article, Response::HTTP_CREATED, ['groups' => ['sysNewsArticle:read']]);

        } catch (\Exception $e) {
            return $this->apiResponse->error('创建失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 获取新闻文章列表（支持多条件查询）
     */
    #[Route('', name: 'api_news_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            // 创建过滤器DTO并从查询参数填充数据
            $filterDto = new NewsFilterDto();
            $filterDto->populateFromData($request->query->all());

            // 设置分页和排序参数（优先使用查询参数）
            $filterDto->setPage($request->query->getInt('page', $filterDto->getPage()));
            $filterDto->setLimit($request->query->getInt('limit', $filterDto->getLimit()));
            $filterDto->setSortBy($request->query->get('sortBy', $filterDto->getSortBy()));
            $filterDto->setSortDirection($request->query->get('sortOrder', $filterDto->getSortDirection()));

            // 验证过滤器
            $filterErrors = $filterDto->validateFilters();
            if (!empty($filterErrors)) {
                return $this->apiResponse->error(implode(', ', $filterErrors), Response::HTTP_BAD_REQUEST);
            }

            // 使用新的 DTO 方式查询数据
            if ($filterDto->includeUser) {
                $articles = $this->sysNewsArticleRepository->findByFilterDtoWithUser($filterDto);
            } else {
                $articles = $this->sysNewsArticleRepository->findByFilterDto($filterDto);
            }

            $total = $this->sysNewsArticleRepository->countByFilterDto($filterDto);
            $pages = (int)ceil($total / $filterDto->getLimit());

            // 为每篇文章添加阅读统计信息
            $articlesWithStats = [];
            foreach ($articles as $article) {
                $articleData = $this->serializer->normalize($article, null, ['groups' => ['sysNewsArticle:read']]);

                // 添加阅读统计信息
                $articleData['viewCount'] = $article->getViewCount();
                $articleData['formattedViewCount'] = $article->getFormattedViewCount();
                $articleData['readHeatLevel'] = $article->getReadHeatLevel();
                $articleData['readHeatDescription'] = $article->getReadHeatDescription();
                $articleData['isPopular'] = $article->isPopular();
                $articleData['isExplosive'] = $article->isExplosive();

                $articlesWithStats[] = $articleData;
            }

            return $this->apiResponse->paginated(
                items: $articlesWithStats,
                total: $total,
                page: $filterDto->getPage(),
                limit: $filterDto->getLimit(),
                pages: $pages,
                request: $request
            );

        } catch (\Exception $e) {
            return $this->apiResponse->error('查询失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 获取单个新闻文章详情
     */
    #[Route('/{id}', name: 'api_news_show', methods: ['GET'])]
    public function show(int $id, Request $request): JsonResponse
    {
        // 添加调试日志
        error_log('[DEBUG] NewsController::show - 访问文章详情接口，ID: ' . $id);
        error_log('[DEBUG] NewsController::show - 当前安全配置是否禁用认证: ' . (var_dump($this->container->get('security.authorization_checker') === null ? 'true' : 'false')));

        try {
            // 是否包含用户信息
            $includeUser = $request->query->get('includeUser', 'false');
            $includeUser = filter_var($includeUser, FILTER_VALIDATE_BOOLEAN);

            if ($includeUser) {
                $article = $this->sysNewsArticleRepository->findWithUser($id);
            } else {
                $article = $this->sysNewsArticleRepository->find($id);
            }

            if (!$article) {
                return $this->apiResponse->error('新闻文章不存在', Response::HTTP_NOT_FOUND);
            }

            if ($article->isDeleted()) {
                return $this->apiResponse->error('新闻文章已被删除', Response::HTTP_NOT_FOUND);
            }

            // 添加阅读数量信息
            $articleData = $this->serializer->normalize($article, null, ['groups' => ['sysNewsArticle:read']]);

            // 添加阅读统计信息
            $articleData['viewCount'] = $article->getViewCount();
            $articleData['formattedViewCount'] = $article->getFormattedViewCount();
            $articleData['readHeatLevel'] = $article->getReadHeatLevel();
            $articleData['readHeatDescription'] = $article->getReadHeatDescription();
            $articleData['isPopular'] = $article->isPopular();
            $articleData['isExplosive'] = $article->isExplosive();

            return $this->apiResponse->success($articleData, Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->apiResponse->error('查询失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 更新新闻文章
     */
    #[Route('/{id}', name: 'api_news_update', methods: ['PUT'])]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateNewsArticleDto $updateDto
    ): JsonResponse {
        try {
            // 查找文章
            $article = $this->sysNewsArticleRepository->find($id);
            if (!$article) {
                return $this->apiResponse->error('新闻文章不存在', Response::HTTP_NOT_FOUND);
            }

            if ($article->isDeleted()) {
                return $this->apiResponse->error('新闻文章已被删除', Response::HTTP_NOT_FOUND);
            }

            // 验证DTO的业务规则
            $businessErrors = $updateDto->validateBusinessRules();
            if (!empty($businessErrors)) {
                return $this->apiResponse->error(implode(', ', $businessErrors), Response::HTTP_BAD_REQUEST);
            }

            // 检查是否有任何更新
            if (!$updateDto->hasUpdates()) {
                return $this->apiResponse->error('没有提供任何要更新的字段', Response::HTTP_BAD_REQUEST);
            }

            // 记录原始发布时间和状态
            $originalReleaseTime = $article->getReleaseTime();
            $originalStatus = $article->getStatus();

            // 更新可修改字段
            if ($updateDto->name !== null) {
                $article->setName($updateDto->name);
            }
            if ($updateDto->cover !== null) {
                $article->setCover($updateDto->cover);
            }
            if ($updateDto->content !== null) {
                $article->setContent($updateDto->content);
            }
            if ($updateDto->perfect !== null) {
                $article->setPerfect($updateDto->perfect);
            }
            if ($updateDto->isRecommend !== null) {
                $article->setIsRecommend($updateDto->isRecommend);
            }
            if ($updateDto->originalUrl !== null) {
                $article->setOriginalUrl($updateDto->originalUrl);
            }
            if ($updateDto->merchantId !== null) {
                $article->setMerchantId($updateDto->merchantId);
            }
            if ($updateDto->userId !== null) {
                $article->setUserId($updateDto->userId);
            }

            // 更新分类（如果提供）
            if ($updateDto->categoryCode !== null) {
                $category = null;

                // 先尝试通过ID查找
                if (is_numeric($updateDto->categoryCode)) {
                    $category = $this->categoryRepository->find((int)$updateDto->categoryCode);
                }

                // 如果通过ID没找到，尝试通过code查找
                if (!$category) {
                    $category = $this->categoryRepository->findOneBy(['code' => $updateDto->categoryCode]);
                }

                if (!$category) {
                    return $this->apiResponse->error('指定的分类不存在', Response::HTTP_NOT_FOUND);
                }
                $article->setCategory($category);
            }

            // 处理发布时间特殊逻辑
            $releaseTimeChanged = false;
            if ($updateDto->releaseTime !== null) {
                if (empty($updateDto->releaseTime)) {
                    // 当 releaseTime 被设置为空字符串时，设置当前时间作为默认值
                    $article->setReleaseTime(new \DateTime());
                    $releaseTimeChanged = true;
                } else {
                    $newReleaseTime = $updateDto->getReleaseTimeDateTime();
                    if (!$newReleaseTime) {
                        return $this->apiResponse->error('发布时间格式不正确', Response::HTTP_BAD_REQUEST);
                    }
                    $article->setReleaseTime($newReleaseTime);
                    $releaseTimeChanged = true;
                }
            }

            // 处理状态（手动设置状态优先级高于releaseTime逻辑）
            if ($updateDto->status !== null) {
                if (!in_array($updateDto->status, [SysNewsArticle::STATUS_ACTIVE, SysNewsArticle::STATUS_INACTIVE])) {
                    return $this->apiResponse->error('状态值无效，必须是1（激活）或2（非激活）', Response::HTTP_BAD_REQUEST);
                }
                $article->setStatus($updateDto->status);
            } elseif ($releaseTimeChanged) {
                // 如果发布时间改变但没有手动设置状态，则自动计算状态
                $article->determinePublishStatus();
            }

            // 验证实体
            $errors = $this->validator->validate($article);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->apiResponse->error(implode(', ', $errorMessages), Response::HTTP_BAD_REQUEST);
            }

            // 保存到数据库
            $this->entityManager->flush();

            return $this->apiResponse->success($article, Response::HTTP_OK, ['groups' => ['sysNewsArticle:read']]);

        } catch (\Exception $e) {
            return $this->apiResponse->error('更新失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 删除新闻文章（逻辑删除）
     */
    #[Route('/{id}', name: 'api_news_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        try {
            // 使用正确的数据库连接
            $newsEntityManager = $this->getNewsEntityManager();
            $connection = $newsEntityManager->getConnection();

            // 首先检查文章是否存在且未删除
            $checkSql = 'SELECT id, status FROM sys_news_article WHERE id = :id';
            $existingArticle = $connection->fetchAssociative($checkSql, ['id' => $id]);

            if (!$existingArticle) {
                return $this->apiResponse->error('新闻文章不存在', Response::HTTP_NOT_FOUND);
            }

            if ((int)$existingArticle['status'] === SysNewsArticle::STATUS_DELETED) {
                return $this->apiResponse->error('新闻文章已被删除', Response::HTTP_NOT_FOUND);
            }

            // 使用原生 SQL 执行删除操作
            $updateTime = (new \DateTime())->format('Y-m-d H:i:s');
            $updateSql = 'UPDATE sys_news_article SET status = :status, update_at = :updateTime WHERE id = :id';

            $result = $connection->executeStatement(
                $updateSql,
                [
                    'status' => SysNewsArticle::STATUS_DELETED,
                    'updateTime' => $updateTime,
                    'id' => $id
                ]
            );

            if ($result === 0) {
                return $this->apiResponse->error('删除失败：文章不存在或已被删除', Response::HTTP_NOT_FOUND);
            }

            return $this->apiResponse->success(['id' => $id], Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->apiResponse->error('删除失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 设置文章状态
     */
    #[Route('/{id}/status', name: 'api_news_status', methods: ['PATCH'])]
    public function setStatus(
        int $id,
        #[MapRequestPayload] SetNewsStatusDto $statusDto,
        Request $request
    ): JsonResponse {
        try {
            // 验证DTO的业务规则
            $businessErrors = $statusDto->validateBusinessRules();
            if (!empty($businessErrors)) {
                return $this->apiResponse->error(implode(', ', $businessErrors), Response::HTTP_BAD_REQUEST);
            }

            // 设置操作员ID（从token中获取）
            $operatorId = $this->jwtService->getUserIdFromRequest($request);
            if ($operatorId && !$statusDto->operatorId) {
                $statusDto->operatorId = $operatorId;
            }

            // 检查是否为批量操作
            if ($statusDto->isBatchOperation()) {
                return $this->handleBatchStatusUpdate($statusDto);
            }

            $article = $this->sysNewsArticleRepository->find($id);
            if (!$article) {
                return $this->apiResponse->error('新闻文章不存在', Response::HTTP_NOT_FOUND);
            }

            if ($article->isDeleted()) {
                return $this->apiResponse->error('新闻文章已被删除', Response::HTTP_NOT_FOUND);
            }

            // 手动设置状态，并强制更新更新时间
            $article->setStatus($statusDto->status);
            $article->setUpdateTime(new \DateTime());

            // 手动发布特殊逻辑：如果状态设为激活，则自动设置发布时间为当前时间
            if ($statusDto->isActivateOperation()) {
                $article->setReleaseTime(new \DateTime());
            }

            $this->entityManager->flush();

            return $this->apiResponse->success($article, Response::HTTP_OK, ['groups' => ['sysNewsArticle:read']]);

        } catch (\Exception $e) {
            return $this->apiResponse->error('状态更新失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 处理批量状态更新
     */
    private function handleBatchStatusUpdate(SetNewsStatusDto $statusDto): JsonResponse
    {
        try {
            $updatedArticles = [];
            $errors = [];

            foreach ($statusDto->articleIds as $articleId) {
                $article = $this->sysNewsArticleRepository->find($articleId);
                if (!$article) {
                    $errors[] = "文章ID {$articleId} 不存在";
                    continue;
                }

                if ($article->isDeleted()) {
                    $errors[] = "文章ID {$articleId} 已被删除";
                    continue;
                }

                // 设置状态
                $article->setStatus($statusDto->status);
                $article->setUpdateTime(new \DateTime());

                // 手动发布特殊逻辑
                if ($statusDto->isActivateOperation()) {
                    $article->setReleaseTime(new \DateTime());
                }

                $updatedArticles[] = $article;
            }

            if (!empty($updatedArticles)) {
                $this->entityManager->flush();
            }

            $result = [
                'updatedCount' => count($updatedArticles),
                'errorCount' => count($errors),
                'errors' => $errors,
                'operationSummary' => $statusDto->getOperationSummary()
            ];

            if (!empty($errors) && empty($updatedArticles)) {
                return $this->apiResponse->error('批量更新失败: ' . implode(', ', $errors), Response::HTTP_BAD_REQUEST);
            }

            return $this->apiResponse->success($result, Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->apiResponse->error('批量状态更新失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 恢复已删除的文章
     */
    #[Route('/{id}/restore', name: 'api_news_restore', methods: ['PATCH'])]
    public function restore(int $id): JsonResponse
    {
        try {
            $article = $this->sysNewsArticleRepository->find($id);

            if (!$article) {
                return $this->apiResponse->error('新闻文章不存在', Response::HTTP_NOT_FOUND);
            }

            if (!$article->isDeleted()) {
                return $this->apiResponse->error('文章未被删除，无需恢复', Response::HTTP_BAD_REQUEST);
            }

            $article->restore();
            $this->entityManager->flush();

            return $this->apiResponse->success($article, Response::HTTP_OK, ['groups' => ['sysNewsArticle:read']]);

        } catch (\Exception $e) {
            return $this->apiResponse->error('恢复失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 安全地解析时间字符串或时间戳为 DateTime 对象
     */
    private function parseDateTime($timeInput): ?\DateTimeInterface
    {
        try {
            // 处理空值
            if (empty($timeInput) && $timeInput !== 0 && $timeInput !== '0') {
                return null;
            }

            // 如果是 DateTime 对象，直接返回
            if ($timeInput instanceof \DateTimeInterface) {
                return $timeInput;
            }

            // 如果是数字（时间戳）
            if (is_numeric($timeInput)) {
                $timestamp = (int)$timeInput;
                // 验证时间戳是否在合理范围内（1970-2100年）
                if ($timestamp > 0 && $timestamp < 4102444800) {
                    $dateTime = new \DateTime();
                    $dateTime->setTimestamp($timestamp);
                    return $dateTime;
                }
                return null;
            }

            // 如果是字符串
            if (is_string($timeInput)) {
                // 尝试解析常见的日期时间格式
                $formats = [
                    'Y-m-d H:i:s',
                    'Y-m-d\TH:i:sP', // ISO 8601
                    'Y-m-d H:i',
                    'Y-m-d',
                    'Y/m/d H:i:s',
                    'Y/m/d H:i',
                    'Y/m/d',
                ];

                foreach ($formats as $format) {
                    $dateTime = \DateTime::createFromFormat($format, $timeInput);
                    if ($dateTime !== false) {
                        return $dateTime;
                    }
                }

                // 如果特定格式都失败，尝试 strtotime
                $timestamp = strtotime($timeInput);
                if ($timestamp !== false) {
                    $dateTime = new \DateTime();
                    $dateTime->setTimestamp($timestamp);
                    return $dateTime;
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
