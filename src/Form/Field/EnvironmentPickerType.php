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
    private array $environments = [];

    public function __construct(private readonly EnvironmentService $environmentService)
    {
        parent::__construct();
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];
        if ($options['userPublishEnvironments']) {
            $environments = $this->environmentService->getUserPublishEnvironments()->toArray();
        } else {
            $environments = $this->environmentService->getEnvironments();
        }

        $defaultEnvironment = $options['defaultEnvironment'];
        if (\is_bool($defaultEnvironment)) {
            $defaultEnvironmentIds = $this->environmentService->getDefaultEnvironmentIds();
            $filterDefaultEnvironments = \array_filter($environments, static fn (Environment $e) => match ($defaultEnvironment) {
                true => $defaultEnvironmentIds->contains($e->getId()),
                false => !$defaultEnvironmentIds->contains($e->getId()),
            });

            if (\count($filterDefaultEnvironments) > 0) {
                $environments = $filterDefaultEnvironments;
            }
        }

        foreach ($environments as $env) {
            if (($env->getManaged() || !$options['managedOnly']) && !\in_array($env->getName(), $options['ignore'], true)) {
                $choices[$env->getName()] = $env;
                $this->environments[$env->getName()] = $env;
            }
        }
        $options['choices'] = \array_map($options['choice_callback'], $choices);
        parent::buildForm($builder, $options);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $this->environments = [];
        parent::configureOptions($resolver);

        $resolver
            ->setDefaults([
                'attr' => [
                    'class' => 'select2',
                ],
                'choice_attr' => function ($category, $key, $index) {
                    /** @var Environment $dataFieldType */
                    $dataFieldType = $this->environments[$index];

                    return [
                        'data-content' => '<span class="text-'.$dataFieldType->getColor().'"><i class="fa fa-square"></i>&nbsp;&nbsp;'.$dataFieldType->getLabel().'</span>',
                    ];
                },
                'multiple' => false,
                'managedOnly' => true,
                'userPublishEnvironments' => true,
                'defaultEnvironment' => null,
                'ignore' => [],
                'choice_translation_domain' => false,
                'choice_callback' => fn (Environment $e) => $e->getName(),
            ])
            ->setAllowedTypes('defaultEnvironment', ['null', 'bool'])
        ;
    }
}
