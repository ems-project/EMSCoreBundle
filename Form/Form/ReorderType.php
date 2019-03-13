<?php 

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReorderType extends AbstractType
{

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $builder->add ( 'items', HiddenType::class, [
                'attr' => [
                        'class' => 'reorder-items'
                ],
        ]);
        
        $builder->add ( 'reorder', SubmitEmsType::class, [
                'attr' => [
                        'class' => 'btn-primary reorder-button'
                ],
                'icon' => 'fa fa-reorder'                
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
        ]);
    }

}