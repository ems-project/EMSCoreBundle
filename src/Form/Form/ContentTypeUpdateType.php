<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContentTypeUpdateType extends AbstractType
{
    /**
     * @param FormBuilderInterface<AbstractType> $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('json', FileType::class, [
            'label_format' => 'form.contenttype.json_update.%name%',
        ]);
        $builder->add('deleteExitingTemplates', CheckboxType::class, [
            'required' => false,
            'label_format' => 'form.contenttype.json_update.%name%',
        ]);
        $builder->add('deleteExitingViews', CheckboxType::class, [
            'required' => false,
            'label_format' => 'form.contenttype.json_update.%name%',
        ]);
        $builder->add('update', SubmitEmsType::class, [
            'label_format' => 'form.contenttype.json_update.%name%',
            'attr' => [
                'class' => 'btn-danger',
            ],
            'icon' => 'fa fa-save',
        ]);
        parent::buildForm($builder, $options);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN

        ]);
    }
}
