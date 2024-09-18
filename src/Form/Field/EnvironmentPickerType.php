<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Form\DataTransformer\EntityNameModelTransformer;
use EMS\CoreBundle\Service\EnvironmentService;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EnvironmentPickerType extends ChoiceType
{
    public function __construct(private readonly EnvironmentService $environmentService)
    {
        parent::__construct();
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
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
                false => !$defaultEnvironmentIds->contains($e->getId())
            });

            if (\count($filterDefaultEnvironments) > 0) {
                $environments = $filterDefaultEnvironments;
            }
        }

        foreach ($environments as $environment) {
            if (($environment->getManaged() || !$options['managedOnly']) && !\in_array($environment->getName(), $options['ignore'], true)) {
                $choices[$environment->getName()] = $environment;
            }
        }
        $options['choices'] = $choices;
        $builder->addModelTransformer(new EntityNameModelTransformer($this->environmentService, $options['multiple']));
        parent::buildForm($builder, $options);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver
            ->setDefaults([
                'attr' => [
                'class' => 'select2',
            ],
                'choice_label' => fn (Environment $value) => \sprintf('<i class="fa fa-square text-%s"></i>&nbsp;%s', $value->getColor(), $value->getLabel()),
                'choice_value' => function ($value) {
                    if ($value instanceof Environment) {
                    return $value->getName();
                }

                    return $value;
            },
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
