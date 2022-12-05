<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Service\EnvironmentService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ReleaseType extends AbstractType
{
    public function __construct(private readonly EnvironmentService $environmentService)
    {
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'required' => true,
                'empty_data' => '',
                'row_attr' => [
                    'class' => 'col-md-3',
                ],
            ])
            ->add('execution_date', DateTimeType::class, [
                'required' => false,
                'date_widget' => 'single_text',
                'input' => 'datetime',
                'attr' => [
                    'class' => 'datetime-picker',
                    'data-date-format' => 'D/MM/YYYY HH:mm:ss',
                    'data-date-days-of-week-disabled' => '',
                    'data-date-disabled-hours' => '',
                ],
                'row_attr' => [
                    'class' => 'col-md-6',
                ],
            ])
            ->add('environmentSource', ChoiceType::class, [
                'attr' => [
                    'class' => 'select2',
                ],
                'choices' => $this->environmentService->getEnvironments(),
                'required' => true,
                'choice_label' => fn (Environment $value) => '<i class="fa fa-square text-'.$value->getColor().'"></i>&nbsp;&nbsp;'.$value->getLabel(),
                'row_attr' => [
                    'class' => 'col-md-3',
                ],
                'choice_value' => function (?Environment $value) {
                    if (null != $value) {
                        return $value->getId();
                    }

                    return $value;
                },
            ])
            ->add('environmentTarget', ChoiceType::class, [
                'attr' => [
                    'class' => 'select2',
                ],
                'choices' => $this->environmentService->getEnvironments(),
                'required' => true,
                'choice_label' => fn (Environment $value) => '<i class="fa fa-square text-'.$value->getColor().'"></i>&nbsp;&nbsp;'.$value->getLabel(),
                'row_attr' => [
                    'class' => 'col-md-3',
                ],
                'choice_value' => function (?Environment $value) {
                    if (null != $value) {
                        return $value->getId();
                    }

                    return $value;
                },
            ]);

        if ($options['add'] ?? false) {
            $builder->add('create', SubmitEmsType::class, [
                    'attr' => [
                        'class' => 'btn btn-primary btn-sm',
                    ],
                    'icon' => 'fa fa-plus',
                    'label' => 'release.add.save',
                ]);
        } else {
            $builder->add('save', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-default btn-sm',
                ],
                'icon' => 'fa fa-save',
                'label' => 'release.edit.save',
            ])
            ->add('saveAndClose', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-default btn-sm',
                ],
                'icon' => 'fa fa-save',
                'label' => 'release.edit.saveAndClose',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Release::class,
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            'add' => false,
        ]);
    }
}
