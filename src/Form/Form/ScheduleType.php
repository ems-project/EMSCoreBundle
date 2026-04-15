<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\Schedule;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class ScheduleType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', IconTextType::class, [
            'label' => t('field.name', [], 'emsco-core'),
            'icon' => 'fa fa-tag',
            'row_attr' => [
                'class' => 'col-md-8',
            ],
        ])->add('cron', IconTextType::class, [
            'label' => t('field.cron', [], 'emsco-core'),
            'icon' => 'fa fa-clock-o',
            'row_attr' => [
                'class' => 'col-md-8',
            ],
            'help' => t('key.schedule_cron', [], 'emsco-core'),
        ])->add('command', IconTextType::class, [
            'label' => t('field.command', [], 'emsco-core'),
            'icon' => 'fa fa-terminal',
            'row_attr' => [
                'class' => 'col-md-8',
            ],
        ])->add('tag', IconTextType::class, [
            'label' => t('field.tag', [], 'emsco-core'),
            'required' => false,
            'icon' => 'fa fa-tags',
            'row_attr' => [
                'class' => 'col-md-8',
            ],
            'help' => t('key.schedule_tag', [], 'emsco-core'),
        ]);

        if (null !== $options['ajax-save-url']) {
            $builder->add('save', SubmitEmsType::class, [
                'label' => t('action.save', [], 'emsco-core'),
                'attr' => [
                    'class' => 'btn btn-primary btn-sm',
                    'data-ajax-save-url' => $options['ajax-save-url'],
                    'data-testid' => 'btn-action-save',
                ],
                'icon' => 'fa fa-save',
            ])->add('save_close', SubmitEmsType::class, [
                'label' => t('action.save_close', [], 'emsco-core'),
                'attr' => [
                    'class' => 'btn btn-primary btn-sm',
                    'data-testid' => 'btn-action-save-close',
                ],
                'icon' => 'fa fa-save',
            ]);
        } else {
            $builder->add('save', SubmitEmsType::class, [
                'label' => t('action.save', [], 'emsco-core'),
                'attr' => ['class' => 'btn btn-primary btn-sm', 'data-testid' => 'btn-action-save'],
                'icon' => 'fa fa-save',
            ]);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Schedule::class,
            'ajax-save-url' => null,
        ]);
    }
}
