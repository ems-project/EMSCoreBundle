<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Wysiwyg;

use EMS\CoreBundle\Core\UI\FlashMessageLogger;
use EMS\CoreBundle\Entity\Form\LoadLinkModalEntity;
use EMS\CoreBundle\Form\Form\EditImageModalType;
use EMS\CoreBundle\Form\Form\LoadLinkModalType;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\Helpers\Html\HtmlHelper;
use EMS\Helpers\Standard\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Twig\Environment;

class ModalController extends AbstractController
{
    public function __construct(
        private readonly RevisionService $revisionService,
        private readonly Environment $twig,
        private readonly FlashMessageLogger $flashMessageLogger,
        private readonly string $templateNamespace,
    ) {
    }

    public function loadLinkModal(Request $request): JsonResponse
    {
        $url = (string) $request->request->get('url', '');
        $target = (string) $request->request->get('target', '');
        $content = (string) $request->request->get('content', '');
        $targets = [];
        if (HtmlHelper::isHtml($content)) {
            $crawler = new Crawler($content);
            foreach ($crawler->filter('[id]') as $tag) {
                if (null === $tag->attributes) {
                    continue;
                }
                $node = $tag->attributes->getNamedItem('id');
                if (null === $node) {
                    continue;
                }
                $id = $node->nodeValue;
                $targets[$id] = "#$id";
            }
        }
        $anchorTargets = $request->query->get('anchorTargets');
        if (empty($targets) && \is_string($anchorTargets)) {
            $targets = Json::decode($anchorTargets);
        }

        $loadLinkModalEntity = new LoadLinkModalEntity($url, $target);
        $form = $this->createForm(LoadLinkModalType::class, $loadLinkModalEntity, [
            LoadLinkModalType::WITH_TARGET_BLANK_FIELD => $loadLinkModalEntity->hasTargetBlank(),
            LoadLinkModalType::ANCHOR_TARGETS => $targets,
            'constraints' => [
                new Callback($this->validate(...)),
            ],
        ]);

        $form->handleRequest($request);
        $response = [
            'body' => $this->twig->render("@$this->templateNamespace/modal/default.html.twig", [
                'form' => $form->createView(),
            ]),
        ];
        if ($form->isSubmitted() && !$form->isValid()) {
            $response['success'] = false;
        } elseif ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            if (!$data instanceof LoadLinkModalEntity) {
                throw new \RuntimeException('Unexpected not LoadLinkModalEntity submitted data');
            }

            $response['type'] = 'select-link';
            $response['url'] = $data->generateUrl();
            $response['target'] = $data->getTarget();
        }

        return $this->flashMessageLogger->buildJsonResponse($response);
    }

    public function editImageModal(Request $request): JsonResponse
    {
        $path = (string) $request->request->get('path', '');
        $form = $this->createForm(EditImageModalType::class, [EditImageModalType::FIELD_IMAGE => $path]);
        $form->handleRequest($request);
        $response = [
            'body' => $this->twig->render("@$this->templateNamespace/modal/default.html.twig", [
                'form' => $form->createView(),
            ]),
        ];
        if ($form->isSubmitted() && $form->isValid()) {
            $response['type'] = 'edit-image';
            $response['url'] = $form->getData()['image'];
        }

        return $this->flashMessageLogger->buildJsonResponse($response);
    }

    public function emsLinkInfo(Request $request): JsonResponse
    {
        $link = $request->query->get('link');
        if (!\is_string($link)) {
            return $this->flashMessageLogger->buildJsonResponse([]);
        }

        return $this->flashMessageLogger->buildJsonResponse([
            'label' => $this->revisionService->display($link),
        ]);
    }

    public function validate(LoadLinkModalEntity $loadLinkModalEntity, ExecutionContextInterface $context): void
    {
        $loadLinkModalEntity->validate($context);
    }
}
