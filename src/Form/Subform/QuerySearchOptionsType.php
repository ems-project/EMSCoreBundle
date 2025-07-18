<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Subform;

use EMS\CoreBundle\Form\Field\CodeEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
final class QuerySearchOptionsType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('query', CodeEditorType::class, [
                'label' => t('field.query', [], 'emsco-core'),
                'required' => true,
                'language' => 'ace/mode/json',
            ]);
    }
}
