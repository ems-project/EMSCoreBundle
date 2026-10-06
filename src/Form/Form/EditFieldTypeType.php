<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\Form\EditFieldType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\FieldType\FieldTypeType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class EditFieldTypeType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var EditFieldType $editFieldType */
        $editFieldType = $builder->getData();

        $builder->add('fieldType', FieldTypeType::class, [
            'data' => $editFieldType->getFieldType(),
            'editSubfields' => false,
        ]);

        $builder->add('save', SubmitEmsType::class, [
            'attr' => ['data-testid' => 'btn-action-save'],
            'label' => t('action.save', [], 'emsco-core'),
        ]);
        $builder->add('saveAndClose', SubmitEmsType::class, [
            'attr' => ['data-testid' => 'btn-action-save-close'],
            'label' => t('action.save_close', [], 'emsco-core'),
        ]);
    }
}
