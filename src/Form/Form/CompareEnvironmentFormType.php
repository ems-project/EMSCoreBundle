<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Form\Field\ContentTypePickerType;
use EMS\CoreBundle\Form\Field\EnvironmentPickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * @extends AbstractType<mixed>
 */
class CompareEnvironmentFormType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // http://symfony.com/doc/current/cookbook/form/dynamic_form_modification.html#cookbook-dynamic-form-modification-suppressing-form-validation
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $event->stopPropagation();
        }, 900);

        $builder
            ->add('environment', EnvironmentPickerType::class, [
                'userPublishEnvironments' => true,
                'managedOnly' => true,
            ])
            ->add('withEnvironment', EnvironmentPickerType::class, [
                'userPublishEnvironments' => true,
                'managedOnly' => true,
            ])
            ->add('contentTypes', ContentTypePickerType::class, [
                'multiple' => true,
                'required' => false,
            ])
            ->add('compare', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary btn-md',
                ],
                'icon' => 'fa fa-columns',
            ]);
    }
}
