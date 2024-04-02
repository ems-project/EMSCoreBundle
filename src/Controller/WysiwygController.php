<?php

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\DataTable\Type\Wysiwyg\WysiwygProfileDataTableType;
use EMS\CoreBundle\DataTable\Type\Wysiwyg\WysiwygStylesSetDataTableType;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\WysiwygProfile;
use EMS\CoreBundle\Entity\WysiwygStylesSet;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Form\Form\WysiwygProfileType;
use EMS\CoreBundle\Form\Form\WysiwygStylesSetType;
use EMS\CoreBundle\Service\WysiwygProfileService;
use EMS\CoreBundle\Service\WysiwygStylesSetService;
use EMS\Helpers\Standard\Json;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class WysiwygController extends AbstractController
{
    public function __construct(private readonly LoggerInterface $logger,
        private readonly WysiwygProfileService $wysiwygProfileService,
        private readonly WysiwygStylesSetService $wysiwygStylesSetService,
        private readonly TranslatorInterface $translator,
        private readonly string $templateNamespace,
        private readonly DataTableFactory $dataTableFactory,
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    public function index(Request $request): Response
    {
        $tableProfile = $this->dataTableFactory->create(WysiwygProfileDataTableType::class);

        $formProfiles = $this->formFactory->createNamed('wysiwyg_profiles', TableType::class, $tableProfile, [
            'title_label' => 'view.wysiwyg.wysiwyg_profiles_label',
        ]);
        $formProfiles->handleRequest($request);
        if ($formProfiles->isSubmitted() && $formProfiles->isValid()) {
            if ($formProfiles instanceof Form && ($action = $formProfiles->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case EntityTable::DELETE_ACTION:
                        $this->wysiwygProfileService->deleteByIds($tableProfile->getSelected());
                        break;
                    case TableType::REORDER_ACTION:
                        $newOrder = TableType::getReorderedKeys($formProfiles->getName(), $request);
                        $this->wysiwygProfileService->reorderByIds($newOrder);
                        break;
                    default:
                        $this->logger->error('log.controller.wysiwyg_profile.unknown_action');
                }
            } else {
                $this->logger->error('log.controller.wysiwyg_profile.unknown_action');
            }

            return $this->redirectToRoute('ems_wysiwyg_index');
        }

        $tableStylesSet = $this->dataTableFactory->create(WysiwygStylesSetDataTableType::class);
        $formStylesSet = $this->formFactory->createNamed('wysiwyg_style_sets', TableType::class, $tableStylesSet, [
            'title_label' => 'view.wysiwyg.wysiwyg_styles_set_label',
        ]);
        $formStylesSet->handleRequest($request);

        if ($formStylesSet->isSubmitted() && $formStylesSet->isValid()) {
            if ($formStylesSet instanceof Form && ($action = $formStylesSet->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case EntityTable::DELETE_ACTION:
                        $this->wysiwygStylesSetService->deleteByIds($tableProfile->getSelected());
                        break;
                    case TableType::REORDER_ACTION:
                        $newOrder = TableType::getReorderedKeys($formStylesSet->getName(), $request);
                        $this->wysiwygStylesSetService->reorderByIds($newOrder);
                        break;
                    default:
                        $this->logger->error('log.controller.wysiwyg_styles_set.unknown_action');
                }
            } else {
                $this->logger->error('log.controller.wysiwyg_styles_set.unknown_action');
            }

            return $this->redirectToRoute('ems_wysiwyg_index');
        }

        return $this->render("@$this->templateNamespace/wysiwygprofile/index.html.twig", [
                'profiles' => $this->wysiwygProfileService->getProfiles(),
                'stylesSets' => $this->wysiwygStylesSetService->getStylesSets(),
                'formProfiles' => $formProfiles->createView(),
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
