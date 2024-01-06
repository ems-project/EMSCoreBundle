<?php

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Entity\Form\I18nFilter;
use EMS\CoreBundle\Entity\I18n;
use EMS\CoreBundle\Form\Form\I18nFormType;
use EMS\CoreBundle\Form\Form\I18nType;
use EMS\CoreBundle\Service\I18nService;
use EMS\Helpers\Standard\Type;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class I18nController extends AbstractController
{
    public function __construct(private readonly I18nService $i18nService, private readonly int $pagingSize, private readonly string $templateNamespace)
    {
    }

    public function index(Request $request): Response
    {
        $filters = $request->query->all('i18n_form');

        $i18nFilter = new I18nFilter();

        $form = $this->createForm(I18nFormType::class, $i18nFilter, [
                 'method' => 'GET',
         ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $form->getData();
        }

        $count = $this->i18nService->counter($filters);
        $paging_size = Type::integer($this->pagingSize);
        $lastPage = \ceil($count / $paging_size);
        $page = $request->query->getInt('page', 1);

        $i18ns = $this->i18nService->findAll(($page - 1) * $paging_size, $paging_size, $filters);

        return $this->render("@$this->templateNamespace/i18n/index.html.twig", [
            'i18nkeys' => $i18ns,
            'lastPage' => $lastPage,
            'paginationPath' => 'i18n_index',
            'filterform' => $form->createView(),
            'page' => $page,
            'paging_size' => $paging_size,
        ]);
    }

    public function new(Request $request): Response
    {
        $i18n = new I18n();
        $i18n->setContent([['locale' => '', 'text' => '']]);

        $form = $this->createForm(I18nType::class, $i18n);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->i18nService->save($i18n);

            return $this->redirectToRoute('i18n_index', ['id' => $i18n->getId()]);
        }

        return $this->render("@$this->templateNamespace/i18n/new.html.twig", [
            'i18n' => $i18n,
            'form' => $form->createView(),
        ]);
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
            $this->i18nService->save($i18n);

            return $this->redirectToRoute('i18n_index');
        }

        return $this->render("@$this->templateNamespace/i18n/edit.html.twig", [
            'i18n' => $i18n,
            'edit_form' => $editForm->createView(),
        ]);
    }

    public function delete(I18n $i18n): Response
    {
        $this->i18nService->delete($i18n);

        return $this->redirectToRoute('i18n_index');
    }
}
