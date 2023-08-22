<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\EntityManager;
use EMS\CoreBundle\Entity\ManagedAlias;
use EMS\CoreBundle\Form\Form\ManagedAliasType;
use EMS\CoreBundle\Service\AliasService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ManagedAliasController extends AbstractController
{
    public function __construct(private readonly LoggerInterface $logger, private readonly AliasService $aliasService, private readonly string $instanceId, private readonly string $templateNamespace)
    {
    }

    public function addAction(Request $request): Response
    {
        $managedAlias = new ManagedAlias();
        $form = $this->createForm(ManagedAliasType::class, $managedAlias);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->save($managedAlias, $this->getIndexActions($form));

            $this->logger->notice('log.managed_alias.created', [
                'managed_alias_name' => $managedAlias->getName(),
            ]);

            return $this->redirectToRoute('environment.index');
        }

        return $this->render("@$this->templateNamespace/environment/managed_alias.html.twig", [
            'new' => true,
            'form' => $form->createView(),
        ]);
    }

    public function editAction(Request $request, int $id): Response
    {
        $managedAlias = $this->aliasService->getManagedAlias($id);

        if (!$managedAlias) {
            throw new NotFoundHttpException('Unknow managed alias');
        }

        $form = $this->createForm(ManagedAliasType::class, $managedAlias);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->save($managedAlias, $this->getIndexActions($form));
            $this->logger->notice('log.managed_alias.updated', [
                'managed_alias_name' => $managedAlias->getName(),
            ]);

            return $this->redirectToRoute('environment.index');
        }

        return $this->render("@$this->templateNamespace/environment/managed_alias.html.twig", [
            'new' => false,
            'form' => $form->createView(),
        ]);
    }

    public function removeAction(int $id): Response
    {
        $managedAlias = $this->aliasService->getManagedAlias($id);

        if ($managedAlias) {
            $this->aliasService->removeAlias($managedAlias->getAlias());

            /* @var $em EntityManager */
            $em = $this->getDoctrine()->getManager();
            $em->remove($managedAlias);
            $em->flush();
            $this->logger->notice('log.managed_alias.deleted', [
                'managed_alias_name' => $managedAlias->getName(),
            ]);
        }

        return $this->redirectToRoute('environment.index');
    }

    /**
     * @param array<mixed> $actions
     */
    private function save(ManagedAlias $managedAlias, array $actions): void
    {
        $managedAlias->setAlias($this->instanceId);
        $this->aliasService->updateAlias($managedAlias->getAlias(), $actions);

        /* @var $em EntityManager */
        $em = $this->getDoctrine()->getManager();
        $em->persist($managedAlias);
        $em->flush();
    }

    /**
     * @param FormInterface<FormInterface> $form
     *
     * @return array<mixed>
     */
    private function getIndexActions(FormInterface $form): array
    {
        $actions = [];
        $submitted = $form->get('indexes')->getData();
        $indexes = \array_keys($form->getConfig()->getOption('indexes'));

        if (empty($submitted)) {
            return $actions;
        }

        foreach ($indexes as $index) {
            if (\in_array($index, $submitted)) {
                $actions['add'][] = $index;
            } else {
                $actions['remove'][] = $index;
            }
        }

        return $actions;
    }
}
