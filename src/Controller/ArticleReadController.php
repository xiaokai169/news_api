<?php

namespace App\Controller;

use App\Entity\SysNewsArticle;
use App\Http\ApiResponse;
use App\Service\ArticleReadService;
use App\Service\JwtService;
use App\DTO\Request\ArticleReadLogDto;
use App\DTO\Request\BatchArticleReadLogDto;
use App\DTO\Request\CleanupReadLogsDto;
use App\DTO\Filter\ArticleReadFilterDto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * 文章阅读记录控制器
 *
 * 提供文章阅读记录、统计和分析的API接口
 */
#[Route('/official-api/article-read')]
class ArticleReadController extends AbstractController
{
    public function __construct(
        private readonly ArticleReadService $articleReadService,
        private readonly ValidatorInterface $validator,
        private readonly ApiResponse $apiResponse,
        private readonly JwtService $jwtService
    ) {
    }

    /**
     * 记录文章阅读
     */
    #[Route('', name: 'api_article_read_log', methods: ['POST'])]
    public function logRead(
        #[MapRequestPayload] ArticleReadLogDto $readLogDto,
        Request $request
    ): JsonResponse {
        try {
            // 从请求头获取额外信息
            $this->enrichReadLogDto($readLogDto, $request);

            // 验证DTO
            $validationErrors = $this->validator->validate($readLogDto);
            if (count($validationErrors) > 0) {
                $errorMessages = [];
                foreach ($validationErrors as $error) {
                    $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                }
                return $this->apiResponse->error(implode(', ', $errorMessages), Response::HTTP_BAD_REQUEST);
            }

            // 验证业务规则
            $businessErrors = $readLogDto->validateBusinessRules();
            if (!empty($businessErrors)) {
                return $this->apiResponse->error(implode(', ', $businessErrors), Response::HTTP_BAD_REQUEST);
            }

            // 记录阅读
            $result = $this->articleReadService->logArticleRead($readLogDto);

            if ($result['success']) {
                return $this->apiResponse->success($result, Response::HTTP_CREATED);
            } else {
                return $this->apiResponse->error($result['message'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

        } catch (\Exception $e) {
            return $this->apiResponse->error('记录失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 批量记录文章阅读
     */
    #[Route('/batch', name: 'api_article_read_batch', methods: ['POST'])]
    public function batchLogRead(
        #[MapRequestPayload] BatchArticleReadLogDto $batchDto,
        Request $request
    ): JsonResponse {
        try {
            // 验证DTO
            $validationErrors = $this->validator->validate($batchDto);
            if (count($validationErrors) > 0) {
                $errorMessages = [];
                foreach ($validationErrors as $error) {
                    $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                }
                return $this->apiResponse->error(implode(', ', $errorMessages), Response::HTTP_BAD_REQUEST);
            }

            // 验证业务规则
            $businessErrors = $batchDto->validateBusinessRules();
            if (!empty($businessErrors)) {
                return $this->apiResponse->error(implode(', ', $businessErrors), Response::HTTP_BAD_REQUEST);
            }

            // 转换为DTO对象并验证
            $validDtos = [];
            foreach ($batchDto->getReadLogs() as $index => $data) {
                $dto = new ArticleReadLogDto($data);
                $this->enrichReadLogDto($dto, $request);

                $validationErrors = $this->validator->validate($dto);
                if (count($validationErrors) > 0) {
                    $errorMessages = [];
                    foreach ($validationErrors as $error) {
                        $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                    }
                    return $this->apiResponse->error("第{$index}条数据验证失败: " . implode(', ', $errorMessages), Response::HTTP_BAD_REQUEST);
                }

                $businessErrors = $dto->validateBusinessRules();
                if (!empty($businessErrors)) {
                    return $this->apiResponse->error("第{$index}条数据业务验证失败: " . implode(', ', $businessErrors), Response::HTTP_BAD_REQUEST);
                }

                $validDtos[] = $dto;
            }

            // 批量记录
            $result = $this->articleReadService->batchLogArticleReads($validDtos);

            if ($result['success'] > 0) {
                $statusCode = $result['failed'] > 0 ? Response::HTTP_PARTIAL_CONTENT : Response::HTTP_CREATED;
                return $this->apiResponse->success($result, $statusCode);
            } else {
                return $this->apiResponse->error('批量记录失败', Response::HTTP_INTERNAL_SERVER_ERROR);
            }

        } catch (\Exception $e) {
            return $this->apiResponse->error('批量记录失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 获取文章阅读统计
     */
    #[Route('/statistics', name: 'api_article_read_statistics', methods: ['GET'])]
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            // 创建过滤器DTO并从查询参数填充数据
            $filterDto = new ArticleReadFilterDto();
            $filterDto->populateFromData($request->query->all());

            // 设置分页和排序参数
            $filterDto->setPage($request->query->getInt('page', $filterDto->getPage()));
            $filterDto->setLimit($request->query->getInt('limit', $filterDto->getLimit()));
            $filterDto->setSortBy($request->query->get('sortBy', $filterDto->getSortBy()));
            $filterDto->setSortDirection($request->query->get('sortOrder', $filterDto->getSortDirection()));

            // 验证过滤器
            $filterErrors = $filterDto->validateFilters();
            if (!empty($filterErrors)) {
                return $this->apiResponse->error(implode(', ', $filterErrors), Response::HTTP_BAD_REQUEST);
            }

            // 获取统计数据
            $result = $this->articleReadService->getArticleReadStatistics($filterDto);

            if ($result['success']) {
                return $this->apiResponse->success($result['data'], Response::HTTP_OK);
            } else {
                return $this->apiResponse->error($result['message'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

        } catch (\Exception $e) {
            return $this->apiResponse->error('获取统计失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 获取热门文章排行
     */
    #[Route('/popular', name: 'api_article_read_popular', methods: ['GET'])]
    public function getPopularArticles(Request $request): JsonResponse
    {
        try {
            $startDate = $request->query->get('startDate');
            $endDate = $request->query->get('endDate');
            $limit = $request->query->getInt('limit', 10);

            // 解析日期参数
            $startDateTime = $startDate ? new \DateTime($startDate) : null;
            $endDateTime = $endDate ? new \DateTime($endDate) : null;

            // 验证日期范围
            if ($startDateTime && $endDateTime && $startDateTime > $endDateTime) {
                return $this->apiResponse->error('开始日期不能大于结束日期', Response::HTTP_BAD_REQUEST);
            }

            // 验证限制数量
            if ($limit < 1 || $limit > 100) {
                return $this->apiResponse->error('限制数量必须在1-100之间', Response::HTTP_BAD_REQUEST);
            }

            // 获取热门文章
            $result = $this->articleReadService->getPopularArticles($startDateTime, $endDateTime, $limit);

            if ($result['success']) {
                return $this->apiResponse->success($result['data'], Response::HTTP_OK);
            } else {
                return $this->apiResponse->error($result['message'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

        } catch (\Exception $e) {
            return $this->apiResponse->error('获取热门文章失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 获取用户阅读历史
     */
    #[Route('/history', name: 'api_article_read_history', methods: ['GET'])]
    public function getUserReadingHistory(Request $request): JsonResponse
    {
        try {
            // 从token获取用户ID
            $userId = $this->jwtService->getUserIdFromRequest($request);
            if (!$userId) {
                return $this->apiResponse->error('用户未认证', Response::HTTP_UNAUTHORIZED);
            }

            $limit = $request->query->getInt('limit', 20);
            $offset = $request->query->getInt('offset', 0);

            // 验证分页参数
            if ($limit < 1 || $limit > 100) {
                return $this->apiResponse->error('限制数量必须在1-100之间', Response::HTTP_BAD_REQUEST);
            }

            if ($offset < 0) {
                return $this->apiResponse->error('偏移量不能为负数', Response::HTTP_BAD_REQUEST);
            }

            // 获取用户阅读历史
            $result = $this->articleReadService->getUserReadingHistory($userId, $limit, $offset);

            if ($result['success']) {
                return $this->apiResponse->success($result['data'], Response::HTTP_OK);
            } else {
                return $this->apiResponse->error($result['message'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

        } catch (\Exception $e) {
            return $this->apiResponse->error('获取阅读历史失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 获取阅读分析报告
     */
    #[Route('/analysis', name: 'api_article_read_analysis', methods: ['GET'])]
    public function getReadAnalysisReport(Request $request): JsonResponse
    {
        try {
            $startDate = $request->query->get('startDate');
            $endDate = $request->query->get('endDate');

            // 验证必需参数
            if (!$startDate || !$endDate) {
                return $this->apiResponse->error('开始日期和结束日期不能为空', Response::HTTP_BAD_REQUEST);
            }

            // 解析日期参数
            try {
                $startDateTime = new \DateTime($startDate);
                $endDateTime = new \DateTime($endDate);
            } catch (\Exception $e) {
                return $this->apiResponse->error('日期格式不正确', Response::HTTP_BAD_REQUEST);
            }

            // 验证日期范围
            if ($startDateTime > $endDateTime) {
                return $this->apiResponse->error('开始日期不能大于结束日期', Response::HTTP_BAD_REQUEST);
            }

            // 限制查询范围不超过一年
            $interval = $startDateTime->diff($endDateTime);
            if ($interval->days > 365) {
                return $this->apiResponse->error('查询范围不能超过一年', Response::HTTP_BAD_REQUEST);
            }

            // 获取分析报告
            $result = $this->articleReadService->getReadAnalysisReport($startDateTime, $endDateTime);

            if ($result['success']) {
                return $this->apiResponse->success($result['data'], Response::HTTP_OK);
            } else {
                return $this->apiResponse->error($result['message'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

        } catch (\Exception $e) {
            return $this->apiResponse->error('获取分析报告失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 批量更新文章阅读数量
     */
    #[Route('/batch-update', name: 'api_article_read_batch_update', methods: ['POST'])]
    public function batchUpdateViewCounts(): JsonResponse
    {
        try {
            // 这个接口通常由定时任务调用，不需要特殊权限
            $result = $this->articleReadService->batchUpdateAllArticleViewCounts();

            if ($result['success']) {
                return $this->apiResponse->success($result, Response::HTTP_OK);
            } else {
                return $this->apiResponse->error($result['message'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

        } catch (\Exception $e) {
            return $this->apiResponse->error('批量更新失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 清理旧的阅读记录
     */
    #[Route('/cleanup', name: 'api_article_read_cleanup', methods: ['POST'])]
    public function cleanupOldReads(#[MapRequestPayload] CleanupReadLogsDto $cleanupDto): JsonResponse
    {
        try {
            // 验证DTO
            $validationErrors = $this->validator->validate($cleanupDto);
            if (count($validationErrors) > 0) {
                $errorMessages = [];
                foreach ($validationErrors as $error) {
                    $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                }
                return $this->apiResponse->error(implode(', ', $errorMessages), Response::HTTP_BAD_REQUEST);
            }

            // 验证业务规则
            $businessErrors = $cleanupDto->validateBusinessRules();
            if (!empty($businessErrors)) {
                return $this->apiResponse->error(implode(', ', $businessErrors), Response::HTTP_BAD_REQUEST);
            }

            try {
                $beforeDate = new \DateTime($cleanupDto->getBeforeDate());
            } catch (\Exception $e) {
                return $this->apiResponse->error('日期格式不正确', Response::HTTP_BAD_REQUEST);
            }

            // 清理旧数据
            $result = $this->articleReadService->cleanupOldReadLogs($beforeDate);

            if ($result['success']) {
                return $this->apiResponse->success($result, Response::HTTP_OK);
            } else {
                return $this->apiResponse->error($result['message'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

        } catch (\Exception $e) {
            return $this->apiResponse->error('清理失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 丰富阅读记录DTO的额外信息
     */
    private function enrichReadLogDto(ArticleReadLogDto $readLogDto, Request $request): void
    {
        // 从token获取用户ID
        $userId = $this->jwtService->getUserIdFromRequest($request);
        if ($userId && $readLogDto->userId === 0) {
            $readLogDto->userId = $userId;
        }

        // 从请求头获取IP地址
        $ipAddress = $request->getClientIp();
        if ($ipAddress && !$readLogDto->ipAddress) {
            $readLogDto->ipAddress = $ipAddress;
        }

        // 从请求头获取User-Agent
        $userAgent = $request->headers->get('User-Agent');
        if ($userAgent && !$readLogDto->userAgent) {
            $readLogDto->userAgent = $userAgent;
        }

        // 从请求头获取Referer
        $referer = $request->headers->get('Referer');
        if ($referer && !$readLogDto->referer) {
            $readLogDto->referer = $referer;
        }

        // 生成会话ID（如果没有提供）
        if (!$readLogDto->sessionId) {
            $readLogDto->sessionId = $request->getSession()->getId() ?: session_id();
        }

        // 自动检测设备类型（如果没有提供）
        if (!$readLogDto->deviceType && $userAgent) {
            $readLogDto->deviceType = $this->detectDeviceType($userAgent);
        }

        // 设置阅读时间（如果没有提供）
        if (!$readLogDto->readTime) {
            $readLogDto->readTime = (new \DateTime())->format('Y-m-d H:i:s');
        }
    }

    /**
     * 检测设备类型
     */
    private function detectDeviceType(string $userAgent): string
    {
        $userAgent = strtolower($userAgent);

        // 检测移动设备
        if (preg_match('/mobile|android|iphone|ipod|phone/i', $userAgent)) {
            return 'mobile';
        }

        // 检测平板设备
        if (preg_match('/tablet|ipad/i', $userAgent)) {
            return 'tablet';
        }

        // 默认为桌面设备
        return 'desktop';
    }
}
