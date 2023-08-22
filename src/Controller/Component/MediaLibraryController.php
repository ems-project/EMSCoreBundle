<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Component;

use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryConfig;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryService;
use EMS\CoreBundle\Core\Component\MediaLibrary\Request\MediaLibraryRequest;
use EMS\CoreBundle\Core\UI\AjaxModal;
use EMS\CoreBundle\Core\UI\AjaxService;
use EMS\CoreBundle\EMSCoreBundle;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

class MediaLibraryController
{
    public function __construct(
        private readonly MediaLibraryService $mediaLibraryService,
        private readonly AjaxService $ajax,
        private readonly TranslatorInterface $translator,
        private readonly FormFactory $formFactory,
        private readonly string $templateNamespace
    ) {
    }

    public function getFiles(MediaLibraryConfig $config, MediaLibraryRequest $request): JsonResponse
    {
        return new JsonResponse($this->mediaLibraryService->getFiles($config, $request));
    }

    public function getFolders(MediaLibraryConfig $config): JsonResponse
    {
        return new JsonResponse($this->mediaLibraryService->getFolders($config));
    }

    public function addFolder(MediaLibraryConfig $config, MediaLibraryRequest $request): JsonResponse
    {
        $form = $this->formFactory->createBuilder(FormType::class, [])
            ->add('folder_name', TextType::class, ['constraints' => [new NotBlank()]])
            ->getForm();

        $form->handleRequest($request->getRequest());

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $folderName = (string) $form->get('folder_name')->getData();
                $folder = $this->mediaLibraryService->createFolder($config, $request, $folderName);

                if ($folder) {
                    $request->clearFlashes();

                    return $this->getAjaxModal()->getSuccessResponse(['path' => $folder->path]);
                }
            }
        }

        return $this
            ->getAjaxModal()
            ->setTitle($this->translator->trans('media_library.folder.add.title', [], EMSCoreBundle::TRANS_COMPONENT))
            ->setBody('bodyAddFolder', ['form' => $form->createView()])
            ->setFooter('footerAddFolder')
            ->getResponse();
    }

    public function addFile(MediaLibraryConfig $config, MediaLibraryRequest $request): JsonResponse
    {
        if (!$this->mediaLibraryService->createFile($config, $request)) {
            return new JsonResponse([
                'messages' => $request->getFlashes(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $request->clearFlashes();

        return new JsonResponse([], Response::HTTP_CREATED);
    }

    private function getAjaxModal(): AjaxModal
    {
        return $this->ajax->newAjaxModel("$this->templateNamespace/components/media_library/modal.html.twig");
    }
}
