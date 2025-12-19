<?php

namespace App\Controller;

use App\Http\ApiResponse;
use App\Entity\Official;
use App\Entity\WechatPublicAccount;
use App\Repository\OfficialRepository;
use App\Repository\WechatPublicAccountRepository;
use App\DTO\Request\Wechat\SyncArticlesDto;
use App\DTO\Request\Wechat\SyncWechatDto;
use App\DTO\Filter\WechatArticleFilterDto;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use App\Service\WechatArticleSyncService;

#[Route('/official-api/wechat')]
class WechatController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WechatPublicAccountRepository $accountRepository,
        private OfficialRepository $articleRepository,
        private ApiResponse $apiResponse,
        private WechatArticleSyncService $syncService,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('/articles/sync', name: 'api_wechat_sync_articles', methods: ['POST'])]
    public function syncArticles(#[MapRequestPayload] SyncArticlesDto $syncArticlesDto): JsonResponse
    {
        try {
            // DTO验证（Symfony自动验证）
            $errors = $this->validator->validate($syncArticlesDto);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->apiResponse->error('验证失败: ' . implode(', ', $errorMessages), Response::HTTP_BAD_REQUEST);
            }

            // 验证同步数据
            $validationErrors = $syncArticlesDto->validateSyncData();
            if (!empty($validationErrors)) {
                return $this->apiResponse->error('数据验证失败: ' . $this->formatValidationErrors($validationErrors), Response::HTTP_BAD_REQUEST);
            }

            $publicAccountId = $syncArticlesDto->getAccountId();
            $articlesData = $syncArticlesDto->getArticles();

            // 步骤1: 验证或创建微信公众号基础数据
            $publicAccount = $this->accountRepository->findOrCreate($publicAccountId);

            // 步骤2和3: 处理并存储文章数据（使用事务）
            $this->entityManager->beginTransaction();

            try {
                $total = count($articlesData);
                $added = 0;
                $skipped = 0;

                foreach ($articlesData as $articleDto) {
                    // 获取文章数据
                    $articleData = $articleDto->toArray();

                    // 检查必需字段
                    if (empty($articleData['article_id'])) {
                        continue;
                    }

                    $articleId = $articleData['article_id'];

                    // 去重检查：根据 article_id 查询
                    if ($this->articleRepository->existsByArticleId($articleId)) {
                        $skipped++;
                        continue;
                    }

                    // 创建新文章记录（写入 official 表）
                    $official = new Official();
                    $official->setArticleId($articleId);
                    $official->setTitle($articleData['title'] ?? '');
                    $official->setContent($articleData['content'] ?? '');

                    // 设置发布时间 - 优先使用 publish_time，其次使用 update_time
                    if (isset($articleData['publish_time'])) {
                        $releaseTime = \DateTime::createFromFormat('U', $articleData['publish_time']);
                        if ($releaseTime) {
                            $official->setReleaseTime($releaseTime->format('Y-m-d H:i:s'));
                        }
                    } elseif (isset($articleData['update_time'])) {
                        $releaseTime = \DateTime::createFromFormat('U', $articleData['update_time']);
                        if ($releaseTime) {
                            $official->setReleaseTime($releaseTime->format('Y-m-d H:i:s'));
                        }
                    }

                    // 其他字段按现有实体的默认值处理
                    $this->entityManager->persist($official);
                    $added++;
                }

                // 提交事务
                $this->entityManager->flush();
                $this->entityManager->commit();

                return $this->apiResponse->success([
                    'total' => $total,
                    'added' => $added,
                    'skipped' => $skipped,
                    'syncSummary' => $syncArticlesDto->getSyncSummary()
                ], Response::HTTP_OK);

            } catch (\Exception $e) {
                // 回滚事务
                $this->entityManager->rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->apiResponse->error('同步失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // 按账号查询文章列表接口已移除（official 表当前未与账号做外键关联）

    #[Route('/articles/sync-from-wechat/{publicAccountId}', name: 'api_wechat_sync_from_wechat', methods: ['POST'])]
    public function syncFromWechat(string $publicAccountId, #[MapRequestPayload] SyncWechatDto $syncWechatDto): JsonResponse
    {
        try {
            // DTO验证（Symfony自动验证）
            $errors = $this->validator->validate($syncWechatDto);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->apiResponse->error('验证失败: ' . implode(', ', $errorMessages), Response::HTTP_BAD_REQUEST);
            }

            // 验证同步数据
            $validationErrors = $syncWechatDto->validateSyncData();
            if (!empty($validationErrors)) {
                return $this->apiResponse->error('数据验证失败: ' . $this->formatValidationErrors($validationErrors), Response::HTTP_BAD_REQUEST);
            }

            // 设置公众号ID到DTO
            $syncWechatDto->setAccountId($publicAccountId);

            $account = $this->accountRepository->find($publicAccountId);
            if (!$account) {
                // 创建最小信息的公众号记录
                $account = new WechatPublicAccount();
                $account->setId($publicAccountId);
                $this->entityManager->persist($account);
            }

            // 从DTO中获取自定义选项来补充公众号信息
            $customOptions = $syncWechatDto->getCustomOptions();
            if (!empty($customOptions['name']) && !$account->getName()) {
                $account->setName($customOptions['name']);
            }
            if (!empty($customOptions['appId']) && !$account->getAppId()) {
                $account->setAppId($customOptions['appId']);
            }
            if (!empty($customOptions['appSecret']) && !$account->getAppSecret()) {
                $account->setAppSecret($customOptions['appSecret']);
            }

            if (!$account->getAppId() || !$account->getAppSecret()) {
                return $this->apiResponse->error('缺少 appId/appSecret，请在请求体中提供或先在基础表配置', Response::HTTP_BAD_REQUEST);
            }

            $base = $_ENV['WECHAT_API_BASE'] ?? 'https://api.weixin.qq.com';
            $tokenUrl = sprintf('%s/cgi-bin/token?grant_type=client_credential&appid=%s&secret=%s',
                rtrim($base, '/'), urlencode($account->getAppId()), urlencode($account->getAppSecret())
            );

            $client = HttpClient::create();
            $tokenResp = $client->request('GET', $tokenUrl);
            $tokenData = $tokenResp->toArray(false);
            if (!isset($tokenData['access_token'])) {
                return $this->apiResponse->error('获取access_token失败', Response::HTTP_BAD_GATEWAY, $tokenData);
            }

            // 从DTO获取分页参数
            $offset = $syncWechatDto->getCustomOptions()['offset'] ?? 0;
            $count = $syncWechatDto->getArticleLimit() ?? 20;

            $batchUrl = sprintf('%s/cgi-bin/freepublish/batchget?access_token=%s', rtrim($base, '/'), $tokenData['access_token']);
            $batchResp = $client->request('POST', $batchUrl, [
                'json' => [ 'offset' => $offset, 'count' => $count ]
            ]);
            $batch = $batchResp->toArray(false);
            if (!isset($batch['item'])) {
                return $this->apiResponse->error('拉取文章失败', Response::HTTP_BAD_GATEWAY, $batch);
            }

            // 保存可能提供的名称（如果接口返回包含公众号名，这里可追加解析逻辑）
            $this->entityManager->flush();

            // 入库文章（沿用现有去重与映射逻辑）
            $this->entityManager->beginTransaction();
            try {
                $added = 0; $skipped = 0; $total = 0;
                foreach ($batch['item'] as $item) {
                    $total++;
                    if (!isset($item['content']['news_item'])) {
                        continue;
                    }
                    foreach ($item['content']['news_item'] as $news) {
                        $aid = $news['url'] ?? ($news['title'] ?? null); // 兜底ID（微信未直接返回article_id时，使用url或标题做去重依据）
                        if (!$aid) { continue; }

                        // 根据重复处理方式决定行为
                        if ($this->articleRepository->existsByArticleId($aid)) {
                            if ($syncWechatDto->getDuplicateAction() === 'skip') {
                                $skipped++;
                                continue;
                            }
                            // 其他处理方式（update、replace）可以在这里实现
                        }

                        $official = new Official();
                        $official->setArticleId($aid);
                        $official->setTitle($news['title'] ?? '');
                        $official->setContent($news['digest'] ?? '');

                        // 设置发布时间 - 优先使用 publish_time，其次使用 update_time
                        if (isset($news['publish_time'])) {
                            $releaseTime = \DateTime::createFromFormat('U', $news['publish_time']);
                            if ($releaseTime) {
                                $official->setReleaseTime($releaseTime->format('Y-m-d H:i:s'));
                            }
                        } elseif (isset($news['update_time'])) {
                            $releaseTime = \DateTime::createFromFormat('U', $news['update_time']);
                            if ($releaseTime) {
                                $official->setReleaseTime($releaseTime->format('Y-m-d H:i:s'));
                            }
                        }

                        $this->entityManager->persist($official);
                        $added++;
                    }
                }
                $this->entityManager->flush();
                $this->entityManager->commit();

                return $this->apiResponse->success([
                    'total' => $total,
                    'added' => $added,
                    'skipped' => $skipped,
                    'syncSummary' => $syncWechatDto->getSyncSummary()
                ], Response::HTTP_OK);
            } catch (\Throwable $e) {
                $this->entityManager->rollback();
                throw $e;
            }
        } catch (\Throwable $e) {
            return $this->apiResponse->error('同步失败: '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/sync', name: 'api_wechat_sync', methods: ['POST'])]
    public function sync(Request $request, ValidatorInterface $validator): JsonResponse
    {
        // 添加调试日志
        error_log('[DEBUG] 微信同步接口被调用');

        // 手动解析JSON请求体
        $rawData = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[DEBUG] JSON解析错误: ' . json_last_error_msg());
            return $this->apiResponse->error('请求体格式错误: ' . json_last_error_msg(), Response::HTTP_BAD_REQUEST);
        }

        error_log('[DEBUG] 请求体原始数据: ' . $request->getContent());

        // 手动创建SyncWechatDto实例
        $syncWechatDto = new SyncWechatDto($rawData);

        error_log('[DEBUG] DTO中的accountId: ' . var_export($syncWechatDto->getAccountId(), true));

        try {
            // DTO验证（手动验证）
            $errors = $validator->validate($syncWechatDto);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                    error_log('[DEBUG] 验证错误: ' . $error->getMessage() . ' - 字段: ' . $error->getPropertyPath());
                }
                return $this->apiResponse->error('验证失败: ' . implode(', ', $errorMessages), Response::HTTP_BAD_REQUEST);
            }

            // 验证同步数据
            $validationErrors = $syncWechatDto->validateSyncData();
            if (!empty($validationErrors)) {
                return $this->apiResponse->error('数据验证失败: ' . $this->formatValidationErrors($validationErrors), Response::HTTP_BAD_REQUEST);
            }

            $accountId = $syncWechatDto->getAccountId();
            $forceSync = $syncWechatDto->isForceSync();

            // 调用同步服务
            $result = $this->syncService->syncArticles($accountId, $forceSync);

            if (!$result['success']) {
                return $this->apiResponse->error($result['message'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // 合并同步结果和DTO摘要
            $result['syncSummary'] = $syncWechatDto->getSyncSummary();

            return $this->apiResponse->success($result, Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->apiResponse->error('同步失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/sync/status/{accountId}', name: 'api_wechat_sync_status', methods: ['GET'])]
    public function getSyncStatus(string $accountId): JsonResponse
    {
        try {
            // 验证accountId参数
            if (empty(trim($accountId))) {
                return $this->apiResponse->error('公众号ID不能为空', Response::HTTP_BAD_REQUEST);
            }

            $status = $this->syncService->getSyncStatus($accountId);

            if (isset($status['error'])) {
                return $this->apiResponse->error($status['error'], Response::HTTP_NOT_FOUND);
            }

            return $this->apiResponse->success($status, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->apiResponse->error('获取同步状态失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/articles', name: 'api_wechat_articles_list', methods: ['GET'])]
    public function getArticles(Request $request): JsonResponse
    {
        try {
            // 手动创建过滤器DTO而不是使用 #[MapRequestPayload]
            $filter = new WechatArticleFilterDto();

            // 支持新旧分页参数名，优先使用新参数 page/size
            $page = $request->query->get('page');
            $size = $request->query->get('size');

            // 如果新参数不存在，则尝试使用旧参数 current/pageSize
            if ($page === null) {
                $page = $request->query->get('current', 1);
            }
            if ($size === null) {
                $size = $request->query->get('pageSize', $request->query->get('limit', 20));
            }

            // 参数验证和默认值处理
            $page = max(1, (int)$page);
            $size = max(1, min(100, (int)$size));

            // 设置分页参数到DTO（使用新的字段名）
            $filter->setPage($page);
            $filter->setSize($size);

            // 从查询参数设置标题（支持 keyword 和 title 两个参数名）
            $title = $request->query->get('title') ?: $request->query->get('keyword');
            if ($title) {
                $filter->setTitle($title);
            }

            // DTO验证（Symfony自动验证）
            $errors = $this->validator->validate($filter);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->apiResponse->error('验证失败: ' . implode(', ', $errorMessages), Response::HTTP_BAD_REQUEST);
            }

            // 验证过滤条件
            $validationErrors = $filter->validateFilterData();
            if (!empty($validationErrors)) {
                return $this->apiResponse->error('过滤条件验证失败: ' . $this->formatValidationErrors($validationErrors), Response::HTTP_BAD_REQUEST);
            }

            // 分页参数已在上面设置

            $offset = ($page - 1) * $size;

            // 获取过滤条件
            $criteria = $filter->getFilterCriteria();

            // 查询文章列表
            $articles = $this->articleRepository->findByCriteria($criteria, $size, $offset);
            $total = $this->articleRepository->countByCriteria($criteria);
            $pages = (int)ceil($total / $size);

            return $this->apiResponse->success([
                'items' => $articles,
                'currentPageNumber' => $page,
                'itemsPerPage' => $size,
                'totalCount' => $total
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->apiResponse->error('获取文章列表失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/articles/{id}', name: 'api_wechat_article_show', methods: ['GET'])]
    public function getArticle(int $id): JsonResponse
    {
        // 添加调试日志
        error_log('[DEBUG] WechatController::getArticle - 访问微信文章详情接口，ID: ' . $id);

        try {
            $article = $this->articleRepository->find($id);

            if (!$article) {
                return $this->apiResponse->error('文章不存在', Response::HTTP_NOT_FOUND);
            }

            return $this->apiResponse->success($article, Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->apiResponse->error('获取文章详情失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/articles/{id}', name: 'api_wechat_article_delete', methods: ['DELETE'])]
    public function deleteArticle(int $id): JsonResponse
    {
        // 添加调试日志
        error_log('[DEBUG] WechatController::deleteArticle - 删除微信文章接口，ID: ' . $id);

        try {
            // 根据ID查找文章
            $article = $this->articleRepository->find($id);

            if (!$article) {
                return $this->apiResponse->error('文章不存在', Response::HTTP_NOT_FOUND);
            }

            // 检查文章是否已经标记为删除
            if ($article->getIsDeleted()) {
                return $this->apiResponse->error('文章已被删除', Response::HTTP_GONE);
            }

            // 使用事务确保数据一致性
            $this->entityManager->beginTransaction();

            try {
                // 软删除：标记为已删除
                $article->setIsDeleted(true);

                // 记录删除操作的时间戳
                $article->setUpdatedAt(new \DateTime());

                // 保存更改
                $this->entityManager->persist($article);
                $this->entityManager->flush();

                // 提交事务
                $this->entityManager->commit();

                // 记录成功删除的日志
                error_log('[DEBUG] WechatController::deleteArticle - 成功删除文章ID: ' . $id . ', 标题: ' . $article->getTitle());

                return $this->apiResponse->success([
                    'id' => $article->getId(),
                    'title' => $article->getTitle(),
                    'message' => '文章删除成功'
                ], Response::HTTP_OK);

            } catch (\Exception $e) {
                // 回滚事务
                $this->entityManager->rollback();

                // 记录错误日志
                error_log('[ERROR] WechatController::deleteArticle - 删除文章失败，ID: ' . $id . ', 错误: ' . $e->getMessage());

                throw $e;
            }

        } catch (\Exception $e) {
            return $this->apiResponse->error('删除文章失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 格式化验证错误
     *
     * @param array $errors
     * @return string
     */
    private function formatValidationErrors(array $errors): string
    {
        $formattedErrors = [];

        foreach ($errors as $field => $error) {
            if (is_array($error)) {
                $formattedErrors[] = $field . ': ' . implode(', ', $error);
            } else {
                $formattedErrors[] = $field . ': ' . $error;
            }
        }

        return implode('; ', $formattedErrors);
    }
}
