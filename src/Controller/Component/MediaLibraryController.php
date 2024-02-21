<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Component;

use EMS\CoreBundle\Core\Component\MediaLibrary\Config\MediaLibraryConfig;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryService;
use EMS\CoreBundle\Core\UI\AjaxModal;
use EMS\CoreBundle\Core\UI\AjaxService;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
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

    public function getHeader(MediaLibraryConfig $config, Request $request): JsonResponse
    {
        $query = $request->query;

        return new JsonResponse([
            'header' => $this->mediaLibraryService->renderHeader(
                config: $config,
                folder: $query->has('folderId') ? $query->get('folderId') : null,
                file: $query->has('fileId') ? $query->get('fileId') : null,
                selectionFiles: $query->has('selectionFiles') ? $query->getInt('selectionFiles') : 0,
                searchValue: $query->get('search')
            ),
        ]);
    }

    public function getFiles(MediaLibraryConfig $config, Request $request): JsonResponse
    {
        $folderId = $request->get('folderId');
        $folder = $folderId ? $this->mediaLibraryService->getFolder($config, $folderId) : null;

        return new JsonResponse($this->mediaLibraryService->renderFiles(
            config: $config,
            from: $request->query->getInt('from'),
            folder: $folder,
            searchValue: $request->get('search')
        ));
    }

    public function getFolders(MediaLibraryConfig $config): JsonResponse
    {
        return new JsonResponse(['folders' => $this->mediaLibraryService->renderFolders($config)]);
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
                'path' => $folder->getPath()->getValue(),
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
        $componentModal = $this->mediaLibraryService->modal($config, [
            'type' => 'delete_folder',
            'title' => $this->translator->trans('media_library.folder.delete.title', [], EMSCoreBundle::TRANS_COMPONENT),
        ]);

        $form = $this->formFactory->createBuilder(FormType::class, $folder)->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $job = $this->mediaLibraryService->jobFolderDelete($config, $user, $folder);
            $this->flashBag($request)->clear();

            $componentModal->modal->data['success'] = true;
            $componentModal->modal->data['jobId'] = $job->getId();
            $componentModal->template->context->append([
                'infoMessage' => $this->translator->trans('media_library.folder.delete.job_info', [], EMSCoreBundle::TRANS_COMPONENT),
            ]);

            return new JsonResponse($componentModal->render());
        }

        $componentModal->template->context->append([
            'confirmMessage' => $this->translator->trans('media_library.folder.delete.warning', [], EMSCoreBundle::TRANS_COMPONENT),
            'form' => $form->createView(),
            'submitIcon' => 'fa-remove',
            'submitClass' => 'btn-outline-danger',
            'submitLabel' => $this->translator->trans('media_library.folder.delete.submit', [], EMSCoreBundle::TRANS_COMPONENT),
        ]);

        return new JsonResponse($componentModal->render());
    }

    public function deleteFile(MediaLibraryConfig $config, Request $request, string $fileId): JsonResponse
    {
        $mediaFile = $this->mediaLibraryService->getFile($config, $fileId);
        $this->mediaLibraryService->deleteDocument($mediaFile);

        $this->flashBag($request)->clear();

        return new JsonResponse(['success' => true]);
    }

    public function deleteFiles(MediaLibraryConfig $config, Request $request): JsonResponse
    {
        $selectionFiles = $request->query->getInt('selectionFiles');
        $folderId = $request->get('folderId');
        $folder = $folderId ? $this->mediaLibraryService->getFolder($config, $folderId) : null;

        $componentModal = $this->mediaLibraryService->modal($config, [
            'type' => 'delete_files',
            'title' => $this->translator->trans('media_library.files.delete.title', ['%count%' => $selectionFiles], EMSCoreBundle::TRANS_COMPONENT),
        ]);

        $form = $this->formFactory->createBuilder(FormType::class, $folder)->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->flashBag($request)->clear();

            $componentModal->modal->data['success'] = true;
            $componentModal->template->context->append([
                'infoMessage' => $this->translator->trans('media_library.files.delete.info', ['%count%' => $selectionFiles], EMSCoreBundle::TRANS_COMPONENT),
            ]);

            return new JsonResponse($componentModal->render());
        }

        $componentModal->template->context->append([
            'confirmMessage' => $this->translator->trans('media_library.files.delete.warning', ['%count%' => $selectionFiles], EMSCoreBundle::TRANS_COMPONENT),
            'form' => $form->createView(),
            'submitIcon' => 'fa-remove',
            'submitClass' => 'btn-outline-danger',
            'submitLabel' => $this->translator->trans('media_library.files.delete.submit', [], EMSCoreBundle::TRANS_COMPONENT),
        ]);

        return new JsonResponse($componentModal->render());
    }

    public function moveFile(MediaLibraryConfig $config, Request $request, string $fileId): JsonResponse
    {
        $data = Json::decode($request->getContent());
        $mediaFile = $this->mediaLibraryService->getFile($config, $fileId);

        $targetFolderId = $data['targetFolderId'] ?? null;
        if (!isset($targetFolderId)) {
            throw new \RuntimeException('Missing target folder id');
        }

        if ('home' === $targetFolderId) {
            $movePath = $mediaFile->getPath()->move('/');
        } else {
            $targetFolder = $this->mediaLibraryService->getFolder($config, $targetFolderId);
            $movePath = $mediaFile->getPath()->move($targetFolder->getPath()->getValue());
        }

        $mediaFile->setPath($movePath);
        $this->mediaLibraryService->updateDocument($mediaFile);

        $this->flashBag($request)->clear();

        return new JsonResponse(['success' => true]);
    }

    public function moveFiles(MediaLibraryConfig $config, Request $request): JsonResponse
    {
        $selectionFiles = $request->query->getInt('selectionFiles');
        $folderId = $request->get('folderId');
        $folder = $folderId ? $this->mediaLibraryService->getFolder($config, $folderId) : null;
        $currentPath = ($folder ? $folder->getPath()->getLabel() : 'Home');

        $componentModal = $this->mediaLibraryService->modal($config, [
            'type' => 'move_files',
            'title' => $this->translator->trans('media_library.files.move.title', ['%count%' => $selectionFiles], EMSCoreBundle::TRANS_COMPONENT),
        ]);

        $folders = $this->mediaLibraryService->getFolders($config)->getChoices();
        $choices = \array_filter($folders, static fn ($folderId) => $folderId !== ($folder->id ?? 'home'));
        $targetId = $request->query->get('targetId');
        $targetFolder = $targetId ? $this->mediaLibraryService->getFolder($config, $targetId) : null;

        $formData = ['target' => $targetFolder?->id];
        $form = $this->formFactory->createBuilder(FormType::class, $formData)->getForm();
        $form
            ->add('target', ChoiceType::class, [
                'constraints' => [new Assert\NotBlank()],
                'label' => 'media_library.files.move.select_folder',
                'translation_domain' => EMSCoreBundle::TRANS_COMPONENT,
                'choice_translation_domain' => false,
                'attr' => ['class' => 'select2'],
                'choices' => $choices,
                'required' => true,
            ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->flashBag($request)->clear();
            $targetId = $form->getData()['target'];
            $targetFolder = 'home' !== $targetId ? $this->mediaLibraryService->getFolder($config, $targetId) : null;

            $componentModal->modal->data['success'] = true;
            $componentModal->modal->data['targetFolderId'] = $targetFolder->id ?? 'home';
            $componentModal->template->context->append([
                'infoMessage' => $this->translator->trans('media_library.files.move.success', [
                    '%count%' => $selectionFiles,
                    '%from%' => $currentPath,
                    '%to%' => $targetFolder ? $targetFolder->getPath()->getLabel() : 'Home',
                ], EMSCoreBundle::TRANS_COMPONENT),
            ]);

            return new JsonResponse($componentModal->render());
        }

        $componentModal->template->context->append([
            'infoMessage' => $this->translator->trans('media_library.files.move.info', ['%path%' => $currentPath], EMSCoreBundle::TRANS_COMPONENT),
            'form' => $form->createView(),
            'submitIcon' => 'fa-location-arrow',
            'submitLabel' => $this->translator->trans('media_library.files.move.submit', [], EMSCoreBundle::TRANS_COMPONENT),
        ]);

        return new JsonResponse($componentModal->render());
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
