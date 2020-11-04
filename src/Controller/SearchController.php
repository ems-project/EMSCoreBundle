<?php

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
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Wysiwyg controller.
 *
 * @Route("/search-options")
 */
class SearchController extends AppController
{
    /**
     * Lists all Search options.
     * @param Request $request
     * @return RedirectResponse|Response
     *
     * @Route("/", name="ems_search_options_index", methods={"GET","POST"})
     */
    public function indexAction(Request $request)
    {
        $reorderSortOptionForm = $this->createForm(ReorderType::class);
        $reorderSortOptionForm->handleRequest($request);
        if ($reorderSortOptionForm->isSubmitted()) {
            $this->getSortOptionService()->reorder($reorderSortOptionForm);
            return $this->redirectToRoute('ems_search_options_index');
        }
        
        $reorderAggregateOptionForm = $this->createForm(ReorderBisType::class);
        $reorderAggregateOptionForm->handleRequest($request);
        if ($reorderAggregateOptionForm->isSubmitted()) {
            $this->getAggregateOptionService()->reorder($reorderAggregateOptionForm);
            return $this->redirectToRoute('ems_search_options_index');
        }

        $searchFieldOptionForm = $this->createForm(ReorderTerType::class);
        $searchFieldOptionForm->handleRequest($request);
        if ($searchFieldOptionForm->isSubmitted()) {
            $this->getSearchFieldOptionService()->reorder($searchFieldOptionForm);
            return $this->redirectToRoute('ems_search_options_index');
        }
        
        
        return $this->render('@EMSCore/search-options/index.html.twig', [
                'sortOptions' => $this->getSortOptionService()->getAll(),
                'aggregateOptions' => $this->getAggregateOptionService()->getAll(),
                'searchFieldOptions' => $this->getSearchFieldOptionService()->getAll(),
                'sortOptionReorderForm' => $reorderSortOptionForm->createView(),
                'aggregateOptionReorderForm' => $reorderAggregateOptionForm->createView(),
                'searchFieldOptionReorderForm' => $searchFieldOptionForm->createView(),
        ]);
    }


