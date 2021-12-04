<?php

namespace EMS\CoreBundle\Controller\Wysiwyg;

use EMS\CoreBundle\Service\WysiwygStylesSetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class StylesetController extends AbstractController
{
    private WysiwygStylesSetService $wysiwygStylesSetService;

    public function __construct(WysiwygStylesSetService $wysiwygStylesSetService)
    {
        $this->wysiwygStylesSetService = $wysiwygStylesSetService;
    }

    public function iframe(string $name): Response
    {
        return $this->render('@EMSCore/wysiwyg_styles_set/iframe.html.twig', [
            'styleSet' => $this->wysiwygStylesSetService->getByName($name),
        ]);
    }
}