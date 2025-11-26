<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WelcomeController extends AbstractController
{
	#[Route('/', name: 'welcome', methods: ['GET'])]
	public function index(): Response
	{
		$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>欢迎使用 官方网站后台</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Liberation Sans', sans-serif; margin: 32px; }
        .card { max-width: 720px; margin: 0 auto; padding: 24px; border: 1px solid #eee; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
        h1 { margin: 0 0 12px; font-size: 24px; }
        p { color: #555; line-height: 1.6; }
        a.button { display: inline-block; margin-top: 12px; padding: 10px 16px; background: #2b7cff; color: #fff; text-decoration: none; border-radius: 8px; }
        a.button:hover { background: #1e64d9; }
    </style>
    </head>
<body>
    <div class="card">
        <h1>欢迎使用 官方网站后台</h1>
        <p>系统已运行。接口文档请访问 <a class="button" href="/api_doc">/api_doc</a></p>
    </div>
</body>
</html>
HTML;

		return new Response($html);
	}
}


