<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Common\Standard\Json;
use EMS\CoreBundle\Core\Revision\LiveEditManager;
use EMS\CoreBundle\Entity\Revision;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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

        if (null !== $revision->getContentType()->getFieldType()) {
            if ($this->liveEditManager->isEditableByUser($revision->getContentType()->getFieldType(), $data['fields'])) {
                $forms = $this->liveEditManager->getFormsFields();
                if (0 < \count($forms)) {
                    $draft = $this->liveEditManager->createNewDraft($revision);
                    return new JsonResponse([
                        'data' => Json::encode([
                            'editable' => true,
                            'forms' => $forms,
                            'draft' => $draft
                        ])
                    ]);
                }
            }
        }
        return new JsonResponse([ 'data' => Json::encode([ 'editable' => false ]) ]);
    }
}
