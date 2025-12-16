<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProjectCardController extends AbstractController
{
    #[Route('/projects', name: 'project_cards', methods: ['GET'])]
    public function index(): Response
    {
        // 模拟项目数据
        $projects = [
            [
                'id' => 1,
                'name' => '官网重构项目',
                'log' => '项目进展顺利，前端开发完成80%',
                'manager' => '张三',
                'teamSize' => 5,
                'startDate' => '2024-01-15',
                'endDate' => '2024-06-30',
                'workHours' => 1200,
                'personnelCost' => 150000,
                'procurementCost' => 25000,
                'totalCost' => 175000
            ],
            [
                'id' => 2,
                'name' => '移动端APP开发',
                'log' => '完成UI设计，开始后端API开发',
                'manager' => '李四',
                'teamSize' => 8,
                'startDate' => '2024-02-01',
                'endDate' => '2024-08-31',
                'workHours' => 1800,
                'personnelCost' => 280000,
                'procurementCost' => 45000,
                'totalCost' => 325000
            ],
            [
                'id' => 3,
                'name' => '数据分析平台',
                'log' => '需求分析完成，技术方案确定',
                'manager' => '王五',
                'teamSize' => 6,
                'startDate' => '2024-03-01',
                'endDate' => '无',
                'workHours' => 900,
                'personnelCost' => 180000,
                'procurementCost' => 35000,
                'totalCost' => 215000
            ],
            [
                'id' => 4,
                'name' => '客户管理系统',
                'log' => '数据库设计完成，开始前端开发',
                'manager' => '赵六',
                'teamSize' => 4,
                'startDate' => '2024-01-20',
                'endDate' => '2024-05-15',
                'workHours' => 750,
                'personnelCost' => 120000,
                'procurementCost' => 15000,
                'totalCost' => 135000
            ],
            [
                'id' => 5,
                'name' => '电商平台升级',
                'log' => '性能优化完成，正在进行测试',
                'manager' => '钱七',
                'teamSize' => 7,
                'startDate' => '2024-02-15',
                'endDate' => '2024-07-30',
                'workHours' => 1400,
                'personnelCost' => 210000,
                'procurementCost' => 30000,
                'totalCost' => 240000
            ],
            [
                'id' => 6,
                'name' => 'AI智能客服',
                'log' => '模型训练完成，集成测试中',
                'manager' => '孙八',
                'teamSize' => 5,
                'startDate' => '2024-03-10',
                'endDate' => '2024-09-30',
                'workHours' => 1100,
                'personnelCost' => 165000,
                'procurementCost' => 50000,
                'totalCost' => 215000
            ],
            [
                'id' => 7,
                'name' => '物联网平台',
                'log' => '硬件采购完成，软件开发进行中',
                'manager' => '周九',
                'teamSize' => 9,
                'startDate' => '2024-01-05',
                'endDate' => '2024-12-31',
                'workHours' => 2000,
                'personnelCost' => 320000,
                'procurementCost' => 120000,
                'totalCost' => 440000
            ],
            [
                'id' => 8,
                'name' => '区块链应用',
                'log' => '原型开发完成，准备上链测试',
                'manager' => '吴十',
                'teamSize' => 3,
                'startDate' => '2024-04-01',
                'endDate' => '2024-10-15',
                'workHours' => 600,
                'personnelCost' => 90000,
                'procurementCost' => 20000,
                'totalCost' => 110000
            ],
            [
                'id' => 9,
                'name' => '云服务迁移',
                'log' => '数据迁移完成，性能调优中',
                'manager' => '郑十一',
                'teamSize' => 4,
                'startDate' => '2024-02-20',
                'endDate' => '2024-06-20',
                'workHours' => 800,
                'personnelCost' => 130000,
                'procurementCost' => 40000,
                'totalCost' => 170000
            ],
            [
                'id' => 10,
                'name' => '安全审计系统',
                'log' => '系统架构设计完成，开发中',
                'manager' => '王十二',
                'teamSize' => 5,
                'startDate' => '2024-03-15',
                'endDate' => '2024-08-15',
                'workHours' => 950,
                'personnelCost' => 155000,
                'procurementCost' => 25000,
                'totalCost' => 180000
            ],
            [
                'id' => 11,
                'name' => '自动化测试平台',
                'log' => '核心功能开发完成，集成测试中',
                'manager' => '李十三',
                'teamSize' => 6,
                'startDate' => '2024-01-10',
                'endDate' => '2024-07-10',
                'workHours' => 1300,
                'personnelCost' => 195000,
                'procurementCost' => 22000,
                'totalCost' => 217000
            ],
            [
                'id' => 12,
                'name' => '微服务架构改造',
                'log' => '服务拆分完成，正在部署测试环境',
                'manager' => '张十四',
                'teamSize' => 8,
                'startDate' => '2024-02-05',
                'endDate' => '2024-09-05',
                'workHours' => 1600,
                'personnelCost' => 240000,
                'procurementCost' => 38000,
                'totalCost' => 278000
            ]
        ];

        return $this->render('project_card/index.html.twig', [
            'projects' => $projects
        ]);
    }
}
