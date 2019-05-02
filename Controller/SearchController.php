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
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

/**
 * Wysiwyg controller.
 *
 * @Route("/search-options")
 */
class SearchController extends AppController
{
    /**
     * Lists all Search options.
     *
     * @Route("/", name="ems_search_options_index")
     * @Method({"GET", "POST"})
     */
    public function indexAction(Request $request)
    {
        $reorderSortOptionform = $this->createForm(ReorderType::class);
        $reorderSortOptionform->handleRequest($request);
        if ($reorderSortOptionform->isSubmitted()) {
            $this->getSortOptionService()->reorder($reorderSortOptionform);
            return $this->redirectToRoute('ems_search_options_index');
        }
        
        $reorderAggregateOptionform = $this->createForm(ReorderBisType::class);
        $reorderAggregateOptionform->handleRequest($request);
        if ($reorderAggregateOptionform->isSubmitted()) {
            $this->getAggregateOptionService()->reorder($reorderAggregateOptionform);
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
                'sortOptionReorderForm' => $reorderSortOptionform->createView(),
                'aggregateOptionReorderForm' => $reorderAggregateOptionform->createView(),
                'searchFieldOptionReorderForm' => $searchFieldOptionForm->createView(),
        ]);
    }


    /**
     * Creates a new Sort Option entity.
     *
     * @Route("/sort/new", name="ems_search_sort_option_new")
     * @Method({"GET", "POST"})
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
            'entity_name' => $this->getTranslator()->trans('Sort Option', [], EMSCoreExtension::TRANS_DOMAIN),
            'form' => $form->createView(),
        ]);
    }


    /**
     * Creates a new Search Field Option entity.
     *
     * @Route("/search-field/new", name="ems_search_field_option_new")
     * @Method({"GET", "POST"})
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
            'entity_name' => $this->getTranslator()->trans('Search Field Option', [], EMSCoreExtension::TRANS_DOMAIN),
            'form' => $form->createView(),
        ]);
    }
    
    
    /**
     * Creates a new Agregate Option entity.
     *
     * @Route("/aggregate/new", name="ems_search_aggregate_option_new")
     * @Method({"GET", "POST"})
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
                'entity_name' => $this->getTranslator()->trans('Aggregate Option', [], EMSCoreExtension::TRANS_DOMAIN),
                'form' => $form->createView(),
        ]);
    }
    
    /**
     * Displays a form to edit an existing SortOption entity.
     *
     * @Route("/sort/{id}", name="ems_search_sort_option_edit")
     * @Method({"GET", "POST"})
     */
    public function editSortOptionAction(Request $request, SortOption $sortOption)
    {
        
        $form = $this->createForm(SortOptionType::class, $sortOption);
        $form->handleRequest($request);
        
        if ($form->isSubmitted()) {
            if ($form->get('remove') instanceof ClickableInterface && $form->get('remove')->isClicked()) {
                $this->getSortOptionService()->remove($sortOption);
                return $this->redirectToRoute('ems_search_options_index');
            }
            
            if ($form->isSubmitted() && $form->isValid()) {
                $this->getSortOptionService()->save($sortOption);
                return $this->redirectToRoute('ems_search_options_index');
            }
        }
        
        return $this->render('@EMSCore/entity/edit.html.twig', array(
                'entity_name' => $this->getTranslator()->trans('Sort Option', [], EMSCoreExtension::TRANS_DOMAIN),
                'form' => $form->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing SearchFieldOption entity.
     *
     * @Route("/search-field/{id}", name="ems_search_field_option_edit")
     * @Method({"GET", "POST"})
     */
    public function editSearchFieldOptionAction(Request $request, SearchFieldOption $searchFieldOption)
    {

        $form = $this->createForm(SearchFieldOptionType::class, $searchFieldOption);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->get('remove') instanceof ClickableInterface && $form->get('remove')->isClicked()) {
                $this->getSearchFieldOptionService()->remove($searchFieldOption);
                return $this->redirectToRoute('ems_search_options_index');
            }

            if ($form->isSubmitted() && $form->isValid()) {
                $this->getSearchFieldOptionService()->save($searchFieldOption);
                return $this->redirectToRoute('ems_search_options_index');
            }
        }

        return $this->render('@EMSCore/entity/edit.html.twig', array(
                'entity_name' => $this->getTranslator()->trans('Search Field Option', [], EMSCoreExtension::TRANS_DOMAIN),
                'form' => $form->createView(),
        ));
    }
    
    /**
     * Displays a form to edit an existing AggregateOption entity.
     *
     * @Route("/aggregate/{id}", name="ems_search_aggregate_option_edit")
     * @Method({"GET", "POST"})
     */
    public function editAggregagteOptionAction(Request $request, AggregateOption $option)
    {
        
        
        $form = $this->createForm(AggregateOptionType::class, $option);
        $form->handleRequest($request);
        
        if ($form->isSubmitted()) {
            if ($form->get('remove') instanceof ClickableInterface && $form->get('remove')->isClicked()) {
                $this->getAggregateOptionService()->remove($option);
                return $this->redirectToRoute('ems_search_options_index');
            }
            
            if ($form->isSubmitted() && $form->isValid()) {
                $this->getAggregateOptionService()->save($option);
                return $this->redirectToRoute('ems_search_options_index');
            }
        }
        
        return $this->render('@EMSCore/entity/edit.html.twig', array(
                'entity_name' => $this->getTranslator()->trans('Aggregate Option', [], EMSCoreExtension::TRANS_DOMAIN),
                'form' => $form->createView(),
        ));
    }
}
