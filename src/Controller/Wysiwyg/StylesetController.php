<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Wysiwyg;

use EMS\CoreBundle\Service\WysiwygStylesSetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class StylesetController extends AbstractController
{
    public function __construct(private readonly WysiwygStylesSetService $wysiwygStylesSetService)
    {
    }

    public function iframe(string $name, string $language): Response
    {
        $splitLanguage = \explode('_', $language);

        return $this->render('@EMSCore/wysiwyg_styles_set/iframe.html.twig', [
            'styleSet' => $this->wysiwygStylesSetService->getByName($name),
            'language' => \array_shift($splitLanguage),
        ]);
    }
}