    /**
     * Creates a new Sort Option entity.
     * @param Request $request
     * @return RedirectResponse|Response
     *
     * @Route("/sort/new", name="ems_search_sort_option_new", methods={"GET","POST"})
     */
    public function newSortOptionAction(Request $request)
    {
        $sortOption = new SortOption();
        $form = $this->createForm(SortOptionType::class, $sortOption, [
            'createform' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getSortOptionService()->create($sortOption);
            return $this->redirectToRoute('ems_search_options_index');
        }

        return $this->render('@EMSCore/entity/new.html.twig', [
            'entity_name' => $this->getTranslator()->trans('search.sort_option_label', [], EMSCoreExtension::TRANS_DOMAIN),
            'form' => $form->createView(),
        ]);
    }


    /**
     * Creates a new Search Field Option entity.
     * @param Request $request
     * @return RedirectResponse|Response
     *
     * @Route("/search-field/new", name="ems_search_field_option_new", methods={"GET","POST"})
     */
    public function newSearchFieldOptionAction(Request $request)
    {
        $searchFieldOption = new SearchFieldOption();
        $form = $this->createForm(SearchFieldOptionType::class, $searchFieldOption, [
            'createform' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getSearchFieldOptionService()->create($searchFieldOption);
            return $this->redirectToRoute('ems_search_options_index');
        }

        return $this->render('@EMSCore/entity/new.html.twig', [
            'entity_name' => $this->getTranslator()->trans('search.search_field_option_label', [], EMSCoreExtension::TRANS_DOMAIN),
            'form' => $form->createView(),
        ]);
    }


    /**
     * Creates a new Agregate Option entity.
     * @param Request $request
     * @return RedirectResponse|Response
     *
     * @Route("/aggregate/new", name="ems_search_aggregate_option_new", methods={"GET","POST"})
     */
    public function newAggregateOptionAction(Request $request)
    {
        $aggregateOption = new AggregateOption();
        $form = $this->createForm(AggregateOptionType::class, $aggregateOption, [
                'createform' => true,
        ]);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $this->getAggregateOptionService()->create($aggregateOption);
            return $this->redirectToRoute('ems_search_options_index');
        }
        
        return $this->render('@EMSCore/entity/new.html.twig', [
                'entity_name' => $this->getTranslator()->trans('search.aggregate_option_label', [], EMSCoreExtension::TRANS_DOMAIN),
                'form' => $form->createView(),
        ]);
    }

    /**
     * Displays a form to edit an existing SortOption entity.
     * @param Request $request
     * @param SortOption $sortOption
     * @return RedirectResponse|Response
     *
     * @Route("/sort/{id}", name="ems_search_sort_option_edit", methods={"GET","POST"})
     */
    public function editSortOptionAction(Request $request, SortOption $sortOption)
    {
        
        $form = $this->createForm(SortOptionType::class, $sortOption);
        $form->handleRequest($request);
        
        if ($form->isSubmitted()) {
            $removeButton = $form->get('remove');
            if ($removeButton instanceof ClickableInterface && $removeButton->isClicked()) {
                $this->getSortOptionService()->remove($sortOption);
                return $this->redirectToRoute('ems_search_options_index');
            }
            
            if ($form->isSubmitted() && $form->isValid()) {
                $this->getSortOptionService()->save($sortOption);
                return $this->redirectToRoute('ems_search_options_index');
            }
        }
        
        return $this->render('@EMSCore/entity/edit.html.twig', array(
                'entity_name' => $this->getTranslator()->trans('search.sort_option_label', [], EMSCoreExtension::TRANS_DOMAIN),
                'form' => $form->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing SearchFieldOption entity.
     * @param Request $request
     * @param SearchFieldOption $searchFieldOption
     * @return RedirectResponse|Response
     *
     * @Route("/search-field/{id}", name="ems_search_field_option_edit", methods={"GET","POST"})
     */
    public function editSearchFieldOptionAction(Request $request, SearchFieldOption $searchFieldOption)
    {

        $form = $this->createForm(SearchFieldOptionType::class, $searchFieldOption);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $removeButton = $form->get('remove');
            if ($removeButton instanceof ClickableInterface && $removeButton->isClicked()) {
                $this->getSearchFieldOptionService()->remove($searchFieldOption);
                return $this->redirectToRoute('ems_search_options_index');
            }

            if ($form->isSubmitted() && $form->isValid()) {
                $this->getSearchFieldOptionService()->save($searchFieldOption);
                return $this->redirectToRoute('ems_search_options_index');
            }
        }

        return $this->render('@EMSCore/entity/edit.html.twig', array(
                'entity_name' => $this->getTranslator()->trans('search.search_field_option_label', [], EMSCoreExtension::TRANS_DOMAIN),
                'form' => $form->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing AggregateOption entity.
     * @param Request $request
     * @param AggregateOption $option
     * @return RedirectResponse|Response
     *
     * @Route("/aggregate/{id}", name="ems_search_aggregate_option_edit", methods={"GET","POST"})
     */
    public function editAggregagteOptionAction(Request $request, AggregateOption $option)
    {
        
        
        $form = $this->createForm(AggregateOptionType::class, $option);
        $form->handleRequest($request);
        
        if ($form->isSubmitted()) {
            $removeButton = $form->get('remove');
            if ($removeButton instanceof ClickableInterface && $removeButton->isClicked()) {
                $this->getAggregateOptionService()->remove($option);
                return $this->redirectToRoute('ems_search_options_index');
            }
            
            if ($form->isSubmitted() && $form->isValid()) {
                $this->getAggregateOptionService()->save($option);
                return $this->redirectToRoute('ems_search_options_index');
            }
        }
        
        return $this->render('@EMSCore/entity/edit.html.twig', array(
                'entity_name' => $this->getTranslator()->trans('search.aggregate_option_label', [], EMSCoreExtension::TRANS_DOMAIN),
                'form' => $form->createView(),
        ));
    }
}
