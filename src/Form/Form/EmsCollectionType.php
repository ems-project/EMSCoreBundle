<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataField\CollectionItemFieldType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class EmsCollectionType extends CollectionType
{
    /** @var AuthorizationCheckerInterface */
    protected $authorizationChecker;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Form\Extension\Core\Type\CollectionType::buildForm()
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var FieldType $fieldType */
        $fieldType = clone $builder->getOptions()['metadata'];
        $options['metadata'] = $fieldType;

        $disabled = false;
        $sapiName = \php_sapi_name();
        if ($sapiName && 0 !== \strcmp('cli', $sapiName)) {
            $enable = ($options['migration'] && !$fieldType->getMigrationOption('protected', true)) || $this->authorizationChecker->isGranted($fieldType->getMinimumRole());
            $disabled = !$enable;
        }

        $options = \array_merge($options, [
                'entry_type' => CollectionItemFieldType::class,
                'entry_options' => [
                    'metadata' => $fieldType,
                    'migration' => $options['migration'],
                    'with_warning' => $options['with_warning'],
                    'raw_data' => $options['raw_data'],
                    'referrer-ems-id' => $options['referrer-ems-id'],
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'prototype_name' => '__name__'.$fieldType->getId().'__',
                'required' => false,
                'disabled' => $disabled,
        ]);

        parent::buildForm($builder, $options);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefaults([
                'collapsible' => false,
                'icon' => null,
                'itemBootstrapClass' => null,
                'singularLabel' => null,
                'sortable' => false,
        ]);
    }
}
