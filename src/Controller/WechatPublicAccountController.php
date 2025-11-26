<?php

namespace App\Controller;

use App\Http\ApiResponse;
use App\Entity\WechatPublicAccount;
use App\Repository\WechatPublicAccountRepository;
use App\DTO\Request\WechatPublicAccount\CreateWechatAccountDto;
use App\DTO\Request\WechatPublicAccount\UpdateWechatAccountDto;
use App\DTO\Filter\WechatAccountFilterDto;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;


#[Route('/official-api/wechatpublicaccount')]
class WechatPublicAccountController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface        $entityManager,
        private WechatPublicAccountRepository $accountRepository,
        private ApiResponse                   $apiResponse,
        private ValidatorInterface            $validator
    )
    {
    }

    #[Route('', name: 'api_wechat_account_list', methods: ['GET'])]
    public function list(#[MapQueryString] WechatAccountFilterDto $filter): JsonResponse
    {
        // 验证过滤条件
        $validationErrors = $filter->validateFilters();
        if (!empty($validationErrors)) {
            return $this->apiResponse->validationError($validationErrors, '过滤条件验证失败');
        }

        // 获取分页参数
        $page = $filter->getPage();
        $limit = $filter->getLimit();
        $offset = $filter->getOffset();

        // 如果有关键词，使用关键词搜索；否则使用过滤条件
        $keyword = $filter->getKeyword();
        if ($keyword !== null) {
            $items = $this->accountRepository->findPaginated($keyword, $limit, $offset);
            $total = $this->accountRepository->countByKeyword($keyword);
        } else {
            // 使用过滤条件查询（这里需要扩展repository方法支持复杂过滤）
            // 暂时保持原有逻辑，后续可以扩展repository方法
            $items = $this->accountRepository->findPaginated($filter->name, $limit, $offset);
            $total = $this->accountRepository->countByKeyword($filter->name);
        }

        $pages = (int)ceil($total / $limit);

        return $this->apiResponse->success([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => $pages,
            'filter' => $filter->getFilterSummary(), // 添加过滤条件摘要到响应中
        ], Response::HTTP_OK, ['groups' => ['wechat_account:read']]);
    }

    #[Route('/{id}', name: 'api_wechat_account_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $account = $this->accountRepository->find($id);
        if (!$account) {
            return $this->apiResponse->error('公众号不存在', Response::HTTP_NOT_FOUND);
        }
        return $this->apiResponse->success($account, Response::HTTP_OK, ['groups' => ['wechat_account:read']]);
    }

    #[Route('', name: 'api_wechat_account_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateWechatAccountDto $createDto): JsonResponse
    {
        // DTO自动验证（通过Symfony属性注入）
        // 额外的业务逻辑验证
        $businessErrors = $createDto->validateBusinessRules();
        if (!empty($businessErrors)) {
            return $this->apiResponse->validationError($businessErrors, '业务规则验证失败');
        }

        // 生成唯一ID（形如 gh_xxxxxxxxxxxxxxxx）
        $maxAttempts = 10;
        $attempts = 0;
        $id = null;
        do {
            $id = 'gh_' . bin2hex(random_bytes(8));
            if (!$this->accountRepository->find($id)) {
                break;
            }
            $attempts++;
        } while ($attempts < $maxAttempts);
        if ($attempts >= $maxAttempts) {
            return $this->apiResponse->error('生成唯一ID失败，请重试', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $account = new WechatPublicAccount();
        $account->setId($id);
        $account->setName($createDto->name);
        $account->setDescription($createDto->description);
        $account->setAvatarUrl($createDto->avatarUrl);
        $account->setAppId($createDto->appId);
        $account->setAppSecret($createDto->appSecret);
        $account->setIsActive($createDto->isActive);

        // 如果DTO中提供了Token和EncodingAESKey则使用，否则自动生成
        if (!empty($createDto->token)) {
            $account->setToken($createDto->token);
        } else {
            $account->setToken(bin2hex(random_bytes(16)));
        }

        if (!empty($createDto->encodingAESKey)) {
            $account->setEncodingAESKey($createDto->encodingAESKey);
        } else {
            $account->setEncodingAESKey(base64_encode(random_bytes(24))); // 生成32字符的base64字符串
        }

        // 验证实体
        $errors = $this->validator->validate($account);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->apiResponse->validationError($errorMessages, '实体验证失败');
        }

        $this->entityManager->persist($account);
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            return $this->apiResponse->error('唯一约束冲突：相同的公众号唯一字段已存在', Response::HTTP_CONFLICT, [
                'sql_state' => $e->getSQLState(),
                'conflict_field' => 'appId或appSecret可能已存在',
            ]);
        }

        return $this->apiResponse->success($account, Response::HTTP_CREATED, ['groups' => ['wechat_account:read']]);
    }

    #[Route('/{id}', name: 'api_wechat_account_put', methods: ['PUT'])]
    public function put(string $id, #[MapRequestPayload] UpdateWechatAccountDto $updateDto): JsonResponse
    {
        $account = $this->accountRepository->find($id);
        if (!$account) {
            return $this->apiResponse->error('公众号不存在', Response::HTTP_NOT_FOUND);
        }

        // 验证业务规则
        $businessErrors = $updateDto->validateBusinessRules();
        if (!empty($businessErrors)) {
            return $this->apiResponse->validationError($businessErrors, '业务规则验证失败');
        }

        // 对于PUT方法，需要确保所有必需字段都有值
        // 由于UpdateWechatAccountDto的字段都是可选的，我们需要检查关键字段
        if ($updateDto->appId === null || $updateDto->appSecret === null) {
            return $this->apiResponse->validationError([
                'appId' => 'PUT方法要求appId是必需的',
                'appSecret' => 'PUT方法要求appSecret是必需的'
            ], 'PUT方法缺少必需字段');
        }

        // 全量更新所有字段（如果DTO中有值则更新，否则设为null）
        $account->setName($updateDto->name);
        $account->setDescription($updateDto->description);
        $account->setAvatarUrl($updateDto->avatarUrl);
        $account->setAppId($updateDto->appId);
        $account->setAppSecret($updateDto->appSecret);

        if ($updateDto->isActive !== null) {
            $account->setIsActive($updateDto->isActive);
        }

        if ($updateDto->token !== null) {
            $account->setToken($updateDto->token);
        }

        if ($updateDto->encodingAESKey !== null) {
            $account->setEncodingAESKey($updateDto->encodingAESKey);
        }

        // 验证实体
        $errors = $this->validator->validate($account);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->apiResponse->validationError($errorMessages, '实体验证失败');
        }

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            return $this->apiResponse->error('唯一约束冲突：相同的公众号唯一字段已存在', Response::HTTP_CONFLICT, [
                'sql_state' => $e->getSQLState(),
                'conflict_field' => 'appId或appSecret可能已存在',
            ]);
        }

        return $this->apiResponse->success($account, Response::HTTP_OK, ['groups' => ['wechat_account:read']]);
    }

    #[Route('/{id}', name: 'api_wechat_account_patch', methods: ['PATCH'])]
    public function patch(string $id, #[MapRequestPayload] UpdateWechatAccountDto $updateDto): JsonResponse
    {
        $account = $this->accountRepository->find($id);
        if (!$account) {
            return $this->apiResponse->error('公众号不存在', Response::HTTP_NOT_FOUND);
        }

        // 验证业务规则
        $businessErrors = $updateDto->validateBusinessRules();
        if (!empty($businessErrors)) {
            return $this->apiResponse->validationError($businessErrors, '业务规则验证失败');
        }

        // 检查是否有任何更新
        if (!$updateDto->hasUpdates()) {
            return $this->apiResponse->validationError(['noUpdates' => '没有提供任何要更新的字段'], '无效的更新请求');
        }

        // 部分更新 - 只更新提供的字段
        $updatedFields = $updateDto->getUpdatedFields();

        if (array_key_exists('name', $updatedFields)) {
            $account->setName($updatedFields['name']);
        }
        if (array_key_exists('description', $updatedFields)) {
            $account->setDescription($updatedFields['description']);
        }
        if (array_key_exists('avatarUrl', $updatedFields)) {
            $account->setAvatarUrl($updatedFields['avatarUrl']);
        }
        if (array_key_exists('appId', $updatedFields)) {
            $account->setAppId($updatedFields['appId']);
        }
        if (array_key_exists('appSecret', $updatedFields)) {
            $account->setAppSecret($updatedFields['appSecret']);
        }
        if (array_key_exists('isActive', $updatedFields)) {
            $account->setIsActive($updatedFields['isActive']);
        }
        if (array_key_exists('token', $updatedFields)) {
            $account->setToken($updatedFields['token']);
        }
        if (array_key_exists('encodingAESKey', $updatedFields)) {
            $account->setEncodingAESKey($updatedFields['encodingAESKey']);
        }

        // 验证实体
        $errors = $this->validator->validate($account);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->apiResponse->validationError($errorMessages, '实体验证失败');
        }

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            return $this->apiResponse->error('唯一约束冲突：相同的公众号唯一字段已存在', Response::HTTP_CONFLICT, [
                'sql_state' => $e->getSQLState(),
                'conflict_field' => 'appId或appSecret可能已存在',
            ]);
        }

        return $this->apiResponse->success([
            'account' => $account,
            'updatedFields' => array_keys($updatedFields),
            'sensitiveFieldUpdates' => $updateDto->getSensitiveFieldUpdates(),
        ], Response::HTTP_OK, ['groups' => ['wechat_account:read']]);
    }

    #[Route('/{id}/deactivate', name: 'api_wechat_account_deactivate', methods: ['PATCH'])]
    public function deactivate(string $id): JsonResponse
    {
        $account = $this->accountRepository->find($id);
        if (!$account) {
            return $this->apiResponse->error('公众号不存在', Response::HTTP_NOT_FOUND);
        }

        if (!$account->isActive()) {
            return $this->apiResponse->error('公众号已是停用状态', Response::HTTP_BAD_REQUEST);
        }

        $account->setIsActive(false);
        $this->entityManager->flush();

        return $this->apiResponse->success($account, Response::HTTP_OK, ['groups' => ['wechat_account:read']]);
    }

    #[Route('/{id}/activate', name: 'api_wechat_account_activate', methods: ['PATCH'])]
    public function activate(string $id): JsonResponse
    {
        $account = $this->accountRepository->find($id);
        if (!$account) {
            return $this->apiResponse->error('公众号不存在', Response::HTTP_NOT_FOUND);
        }

        if ($account->isActive()) {
            return $this->apiResponse->error('公众号已是启用状态', Response::HTTP_BAD_REQUEST);
        }

        $account->setIsActive(true);
        $this->entityManager->flush();

        return $this->apiResponse->success($account, Response::HTTP_OK, ['groups' => ['wechat_account:read']]);
    }

    #[Route('/{id}', name: 'api_wechat_account_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $account = $this->accountRepository->find($id);
        if (!$account) {
            return $this->apiResponse->error('公众号不存在', Response::HTTP_NOT_FOUND);
        }

        // 如未来 official 表建立外键关系，这里可增加删除保护
        $this->entityManager->remove($account);
        $this->entityManager->flush();
        return $this->apiResponse->success(['id' => $id], Response::HTTP_OK);
    }
}
