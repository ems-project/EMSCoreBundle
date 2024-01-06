<?php

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\WysiwygProfile;
use EMS\CoreBundle\Entity\WysiwygStylesSet;
use EMS\CoreBundle\Form\Form\ReorderBisType;
use EMS\CoreBundle\Form\Form\ReorderType;
use EMS\CoreBundle\Form\Form\WysiwygProfileType;
use EMS\CoreBundle\Form\Form\WysiwygStylesSetType;
use EMS\CoreBundle\Service\WysiwygProfileService;
use EMS\CoreBundle\Service\WysiwygStylesSetService;
use EMS\Helpers\Standard\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class WysiwygController extends AbstractController
{
    public function __construct(private readonly WysiwygProfileService $wysiwygProfileService, private readonly WysiwygStylesSetService $wysiwygStylesSetService, private readonly TranslatorInterface $translator, private readonly string $templateNamespace)
    {
    }

    public function index(Request $request): Response
    {
        $data = [];
        $form = $this->createForm(ReorderType::class, $data, [
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $order = Json::decode((string) $form->getData()['items']);
            $i = 1;
            foreach ($order as $id) {
                $profile = $this->wysiwygProfileService->getById(\intval($id['id']));
                if (null === $profile) {
                    throw new NotFoundHttpException(\sprintf('WYSIWYG Profile %d not found', \intval($id['id'])));
                }
                $profile->setOrderKey($i++);

                $this->wysiwygProfileService->saveProfile($profile);
            }

            return $this->redirectToRoute('ems_wysiwyg_index');
        }

        $dataStylesSet = [];
        $formStylesSet = $this->createForm(ReorderBisType::class, $dataStylesSet, [
        ]);
        $formStylesSet->handleRequest($request);

        if ($formStylesSet->isSubmitted()) {
            $order = Json::decode((string) $formStylesSet->getData()['items']);
            $i = 1;
            foreach ($order as $id) {
                $stylesSet = $this->wysiwygStylesSetService->getById(\intval($id['id']));
                if (null === $stylesSet) {
                    throw new NotFoundHttpException(\sprintf('WYSIWYG Styles Set %d not found', \intval($id['id'])));
                }
                $stylesSet->setOrderKey($i++);

                $this->wysiwygStylesSetService->save($stylesSet);
            }

            return $this->redirectToRoute('ems_wysiwyg_index');
        }

        return $this->render("@$this->templateNamespace/wysiwygprofile/index.html.twig", [
                'profiles' => $this->wysiwygProfileService->getProfiles(),
                'stylesSets' => $this->wysiwygStylesSetService->getStylesSets(),
                'form' => $form->createView(),
                'formStylesSet' => $formStylesSet->createView(),
        ]);
    }

    public function newProfile(Request $request): Response
    {
        $profile = new WysiwygProfile();

        $form = $this->createForm(WysiwygProfileType::class, $profile, [
                'createform' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                Json::decode($profile->getConfig() ?? '{}');
                $profile->setOrderKey(100 + \count($this->wysiwygProfileService->getProfiles()));
                $this->wysiwygProfileService->saveProfile($profile);

                return $this->redirectToRoute('ems_wysiwyg_index');
            } catch (\Throwable $e) {
                $form->get('config')->addError(new FormError($this->translator->trans('wysiwyg.invalid_config_format', ['%msg%' => $e->getMessage()], EMSCoreBundle::TRANS_DOMAIN)));
            }
        }

        return $this->render("@$this->templateNamespace/wysiwygprofile/new.html.twig", [
            'form' => $form->createView(),
        ]);
    }

    public function newStylesSet(Request $request): Response
    {
        $stylesSet = new WysiwygStylesSet();

        $form = $this->createForm(WysiwygStylesSetType::class, $stylesSet, [
                'createform' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                Json::decode($stylesSet->getConfig());
                $stylesSet->setOrderKey(100 + \count($this->wysiwygStylesSetService->getStylesSets()));
                $this->wysiwygStylesSetService->save($stylesSet);

                return $this->redirectToRoute('ems_wysiwyg_index');
            } catch (\Throwable $e) {
                $form->get('config')->addError(new FormError($this->translator->trans('wysiwyg.invalid_config_format', ['%msg%' => $e->getMessage()], 'EMSCoreBundle')));
            }
        }

        return $this->render("@$this->templateNamespace/wysiwyg_styles_set/new.html.twig", [
                'form' => $form->createView(),
        ]);
    }

    public function editStylesSet(Request $request, WysiwygStylesSet $stylesSet): Response
    {
        $form = $this->createForm(WysiwygStylesSetType::class, $stylesSet);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $removedButton = $form->get('remove');
            if ($removedButton instanceof ClickableInterface && $removedButton->isClicked()) {
                $this->wysiwygStylesSetService->remove($stylesSet);

                return $this->redirectToRoute('ems_wysiwyg_index');
            }

            if ($form->isValid()) {
                try {
                    Json::decode($stylesSet->getConfig());
                    $this->wysiwygStylesSetService->save($stylesSet);

                    return $this->redirectToRoute('ems_wysiwyg_index');
                } catch (\Throwable $e) {
                    $form->get('config')->addError(new FormError($this->translator->trans('wysiwyg.invalid_config_format', ['%msg%' => $e->getMessage()], 'EMSCoreBundle')));
                }
            }
        }

        return $this->render("@$this->templateNamespace/wysiwyg_styles_set/edit.html.twig", [
                'form' => $form->createView(),
        ]);
    }

    public function editProfile(Request $request, WysiwygProfile $profile): Response
    {
        $form = $this->createForm(WysiwygProfileType::class, $profile);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $removeButton = $form->get('remove');
            if ($removeButton instanceof ClickableInterface && $removeButton->isClicked()) {
                $this->wysiwygProfileService->remove($profile);

                return $this->redirectToRoute('ems_wysiwyg_index');
            }

            if ($form->isValid()) {
                try {
                    Json::decode($profile->getConfig() ?? '{}');
                    $this->wysiwygProfileService->saveProfile($profile);

                    return $this->redirectToRoute('ems_wysiwyg_index');
                } catch (\Throwable $e) {
                    $form->get('config')->addError(new FormError($this->translator->trans('wysiwyg.invalid_config_format', ['%msg%' => $e->getMessage()], 'EMSCoreBundle')));
                }
            }
        }

        return $this->render("@$this->templateNamespace/wysiwygprofile/edit.html.twig", [
                'form' => $form->createView(),
        ]);
    }
}
