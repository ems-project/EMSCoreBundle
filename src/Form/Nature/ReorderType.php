<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Nature;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReorderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
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

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'result' => [],
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
        ]);
    }
}
