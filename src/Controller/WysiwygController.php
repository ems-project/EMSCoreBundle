<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\DataTable\Type\Wysiwyg\WysiwygProfileDataTableType;
use EMS\CoreBundle\DataTable\Type\Wysiwyg\WysiwygStylesSetDataTableType;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\WysiwygProfile;
use EMS\CoreBundle\Entity\WysiwygStylesSet;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Form\Form\WysiwygProfileType;
use EMS\CoreBundle\Form\Form\WysiwygStylesSetType;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\WysiwygProfileService;
use EMS\CoreBundle\Service\WysiwygStylesSetService;
use EMS\Helpers\Standard\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class WysiwygController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly WysiwygProfileService $wysiwygProfileService,
        private readonly WysiwygStylesSetService $wysiwygStylesSetService,
        private readonly DataTableFactory $dataTableFactory,
        private readonly FormFactoryInterface $formFactory,
        private readonly LocalizedLoggerInterface $logger,
        private readonly TranslatorInterface $translator,
        private readonly string $templateNamespace,
    ) {
    }

    public function index(Request $request): Response
    {
        $datatableProfiles = $this->datatableProfiles($request);
        $datatableStyleSets = $this->datatableStyleSets($request);

        return match (true) {
            $datatableProfiles instanceof RedirectResponse => $datatableProfiles,
            $datatableStyleSets instanceof RedirectResponse => $datatableStyleSets,
            default => $this->render("@$this->templateNamespace/crud/overview.html.twig", [
                'icon' => 'fa fa-language',
                'title' => t('type.title_overview', ['type' => 'wysiwyg'], 'emsco-core'),
                'datatables' => [
                    [
                        'title' => t('type.title_overview', ['type' => 'wysiwyg_profile'], 'emsco-core'),
                        'form' => $datatableProfiles->createView(),
                    ],
                    [
                        'title' => t('type.title_overview', ['type' => 'wysiwyg_style_set'], 'emsco-core'),
                        'form' => $datatableStyleSets->createView(),
                    ],
                ],
                'breadcrumb' => [
                    'admin' => t('key.admin', [], 'emsco-core'),
                    'page' => t('key.wysiwyg', [], 'emsco-core'),
                ],
            ]),
        };
    }

    public function profileAdd(Request $request): Response
    {
        $profile = new WysiwygProfile();

        $form = $this->createForm(WysiwygProfileType::class, $profile, [
            'createform' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                Json::decode($profile->getConfig() ?? '{}');
                $profile->setOrderKey(100 + $this->wysiwygProfileService->count());
                $this->wysiwygProfileService->update($profile);

                return $this->redirectToRoute(Routes::WYSIWYG_INDEX);
            } catch (\Throwable $e) {
                $form->get('config')->addError(new FormError($this->translator->trans('wysiwyg.invalid_config_format', ['%msg%' => $e->getMessage()], EMSCoreBundle::TRANS_DOMAIN)));
            }
        }

        return $this->render("@$this->templateNamespace/wysiwygprofile/new.html.twig", [
            'form' => $form->createView(),
        ]);
    }

    public function profileDelete(WysiwygProfile $wysiwygProfile): Response
    {
        $this->wysiwygProfileService->delete($wysiwygProfile);

        return $this->redirectToRoute(Routes::WYSIWYG_INDEX);
    }

    public function profileEdit(Request $request, WysiwygProfile $wysiwygProfile): Response
    {
        $form = $this->createForm(WysiwygProfileType::class, $wysiwygProfile);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $removeButton = $form->get('remove');
            if ($removeButton instanceof ClickableInterface && $removeButton->isClicked()) {
                $this->wysiwygProfileService->delete($wysiwygProfile);

                return $this->redirectToRoute(Routes::WYSIWYG_INDEX);
            }

            if ($form->isValid()) {
                try {
                    Json::decode($wysiwygProfile->getConfig() ?? '{}');
                    $this->wysiwygProfileService->update($wysiwygProfile);

                    return $this->redirectToRoute(Routes::WYSIWYG_INDEX);
                } catch (\Throwable $e) {
                    $form->get('config')->addError(new FormError($this->translator->trans('wysiwyg.invalid_config_format', ['%msg%' => $e->getMessage()], 'EMSCoreBundle')));
                }
            }
        }

        return $this->render("@$this->templateNamespace/wysiwygprofile/edit.html.twig", [
            'form' => $form->createView(),
        ]);
    }

    public function styleSetAdd(Request $request): Response
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
                $this->wysiwygStylesSetService->update($stylesSet);

                return $this->redirectToRoute(Routes::WYSIWYG_INDEX);
            } catch (\Throwable $e) {
                $form->get('config')->addError(new FormError($this->translator->trans('wysiwyg.invalid_config_format', ['%msg%' => $e->getMessage()], 'EMSCoreBundle')));
            }
        }

        return $this->render("@$this->templateNamespace/wysiwyg_styles_set/new.html.twig", [
            'form' => $form->createView(),
        ]);
    }

    public function styleSetDelete(WysiwygStylesSet $wysiwygStyleSet): Response
    {
        $this->wysiwygStylesSetService->delete($wysiwygStyleSet);

        return $this->redirectToRoute(Routes::WYSIWYG_INDEX);
    }

    public function styleSetEdit(Request $request, WysiwygStylesSet $wysiwygStyleSet): Response
    {
        $form = $this->createForm(WysiwygStylesSetType::class, $wysiwygStyleSet);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $removedButton = $form->get('remove');
            if ($removedButton instanceof ClickableInterface && $removedButton->isClicked()) {
                $this->wysiwygStylesSetService->delete($wysiwygStyleSet);

                return $this->redirectToRoute(Routes::WYSIWYG_INDEX);
            }

            if ($form->isValid()) {
                try {
                    Json::decode($wysiwygStyleSet->getConfig());
                    $this->wysiwygStylesSetService->update($wysiwygStyleSet);

                    return $this->redirectToRoute(Routes::WYSIWYG_INDEX);
                } catch (\Throwable $e) {
                    $form->get('config')->addError(new FormError($this->translator->trans('wysiwyg.invalid_config_format', ['%msg%' => $e->getMessage()], 'EMSCoreBundle')));
                }
            }
        }

        return $this->render("@$this->templateNamespace/wysiwyg_styles_set/edit.html.twig", [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @return RedirectResponse|FormInterface<mixed>
     */
    private function datatableProfiles(Request $request): RedirectResponse|FormInterface
    {
        $table = $this->dataTableFactory->create(WysiwygProfileDataTableType::class);
        $form = $this->formFactory->createNamed('wysiwyg_profiles', TableType::class, $table, [
            'reorder_label' => t('type.reorder', ['type' => 'wysiwyg_profile'], 'emsco-core'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                TableAbstract::DELETE_ACTION => $this->wysiwygProfileService->deleteByIds(...$table->getSelected()),
                TableType::REORDER_ACTION => $this->wysiwygProfileService->reorderByIds(
                    ...TableType::getReorderedKeys($form->getName(), $request)
                ),
                default => $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute(Routes::WYSIWYG_INDEX);
        }

        return $form;
    }

    /**
     * @return RedirectResponse|FormInterface<mixed>
     */
    private function datatableStyleSets(Request $request): RedirectResponse|FormInterface
    {
        $table = $this->dataTableFactory->create(WysiwygStylesSetDataTableType::class);
        $form = $this->formFactory->createNamed('wysiwyg_style_sets', TableType::class, $table, [
            'reorder_label' => t('type.reorder', ['type' => 'wysiwyg_style_set'], 'emsco-core'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                TableAbstract::DELETE_ACTION => $this->wysiwygStylesSetService->deleteByIds(...$table->getSelected()),
                TableType::REORDER_ACTION => $this->wysiwygStylesSetService->reorderByIds(
                    ...TableType::getReorderedKeys($form->getName(), $request)
                ),
                default => $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute(Routes::WYSIWYG_INDEX);
        }

        return $form;
    }
}
