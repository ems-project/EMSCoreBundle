<?php

namespace EMS\CoreBundle\Form\Nature;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ItemsType extends AbstractType
{
    public const PREFIX = 'item-';

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $result = $options['result'];

        foreach ($result['hits']['hits'] as $hit) {
            $builder->add(\join('', [self::PREFIX, $hit['_id']]), HiddenType::class, [
                'attr' => [
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'result' => [],
        ]);
    }
}
