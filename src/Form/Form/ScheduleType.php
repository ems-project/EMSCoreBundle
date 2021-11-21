<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\Schedule;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Psr\Container\ContainerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ScheduleType extends AbstractType
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $schedule = $builder->getData();
        if (!$schedule instanceof Schedule) {
            throw new \RuntimeException('Unexpected non Schedule object');
        }

        $builder->add('name', IconTextType::class, [
            'icon' => 'fa fa-tag',
            'row_attr' => [
                'class' => 'col-md-8',
            ],
        ])->add('cron', IconTextType::class, [
            'icon' => 'fa fa-clock-o',
            'row_attr' => [
                'class' => 'col-md-8',
            ],
        ])->add('command', IconTextType::class, [
            'icon' => 'fa fa-terminal',
            'row_attr' => [
                'class' => 'col-md-8',
            ],
        ]);

        if ($options['create']) {
            $builder->add('create', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary btn-sm',
                ],
                'icon' => 'fa fa-save',
            ]);
        } else {
            $builder->add('save', SubmitEmsType::class, [
                    'attr' => [
                        'class' => 'btn-primary btn-sm',
                        'data-ajax-save-url' => $options['ajax-save-url'],
                    ],
                    'icon' => 'fa fa-save',
                ])
                ->add('saveAndClose', SubmitEmsType::class, [
                    'attr' => [
                        'class' => 'btn-primary btn-sm',
                    ],
                    'icon' => 'fa fa-save',
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Schedule::class,
            'label_format' => 'schedule.%name%',
            'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
            'create' => false,
            'ajax-save-url' => null,
        ]);
    }
}
