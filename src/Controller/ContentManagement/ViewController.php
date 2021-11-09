<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Form\ViewType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\ViewRepository;
use Psr\Container\ContainerInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class ViewController extends AppController
{
    /**
     * @param string $type
     *
     * @return Response
     *
     * @Route("/view/{type}", name="view.index")
     */
    public function indexAction($type)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var ContentTypeRepository $contentTypeRepository */
        $contentTypeRepository = $em->getRepository('EMSCoreBundle:ContentType');

        $contentTypes = $contentTypeRepository->findBy([
            'deleted' => false,
            'name' => $type,
        ]);

        if (!$contentTypes || 1 != \count($contentTypes)) {
            throw new NotFoundHttpException('Content type not found');
        }

        return $this->render('@EMSCore/view/index.html.twig', [
            'contentType' => $contentTypes[0],
        ]);
    }

    /**
     * @param string $type
     *
     * @return RedirectResponse|Response
     *
     * @throws ORMException
     * @throws OptimisticLockException
     *
     * @Route("/view/add/{type}", name="view.add")
     */
    public function addAction($type, Request $request)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var ContentTypeRepository $contentTypeRepository */
        $contentTypeRepository = $em->getRepository('EMSCoreBundle:ContentType');

        $contentTypes = $contentTypeRepository->findBy([
            'deleted' => false,
            'name' => $type,
        ]);

        if (!$contentTypes || 1 != \count($contentTypes)) {
            throw new NotFoundHttpException('Content type not found');
        }

        $view = new View();
        $view->setContentType($contentTypes[0]);

        $form = $this->createForm(ViewType::class, $view, [
            'create' => true,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($view);
            $em->flush();

            $this->getLogger()->notice('log.view.created', [
                'view_name' => $view->getName(),
            ]);

            return $this->redirectToRoute('view.edit', [
                'id' => $view->getId(),
            ]);
        }

        return $this->render('@EMSCore/view/add.html.twig', [
            'contentType' => $contentTypes[0],
            'form' => $form->createView(),
        ]);
    }

    /**
     * @return Response
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @Route("/view/edit/{id}.{_format}", name="view.edit", defaults={"_format"="html"})
     */
    public function editAction(string $id, string $_format, Request $request, ContainerInterface $container)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var ViewRepository $viewRepository */
        $viewRepository = $em->getRepository('EMSCoreBundle:View');

        /** @var View|null $view */
        $view = $viewRepository->find($id);

        if (null === $view) {
            throw new NotFoundHttpException('View type not found');
        }

        $form = $this->createFormBuilder($view)
            ->add('name', IconTextType::class, [
                'icon' => 'fa fa-tag',
            ])
            ->add('public', CheckboxType::class, [
                'required' => false,
            ])
            ->add('icon', IconPickerType::class, [
                'required' => false,
            ])
            ->add('options', \get_class($container->get($view->getType())), [
                'view' => $view,
            ])
            ->add('save', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn-primary btn-sm',
                    'data-ajax-save-url' => $this->generateUrl('view.edit', ['id' => $id, '_format' => 'json']),
                ],
                'icon' => 'fa fa-save',
            ])
            ->add('saveAndClose', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn-primary btn-sm',
                ],
                'icon' => 'fa fa-save',
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($view);
            $em->flush();

            $this->getLogger()->notice('log.view.updated', [
                'view_name' => $view->getName(),
            ]);

            if ('json' === $_format) {
                return $this->render('@EMSCore/ajax/notification.json.twig', [
                    'success' => true,
                ]);
            }

            return $this->redirectToRoute('view.index', [
                'type' => $view->getContentType()->getName(),
            ]);
        }

        return $this->render('@EMSCore/view/edit.html.twig', [
            'form' => $form->createView(),
            'view' => $view,
        ]);
    }

    /**
     * @return RedirectResponse
     *
     * @throws ORMException
     * @throws OptimisticLockException
     *
     * @Route("/view/duplicate/{view}", name="emsco_view_duplicate", methods={"POST"})
     */
    public function duplicateAction(View $view)
    {
        $newView = clone $view;

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        $em->persist($newView);
        $em->flush();

        return $this->redirectToRoute('view.edit', ['id' => $newView->getId()]);
    }

    /**
     * @param int $id
     *
     * @return RedirectResponse
     *
     * @throws ORMException
     * @throws OptimisticLockException
     *
     * @Route("/view/remove/{id}", name="view.remove", methods={"POST"})
     */
    public function removeAction($id)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var ViewRepository $viewRepository */
        $viewRepository = $em->getRepository('EMSCoreBundle:View');

        /** @var View|null $view */
        $view = $viewRepository->find($id);

        if (null === $view) {
            throw new NotFoundHttpException('View not found');
        }

        $em->remove($view);
        $em->flush();

        $this->getLogger()->notice('log.view.deleted', [
            'view_name' => $view->getName(),
        ]);

        return $this->redirectToRoute('view.index', [
            'type' => $view->getContentType()->getName(),
        ]);
    }
}
