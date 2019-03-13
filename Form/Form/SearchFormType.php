<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\SortOption;
use EMS\CoreBundle\Form\Field\ContentTypePickerType;
use EMS\CoreBundle\Form\Field\EnvironmentPickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Subform\SearchFilterType;
use EMS\CoreBundle\Service\SearchFieldOptionService;
use EMS\CoreBundle\Service\SortOptionService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class SearchFormType extends AbstractType
{



    /** @var AuthorizationCheckerInterface $authorizationChecker*/
    private $authorizationChecker;
    /** @var SortOptionService $sortOptionService*/
    private $sortOptionService;
    /** @var SearchFieldOptionService $searchFieldOptionService*/
    private $searchFieldOptionService;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker, SortOptionService $sortOptionService, SearchFieldOptionService $searchFieldOptionService)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->sortOptionService = $sortOptionService;
        $this->searchFieldOptionService = $searchFieldOptionService;
    }

    /**
     *
     * {@inheritdoc}
     *
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $isSuper = $this->authorizationChecker->isGranted('ROLE_SUPER');

        $searchFields = [];
        $searchFieldsData = [];
        /**@var SortOption $sortOption*/
        foreach ($this->searchFieldOptionService->getAll() as $searchFieldOption) {
            $searchFieldsData[$searchFieldOption->getName()] = $searchFieldOption->getField();
            $searchFields[$searchFieldOption->getName()] = $searchFieldOption;
        }


        $builder->add('filters', CollectionType::class, array(
            'entry_type'   => SearchFilterType::class,
            'allow_add'    => true,
            'entry_options' => [
                'is_super'     => $isSuper,
                'searchFieldsData' => $searchFieldsData,
                'searchFields' => $searchFields,
            ],
        ));
        if ($options['light']) {
            $builder->add('applyFilters', SubmitEmsType::class, [
                'attr' => [
                        'class' => 'btn-primary btn-md',
                ],
                'icon' => 'fa fa-check',
            ]);
        } else {
            $sortOptions = $this->sortOptionService->getAll();
            if ($isSuper || empty($sortOptions)) {
                $builder->add('sortBy', TextType::class, [
                    'required' => false,
                ]);
            } else {
                $sortFields = [];
                $sortFieldIcons = [];
                /**@var SortOption $sortOption*/
                foreach ($sortOptions as $sortOption) {
                    $sortFields[$sortOption->getName()] = $sortOption->getField();
                    $sortFieldIcons[$sortOption->getField()] = $sortOption->getIcon();
                }

                $builder->add('sortBy', ChoiceType::class, [
                    'required' => false,
                    'choices' => $sortFields,
                    'choice_attr' => function ($category, $key, $index) use ($sortFieldIcons) {
                        return [
                            'data-content' => '<span class=""><i class="'.($sortFieldIcons[$index]?:'fa fa-square').'"></i>&nbsp;&nbsp;'.$key.'</span>'
                        ];
                    },
                    'attr' => [
                        'class' => 'selectpicker',
                    ],
                ]);
            }


            $builder->add('sortOrder', ChoiceType::class, [
                    'choices' => [
                            'Ascending' => 'asc',
                            'Descending' => 'desc',
                    ],
                    'choice_attr' => function ($category, $key, $index) {
                        return [
                            'data-content' => '<span class=""><i class="fa fa-sort-'.$index.'"></i>&nbsp;&nbsp;'.$key.'</span>'
                        ];
                    },
                    'attr' => [
                        'class' => 'selectpicker',
                    ],
                    'required' => false,
            ]);
            $builder->add('search', SubmitEmsType::class, [
                    'attr' => [
                            'class' => 'btn-primary btn-md'
                    ],
                    'icon' => 'fa fa-search'
            ])->add('exportResults', SubmitEmsType::class, [
                    'attr' => [
                            'class' => 'btn-primary btn-sm'
                    ],
                    'icon' => 'glyphicon glyphicon-export',
            ])->add('environments', EnvironmentPickerType::class, [
                'multiple' => true,
                'required' => false,
                'managedOnly' => false,
            ])->add('contentTypes', ContentTypePickerType::class, [
                'multiple' => true,
                'required' => false,
            ]);
            if (!$options['savedSearch']) {
                $builder->add('save', SubmitEmsType::class, [
                        'attr' => [
                                'class' => 'btn-primary btn-md'
                        ],
                        'icon' => 'fa fa-save',
                ]);
            }
        }
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'EMS\CoreBundle\Entity\Form\Search',
            'savedSearch' => false,
            'csrf_protection' => false,
            'light' => false,
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
        ]);
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        /* give options for twig context */
        parent::buildView($view, $form, $options);
    }
}
