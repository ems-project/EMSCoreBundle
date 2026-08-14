<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Component;

use EMS\CoreBundle\Core\Component\MediaLibrary\Config\MediaLibraryConfig;
use EMS\CoreBundle\Core\Component\MediaLibrary\Folder\MediaLibraryFolder;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryService;
use EMS\CoreBundle\Core\UI\FlashMessageLogger;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Form\Form\MediaLibrary\MediaLibraryDocumentFormType;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class MediaLibraryController
{
    public function __construct(
        private readonly MediaLibraryService $mediaLibraryService,
        private readonly FlashMessageLogger $flashMessageLogger,
        private readonly TranslatorInterface $translator,
        private readonly FormFactory $formFactory,
        private readonly bool $asyncEnabled,
    ) {
    }

    public function addFile(Request $request, ?string $folderId): JsonResponse
    {
        $parentFolder = $folderId ? $this->mediaLibraryService->getFolder($folderId) : null;

        $newFile = $this->mediaLibraryService->newFile($parentFolder);
        $form = $this->formFactory->create(MediaLibraryDocumentFormType::class, $newFile, [
            'csrf_protection' => false,
        ]);
        $form->submit($request->request->all());

        if (!$form->isValid()) {
            $firstError = $form->getErrors(true)->current()->getMessage();

            return new JsonResponse(['error' => $firstError], Response::HTTP_CONFLICT);
        }

        if (null === $this->mediaLibraryService->createFile($newFile)) {
            return new JsonResponse(['messages' => $this->flashBag($request)->all()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->flashBag($request)->clear();

        return new JsonResponse([], Response::HTTP_CREATED);
    }

    public function addFolder(Request $request, ?string $folderId): JsonResponse
    {
        $parentFolder = $folderId ? $this->mediaLibraryService->getFolder($folderId) : null;
        $newFolder = $this->mediaLibraryService->newFolder($parentFolder);

        $componentModal = $this->mediaLibraryService->modal([
            'type' => 'add_folder',
            'title' => $this->translator->trans('media_library.folder.add.title', [], EMSCoreBundle::TRANS_COMPONENT),
        ]);

        $form = $this->formFactory->create(MediaLibraryDocumentFormType::class, $newFolder);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $folder = $this->mediaLibraryService->createFolder($newFolder);

            if ($folder instanceof MediaLibraryFolder) {
                $this->flashBag($request)->clear();

                $componentModal->modal->data['success'] = true;
                $componentModal->modal->data['path'] = $folder->getPath()->getValue();

                return new JsonResponse($componentModal->render());
            }
        }

        $componentModal->template->context->append([
            'form' => $form->createView(),
        ]);

        return new JsonResponse($componentModal->render());
    }

    public function deleteFile(Request $request, string $fileId): JsonResponse
    {
        $mediaFile = $this->mediaLibraryService->getFile($fileId);
        $this->mediaLibraryService->deleteDocument($mediaFile);

        $this->flashBag($request)->clear();

        return new JsonResponse(['success' => true]);
    }

    public function deleteFiles(Request $request, ?string $folderId): JsonResponse
    {
        $selectionFiles = $request->query->getInt('selectionFiles');
        $folder = $folderId ? $this->mediaLibraryService->getFolder($folderId) : null;

        $componentModal = $this->mediaLibraryService->modal([
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
        ]);

        return new JsonResponse($componentModal->render());
    }

    public function deleteFolder(Request $request, UserInterface $user, string $folderId): JsonResponse
    {
        $folder = $this->mediaLibraryService->getFolder($folderId);
        $componentModal = $this->mediaLibraryService->modal([
            'type' => 'delete_folder',
            'title' => $this->translator->trans('media_library.folder.delete.title', [], EMSCoreBundle::TRANS_COMPONENT),
        ]);

        $form = $this->formFactory->createBuilder(FormType::class, $folder)->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $job = $this->mediaLibraryService->jobFolderDelete($user, $folder);
            $this->flashBag($request)->clear();
            $modalMessage = ($this->asyncEnabled)
                ? t('media_library.folder.delete.job_info_async')
                : t('media_library.folder.delete.job_info')
            ;

            $componentModal->modal->data['success'] = true;
            $componentModal->modal->data['jobId'] = $job->getId();
            $componentModal->modal->data['async'] = $this->asyncEnabled;
            $componentModal->template->context->append([
                'infoMessage' => $this->translator->trans($modalMessage->getMessage(), [], EMSCoreBundle::TRANS_COMPONENT),
            ]);

            return new JsonResponse($componentModal->render());
        }

        $componentModal->template->context->append([
            'confirmMessage' => $this->translator->trans('media_library.folder.delete.warning', [], EMSCoreBundle::TRANS_COMPONENT),
            'form' => $form->createView(),
        ]);

        return new JsonResponse($componentModal->render());
    }

    public function getFiles(Request $request, ?string $folderId): JsonResponse
    {
        $query = $request->query;
        $folder = $folderId ? $this->mediaLibraryService->getFolder($folderId) : null;

        $sortOrder = $query->get('sortOrder');
        if ($sortOrder && !\in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'asc';
        }
        $searchType = $query->getString('searchType', MediaLibraryConfig::TERM_SEARCH_TYPE);

        return new JsonResponse($this->mediaLibraryService->renderFiles(
            from: $query->getInt('from'),
            folder: $folder,
            sortId: $query->get('sortId'),
            sortOrder: $sortOrder,
            selectionFiles: $query->has('selectionFiles') ? $query->getInt('selectionFiles') : 0,
            searchValue: $query->get('search'),
            searchType: $searchType,
        ));
    }

    public function getFolders(): JsonResponse
    {
        return new JsonResponse(['folders' => $this->mediaLibraryService->renderFolders()]);
    }

    public function getLayout(Request $request): JsonResponse
    {
        $query = $request->query;

        return new JsonResponse($this->mediaLibraryService->renderLayout(
            loaded: $query->getInt('loaded'),
            folder: $query->has('folderId') ? $query->get('folderId') : null,
            file: $query->has('fileId') ? $query->get('fileId') : null,
            selectionFiles: $query->has('selectionFiles') ? $query->getInt('selectionFiles') : 0,
            searchValue: $query->get('search')
        ));
    }

    public function moveFile(Request $request, string $fileId): JsonResponse
    {
        $file = $this->mediaLibraryService->getFile($fileId);
        $data = Json::decode($request->getContent());

        $targetFolderId = $data['targetFolderId'] ?? null;
        if (!isset($targetFolderId)) {
            throw new \RuntimeException('Missing target folder id');
        }

        $folder = 'home' !== $targetFolderId ? $this->mediaLibraryService->getFolder($targetFolderId) : null;
        $this->mediaLibraryService->moveFile($file, $folder);

        $form = $this->formFactory->create(MediaLibraryDocumentFormType::class, $file, ['csrf_protection' => false]);
        $form->submit($request->request->all(), false);

        if (!$form->isValid()) {
            $firstError = $form->getErrors(true)->current()->getMessage();

            return new JsonResponse(['error' => $firstError], Response::HTTP_CONFLICT);
        }

        $this->mediaLibraryService->updateDocument($file);
        $this->flashBag($request)->clear();

        return new JsonResponse(['success' => true]);
    }

    public function moveFiles(Request $request, ?string $folderId): JsonResponse
    {
        $selectionFiles = $request->query->getInt('selectionFiles');
        $folder = $folderId ? $this->mediaLibraryService->getFolder($folderId) : null;
        $currentPath = ($folder instanceof MediaLibraryFolder ? $folder->getPath()->getLabel() : 'Home');

        $componentModal = $this->mediaLibraryService->modal([
            'type' => 'move_files',
            'title' => $this->translator->trans('media_library.files.move.title', ['%count%' => $selectionFiles], EMSCoreBundle::TRANS_COMPONENT),
        ]);

        $folders = $this->mediaLibraryService->getFolders()->getChoices();
        $choices = \array_filter($folders, static fn ($folderId) => $folderId !== ($folder->id ?? 'home'));
        $targetId = $request->query->get('targetId');
        $targetFolder = $targetId ? $this->mediaLibraryService->getFolder($targetId) : null;

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
            $targetFolder = 'home' !== $targetId ? $this->mediaLibraryService->getFolder($targetId) : null;

            $componentModal->modal->data['success'] = true;
            $componentModal->modal->data['targetFolderId'] = $targetFolder->id ?? 'home';
            $componentModal->template->context->append([
                'infoMessage' => $this->translator->trans('media_library.files.move.success', [
                    '%count%' => $selectionFiles,
                    '%from%' => $currentPath,
                    '%to%' => $targetFolder instanceof MediaLibraryFolder ? $targetFolder->getPath()->getLabel() : 'Home',
                ], EMSCoreBundle::TRANS_COMPONENT),
            ]);

            return new JsonResponse($componentModal->render());
        }

        $componentModal->template->context->append([
            'infoMessage' => $this->translator->trans('media_library.files.move.info', ['%path%' => $currentPath], EMSCoreBundle::TRANS_COMPONENT),
            'form' => $form->createView(),
        ]);

        return new JsonResponse($componentModal->render());
    }

    public function moveFolder(Request $request, UserInterface $user, string $folderId): JsonResponse
    {
        $folder = $this->mediaLibraryService->getFolder($folderId);
        $currentPath = $folder->getPath()->getLabel();

        $modal = $this->mediaLibraryService->modal([
            'type' => 'move_folder',
            'title' => $this->translator->trans('media_library.folder.move.title', [], EMSCoreBundle::TRANS_COMPONENT),
        ]);

        $folders = $this->mediaLibraryService->getFolders()->getChoices();
        $choices = \array_filter($folders, static function ($folderId) use ($folders, $folder) {
            if ($folderId === ($folder->id ?? 'home')) {
                return false;
            }

            if ($folderId === ($folder->getParent()->id ?? 'home')) {
                return false;
            }

            $targetPathRaw = \array_flip($folders)[$folderId] ?? null;
            $targetPath = $targetPathRaw ? '/'.\str_replace(' ', '', $targetPathRaw) : null;
            $currentPath = \str_replace(' ', '', $folder->getPath()->getValue());

            return !$targetPath || !\str_starts_with($targetPath, $currentPath);
        });

        $formData = ['target' => $request->query->get('targetId') ?: 'home'];
        $form = $this->formFactory->createBuilder(FormType::class, $formData)->getForm();
        $form
            ->add('target', ChoiceType::class, [
                'constraints' => [new Assert\NotBlank()],
                'label' => 'media_library.folder.move.select_folder',
                'translation_domain' => EMSCoreBundle::TRANS_COMPONENT,
                'choice_translation_domain' => false,
                'attr' => ['class' => 'select2'],
                'choices' => $choices,
                'required' => true,
            ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $targetId = $form->getData()['target'];

            $folder->setPath($folder->getpath());
            $job = $this->mediaLibraryService->jobFolderMove($user, $folder, $targetId);

            $modalMessage = ($this->asyncEnabled)
                ? t('media_library.folder.move.job_info_async')
                : t('media_library.folder.move.job_info')
            ;

            return $this->flashMessageLogger->buildJsonResponse([
                'success' => true,
                'async' => $this->asyncEnabled,
                'jobId' => $job->getId(),
                'path' => $folder->getPath()->getValue(),
                'modalBody' => '',
                'modalMessages' => [
                    ['info' => $this->translator->trans($modalMessage->getMessage(), [], EMSCoreBundle::TRANS_COMPONENT)],
                ],
            ]);
        }

        $modal->template->context->append([
            'infoMessage' => $this->translator->trans('media_library.folder.move.info', ['%path%' => $currentPath], EMSCoreBundle::TRANS_COMPONENT),
            'form' => $form->createView(),
        ]);

        return new JsonResponse($modal->render());
    }

    public function renameFile(Request $request, string $fileId): JsonResponse
    {
        $file = $this->mediaLibraryService->getFile($fileId);

        $form = $this->formFactory->create(MediaLibraryDocumentFormType::class, $file);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->mediaLibraryService->updateDocument($file);
            $this->flashBag($request)->clear();

            return new JsonResponse([
                'success' => true,
                'fileRow' => $this->mediaLibraryService->renderFileRow($file),
            ]);
        }

        $modal = $this->mediaLibraryService->modal([
            'type' => 'rename_file',
            'title' => $this->translator->trans('media_library.file.rename.title', [], EMSCoreBundle::TRANS_COMPONENT),
            'form' => $form->createView(),
        ]);

        return new JsonResponse($modal->render());
    }

    public function renameFolder(Request $request, UserInterface $user, string $folderId): JsonResponse
    {
        $folder = $this->mediaLibraryService->getFolder($folderId);

        $form = $this->formFactory->create(MediaLibraryDocumentFormType::class, $folder);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $folder->setName($folder->giveName());
            $job = $this->mediaLibraryService->jobFolderRename($user, $folder);
            $this->flashBag($request)->clear();

            $modalMessage = ($this->asyncEnabled)
                ? t('media_library.folder.rename.job_info_async')
                : t('media_library.folder.rename.job_info')
            ;

            return new JsonResponse([
                'success' => true,
                'async' => $this->asyncEnabled,
                'jobId' => $job->getId(),
                'path' => $folder->getPath()->getValue(),
                'modalBody' => '',
                'modalMessages' => [
                    ['info' => $this->translator->trans($modalMessage->getMessage(), [], EMSCoreBundle::TRANS_COMPONENT)],
                ],
            ]);
        }

        $modal = $this->mediaLibraryService->modal([
            'type' => 'rename_folder',
            'title' => $this->translator->trans('media_library.folder.rename.title', [], EMSCoreBundle::TRANS_COMPONENT),
            'form' => $form->createView(),
        ]);

        return new JsonResponse($modal->render());
    }

    public function viewFile(string $fileId): JsonResponse
    {
        $file = $this->mediaLibraryService->getFile($fileId);
        $modalTitle = $this->translator->trans('media_library.file.view.title_modal', ['%name%' => $file->giveName()], EMSCoreBundle::TRANS_COMPONENT);

        $modal = $this->mediaLibraryService->modal([
            'type' => 'view',
            'title' => $modalTitle,
            'mediaFile' => $file,
        ]);
        $modal
            ->setBlockBody('media_lib_modal_preview')
            ->setBlockFooter('media_lib_modal_preview_footer');

        return new JsonResponse($modal->render());
    }

    private function flashBag(Request $request): FlashBagInterface
    {
        /** @var Session $session */
        $session = $request->getSession();

        return $session->getFlashBag();
    }
}
