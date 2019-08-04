<?php

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\DependencyInjection\EMSCoreExtension;
use EMS\CoreBundle\Entity\AggregateOption;
use EMS\CoreBundle\Entity\QueryOption;
use EMS\CoreBundle\Entity\SearchFieldOption;
use EMS\CoreBundle\Entity\SortOption;
use EMS\CoreBundle\Form\Form\AggregateOptionType;
use EMS\CoreBundle\Form\Form\QueryOptionType;
use EMS\CoreBundle\Form\Form\ReorderType;
use EMS\CoreBundle\Form\Form\SearchFieldOptionType;
use EMS\CoreBundle\Form\Form\SortOptionType;
use EMS\CoreBundle\Service\AggregateOptionService;
use EMS\CoreBundle\Service\HelperService;
use EMS\CoreBundle\Service\QueryOptionService;
use EMS\CoreBundle\Service\SearchFieldOptionService;
use EMS\CoreBundle\Service\SortOptionService;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Wysiwyg controller.
 *
 * @Route("/search-options")
 */
class SearchController extends AppController
{
    /**
     * @return RedirectResponse|Response
     *
     * @Route("/", name="ems_search_options_index", methods={"GET","POST"})
     */
    public function indexAction()
    {
        return $this->render('@EMSCore/search-options/index.html.twig', [
        ]);
    }


