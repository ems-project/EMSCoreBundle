<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Core\ContentType\ContentTypeFields;
use EMS\CoreBundle\Form\Field\ContentTypeFieldPickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContentTypeFieldsType extends AbstractType
{
    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(ContentTypeFields::LABEL, ContentTypeFieldPickerType::class, [
            'required' => false,
            'firstLevelOnly' => true,
            'mapping' => $options['mapping'],
            'types' => ['text',  'keyword', 'string', 'integer'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['mapping']);
    }
}
