<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\EntityManager;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Form\Data\BoolTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\ActionType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Helper\DataTableRequest;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\TemplateRepository;
use EMS\CoreBundle\Service\ActionService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ActionController extends AbstractController
{
    public function __construct(private readonly LoggerInterface $logger, private readonly ActionService $actionService)
    {
    }

    public function ajaxDataTableAction(ContentType $contentType, Request $request): Response
    {
        $table = $this->initTable($contentType);
        $dataTableRequest = DataTableRequest::fromRequest($request);
        $table->resetIterator($dataTableRequest);

        return $this->render('@EMSCore/datatable/ajax.html.twig', [
            'dataTableRequest' => $dataTableRequest,
            'table' => $table,
        ], new JsonResponse());
    }

    /** @deprecated */
    public function indexAction(string $type): Response
    {
        \trigger_error('Route template.index is now deprecated, use the route ems_core_action_index', E_USER_DEPRECATED);

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var ContentTypeRepository $contentTypeRepository */
        $contentTypeRepository = $em->getRepository(ContentType::class);

        $contentTypes = $contentTypeRepository->findBy([
            'deleted' => false,
            'name' => $type,
        ]);

        if (!$contentTypes || 1 != \count($contentTypes)) {
            throw new NotFoundHttpException('Content type not found');
        }

        return $this->redirectToRoute('ems_core_action_index', ['contentType' => $contentTypes[0]->getId()]);
    }

    public function index(Request $request, ContentType $contentType): Response
    {
        if ($contentType->getDeleted()) {
            throw new \RuntimeException('Unexpected deleted contentType');
        }

        $table = $this->initTable($contentType);

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case EntityTable::DELETE_ACTION:
                        $this->actionService->deleteByIds($table->getSelected());
                        break;
                    case TableType::REORDER_ACTION:
                        $newOrder = TableType::getReorderedKeys($form->getName(), $request);
                        $this->actionService->reorderByIds($newOrder);
                        break;
                    default:
                        $this->logger->error('log.controller.action.unknown_action');
                }
            } else {
                $this->logger->error('log.controller.action.unknown_action');
            }

            return $this->redirectToRoute('ems_core_action_index', ['contentType' => $contentType->getId()]);
        }

        return $this->render('@EMSCore/action/index.html.twig', [
            'form' => $form->createView(),
            'contentType' => $contentType,
        ]);
    }

    /** @deprecated */
    public function addAction(string $type, Request $request): Response
    {
        \trigger_error('Route template.add is now deprecated, use the route ems_core_action_add', E_USER_DEPRECATED);

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var ContentTypeRepository $contentTypeRepository */
        $contentTypeRepository = $em->getRepository(ContentType::class);

        $contentTypes = $contentTypeRepository->findBy([
            'deleted' => false,
            'name' => $type,
        ]);

        if (!$contentTypes || 1 != \count($contentTypes)) {
            throw new NotFoundHttpException('Content type not found');
        }

        return $this->add($contentTypes[0], $request);
    }

    public function add(ContentType $contentType, Request $request): Response
    {
        $action = new Template();
        $action->setContentType($contentType);

        $form = $this->createForm(ActionType::class, $action);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $action->setOrderKey($this->actionService->count('', $contentType) + 1);
            $action->setName(Encoder::webalize($action->getName()));

            /** @var EntityManager $em */
            $em = $this->getDoctrine()->getManager();

            $em->persist($action);
            $em->flush();
            $this->logger->notice('log.action.added', [
                'action_name' => $action->getName(),
            ]);

            return $this->redirectToRoute('ems_core_action_index', [
                'contentType' => $contentType->getId(),
            ]);
        }

        return $this->render('@EMSCore/action/add.html.twig', [
            'contentType' => $contentType,
            'form' => $form->createView(),
        ]);
    }

    /** @deprecated */
    public function editAction(Template $id, Request $request, string $_format): Response
    {
        \trigger_error('Route template.edit is now deprecated, use the route ems_core_action_edit', E_USER_DEPRECATED);

        return $this->edit($id, $request, $_format);
    }

    public function edit(Template $action, Request $request, string $_format): Response
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        $id = $action->getId();

        $form = $this->createForm(ActionType::class, $action, [
            'ajax-save-url' => $this->generateUrl('ems_core_action_edit', ['contentType' => $action->getContentType(), 'action' => $id, '_format' => 'json']),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($action);
            $em->flush();

            $this->logger->notice('log.action.updated', [
                'action_name' => $action->getName(),
            ]);

            if ('json' === $_format) {
                return $this->render('@EMSCore/ajax/notification.json.twig', [
                    'success' => true,
                ]);
            }

            return $this->redirectToRoute('ems_core_action_index', [
                    'contentType' => $action->giveContentType()->getId(),
            ]);
        }

        if ('json' === $_format) {
            foreach ($form->getErrors() as $error) {
                if ($error instanceof FormError) {
                    $this->logger->error('log.error', [EmsFields::LOG_ERROR_MESSAGE_FIELD => $error->getMessage()]);
                }
            }

            return $this->render('@EMSCore/ajax/notification.json.twig', [
                'success' => $form->isValid(),
            ]);
        }

        return $this->render('@EMSCore/action/edit.html.twig', [
            'form' => $form->createView(),
            'action' => $action,
            'contentType' => $action->giveContentType(),
        ]);
    }

    /** @deprecated */
    public function removeAction(string $id): RedirectResponse
    {
        \trigger_error('Route template.remove is now deprecated, use the route ems_core_action_delete', E_USER_DEPRECATED);

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var TemplateRepository $templateRepository */
        $templateRepository = $em->getRepository(Template::class);

        $action = $templateRepository->find($id);

        if (!$action instanceof Template) {
            throw new NotFoundHttpException('Template type not found');
        }

        return $this->delete($action);
    }

    public function delete(Template $action): RedirectResponse
    {
        $this->actionService->delete($action);
        $this->logger->notice('log.action.deleted', [
            'action_name' => $action->getName(),
        ]);

        return $this->redirectToRoute('ems_core_action_index', [
            'contentType' => $action->giveContentType()->getId(),
        ]);
    }

    private function initTable(ContentType $contentType): EntityTable
    {
        $table = new EntityTable($this->actionService, $this->generateUrl('ems_core_action_datatable_ajax', ['contentType' => $contentType->getId()]), $contentType);
        $table->addColumn('table.index.column.loop_count', 'orderKey');
        $table->addColumnDefinition(new BoolTableColumn('action.index.column.public', 'public'));
        $table->addColumn('action.index.column.name', 'name');
        $table->addColumn('action.index.column.label', 'label')
            ->setItemIconCallback(fn (Template $action) => $action->getIcon());
        $table->addColumn('action.index.column.type', 'renderOption');
        $table->addItemGetAction('ems_core_action_edit', 'action.actions.edit', 'pencil', ['contentType' => $contentType]);
        $table->addItemPostAction('ems_core_action_delete', 'action.actions.delete', 'trash', 'action.actions.delete_confirm', ['contentType' => $contentType->getId()]);
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'action.actions.delete_selected', 'action.actions.delete_selected_confirm');

        return $table;
    }
}
