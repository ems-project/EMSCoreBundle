<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Environment;

use EMS\CoreBundle\Core\Table\TableFactoryInterface;
use EMS\CoreBundle\Table\Environment\UnreferencedAliasesType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class IndexController extends AbstractController
{
    /** @var TableFactoryInterface */
    private $tableFactory;

    public function __construct(TableFactoryInterface $tableManager)
    {
        $this->tableFactory = $tableManager;
    }

    /**
     * @Route("/environment-v2", name="environment-v2.index")
     */
    public function index(): Response
    {
        return $this->render('@EMSCore/environmentV2/index.html.twig', [
            'unreferencedAliasesTable' => $this->tableFactory->create(UnreferencedAliasesType::class)
        ]);
    }
}