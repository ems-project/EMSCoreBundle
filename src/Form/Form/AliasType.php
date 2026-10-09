<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Form\Field\CancelType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Routes;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class AliasType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', IconTextType::class, [
                'icon' => 'fa fa-key',
                'label' => t('field.alias', [], 'emsco-core'),
                'required' => true,
            ])
            ->add('save', SubmitEmsType::class, [
                'attr' => ['data-testid' => 'btn-action-save'],
                'label' => t('action.add', [], 'emsco-core'),
            ])
            ->add('cancel', CancelType::class, [
                'attr' => ['data-testid' => 'btn-cancel'],
                'route' => Routes::ADMIN_ELASTIC_ORPHAN,
            ]);
    }
}
