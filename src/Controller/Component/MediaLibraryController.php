<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Component;

use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryConfig;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryService;
use EMS\CoreBundle\Core\UI\AjaxModal;
use EMS\CoreBundle\Core\UI\AjaxService;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

class MediaLibraryController
{
    public function __construct(
        private readonly MediaLibraryService $mediaLibraryService,
        private readonly AjaxService $ajax,
        private readonly TranslatorInterface $translator,
        private readonly FormFactory $formFactory
    ) {
    }

    public function getFiles(MediaLibraryConfig $config, Request $request): JsonResponse
    {
        return new JsonResponse($this->mediaLibraryService->getFiles($config, $this->getPath($request)));
    }

    public function getFolders(MediaLibraryConfig $config): JsonResponse
    {
        return new JsonResponse($this->mediaLibraryService->getFolders($config));
    }

    public function addFolder(MediaLibraryConfig $config, Request $request): JsonResponse
    {
        $form = $this->formFactory->createBuilder(FormType::class, [])
            ->add('folder_name', TextType::class, ['constraints' => [new NotBlank()]])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $path = $this->getPath($request);
                $folderName = (string) $form->get('folder_name')->getData();

                if ($this->mediaLibraryService->createFolder($config, $folderName, $path)) {
                    /** @var Session $session */
                    $session = $request->getSession();
                    $session->getFlashBag()->clear();

                    return $this->getAjaxModal()->getSuccessResponse(['path' => $path]);
                }
            }
        }

        return $this
            ->getAjaxModal()
            ->setTitle($this->translator->trans('media_library.folder.add.title', [], EMSCoreBundle::TRANS_COMPONENT))
            ->setBody('bodyAddFolder', [
                'form' => $form->createView(),
            ])
            ->setFooter('footerAddFolder')
            ->getResponse();
    }

    public function addFile(MediaLibraryConfig $config, Request $request, string $fileHash): JsonResponse
    {
        $requestJson = Json::decode($request->getContent());
        $file = $requestJson['file'];

        /** @var Session $session */
        $session = $request->getSession();

        if (!$this->mediaLibraryService->createFile($config, $fileHash, $file, $this->getPath($request))) {
            return new JsonResponse([
                'messages' => $session->getFlashBag()->all(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $session->getFlashBag()->clear();

        return new JsonResponse([], Response::HTTP_CREATED);
    }

    private function getPath(Request $request): string
    {
        return $request->query->has('path') ? $request->query->get('path').'/' : '/';
    }

    private function getAjaxModal(): AjaxModal
    {
        return $this->ajax->newAjaxModel('@EMSCore/components/media_library_modal.html.twig');
    }
}
