<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Common\Standard\Json;
use EMS\CoreBundle\Core\Revision\LiveEditManager;
use EMS\CoreBundle\Core\Revision\RawDataTransformer;
use EMS\CoreBundle\Entity\Revision;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccess;

class LiveEditController extends AbstractController
{
    private LiveEditManager $liveEditManager;

    public function __construct(LiveEditManager $liveEditManager)
    {
        $this->liveEditManager = $liveEditManager;
    }

    public function liveEditRevision(string $revisionId, Request $request): JsonResponse
    {
        $requestContent = $request->getContent();
        $decoded = \is_string($requestContent) && \strlen($requestContent) > 0 ? Json::decode($requestContent) : [];
        $data = $decoded['_data'] ?? [];

        $emsLink = EMSLink::fromText($data['emsLink']);
        $revision = $this->liveEditManager->getRevision($emsLink);

        if (!$revision instanceof Revision) {
            throw new \RuntimeException(\sprintf('Revision with emslink "%s" not found', $emsLink));
        }
        if (null == $revision->getContentType()->getFieldType() || $revision->getContentType()->getEnvironment()->getManaged()) {
            new JsonResponse([ 'data' => Json::encode([ 'editable' => false ]) ]);
        }

        if ($this->liveEditManager->isEditableByUser($revision->getContentType(), $revision->getRawData(), $data['fields']) ) {

            $forms = $this->liveEditManager->getFormsFields($revision->getContentType(), $revision->getRawData(), $data['fields']);
            if (0 < \count($forms)) {
                $formsRendered = [];
                /** @var Form $form */
                foreach ($forms as $key => $form) {
                    $formsRendered[$key] = $this->render(
                        '@EMSCore/form/liveEditField.html.twig' ,
                        ['form' => $form->createView() ]
                    )->getContent();
                }

                $draft = $this->liveEditManager->createNewDraft($revision);
                return new JsonResponse([
                    'data' => Json::encode([
                        'editable' => true,
                        'forms' => $formsRendered,
                        'draft' => $draft
                    ])
                ]);
            }
        }

        return new JsonResponse([ 'data' => Json::encode([ 'editable' => false ]) ]);
    }
}
