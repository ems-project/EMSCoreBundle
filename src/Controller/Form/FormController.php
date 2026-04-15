<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Form;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\Form\FieldTypeManager;
use EMS\CoreBundle\Core\Form\FormManager;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Core\UI\Page\Page;
use EMS\CoreBundle\DataTable\Type\FormDataTableType;
use EMS\CoreBundle\Entity\Form;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\FormType;
use EMS\CoreBundle\Form\Form\ReorderType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use EMS\Helpers\Standard\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

class FormController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly LocalizedLoggerInterface $logger,
        private readonly FormManager $formManager,
        private readonly FieldTypeManager $fieldTypeManager,
        private readonly DataTableFactory $dataTableFactory,
        private readonly string $templateNamespace,
    ) {
    }

    public function index(Request $request): Page|RedirectResponse
    {
        $table = $this->dataTableFactory->create(FormDataTableType::class);

        $form = $this->createForm(TableType::class, $table, [
            'reorder_label' => t('type.reorder', ['type' => 'form'], 'emsco-core'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                TableAbstract::DELETE_ACTION => $this->formManager->deleteByIds($table->getSelected()),
                TableType::REORDER_ACTION => $this->formManager->reorderByIds(
                    ids: TableType::getReorderedKeys($form->getName(), $request)
                ),
                default => $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute(Routes::FORM_ADMIN_INDEX);
        }

        return new Page([
            'datatable' => ['form' => $form->createView(), 'table_id' => 'forms'],
            'icon' => 'fa fa-keyboard-o',
            'title' => t('type.title_overview', ['type' => 'form'], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'form'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb(),
        ]);
    }

    public function add(Request $request): Response
    {
        $form = new Form();

        return $this->edit($request, $form, true);
    }

    public function edit(Request $request, Form $form, bool $create = false): Response
    {
        $inputFieldType = $request->request->all('form')['fieldType'] ?? [];
        $formType = $this->createForm(FormType::class, $form, [
            'create' => $create,
        ]);
        $formType->handleRequest($request);

        if ($formType->isSubmitted() && $formType->isValid()) {
            if ($create) {
                $this->formManager->update($form);

                return $this->redirectToRoute(Routes::FORM_ADMIN_EDIT, ['form' => $form->getId()]);
            }
            // TODO: mark related content types as dirty. An event maybe?
            $openFiledForm = $this->fieldTypeManager->handleRequest($form->getFieldType(), $inputFieldType);
            $form->getFieldType()->updateOrderKeys();

            $this->formManager->update($form);
            $saveButton = $formType->get('save');
            if (!$saveButton instanceof SubmitButton) {
                throw new \RuntimeException('Unexpected submit button type');
            }
            if ($saveButton->isClicked()) {
                return $this->redirectToRoute(Routes::FORM_ADMIN_INDEX);
            }

            return $this->redirectToRoute(Routes::FORM_ADMIN_EDIT, \array_filter([
                'form' => $form->getId(),
                'open' => $openFiledForm,
            ]));
        }

        if ($create) {
            return $this->render(\sprintf('@%s/admin-form/add.html.twig', $this->templateNamespace), [
                'form' => $formType->createView(),
                'entity' => $form,
                'title' => t('type.title_create', ['type' => 'form'], 'emsco-core'),
                'subTitle' => t('type.title_sub', ['type' => 'form'], 'emsco-core'),
                'breadcrumb' => $this->breadcrumb()->add(
                    t('type.title_create', ['type' => 'form'], 'emsco-core')
                ),
            ]);
        }

        return $this->render(\sprintf('@%s/admin-form/edit.html.twig', $this->templateNamespace), [
            'form' => $formType->createView(),
            'entity' => $form,
            'title' => t('type.title_edit', ['type' => 'form', 'label' => $form->getLabel()], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'form'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb()->add(
                t('type.title_edit', ['type' => 'form', 'label' => $form->getLabel()], 'emsco-core')
            ),
        ]);
    }

    public function reorder(Request $request, Form $form): Response
    {
        $formType = $this->createForm(ReorderType::class, []);

        $formType->handleRequest($request);
        if ($formType->isSubmitted()) {
            $data = $formType->getData();
            $structure = Json::decode((string) $data['items']);
            $this->formManager->reorderFields($form, $structure);

            return $this->redirectToRoute(Routes::FORM_ADMIN_INDEX);
        }

        return $this->render(\sprintf('@%s/admin-form/reorder.html.twig', $this->templateNamespace), [
            'form' => $formType->createView(),
            'entity' => $form,
            'title' => t('form.reorder.title', ['label' => $form->getLabel()], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'form'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb()->add(
                t('form.reorder.title', ['label' => $form->getLabel()], 'emsco-core')
            ),
        ]);
    }

    public function delete(Form $form): Response
    {
        $this->formManager->delete($form);

        return $this->redirectToRoute(Routes::FORM_ADMIN_INDEX);
    }

    private function breadcrumb(): Navigation
    {
        return Navigation::admin()->add(
            label: t('key.forms', [], 'emsco-core'),
            icon: 'fa fa-keyboard-o',
            route: 'emsco_form_admin_index',
        );
    }
}
