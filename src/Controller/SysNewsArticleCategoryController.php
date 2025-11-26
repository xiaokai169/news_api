<?php

namespace App\Controller;

use App\Entity\SysNewsArticleCategory;
use App\Http\ApiResponse;
use App\Repository\SysNewsArticleCategoryRepository;
use App\Repository\SysNewsArticleRepository;
use App\DTO\Request\Category\CreateCategoryDto;
use App\DTO\Request\Category\UpdateCategoryDto;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use App\OpenApi\Operations\SysNewsArticleCategoryOperations;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use OpenApi\Attributes as OA;

#[Route('/official-api/sys-news-article-category')]
class SysNewsArticleCategoryController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface           $entityManager,
        private readonly SysNewsArticleCategoryRepository $categoryRepository,
        private readonly SysNewsArticleRepository         $articleRepository,
        private readonly ValidatorInterface               $validator,
        private readonly ApiResponse                      $apiResponse
    )
    {
    }

    #[Route('', name: 'api_sys_news_category_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            $page = max(1, (int)$request->query->get('page', 1));
            $limit = max(1, min(100, (int)$request->query->get('limit', 20)));
            $code = $request->query->get('code');
            $name = $request->query->get('name');
            $offset = ($page - 1) * $limit;

            $categories = $this->categoryRepository->findByPage(['code' => $code, 'name' => $name], $limit, $offset);
            $total = $this->categoryRepository->count([]);
            $pages = (int)ceil($total / $limit);
            return $this->apiResponse->paginated(
                items: $categories,
                total: $total,
                page: $page,
                limit: $limit,
                pages: $pages,
                request: $request,
                context: ['groups' => ['SysNewsArticleCategory:read']]
            );
        } catch (\Exception $e) {
            return $this->json($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'api_sys_news_category_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        try {
            $category = $this->categoryRepository->find($id);

            if (!$category) {
                $response = $this->apiResponse->error('分类不存在', Response::HTTP_NOT_FOUND);
                // 添加工作区信息以处理Web服务器状态码重写问题
                $response->headers->set('X-Actual-Status', (string)Response::HTTP_NOT_FOUND);
                return $response;
            }

            return $this->apiResponse->success($category, Response::HTTP_OK, ['groups' => ['SysNewsArticleCategory:read']]);
        } catch (\Exception $e) {
            $response = $this->apiResponse->error('获取失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            $response->headers->set('X-Actual-Status', (string)Response::HTTP_INTERNAL_SERVER_ERROR);
            return $response;
        }
    }

    #[Route('', name: 'api_sys_news_category_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateCategoryDto $createCategoryDto): JsonResponse
    {
        try {
            // DTO验证（Symfony自动验证）
            $errors = $this->validator->validate($createCategoryDto);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->apiResponse->error('验证失败: ' . implode(', ', $errorMessages), Response::HTTP_BAD_REQUEST);
            }

            // 检查分类编码是否已存在
            if ($this->categoryRepository->existsByCode($createCategoryDto->getCode())) {
                return $this->apiResponse->error('该分类编码已存在', Response::HTTP_BAD_REQUEST);
            }

            // 创建分类实体
            $category = new SysNewsArticleCategory();
            $category->setCode($createCategoryDto->getCode());
            $category->setName($createCategoryDto->getName());
            $category->setCreator($createCategoryDto->getCreator() ?: '系统');

            // 验证实体
            $entityErrors = $this->validator->validate($category);
            if (count($entityErrors) > 0) {
                $errorMessages = [];
                foreach ($entityErrors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->apiResponse->error('实体验证失败: ' . implode(', ', $errorMessages), Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->persist($category);
            $this->entityManager->flush();

            return $this->apiResponse->success($category, Response::HTTP_CREATED, ['groups' => ['SysNewsArticleCategory:read']]);
        } catch (\Exception $e) {
            return $this->apiResponse->error('创建失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'api_sys_news_category_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, #[MapRequestPayload] UpdateCategoryDto $updateCategoryDto): JsonResponse
    {
        try {
            // DTO验证（Symfony自动验证）
            $errors = $this->validator->validate($updateCategoryDto);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->apiResponse->error('验证失败: ' . implode(', ', $errorMessages), Response::HTTP_BAD_REQUEST);
            }

            // 查找分类
            $category = $this->categoryRepository->find($id);
            if (!$category) {
                return $this->apiResponse->error('分类不存在', Response::HTTP_NOT_FOUND);
            }

            // 检查是否有更新数据
            if (!$updateCategoryDto->hasUpdates()) {
                return $this->apiResponse->error('没有提供任何更新数据', Response::HTTP_BAD_REQUEST);
            }

            // 保存原始数据用于比较
            $originalData = [
                'code' => $category->getCode(),
                'name' => $category->getName(),
                'creator' => $category->getCreator(),
            ];

            // 应用更新
            $updatedFields = $updateCategoryDto->getUpdatedFields();
            foreach ($updatedFields as $field => $value) {
                switch ($field) {
                    case 'code':
                        $category->setCode($value);
                        break;
                    case 'name':
                        $category->setName($value);
                        break;
                    case 'creator':
                        $category->setCreator($value);
                        break;
                }
            }

            // 检查编码重复性（排除当前ID）
            if ($updateCategoryDto->getCode() !== null &&
                $this->categoryRepository->existsByCode($category->getCode(), $category->getId())) {
                return $this->apiResponse->error('该分类编码已存在', Response::HTTP_BAD_REQUEST);
            }

            // 验证实体
            $entityErrors = $this->validator->validate($category);
            if (count($entityErrors) > 0) {
                $errorMessages = [];
                foreach ($entityErrors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->apiResponse->error('实体验证失败: ' . implode(', ', $errorMessages), Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->flush();

            return $this->apiResponse->success($category, Response::HTTP_OK, ['groups' => ['SysNewsArticleCategory:read']]);
        } catch (\Exception $e) {
            return $this->apiResponse->error('更新失败: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'api_sys_news_category_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        // 1. 查找分类
        $category = $this->categoryRepository->find($id);

        // 2. 分类不存在，返回 404
        if (!$category) {
          return   $this->apiResponse->error('分类不存在', Response::HTTP_NOT_FOUND);
        }

        // 3. 主动查询该分类下是否有文章
        $hasRelatedArticles = $this->articleRepository->countByCategoryId($id) > 0;

        // 4. 如果有文章关联，返回 409 Conflict
        if ($hasRelatedArticles) {
            return $this->apiResponse->error('分类正在被使用，无法删除', Response::HTTP_CONFLICT);
        }

        // 5. 没有依赖，执行删除
        $this->entityManager->remove($category);
        $this->entityManager->flush();
        // 6. 返回 200 OK 或者 204 No Content（推荐 200 更友好，可带消息）
        return $this->apiResponse->success($category);
    }

    /**
     * 检查分类下是否有文章
     */
    private function countArticlesByCategoryCode(string $code): int
    {
        try {
            // 先通过code获取分类对象
            $category = $this->categoryRepository->findOneBy(['code' => $code]);

            if (!$category) {
                return 0;
            }

            // 使用关联关系查询文章数量
            return $this->articleRepository->count(['category' => $category]);

        } catch (\Exception $e) {
            error_log("检查文章数量时出错: " . $e->getMessage());
            return 0;
        }
    }
}
