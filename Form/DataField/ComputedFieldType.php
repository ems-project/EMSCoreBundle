<?php

namespace EMS\CoreBundle\Form\DataField;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\DataField;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class ComputedFieldType extends DataFieldType
{
    /**
     *
     * {@inheritdoc}
     *
     */
    public function getLabel()
    {
        return 'Computed from the raw-data';
    }
    


    /**
     *
     * {@inheritdoc}
     *
     */
    public function generateMapping(FieldType $current, $withPipeline)
    {
        if (!empty($current->getMappingOptions()) && !empty($current->getMappingOptions()['mappingOptions'])) {
            try {
                $mapping = json_decode($current->getMappingOptions()['mappingOptions'], true);
                return [ $current->getName() =>  $this->elasticsearchService->updateMapping($mapping) ];
            } catch (\Exception $e) {
                //TODO send message to user, mustr move to service first
            }
        }
        return [];
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public static function getIcon()
    {
        return 'fa fa-gears';
    }


    /**
     *
     * {@inheritdoc}
     *
     */
    public function buildObjectArray(DataField $data, array &$out)
    {
        if (! $data->getFieldType()->getDeleted()) {
            /**
             * by default it serialize the text value.
             * It can be overrided.
             */
            $out [$data->getFieldType()->getName()] = $data->getRawData();
        }
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');
        
        // String specific display options
        $optionsForm->get('displayOptions')->add('valueTemplate', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'rows' => 8,
                ],
        ])->add('json', CheckboxType::class, [
                'required' => false,
                'label' => 'Try to JSON decode'
        ])->add('displayTemplate', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'rows' => 8,
                ],
        ]);


        $optionsForm->get('mappingOptions')->remove('index')->remove('analyzer')->add('mappingOptions', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'rows' => 8,
                ],
        ])
        ->add('copy_to', TextType::class, [
                'required' => false,
        ]);
        $optionsForm->remove('restrictionOptions');
        $optionsForm->remove('migrationOptions');
    }

//    public function getBlockPrefix() {
//        return  'empty';
//    }



    /**
     *
     * {@inheritdoc}
     *
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('value', HiddenType::class, [
            'required' => false,
        ]);
    }

    /**
     *
     * {@inheritDoc}
     * @see \EMS\CoreBundle\Form\DataField\DataFieldType::viewTransform()
     */
    public function viewTransform(DataField $dataField)
    {
        $out = parent::viewTransform($dataField);
        return ['value' => json_encode($out)];
    }

    /**
     *
     * {@inheritDoc}
     * @see \EMS\CoreBundle\Form\DataField\DataFieldType::reverseViewTransform()
     */
    public function reverseViewTransform($data, FieldType $fieldType)
    {
        $dataField = parent::reverseViewTransform($data, $fieldType);
        try {
            $value = json_decode($data['value']);
            $dataField->setRawData($value);
        } catch (\Exception $e) {
            $dataField->setRawData(null);
            $dataField->addMessage('ems was not able to parse the field');
        }
        return $dataField;
    }



    /**
     *
     * {@inheritdoc}
     *
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('displayTemplate', null);
        $resolver->setDefault('json', false);
        $resolver->setDefault('valueTemplate', null);
    }
}
