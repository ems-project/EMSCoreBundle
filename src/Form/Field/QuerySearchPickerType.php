<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Entity\QuerySearch;
use EMS\CoreBundle\Form\DataTransformer\EntityNameModelTransformer;
use EMS\CoreBundle\Service\QuerySearchService;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QuerySearchPickerType extends ChoiceType
{
    public function __construct(private readonly QuerySearchService $querySearchService)
    {
        parent::__construct();
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $options['choices'] = $this->querySearchService->getAll();
        $builder->addModelTransformer(new EntityNameModelTransformer($this->querySearchService, $options['multiple']));
        parent::buildForm($builder, $options);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'attr' => [
                'class' => 'select2',
            ],
            'choice_label' => fn (QuerySearch $querySearch) => \sprintf('<span><i class="fa fa-search"></i>&nbsp;%s', $querySearch->getLabel()),
            'choice_value' => function ($value) {
                if ($value instanceof QuerySearch) {
                    return $value->getName();
                }

                return $value;
            },
            'multiple' => false,
            'choice_translation_domain' => false,
        ]);
    }
}
