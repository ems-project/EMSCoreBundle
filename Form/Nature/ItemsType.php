<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Nature;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ItemsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $result = $options['result'];

        foreach ($result['hits']['hits'] as $hit) {
            $builder->add($hit['_id'], HiddenType::class, [
                    'attr' => [
                    ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'result' => [],
        ]);
    }
}
