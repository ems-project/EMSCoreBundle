<?php
namespace EMS\CoreBundle\Form;

use Doctrine\ORM\EntityRepository;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;


class UserProfileType extends AbstractType
{
    
    /**@var TokenStorageInterface */
    private $tokenStorage;
    
    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }
    
    
    /**
     *
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $builder
            ->add('displayName')
            ->add('emailNotification', CheckboxType::class, [
                    'required' => false,
            ])
            ->add('layoutBoxed')
            ->add('sidebarMini')
            ->add('sidebarCollapse')
            ->remove('username');
        
        $builder
            ->add('wysiwygProfile', EntityType::class, [
                'required' => false,
                'label' => 'WYSIWYG profile',
                'class' => 'EMSCoreBundle:WysiwygProfile',
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('p')->orderBy('p.orderKey', 'ASC');
                },
                'attr' => [
                    'data-live-search' => true,
                    'class' => 'wysiwyg-profile-picker',
                ],
            ])
            ->add('wysiwygOptions', CodeEditorType::class, [
                'label' => 'WYSIWYG Options',
                'required' => false,
                'language' => 'ace/mode/json',
                'attr' => [
                    'class' => 'wysiwyg-profile-options',
                ],
            ]);
    }



    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        /*set the default option value for this kind of compound field*/
        parent::configureOptions($resolver);
        $resolver->setDefault('translation_domain', EMSCoreBundle::TRANS_DOMAIN);
    }
    
    public function getParent()
    {
        return 'FOS\UserBundle\Form\Type\ProfileFormType';
    }
}
