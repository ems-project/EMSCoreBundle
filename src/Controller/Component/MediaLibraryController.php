<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Component;

use EMS\CoreBundle\Core\Component\MediaLibrary\Config\MediaLibraryConfig;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryService;
use EMS\CoreBundle\Core\UI\AjaxModal;
use EMS\CoreBundle\Core\UI\AjaxService;
use EMS\CoreBundle\Core\UI\Modal\ModalMessageType;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\User\UserInterface;
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

    public function getHeader(MediaLibraryConfig $config, Request $request, string $folderId = null): JsonResponse
    {
        return new JsonResponse([
            'header' => $this->mediaLibraryService->renderHeader(
                config: $config,
                folder: $folderId,
                fileIds: $request->query->all('files')
            ),
        ]);
    }

    public function getFiles(MediaLibraryConfig $config, Request $request): JsonResponse
    {
        $from = $request->query->getInt('from');
        $folderId = $request->get('folderId');
        $folder = $folderId ? $this->mediaLibraryService->getFolder($config, $folderId) : null;

        return new JsonResponse($this->mediaLibraryService->getFiles($config, $from, $folder));
    }

    public function getFolders(MediaLibraryConfig $config): JsonResponse
    {
        return new JsonResponse($this->mediaLibraryService->getFolders($config));
    }

    public function addFolder(MediaLibraryConfig $config, Request $request): JsonResponse
    {
        $folderId = $request->get('folderId');
        $parentFolder = $folderId ? $this->mediaLibraryService->getFolder($config, $folderId) : null;

        $form = $this->formFactory->createBuilder(FormType::class, [])
            ->add('folder_name', TextType::class, ['constraints' => [new NotBlank()]])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $folderName = (string) $form->get('folder_name')->getData();
            $folder = $this->mediaLibraryService->createFolder($config, $folderName, $parentFolder);

            if ($folder) {
                $this->flashBag($request)->clear();

                return $this->getAjaxModal()->getSuccessResponse(['path' => $folder->getPath()->getValue()]);
            }
        }

        return $this
            ->getAjaxModal()
            ->setTitle($this->translator->trans('media_library.folder.add.title', [], EMSCoreBundle::TRANS_COMPONENT))
            ->setBody('bodyAddFolder', ['form' => $form->createView()])
            ->setFooter('footerAddFolder')
            ->getResponse();
    }

    public function addFile(MediaLibraryConfig $config, Request $request): JsonResponse
    {
        $folderId = $request->get('folderId');
        $folder = $folderId ? $this->mediaLibraryService->getFolder($config, $folderId) : null;
        $file = Json::decode($request->getContent())['file'];

        if (!$this->mediaLibraryService->createFile($config, $file, $folder)) {
            return new JsonResponse([
                'messages' => $this->flashBag($request)->all(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->flashBag($request)->clear();

        return new JsonResponse([], Response::HTTP_CREATED);
    }

    public function renameFile(MediaLibraryConfig $config, Request $request, string $fileId): JsonResponse
    {
        $mediaFile = $this->mediaLibraryService->getFile($config, $fileId);

        $form = $this->formFactory->createBuilder(FormType::class, $mediaFile)
            ->add('name', TextType::class, ['constraints' => [new NotBlank()]])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->mediaLibraryService->updateDocument($mediaFile);
            $this->mediaLibraryService->refresh($config);
            $this->flashBag($request)->clear();

            return new JsonResponse([
                'success' => true,
                'fileRow' => $this->mediaLibraryService->renderFileRow($config, $mediaFile),
            ]);
        }

        $modal = $this->mediaLibraryService->modal($config, [
            'type' => 'rename',
            'title' => $this->translator->trans('media_library.file.rename.title', [], EMSCoreBundle::TRANS_COMPONENT),
            'form' => $form->createView(),
        ]);

        return new JsonResponse($modal->render());
    }

    public function renameFolder(MediaLibraryConfig $config, Request $request, UserInterface $user, string $folderId): JsonResponse
    {
        $folder = $this->mediaLibraryService->getFolder($config, $folderId);

        $form = $this->formFactory->createBuilder(FormType::class, $folder)
            ->add('name', TextType::class, ['constraints' => [new NotBlank()]])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $job = $this->mediaLibraryService->jobFolderRename($config, $user, $folder);
            $this->flashBag($request)->clear();

            return new JsonResponse([
                'success' => true,
                'jobId' => $job->getId(),
                'modalBody' => '',
                'modalMessages' => [
                    ['info' => $this->translator->trans('media_library.folder.rename.job_info', [], EMSCoreBundle::TRANS_COMPONENT)],
                ],
            ]);
        }

        $modal = $this->mediaLibraryService->modal($config, [
            'type' => 'rename',
            'title' => $this->translator->trans('media_library.folder.rename.title', [], EMSCoreBundle::TRANS_COMPONENT),
            'form' => $form->createView(),
            'submitIcon' => 'fa-pencil',
            'submitLabel' => $this->translator->trans('media_library.folder.rename.submit', [], EMSCoreBundle::TRANS_COMPONENT),
        ]);

        return new JsonResponse($modal->render());
    }

    public function deleteFolder(MediaLibraryConfig $config, Request $request, UserInterface $user, string $folderId): JsonResponse
    {
        $folder = $this->mediaLibraryService->getFolder($config, $folderId);

        $form = $this->formFactory->createBuilder(FormType::class, $folder)->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $job = $this->mediaLibraryService->jobFolderDelete($config, $user, $folder);
            $this->flashBag($request)->clear();

            return new JsonResponse([
                'success' => true,
                'jobId' => $job->getId(),
                'modalBody' => '',
                'modalMessages' => [
                    ['info' => $this->translator->trans('media_library.folder.delete.job_info', [], EMSCoreBundle::TRANS_COMPONENT)],
                ],
            ]);
        }

        $componentModal = $this->mediaLibraryService->modal($config, [
            'type' => 'delete',
            'title' => $this->translator->trans('media_library.folder.delete.title', [], EMSCoreBundle::TRANS_COMPONENT),
            'form' => $form->createView(),
            'submitIcon' => 'fa-remove',
            'submitClass' => 'btn-outline-danger',
            'submitLabel' => $this->translator->trans('media_library.folder.delete.submit', [], EMSCoreBundle::TRANS_COMPONENT),
        ]);

        $componentModal->modal->addMessage(
            ModalMessageType::Warning,
            $this->translator->trans('media_library.folder.delete.warning', [], EMSCoreBundle::TRANS_COMPONENT)
        );

        return new JsonResponse($componentModal->render());
    }

    public function deleteFiles(MediaLibraryConfig $config, Request $request): JsonResponse
    {
        $fileIds = Json::decode($request->getContent())['files'];

        $success = $this->mediaLibraryService->deleteFiles($config, $fileIds);
        $this->flashBag($request)->clear();

        return new JsonResponse(['success' => $success]);
    }

    private function getAjaxModal(): AjaxModal
    {
        return $this->ajax->newAjaxModel("@$this->templateNamespace/components/media_library/modal.html.twig");
    }

    private function flashBag(Request $request): FlashBagInterface
    {
        /** @var Session $session */
        $session = $request->getSession();

        return $session->getFlashBag();
    }
}
