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

        $defaultEnvironment = $options['defaultEnvironment'];
        if (\is_bool($defaultEnvironment)) {
            $defaultEnvironmentIds = $this->service->getDefaultEnvironmentIds();
            $filterDefaultEnvironments = \array_filter($environments, static fn (Environment $e) => match ($defaultEnvironment) {
                true => $defaultEnvironmentIds->contains($e->getId()),
                false => !$defaultEnvironmentIds->contains($e->getId())
            });

            if (\count($filterDefaultEnvironments) > 0) {
                $environments = $filterDefaultEnvironments;
            }
        }

        foreach ($environments as $env) {
            if (($env->getManaged() || !$options['managedOnly']) && !\in_array($env->getName(), $options['ignore'], true)) {
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

        $resolver
            ->setDefaults([
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
                'defaultEnvironment' => null,
                'ignore' => [],
                'choice_translation_domain' => false,
            ])
            ->setAllowedTypes('defaultEnvironment', ['null', 'bool'])
        ;
    }
}
