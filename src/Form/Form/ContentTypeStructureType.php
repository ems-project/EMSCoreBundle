<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\FieldType\FieldTypeType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @extends AbstractType<mixed>
 */
class ContentTypeStructureType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ContentType $contentType */
        $contentType = $builder->getData();

        if ($contentType->giveEnvironment()->getManaged()) {
            $builder->add('fieldType', FieldTypeType::class, [
                'data' => $contentType->getFieldType(),
            ]);
        }

        $builder->add('save', SubmitEmsType::class, [
            'attr' => [
                'class' => 'btn btn-primary btn-sm ',
            ],
            'icon' => 'fa fa-save',
        ]);
        $builder->add('saveAndClose', SubmitEmsType::class, [
            'attr' => [
                'class' => 'btn btn-primary btn-sm ',
            ],
            'icon' => 'fa fa-save',
        ]);
        $builder->add('saveAndReorder', SubmitEmsType::class, [
            'attr' => [
                'class' => 'btn btn-primary btn-sm ',
            ],
            'icon' => 'fa fa-reorder',
        ]);
    }
}
