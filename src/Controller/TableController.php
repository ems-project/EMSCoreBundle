<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Core\Table\TableFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class TableController extends AbstractController
{
    /** @var TableFactoryInterface */
    private $tableFactory;

    public function __construct(TableFactoryInterface $tableManager)
    {
        $this->tableFactory = $tableManager;
    }

    /**
     * @Route("/table/{name}/data", name="ems.table.data", requirements={"name": "\S+"})
     */
    public function index(string $name): JsonResponse
    {
        $data = [];
        $rows = 10000;

        while ($rows-- > 0) {
            $data[] = [
                '#' => $rows,
                'name' => 'test',
                'indexes' => 54654,
                'total' => 9,
                'action' => '-'
            ];
        }

        return new JsonResponse(['data' => $data]);
    }


}