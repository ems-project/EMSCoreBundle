<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Core\UI\Page\Page;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use function Symfony\Component\Translation\t;

class DefaultController extends AbstractController
{
    public function documentation(): Page
    {
        return new Page(
            context: [
                'icon' => 'fa fa-book',
                'title' => t('key.documentation', [], 'emsco-core'),
                'breadcrumb' => new Navigation()->add(t('key.documentation', [], 'emsco-core')),
            ],
            template: 'page/page_documentation.html.twig',
        );
    }
}
