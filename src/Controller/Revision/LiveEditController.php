<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Common\Standard\Json;
use EMS\CoreBundle\Core\Revision\LiveEditManager;
use EMS\CoreBundle\Entity\Revision;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
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

        $emsLink = EMSLink::fromText($data['emsId']);
        $revision = $this->liveEditManager->getRevision($emsLink);

        if (!$revision instanceof Revision) {
            throw new \RuntimeException(\sprintf('Revision with emsId "%s" not found', $data['emsId']));
        }
        if (null == $revision->giveContentType()->getFieldType() || $revision->giveContentType()->giveEnvironment()->getManaged()) {
            new JsonResponse(['data' => Json::encode(['isEditable' => false])]);
        }

        if ($this->liveEditManager->isEditableByUser($revision->giveContentType(), $revision->getRawData(), $data['fields'])) {
            $forms = $this->liveEditManager->getFormsFields($revision->giveContentType(), $revision->getRawData(), $data['fields']);
            if (0 < \count($forms)) {
                $formsRendered = [];
                /** @var Form $form */
                foreach ($forms as $key => $form) {
                    $formsRendered[$key] = $this->render(
                        '@EMSCore/live-edit/field.html.twig',
                        ['form' => $form->createView()]
                    )->getContent();
                }

                $draft = $this->liveEditManager->createNewDraft($revision);

                if (null != $draft) {
                    return new JsonResponse([
                        'data' => Json::encode([
                            'isEditable' => true,
                            'emsId' => $emsLink->getEmsId(),
                            'forms' => $formsRendered,
                            'draftRevision' => $draft->getId(),
                            'buttons' => $this->render(
                                '@EMSCore/live-edit/buttons.html.twig',
                                [
                                    'emsId' => $emsLink->getEmsId(),
                                    'draftRevision' => $draft->getId(),
                                ])
                                ->getContent(),
                        ]),
                    ]);
                }
            }
        }

        return new JsonResponse(['data' => Json::encode(['isEditable' => false])]);
    }

    public function saveLiveEditRevision(string $revisionId, Request $request): JsonResponse
    {
        $requestContent = $request->getContent();
        $decoded = \is_string($requestContent) && \strlen($requestContent) > 0 ? Json::decode($requestContent) : [];
        $data = $decoded['_data'] ?? [];

        $emsLink = EMSLink::fromText($data['emsId']);
        $revision = $this->liveEditManager->getRevision($emsLink);

        if (!$revision instanceof Revision) {
            throw new \RuntimeException(\sprintf('Revision with emsId "%s" not found', $data['emsId']));
        }

        if ($revision->getId() !== \intval($revisionId)) {
            throw new \RuntimeException(\sprintf('RevisonId %d not corresponding to draftId %d !', $revision->getId(), $revisionId));
        }

        $this->liveEditManager->saveFields($revision, $data['fields']);

        return new JsonResponse(['data' => Json::encode(['isEditable' => false])]);
    }
}
