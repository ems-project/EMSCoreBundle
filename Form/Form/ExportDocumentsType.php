<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExportDocumentsType extends AbstractType
{
    public function __construct()
    {
    }
    
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $data = $builder->getData();
        
        $builder
            ->setAction($data['action'])
            ->add('query', HiddenType::class, [
                'data' => $data['query'],
            ])
            ->add('format', ChoiceType::class, [
                'choices' => $data['formats'],
            ])
            ->add('withBusinessKey', CheckboxType::class, [
                'data' => true,
            ])
            ->add('export', SubmitEmsType::class, [
                'label' => 'Export ' . $data['contentType']->getPluralName(),
                'attr' => ['class' => 'btn-primary btn-sm '],
                'icon' => 'glyphicon glyphicon-export'
            ]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
        ]);
    }
}
