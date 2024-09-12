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

    public function __construct(private readonly EnvironmentService $service)
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

        if ($options['userPublishEnvironments']) {
            $environments = $this->service->getUserPublishEnvironments()->toArray();
        } else {
            $environments = $this->service->getEnvironments();
        }

        foreach ($environments as $env) {
            if (($env->getManaged() || !$options['managedOnly']) && !\in_array($env->getName(), $options['ignore'])) {
                $keys[$env->getName()] = $env;
                $this->choices[$env->getName()] = $env;
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
            'choice_value' => fn ($value) => $value,
            'multiple' => false,
            'managedOnly' => true,
            'userPublishEnvironments' => true,
            'ignore' => [],
            'choice_translation_domain' => false,
        ]);
    }
}
