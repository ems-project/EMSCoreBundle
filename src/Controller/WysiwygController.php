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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
class WysiwygController extends AbstractController
{
    private WysiwygProfileService $wysiwygProfileService;
    private WysiwygStylesSetService $wysiwygStylesSetService;
    private TranslatorInterface $translator;

    public function __construct(WysiwygProfileService $wysiwygProfileService, WysiwygStylesSetService $wysiwygStylesSetService, TranslatorInterface $translator)
    {
        $this->wysiwygProfileService = $wysiwygProfileService;
        $this->wysiwygStylesSetService = $wysiwygStylesSetService;
        $this->translator = $translator;
    }

    /**
     * Lists all Wysiwyg options.
     *
     * @return RedirectResponse|Response
     *
     * @Route("/", name="ems_wysiwyg_index", methods={"GET", "POST"})
     */
    public function indexAction(Request $request): Response
    {
        $data = [];
        $form = $this->createForm(ReorderType::class, $data, [
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $order = \json_decode($form->getData()['items'], true);
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
            $order = \json_decode($formStylesSet->getData()['items'], true);
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

        return $this->render('@EMSCore/wysiwygprofile/index.html.twig', [
                'profiles' => $this->wysiwygProfileService->getProfiles(),
                'stylesSets' => $this->wysiwygStylesSetService->getStylesSets(),
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
    public function newProfileAction(Request $request): Response
    {
        $profile = new WysiwygProfile();

        $form = $this->createForm(WysiwygProfileType::class, $profile, [
                'createform' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            \json_decode($profile->getConfig(), true);
            if (\json_last_error()) {
                $form->get('config')->addError(new FormError($this->translator->trans('wysiwyg.invalid_config_format', ['%msg%' => \json_last_error_msg()], EMSCoreBundle::TRANS_DOMAIN)));
            } else {
                $profile->setOrderKey(100 + \count($this->wysiwygProfileService->getProfiles()));
                $this->wysiwygProfileService->saveProfile($profile);

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
    public function newStylesSetAction(Request $request): Response
    {
        $stylesSet = new WysiwygStylesSet();

        $form = $this->createForm(WysiwygStylesSetType::class, $stylesSet, [
                'createform' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            \json_decode($stylesSet->getConfig(), true);
            if (\json_last_error()) {
                $form->get('config')->addError(new FormError($this->translator->trans('wysiwyg.invalid_config_format', ['%msg%' => \json_last_error_msg()], 'EMSCoreBundle')));
            } else {
                $stylesSet->setOrderKey(100 + \count($this->wysiwygStylesSetService->getStylesSets()));
                $this->wysiwygStylesSetService->save($stylesSet);

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
    public function editStylesSetAction(Request $request, WysiwygStylesSet $stylesSet): Response
    {
        $form = $this->createForm(WysiwygStylesSetType::class, $stylesSet);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $removedButton = $form->get('remove');
            if ($removedButton instanceof ClickableInterface && $removedButton->isClicked()) {
                $this->wysiwygStylesSetService->remove($stylesSet);

                return $this->redirectToRoute('ems_wysiwyg_index');
            }

            if ($form->isSubmitted() && $form->isValid()) {
                \json_decode($stylesSet->getConfig(), true);
                if (\json_last_error()) {
                    $form->get('config')->addError(new FormError($this->translator->trans('wysiwyg.invalid_config_format', ['%msg%' => \json_last_error_msg()], 'EMSCoreBundle')));
                } else {
                    $this->wysiwygStylesSetService->save($stylesSet);

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
    public function editProfileAction(Request $request, WysiwygProfile $profile): Response
    {
        $form = $this->createForm(WysiwygProfileType::class, $profile);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $removeButton = $form->get('remove');
            if ($removeButton instanceof ClickableInterface && $removeButton->isClicked()) {
                $this->wysiwygProfileService->remove($profile);

                return $this->redirectToRoute('ems_wysiwyg_index');
            }

            if ($form->isSubmitted() && $form->isValid()) {
                \json_decode($profile->getConfig(), true);
                if (\json_last_error()) {
                    $form->get('config')->addError(new FormError($this->translator->trans('wysiwyg.invalid_config_format', ['%msg%' => \json_last_error_msg()], 'EMSCoreBundle')));
                } else {
                    $this->wysiwygProfileService->saveProfile($profile);

                    return $this->redirectToRoute('ems_wysiwyg_index');
                }
            }
        }

        return $this->render('@EMSCore/wysiwygprofile/edit.html.twig', [
                'form' => $form->createView(),
        ]);
    }
}
