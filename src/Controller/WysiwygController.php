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
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Wysiwyg controller.
 *
 * @Route("/wysiwyg-options")
 */
class WysiwygController extends AppController
{
    /**
     * Lists all Wysiwyg options.
     *
     * @return RedirectResponse|Response
     *
     * @Route("/", name="ems_wysiwyg_index", methods={"GET", "POST"})
     */
    public function indexAction(Request $request, WysiwygProfileService $wysiwygProfileService, WysiwygStylesSetService $wysiwygStylesSetService)
    {
        $data = [];
        $form = $this->createForm(ReorderType::class, $data, [
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $order = \json_decode($form->getData()['items'], true);
            $i = 1;
            foreach ($order as $id) {
                $profile = $wysiwygProfileService->getById(\intval($id['id']));
                if (null === $profile) {
                    throw new NotFoundHttpException(\sprintf('WYSIWYG Profile %d not found', \intval($id['id'])));
                }
                $profile->setOrderKey($i++);

                $wysiwygProfileService->saveProfile($profile);
            }

            return $this->redirectToRoute('ems_wysiwyg_index');
        }

        $dataStylesSet = [];
        $formStylesSet = $this->createForm(ReorderBisType::class, $dataStylesSet, [
        ]);
        $formStylesSet->handleRequest($request);

        if ($formStylesSet->isSubmitted()) {
            $order = \json_decode($formStylesSet->getData()['items'], true);
            $i = 1;
            foreach ($order as $id) {
                $stylesSet = $wysiwygStylesSetService->getById(\intval($id['id']));
                if (null === $stylesSet) {
                    throw new NotFoundHttpException(\sprintf('WYSIWYG Styles Set %d not found', \intval($id['id'])));
                }
                $stylesSet->setOrderKey($i++);

                $wysiwygStylesSetService->save($stylesSet);
            }

            return $this->redirectToRoute('ems_wysiwyg_index');
        }

        return $this->render('@EMSCore/wysiwygprofile/index.html.twig', [
                'profiles' => $wysiwygProfileService->getProfiles(),
                'stylesSets' => $wysiwygStylesSetService->getStylesSets(),
                'form' => $form->createView(),
                'formStylesSet' => $formStylesSet->createView(),
        ]);
    }

    /**
     * Creates a new WYSIWYG profile entity.
     *
     * @return RedirectResponse|Response
     *
     * @Route("/profile/new", name="ems_wysiwyg_profile_new", methods={"GET", "POST"})
     */
    public function newProfileAction(Request $request, TranslatorInterface $translator, WysiwygProfileService $wysiwygProfileService)
    {
        $profile = new WysiwygProfile();

        $form = $this->createForm(WysiwygProfileType::class, $profile, [
                'createform' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            \json_decode($profile->getConfig(), true);
            if (\json_last_error()) {
                $form->get('config')->addError(new FormError($translator->trans('wysiwyg.invalid_config_format', ['%msg%' => \json_last_error_msg()], EMSCoreBundle::TRANS_DOMAIN)));
            } else {
                $profile->setOrderKey(100 + \count($wysiwygProfileService->getProfiles()));
                $wysiwygProfileService->saveProfile($profile);

                return $this->redirectToRoute('ems_wysiwyg_index');
            }
        }

        return $this->render('@EMSCore/wysiwygprofile/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Creates a new WYSIWYG Styles Set entity.
     *
     * @return RedirectResponse|Response
     *
     * @Route("/styles-set/new", name="ems_wysiwyg_styles_set_new", methods={"GET", "POST"})
     */
    public function newStylesSetAction(Request $request, TranslatorInterface $translator, WysiwygStylesSetService $wysiwygStylesSetService)
    {
        $stylesSet = new WysiwygStylesSet();

        $form = $this->createForm(WysiwygStylesSetType::class, $stylesSet, [
                'createform' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            \json_decode($stylesSet->getConfig(), true);
            if (\json_last_error()) {
                $form->get('config')->addError(new FormError($translator->trans('wysiwyg.invalid_config_format', ['%msg%' => \json_last_error_msg()], 'EMSCoreBundle')));
            } else {
                $stylesSet->setOrderKey(100 + \count($wysiwygStylesSetService->getStylesSets()));
                $wysiwygStylesSetService->save($stylesSet);

                return $this->redirectToRoute('ems_wysiwyg_index');
            }
        }

        return $this->render('@EMSCore/wysiwyg_styles_set/new.html.twig', [
                'form' => $form->createView(),
        ]);
    }

    /**
     * Displays a form to edit an existing WysiwygStylesSet entity.
     *
     * @return RedirectResponse|Response
     *
     * @Route("/styles-set/{id}", name="ems_wysiwyg_styles_set_edit", methods={"GET", "POST"})
     */
    public function editStylesSetAction(Request $request, WysiwygStylesSet $stylesSet, TranslatorInterface $translator, WysiwygStylesSetService $wysiwygStylesSetService)
    {
        $form = $this->createForm(WysiwygStylesSetType::class, $stylesSet);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $removedButton = $form->get('remove');
            if ($removedButton instanceof ClickableInterface && $removedButton->isClicked()) {
                $wysiwygStylesSetService->remove($stylesSet);

                return $this->redirectToRoute('ems_wysiwyg_index');
            }

            if ($form->isSubmitted() && $form->isValid()) {
                \json_decode($stylesSet->getConfig(), true);
                if (\json_last_error()) {
                    $form->get('config')->addError(new FormError($translator->trans('wysiwyg.invalid_config_format', ['%msg%' => \json_last_error_msg()], 'EMSCoreBundle')));
                } else {
                    $wysiwygStylesSetService->save($stylesSet);

                    return $this->redirectToRoute('ems_wysiwyg_index');
                }
            }
        }

        return $this->render('@EMSCore/wysiwyg_styles_set/edit.html.twig', [
                'form' => $form->createView(),
        ]);
    }

    /**
     * Displays a form to edit an existing WysiwygProfile entity.
     *
     * @return RedirectResponse|Response
     *
     * @Route("/profile/{id}", name="ems_wysiwyg_profile_edit", methods={"GET", "POST"})
     */
    public function editProfileAction(Request $request, WysiwygProfile $profile, TranslatorInterface $translator, WysiwygProfileService $wysiwygProfileService)
    {
        $form = $this->createForm(WysiwygProfileType::class, $profile);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $removeButton = $form->get('remove');
            if ($removeButton instanceof ClickableInterface && $removeButton->isClicked()) {
                $wysiwygProfileService->remove($profile);

                return $this->redirectToRoute('ems_wysiwyg_index');
            }

            if ($form->isSubmitted() && $form->isValid()) {
                \json_decode($profile->getConfig(), true);
                if (\json_last_error()) {
                    $form->get('config')->addError(new FormError($translator->trans('wysiwyg.invalid_config_format', ['%msg%' => \json_last_error_msg()], 'EMSCoreBundle')));
                } else {
                    $wysiwygProfileService->saveProfile($profile);

                    return $this->redirectToRoute('ems_wysiwyg_index');
                }
            }
        }

        return $this->render('@EMSCore/wysiwygprofile/edit.html.twig', [
                'form' => $form->createView(),
        ]);
    }
}
