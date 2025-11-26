<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    /**
     * 用户登录页面
     */
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // 如果用户已经登录，重定向到仪表板
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        // 获取登录错误信息
        $error = $authenticationUtils->getLastAuthenticationError();
        // 最后输入的用户名
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    /**
     * 用户仪表板页面
     */
    #[Route('/dashboard', name: 'app_dashboard')]
    public function dashboard(): Response
    {
        // 确保用户已登录
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        return $this->render('security/dashboard.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    /**
     * 用户注销
     */
    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // 这个方法不需要实现任何代码
        // Symfony的安全系统会自动处理注销
        throw new \LogicException('这个方法应该被Symfony的安全系统拦截，不会被执行。');
    }
}
