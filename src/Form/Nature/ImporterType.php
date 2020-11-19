<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Nature;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Form\Field\FileType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ImporterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
           ->add('archive', FileType::class, [
               'meta_fields' => false,
           ])
           ->add('import', SubmitEmsType::class, [
               'attr' => [
                   'class' => 'btn-danger',
               ],
               'icon' => 'glyphicon glyphicon-import',
           ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'view' => null,
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
        ]);
    }
}
