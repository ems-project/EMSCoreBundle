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

        foreach ($environments as $environment) {
            if (($environment->getManaged() || !$options['managedOnly']) && !\in_array($environment->getName(), $options['ignore'])) {
                $choices[] = $environment;
            }
        }
        $options['choices'] = $choices;
        $builder->addModelTransformer(new EntityNameModelTransformer($this->environmentService, $options['multiple']));
        parent::buildForm($builder, $options);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
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
            'ignore' => [],
            'choice_translation_domain' => false,
        ]);
    }
}