    /**
     * Creates a new Sort Option entity.
     * @param Request $request
     * @param TranslatorInterface $translator
     * @param SortOptionService $optionService
     * @return RedirectResponse|Response
     *
     * @Route("/sort/new", name="ems_sortoption_add", methods={"GET","POST"})
     */
    public function newSortOptionAction(Request $request, TranslatorInterface $translator, SortOptionService $optionService)
    {
        $sortOption = new SortOption();
        $form = $this->createForm(SortOptionType::class, $sortOption, [
            'createform' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $optionService->create($sortOption);
            return $this->redirectToRoute('ems_search_options_index');
        }

        return $this->render('@EMSCore/entity/new.html.twig', [
            'entity_name' => $translator->trans('search.sort_option_label', [], EMSCoreExtension::TRANS_DOMAIN),
            'form' => $form->createView(),
        ]);
    }


    /**
     * Creates a new Query Option entity.
     * @param Request $request
     * @param QueryOptionService $queryOptionService
     * @param TranslatorInterface $translator
     * @return RedirectResponse|Response
     *
     * @Route("/query/new", name="ems_queryoption_add", methods={"GET","POST"})
     */
    public function newQueryOptionAction(Request $request, QueryOptionService $queryOptionService, TranslatorInterface $translator)
    {
        $queryOption = new QueryOption();
        $form = $this->createForm(QueryOptionType::class, $queryOption, [
            'createform' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $queryOptionService->create($queryOption);
            return $this->redirectToRoute('ems_queryoption_index');
        }

        return $this->render('@EMSCore/entity/new.html.twig', [
            'entity_name' => $translator->trans('search.query_option_label', [], EMSCoreExtension::TRANS_DOMAIN),
            'form' => $form->createView(),
        ]);
    }


    /**
     * Creates a new Search Field Option entity.
     * @param Request $request
     * @param TranslatorInterface $translator
     * @param SearchFieldOptionService $searchFieldOptionService
     * @return RedirectResponse|Response
     *
     * @Route("/search-field/new", name="ems_searchfieldoption_add", methods={"GET","POST"})
     */
    public function newSearchFieldOptionAction(Request $request, TranslatorInterface $translator, SearchFieldOptionService $searchFieldOptionService)
    {
        $searchFieldOption = new SearchFieldOption();
        $form = $this->createForm(SearchFieldOptionType::class, $searchFieldOption, [
            'createform' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $searchFieldOptionService->create($searchFieldOption);
            return $this->redirectToRoute('ems_search_options_index');
        }

        return $this->render('@EMSCore/entity/new.html.twig', [
            'entity_name' => $translator->trans('search.search_field_option_label', [], EMSCoreExtension::TRANS_DOMAIN),
            'form' => $form->createView(),
        ]);
    }


    /**
     * List aggsoption entity.
     * @param Request $request
     * @param AggregateOptionService $aggregateOptionService
     * @param HelperService $helperService
     * @return RedirectResponse|Response
     *
     * @Route("/aggregate", name="ems_aggregateoption_index", methods={"GET","POST"})
     */
    public function indexAggregateOptionAction(Request $request, AggregateOptionService $aggregateOptionService, HelperService $helperService)
    {
        $optionForm = $this->createForm(ReorderType::class);
        $optionForm->handleRequest($request);
        if ($optionForm->isSubmitted()) {
            $aggregateOptionService->reorder($optionForm);
            return $this->redirectToRoute('ems_aggregateoption_index');
        }

        return $this->render('@EMSCore/entity/index.html.twig', [
            'indexView' => $helperService->getIndexView(AggregateOption::class, 'fa fa-object-group'),
            'options' => $aggregateOptionService->getAll(),
            'reorderForm' => $optionForm->createView(),
        ]);
    }


    /**
     * List sortoption entity.
     * @param Request $request
     * @param SortOptionService $sortOptionService
     * @param HelperService $helperService
     * @return RedirectResponse|Response
     *
     * @Route("/sort", name="ems_sortoption_index", methods={"GET","POST"})
     */
    public function indexSortOptionAction(Request $request, SortOptionService $sortOptionService, HelperService $helperService)
    {
        $optionForm = $this->createForm(ReorderType::class);
        $optionForm->handleRequest($request);
        if ($optionForm->isSubmitted()) {
            $sortOptionService->reorder($optionForm);
            return $this->redirectToRoute('ems_sortoption_index');
        }

        return $this->render('@EMSCore/entity/index.html.twig', [
            'indexView' => $helperService->getIndexView(SortOption::class, 'fa fa-sort'),
            'options' => $sortOptionService->getAll(),
            'reorderForm' => $optionForm->createView(),
        ]);
    }


    /**
     * List query option entities.
     * @param Request $request
     * @param HelperService $helperService
     * @param QueryOptionService $queryOptionService
     * @return RedirectResponse|Response
     *
     * @Route("/query", name="ems_queryoption_index", methods={"GET","POST"})
     */
    public function indexQueryOptionAction(Request $request, HelperService $helperService, QueryOptionService $queryOptionService)
    {
        $optionForm = $this->createForm(ReorderType::class);
        $optionForm->handleRequest($request);
        if ($optionForm->isSubmitted()) {
            $queryOptionService->reorder($optionForm);
            return $this->redirectToRoute('ems_queryoption_index');
        }

        return $this->render('@EMSCore/entity/index.html.twig', [
            'indexView' => $helperService->getIndexView(QueryOption::class, 'fa fa-sort'),
            'options' => $queryOptionService->getAll(),
            'reorderForm' => $optionForm->createView(),
        ]);
    }


    /**
     * List search fields entity.
     * @param Request $request
     * @param SearchFieldOptionService $searchFieldOptionService
     * @param HelperService $helperService
     * @return RedirectResponse|Response
     *
     * @Route("/search-field", name="ems_searchfieldoption_index", methods={"GET","POST"})
     */
    public function indexSearchFieldOptionAction(Request $request, SearchFieldOptionService $searchFieldOptionService, HelperService $helperService)
    {
        $optionForm = $this->createForm(ReorderType::class);
        $optionForm->handleRequest($request);
        if ($optionForm->isSubmitted()) {
            $searchFieldOptionService->reorder($optionForm);
            return $this->redirectToRoute('ems_searchfieldoption_index');
        }

        return $this->render('@EMSCore/entity/index.html.twig', [
            'indexView' => $helperService->getIndexView(SearchFieldOption::class, 'fa fa-search'),
            'options' => $searchFieldOptionService->getAll(),
            'reorderForm' => $optionForm->createView(),
        ]);
    }


    /**
     * Creates a new Agregate Option entity.
     * @param Request $request
     * @param AggregateOptionService $aggregateOptionService
     * @param TranslatorInterface $translator
     * @return RedirectResponse|Response
     *
     * @Route("/aggregate/new", name="ems_aggregateoption_add", methods={"GET","POST"})
     */
    public function newAggregateOptionAction(Request $request, AggregateOptionService $aggregateOptionService, TranslatorInterface $translator)
    {
        $aggregateOption = new AggregateOption();
        $form = $this->createForm(AggregateOptionType::class, $aggregateOption, [
                'createform' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $aggregateOptionService->create($aggregateOption);
            return $this->redirectToRoute('ems_search_options_index');
        }

        return $this->render('@EMSCore/entity/new.html.twig', [
                'entity_name' => $translator->trans('search.aggregate_option_label', [], EMSCoreExtension::TRANS_DOMAIN),
                'form' => $form->createView(),
        ]);
    }

    /**
     * Displays a form to edit an existing SortOption entity.
     * @param Request $request
     * @param SortOption $sortOption
     * @param TranslatorInterface $translator
     * @param SortOptionService $optionService
     * @return RedirectResponse|Response
     *
     * @Route("/sort/{id}", name="ems_sortoption_edit", methods={"GET","POST"})
     */
    public function editSortOptionAction(Request $request, SortOption $sortOption, TranslatorInterface $translator, SortOptionService $optionService)
    {
        
        $form = $this->createForm(SortOptionType::class, $sortOption);
        $form->handleRequest($request);
        
        if ($form->isSubmitted()) {
            $removeButton = $form->get('remove');
            if ($removeButton instanceof ClickableInterface && $removeButton->isClicked()) {
                $optionService->remove($sortOption);
                return $this->redirectToRoute('ems_sortoption_index');
            }
            
            if ($form->isSubmitted() && $form->isValid()) {
                $optionService->save($sortOption);
                return $this->redirectToRoute('ems_sortoption_index');
            }
        }
        
        return $this->render('@EMSCore/entity/edit.html.twig', array(
                'entity_name' => $translator->trans('search.sort_option_label', [], EMSCoreExtension::TRANS_DOMAIN),
                'form' => $form->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing QueryOption entity.
     * @param Request $request
     * @param QueryOption $queryOption
     * @param QueryOptionService $queryOptionService
     * @param TranslatorInterface $translator
     * @return RedirectResponse|Response
     *
     * @Route("/query/{id}", name="ems_queryoption_edit", methods={"GET","POST"})
     */
    public function editQueryOptionAction(Request $request, QueryOPtion $queryOption, QueryOptionService $queryOptionService, TranslatorInterface $translator)
    {

        $form = $this->createForm(QueryOptionType::class, $queryOption);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $removeButton = $form->get('remove');
            if ($removeButton instanceof ClickableInterface && $removeButton->isClicked()) {
                $queryOptionService->remove($queryOption);
                return $this->redirectToRoute('ems_queryoption_index');
            }

            if ($form->isSubmitted() && $form->isValid()) {
                $queryOptionService->save($queryOption);
                return $this->redirectToRoute('ems_queryoption_index');
            }
        }

        return $this->render('@EMSCore/entity/edit.html.twig', array(
                'entity_name' => $translator->trans('search.sort_option_label', [], EMSCoreExtension::TRANS_DOMAIN),
                'form' => $form->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing SearchFieldOption entity.
     * @param Request $request
     * @param SearchFieldOption $searchFieldOption
     * @param TranslatorInterface $translator
     * @param SearchFieldOptionService $searchFieldOptionService
     * @return RedirectResponse|Response
     *
     * @Route("/search-field/{id}", name="ems_searchfieldoption_edit", methods={"GET","POST"})
     */
    public function editSearchFieldOptionAction(Request $request, SearchFieldOption $searchFieldOption, TranslatorInterface $translator, SearchFieldOptionService $searchFieldOptionService)
    {

        $form = $this->createForm(SearchFieldOptionType::class, $searchFieldOption);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $removeButton = $form->get('remove');
            if ($removeButton instanceof ClickableInterface && $removeButton->isClicked()) {
                $searchFieldOptionService->remove($searchFieldOption);
                return $this->redirectToRoute('ems_search_options_index');
            }

            if ($form->isSubmitted() && $form->isValid()) {
                $searchFieldOptionService->save($searchFieldOption);
                return $this->redirectToRoute('ems_search_options_index');
            }
        }

        return $this->render('@EMSCore/entity/edit.html.twig', array(
                'entity_name' => $translator->trans('search.search_field_option_label', [], EMSCoreExtension::TRANS_DOMAIN),
                'form' => $form->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing AggregateOption entity.
     * @param Request $request
     * @param AggregateOption $option
     * @param AggregateOptionService $aggregateOptionService
     * @param TranslatorInterface $translator
     * @return RedirectResponse|Response
     *
     * @Route("/aggregate/{id}", name="ems_aggregateoption_edit", methods={"GET","POST"})
     */
    public function editAggregagteOptionAction(Request $request, AggregateOption $option, AggregateOptionService $aggregateOptionService, TranslatorInterface $translator)
    {
        
        
        $form = $this->createForm(AggregateOptionType::class, $option);
        $form->handleRequest($request);
        
        if ($form->isSubmitted()) {
            $removeButton = $form->get('remove');
            if ($removeButton instanceof ClickableInterface && $removeButton->isClicked()) {
                $aggregateOptionService->remove($option);
                return $this->redirectToRoute('ems_aggregateoption_index');
            }
            
            if ($form->isSubmitted() && $form->isValid()) {
                $aggregateOptionService->save($option);
                return $this->redirectToRoute('ems_aggregateoption_index');
            }
        }
        
        return $this->render('@EMSCore/entity/edit.html.twig', array(
                'entity_name' => $translator->trans('search.aggregate_option_label', [], EMSCoreExtension::TRANS_DOMAIN),
                'form' => $form->createView(),
        ));
    }
}
