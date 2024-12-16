<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\DataTable\Type\I18nDataTableType;
use EMS\CoreBundle\Entity\I18n;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\I18nType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\I18nService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

class I18nController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly I18nService $i18nService,
        private readonly DataTableFactory $dataTableFactory,
        private readonly LocalizedLoggerInterface $logger,
        private readonly string $templateNamespace,
    ) {
    }

    public function add(Request $request): Response
    {
        $i18n = new I18n();
        $i18n->setContent([['locale' => '', 'text' => '']]);

        $form = $this->createForm(I18nType::class, $i18n);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->i18nService->update($i18n);

            return $this->redirectToRoute(Routes::I18N_INDEX);
        }

        return $this->render("@$this->templateNamespace/i18n/new.html.twig", [
            'i18n' => $i18n,
            'form' => $form->createView(),
        ]);
    }

    public function delete(I18n $i18n): Response
    {
        $this->i18nService->delete($i18n);

        return $this->redirectToRoute(Routes::I18N_INDEX);
    }

    public function edit(Request $request, I18n $i18n): Response
    {
        if (empty($i18n->getContent())) {
            $i18n->setContent([
                [
                    'locale' => '',
                    'text' => '',
                ],
            ]);
        }
        $editForm = $this->createForm(I18nType::class, $i18n);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            // renumber array elements
            $i18n->setContent(\array_values($i18n->getContent()));
            $this->i18nService->update($i18n);

            return $this->redirectToRoute(Routes::I18N_INDEX);
        }

        return $this->render("@$this->templateNamespace/i18n/edit.html.twig", [
            'i18n' => $i18n,
            'edit_form' => $editForm->createView(),
        ]);
    }

    public function index(Request $request): Response
    {
        $table = $this->dataTableFactory->create(I18nDataTableType::class);

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                TableAbstract::DELETE_ACTION => $this->i18nService->deleteByIds(...$table->getSelected()),
                default => $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute(Routes::FILTER_INDEX);
        }

        return $this->render("@$this->templateNamespace/crud/overview.html.twig", [
            'form' => $form->createView(),
            'icon' => 'fa fa-language',
            'title' => t('type.title_overview', ['type' => 'i18n'], 'emsco-core'),
            'breadcrumb' => [
                'admin' => t('key.admin', [], 'emsco-core'),
                'page' => t('key.i18n', [], 'emsco-core'),
            ],
        ]);
    }
}
