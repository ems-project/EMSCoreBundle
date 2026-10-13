<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form\Environment;

use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class RebuildType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('option', ChoiceType::class, [
                'label' => false,
                'choices' => [
                    t('option.rebuild_new_index', [], 'emsco-core')->getMessage() => 'newIndex',
                    t('option.rebuild_same_index', [], 'emsco-core')->getMessage() => 'sameIndex',
                ],
                'choice_translation_domain' => 'emsco-core',
                'expanded' => true,
            ])
             ->add('save', SubmitEmsType::class, [
                 'attr' => ['data-testid' => 'btn-action-rebuild'],
                 'label' => t('action.rebuild', [], 'emsco-core'),
                 'icon' => 'fa fa-recycle',
             ]);
    }
}
