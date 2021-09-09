<?php

namespace EMS\CoreBundle\Form\Nature;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\DataField\DataLinkFieldType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReorganizeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('structure', HiddenType::class, [
            'attr' => [
                'class' => 'reorder-items',
            ],
        ])
        ->add('reorder', SubmitEmsType::class, [
                'attr' => [
                        'class' => 'btn btn-primary reorder-button',
                ],
                'icon' => 'fa fa-reorder',
        ]);

        /** @var View */
        $view = $options['view'];
        if ($view instanceof View) {
            $fieldType = $view->getContentType()->getFieldType()->getChildByPath($view->getOptions()['field']);
            $builder->add('addItem', DataLinkFieldType::class, [
                    'metadata' => $fieldType,
                    'label' => 'Add item',
                    'required' => false,
                    'type' => $fieldType->getDisplayOption('type', null),
            ]);

            $builder->get('addItem')->addModelTransformer(new CallbackTransformer(
                function ($raw) {
                    $dataField = new DataField();

                    return $dataField;
                },
                function (DataField $tagsAsString) {
                    // transform the string back to an array
                    return null;
                }
            ))->addViewTransformer(new CallbackTransformer(
                function (DataField $tagsAsString) {
                    // transform the string back to an array
                    return null;
                },
                function ($raw) {
                    $dataField = new DataField();

                    return $dataField;
                }
            ));
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'view' => null,
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
        ]);
    }
}
