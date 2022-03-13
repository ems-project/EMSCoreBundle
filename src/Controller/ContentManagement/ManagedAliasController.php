<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use EMS\CommonBundle\Common\Standard\Type;
use EMS\CoreBundle\Entity\ManagedAlias;
use EMS\CoreBundle\Form\Form\ManagedAliasType;
use EMS\CoreBundle\Service\AliasService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/environment/managed-alias")
 */
class ManagedAliasController extends AbstractController
{
    private AliasService $aliasService;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger, AliasService $aliasService)
    {
        $this->aliasService = $aliasService;
        $this->logger = $logger;
    }

    /**
     * @return RedirectResponse|Response
     *
     * @throws ORMException
     * @throws OptimisticLockException
     *
     * @Route("/add", name="environment_add_managed_alias")
     */
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

        return $this->render('@EMSCore/environment/managed_alias.html.twig', [
            'new' => true,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param string $id
     *
     * @return RedirectResponse|Response
     *
     * @throws ORMException
     * @throws OptimisticLockException
     *
     * @Route("/edit/{id}", requirements={"id"="\d+"}, name="environment_edit_managed_alias")
     */
    public function editAction(Request $request, $id): Response
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

        return $this->render('@EMSCore/environment/managed_alias.html.twig', [
            'new' => false,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param string $id
     *
     * @return RedirectResponse
     *
     * @throws ORMException
     * @throws OptimisticLockException
     *
     * @Route("/remove/{id}", requirements={"id": "\d+"}, name="environment_remove_managed_alias", methods={"POST"})
     */
    public function removeAction($id): Response
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
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function save(ManagedAlias $managedAlias, array $actions): void
    {
        $managedAlias->setAlias(Type::string($this->getParameter('ems_core.instance_id')));
        $this->aliasService->updateAlias($managedAlias->getAlias(), $actions);

        /* @var $em EntityManager */
        $em = $this->getDoctrine()->getManager();
        $em->persist($managedAlias);
        $em->flush();
    }

    /**
     * @return array
     */
    private function getIndexActions(FormInterface $form)
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
