<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\DependencyInjection\EMSCoreExtension;
use EMS\CoreBundle\Entity\AggregateOption;
use EMS\CoreBundle\Entity\SearchFieldOption;
use EMS\CoreBundle\Entity\SortOption;
use EMS\CoreBundle\Form\Form\AggregateOptionType;
use EMS\CoreBundle\Form\Form\ReorderBisType;
use EMS\CoreBundle\Form\Form\ReorderTerType;
use EMS\CoreBundle\Form\Form\ReorderType;
use EMS\CoreBundle\Form\Form\SearchFieldOptionType;
use EMS\CoreBundle\Form\Form\SortOptionType;
use EMS\CoreBundle\Service\AggregateOptionService;
use EMS\CoreBundle\Service\SearchFieldOptionService;
use EMS\CoreBundle\Service\SortOptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class SearchController extends AbstractController
{
    public function __construct(
        private readonly SortOptionService $sortOptionService,
        private readonly AggregateOptionService $aggregateOptionService,
        private readonly SearchFieldOptionService $searchFieldOptionService,
        private readonly TranslatorInterface $translator,
        private readonly string $templateNamespace)
    {
    }

    public function index(Request $request): Response
    {
        $reorderSortOptionForm = $this->createForm(ReorderType::class);
        $reorderSortOptionForm->handleRequest($request);
        if ($reorderSortOptionForm->isSubmitted()) {
            $this->sortOptionService->reorder($reorderSortOptionForm);

            return $this->redirectToRoute('ems_search_options_index');
        }

        $reorderAggregateOptionForm = $this->createForm(ReorderBisType::class);
        $reorderAggregateOptionForm->handleRequest($request);
        if ($reorderAggregateOptionForm->isSubmitted()) {
            $this->aggregateOptionService->reorder($reorderAggregateOptionForm);

            return $this->redirectToRoute('ems_search_options_index');
        }

        $searchFieldOptionForm = $this->createForm(ReorderTerType::class);
        $searchFieldOptionForm->handleRequest($request);
        if ($searchFieldOptionForm->isSubmitted()) {
            $this->searchFieldOptionService->reorder($searchFieldOptionForm);

            return $this->redirectToRoute('ems_search_options_index');
        }

        return $this->render("@$this->templateNamespace/search-options/index.html.twig", [
            'sortOptions' => $this->sortOptionService->getAll(),
            'aggregateOptions' => $this->aggregateOptionService->getAll(),
            'searchFieldOptions' => $this->searchFieldOptionService->getAll(),
            'sortOptionReorderForm' => $reorderSortOptionForm->createView(),
            'aggregateOptionReorderForm' => $reorderAggregateOptionForm->createView(),
            'searchFieldOptionReorderForm' => $searchFieldOptionForm->createView(),
        ]);
    }

    public function newSortOption(Request $request): Response
    {
        $sortOption = new SortOption();
        $form = $this->createForm(SortOptionType::class, $sortOption, [
            'createform' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->sortOptionService->create($sortOption);

            return $this->redirectToRoute('ems_search_options_index');
        }

        return $this->render("@$this->templateNamespace/entity/new.html.twig", [
            'entity_name' => $this->translator->trans('search.sort_option_label', [], EMSCoreExtension::TRANS_DOMAIN),
            'form' => $form->createView(),
        ]);
    }

    public function newSearchFieldOption(Request $request): Response
    {
        $searchFieldOption = new SearchFieldOption();
        $form = $this->createForm(SearchFieldOptionType::class, $searchFieldOption, [
            'createform' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->searchFieldOptionService->create($searchFieldOption);

            return $this->redirectToRoute('ems_search_options_index');
        }

        return $this->render("@$this->templateNamespace/entity/new.html.twig", [
            'entity_name' => $this->translator->trans('search.search_field_option_label', [], EMSCoreExtension::TRANS_DOMAIN),
            'form' => $form->createView(),
        ]);
    }

    public function newAggregateOption(Request $request): Response
    {
        $aggregateOption = new AggregateOption();
        $form = $this->createForm(AggregateOptionType::class, $aggregateOption, [
            'createform' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->aggregateOptionService->create($aggregateOption);

            return $this->redirectToRoute('ems_search_options_index');
        }

        return $this->render("@$this->templateNamespace/entity/new.html.twig", [
            'entity_name' => $this->translator->trans('search.aggregate_option_label', [], EMSCoreExtension::TRANS_DOMAIN),
            'form' => $form->createView(),
        ]);
    }

    public function editSortOption(Request $request, SortOption $sortOption): Response
    {
        $form = $this->createForm(SortOptionType::class, $sortOption);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $removeButton = $form->get('remove');
            if ($removeButton instanceof ClickableInterface && $removeButton->isClicked()) {
                $this->sortOptionService->remove($sortOption);

                return $this->redirectToRoute('ems_search_options_index');
            }

            if ($form->isValid()) {
                $this->sortOptionService->save($sortOption);

                return $this->redirectToRoute('ems_search_options_index');
            }
        }

        return $this->render("@$this->templateNamespace/entity/edit.html.twig", [
            'entity_name' => $this->translator->trans('search.sort_option_label', [], EMSCoreExtension::TRANS_DOMAIN),
            'form' => $form->createView(),
        ]);
    }

    public function editSearchFieldOption(Request $request, SearchFieldOption $searchFieldOption): Response
    {
        $form = $this->createForm(SearchFieldOptionType::class, $searchFieldOption);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $removeButton = $form->get('remove');
            if ($removeButton instanceof ClickableInterface && $removeButton->isClicked()) {
                $this->searchFieldOptionService->remove($searchFieldOption);

                return $this->redirectToRoute('ems_search_options_index');
            }

            if ($form->isValid()) {
                $this->searchFieldOptionService->save($searchFieldOption);

                return $this->redirectToRoute('ems_search_options_index');
            }
        }

        return $this->render("@$this->templateNamespace/entity/edit.html.twig", [
            'entity_name' => $this->translator->trans('search.search_field_option_label', [], EMSCoreExtension::TRANS_DOMAIN),
            'form' => $form->createView(),
        ]);
    }

    public function editAggregagteOption(Request $request, AggregateOption $option): Response
    {
        $form = $this->createForm(AggregateOptionType::class, $option);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $removeButton = $form->get('remove');
            if ($removeButton instanceof ClickableInterface && $removeButton->isClicked()) {
                $this->aggregateOptionService->remove($option);

                return $this->redirectToRoute('ems_search_options_index');
            }

            if ($form->isValid()) {
                $this->aggregateOptionService->save($option);

                return $this->redirectToRoute('ems_search_options_index');
            }
        }

        return $this->render("@$this->templateNamespace/entity/edit.html.twig", [
            'entity_name' => $this->translator->trans('search.aggregate_option_label', [], EMSCoreExtension::TRANS_DOMAIN),
            'form' => $form->createView(),
        ]);
    }
}
