<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Form\Form\TemplateType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\TemplateRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class TemplateController extends AppController
{
    /**
     * @Route("/template/{type}", name="template.index", methods={"GET","HEAD"})
     */
    public function indexAction(string $type): Response
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

        return $this->render('@EMSCore/template/index.html.twig', [
                'contentType' => $contentTypes[0],
        ]);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     *
     * @Route("/template/add/{type}", name="template.add", methods={"GET","HEAD", "POST"})
     */
    public function addAction(string $type, Request $request): Response
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

        $template = new Template();
        $template->setContentType($contentTypes[0]);

        $form = $this->createForm(TemplateType::class, $template);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($template);
            $em->flush();
            $this->getLogger()->notice('log.template.added', [
                'template_name' => $template->getName(),
            ]);

            return $this->redirectToRoute('template.index', [
                    'type' => $type,
            ]);
        }

        return $this->render('@EMSCore/template/add.html.twig', [
                'contentType' => $contentTypes[0],
                'form' => $form->createView(),
        ]);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     *
     * @Route("/template/edit/{id}.{_format}", name="template.edit", defaults={"_format"="html"}, methods={"GET", "HEAD", "POST"})
     */
    public function editAction(int $id, Request $request, string $_format): Response
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var TemplateRepository $templateRepository */
        $templateRepository = $em->getRepository('EMSCoreBundle:Template');

        /** @var Template|null $template * */
        $template = $templateRepository->find($id);

        if (null === $template) {
            throw new NotFoundHttpException('Template type not found');
        }

        $form = $this->createForm(TemplateType::class, $template, [
            'ajax-save-url' => $this->generateUrl('template.edit', ['id' => $id, '_format' => 'json']),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($template);
            $em->flush();

            $this->getLogger()->notice('log.template.updated', [
                'template_name' => $template->getName(),
            ]);

            if ('json' === $_format) {
                return $this->render('@EMSCore/ajax/notification.json.twig', [
                    'success' => true,
                ]);
            }

            return $this->redirectToRoute('template.index', [
                    'type' => $template->getContentType()->getName(),
            ]);
        }

        if ('json' === $_format) {
            foreach ($form->getErrors() as $error) {
                $this->getLogger()->error('log.error', [
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $error->getMessage(),
                ]);
            }

            return $this->render('@EMSCore/ajax/notification.json.twig', [
                'success' => $form->isValid(),
            ]);
        }

        return $this->render('@EMSCore/template/edit.html.twig', [
                'form' => $form->createView(),
                'template' => $template,
        ]);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     *
     * @Route("/template/remove/{id}", name="template.remove", methods={"POST"})
     */
    public function removeAction(string $id): RedirectResponse
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var TemplateRepository $templateRepository */
        $templateRepository = $em->getRepository('EMSCoreBundle:Template');

        /** @var Template|null $template * */
        $template = $templateRepository->find($id);

        if (null === $template) {
            throw new NotFoundHttpException('Template type not found');
        }

        $em->remove($template);
        $em->flush();

        $this->getLogger()->notice('log.template.deleted', [
            'template_name' => $template->getName(),
        ]);

        return $this->redirectToRoute('template.index', [
                'type' => $template->getContentType()->getName(),
        ]);
    }
}
