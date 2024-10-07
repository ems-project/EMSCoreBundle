<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Wysiwyg;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CoreBundle\Service\WysiwygStylesSetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class StylesetController extends AbstractController
{
    public function __construct(
        private readonly WysiwygStylesSetService $wysiwygStylesSetService,
        private readonly string $templateNamespace
    ) {
    }

    public function iframe(Request $request, string $name, string $language): Response
    {
        $emsLink = $request->get('emsLink');

        return $this->render("@$this->templateNamespace/wysiwyg_styles_set/iframe.html.twig", [
            'styleSet' => $this->wysiwygStylesSetService->getByName($name),
            'language' => $language,
            'field' => $request->get('field'),
            'fieldPath' => $request->get('fieldPath'),
            'emsLink' => $emsLink ? EMSLink::fromText($emsLink) : null,
        ]);
    }
}
