<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Form\Field\EnvironmentPickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
final class ReleaseType extends AbstractType
{
    public const string BTN_SAVE = 'save';
    public const string BTN_SAVE_CLOSE = 'saveAndClose';

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'required' => true,
                'empty_data' => '',
                'label' => t('field.name', [], 'emsco-core'),
            ])
            ->add('environmentTarget', EnvironmentPickerType::class, [
                'userPublishEnvironments' => true,
                'defaultEnvironment' => false,
                'managedOnly' => true,
                'label' => t('field.release_environment_target', [], 'emsco-core'),
                'choice_callback' => fn (Environment $environment) => $environment,
            ])
            ->add('execution_date', DateTimeType::class, [
                'required' => false,
                'date_widget' => 'single_text',
                'input' => 'datetime',
                'label' => t('field.date_execution', [], 'emsco-core'),
                'attr' => [
                    'class' => 'datetime-picker',
                    'data-date-format' => 'D/MM/YYYY HH:mm:ss',
                    'data-date-days-of-week-disabled' => '',
                    'data-date-disabled-hours' => '',
                ],
            ])
            ->add('environmentSource', EnvironmentPickerType::class, [
                'userPublishEnvironments' => true,
                'defaultEnvironment' => true,
                'managedOnly' => true,
                'label' => t('field.release_environment_source', [], 'emsco-core'),
                'choice_callback' => fn (Environment $environment) => $environment,
            ])
        ;

        if ($options['add'] ?? false) {
            $builder->add('create', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary btn-sm',
                ],
                'icon' => 'fa fa-plus',
                'label' => t('action.create', [], 'emsco-core'),
            ]);
        } else {
            $builder->add(self::BTN_SAVE, SubmitEmsType::class, [
                'attr' => ['class' => 'btn btn-default btn-sm'],
                'icon' => 'fa fa-save',
                'label' => t('action.save', [], 'emsco-core'),
            ])
            ->add(self::BTN_SAVE_CLOSE, SubmitEmsType::class, [
                'attr' => ['class' => 'btn btn-default btn-sm'],
                'icon' => 'fa fa-save',
                'label' => t('action.save_close', [], 'emsco-core'),
            ]);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Release::class,
            'add' => false,
        ]);
    }
}
