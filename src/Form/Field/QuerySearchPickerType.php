<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Entity\QuerySearch;
use EMS\CoreBundle\Service\QuerySearchService;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QuerySearchPickerType extends ChoiceType
{
    /** @var array<QuerySearch> */
    private array $choices = [];
    private QuerySearchService $querySearchService;

    public function __construct(QuerySearchService $querySearchService)
    {
        parent::__construct();
        $this->querySearchService = $querySearchService;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $this->choices = [];
        $keys = [];
        /** @var QuerySearch $choice */
        foreach ($this->querySearchService->getAll() as $choice) {
            $keys[] = $choice->getName();
            $this->choices[$choice->getName()] = $choice;
        }
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'choices' => $keys,
            'attr' => [
                    'data-live-search' => false,
                    'class' => 'selectpicker',
            ],
            'choice_attr' => function ($name, $key, $index) {
                /** @var QuerySearch $querySearch */
                $querySearch = $this->choices[$index];

                return [
                    'data-content' => "<div class='text-".$name."'><i class='fa fa-square'></i>&nbsp;&nbsp;".$querySearch->getName().'</div>',
                ];
            },
            'choice_value' => function ($value) {
                return $value;
            },
            'multiple' => false,
            'choice_translation_domain' => false,
        ]);
    }
}
