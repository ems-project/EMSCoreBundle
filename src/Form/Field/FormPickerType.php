<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Core\Form\FormManager;
use EMS\CoreBundle\Entity\Form;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormPickerType extends ChoiceType
{
    /** @var array<string, Form> */
    private array $choices = [];

    public function __construct(private readonly FormManager $formManager)
    {
        parent::__construct();
    }

    public function getBlockPrefix(): string
    {
        return 'selectpicker';
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $keys = [];
        foreach ($this->formManager->getAll() as $forn) {
            $keys[$forn->getLabel()] = $forn->getName();
            $this->choices[$forn->getName()] = $forn;
        }
        $options['choices'] = $keys;
        parent::buildForm($builder, $options);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $this->choices = [];
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'choices' => [],
            'attr' => [
                    'data-live-search' => false,
            ],
            'choice_attr' => function ($category, $key, $index) {
                /** @var Form $dataFieldType */
                $dataFieldType = $this->choices[$index];

                return [
                        'data-content' => '<span><i class="fa fa-square"></i>&nbsp;&nbsp;'.$dataFieldType->getLabel().'</span>',
                ];
            },
            'choice_value' => fn ($value) => $value,
            'multiple' => false,
            'managedOnly' => true,
            'userPublishEnvironments' => true,
            'ignore' => [],
            'choice_translation_domain' => false,
        ]);
    }
}
