<?php

namespace EMS\CoreBundle\Form\Nature;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReorderType extends AbstractType
{
    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('items', ItemsType::class, [
            'result' => $options['result'],
        ]);

        $builder->add('reorder', SubmitEmsType::class, [
            'attr' => [
                'class' => 'btn-primary ',
            ],
            'icon' => 'fa fa-reorder',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'result' => [],
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
        ]);
    }
}
