<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Service\EnvironmentService;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EnvironmentPickerType extends ChoiceType
{
    /** @var array<mixed> */
    private array $choices = [];
    private EnvironmentService $service;

    public function __construct(EnvironmentService $service)
    {
        parent::__construct();
        $this->service = $service;
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
        $environments = null;

        if ($options['inMyCircle']) {
            $environments = $this->service->getAllInMyCircle();
        } else {
            $environments = $this->service->getAll();
        }

        $this->service->getAllInMyCircle();

        /** @var Environment $choice */
        foreach ($environments as $key => $choice) {
            if (($choice->getManaged() || !$options['managedOnly']) && !\in_array($choice->getName(), $options['ignore'])) {
                $keys[] = $choice->getName();
                $this->choices[$choice->getName()] = $choice;
            }
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
                /** @var Environment $dataFieldType */
                $dataFieldType = $this->choices[$index];

                return [
                        'data-content' => '<span class="text-'.$dataFieldType->getColor().'"><i class="fa fa-square"></i>&nbsp;&nbsp;'.$dataFieldType->getLabel().'</span>',
                ];
            },
            'choice_value' => function ($value) {
                return $value;
            },
            'multiple' => false,
            'managedOnly' => true,
            'inMyCircle' => true,
            'ignore' => [],
            'choice_translation_domain' => false,
        ]);
    }
}
