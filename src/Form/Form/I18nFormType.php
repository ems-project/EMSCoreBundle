<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @extends AbstractType<mixed>
 */
class I18nFormType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('identifier', null, ['required' => false, 'label' => 'Key'])
            ->add('filter', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary btn-sm',
                ],
                'icon' => 'fa fa-columns',
            ]);
    }
}
