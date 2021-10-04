<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\DependencyInjection\EMSCoreExtension;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\DataTransformer\DataFieldModelTransformer;
use EMS\CoreBundle\Form\DataTransformer\DataFieldViewTransformer;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RevisionType extends AbstractType
{
    /** @var FormRegistryInterface* */
    private $formRegistry;

    public function __construct(FormRegistryInterface $formRegistry)
    {
        $this->formRegistry = $formRegistry;
    }

    /**
     * @param FormInterface<FormInterface> $form
     * @param array<mixed>                 $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $jsonMenuNestedModalNames = [];
        foreach ($this->allChildren($form) as $child) {
            if ($child->getConfig()->hasOption('json_menu_nested_modal')) {
                $blockPrefix = [];
                $parent = $child;
                do {
                    \array_unshift($blockPrefix, $parent->getName());
                } while (null !== ($parent = $parent->getParent()));
                $jsonMenuNestedModalNames[] = \sprintf('json-menu-nested-modal_%s', \join('_', $blockPrefix));
            }
        }
        $view->vars['json_menu_nested_modal_names'] = $jsonMenuNestedModalNames;
    }

    /**
     * @param FormInterface<FormInterface> $form
     *
     * @return iterable|FormInterface[]
     */
    private function allChildren(FormInterface $form): iterable
    {
        foreach ($form->all() as $element) {
            yield $element;

            foreach ($this->allChildren($element) as $child) {
                yield $child;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var Revision|null $revision */
        $revision = $builder->getData();
        $contentType = $options['content_type'] ? $options['content_type'] : $revision->getContentType();

        $builder->add('data', $contentType->getFieldType()->getType(), [
                'metadata' => $contentType->getFieldType(),
                'error_bubbling' => false,
                'migration' => $options['migration'],
                'with_warning' => $options['with_warning'],
                'raw_data' => $options['raw_data'],
                'disabled_fields' => $contentType->getDisabledDataFields(),
        ])->add('save', SubmitEmsType::class, [
            'label' => 'form.form.revision-type.save-draft-label',
            'attr' => [
                    'class' => 'btn btn-default btn-sm ',
            ],
            'icon' => 'fa fa-save',
        ]);

        $builder->get('data')
        ->addModelTransformer(new DataFieldModelTransformer($contentType->getFieldType(), $this->formRegistry))
        ->addViewTransformer(new DataFieldViewTransformer($contentType->getFieldType(), $this->formRegistry));

        if ($options['has_clipboard']) {
            $builder->add('paste', SubmitEmsType::class, [
                'label' => 'form.form.revision-type.paste-label',
                'attr' => [
                    'class' => '',
                ],
                'icon' => 'fa fa-paste',
            ]);
        }

        if ($options['has_copy']) {
            $builder->add('copy', SubmitEmsType::class, [
                    'label' => 'form.form.revision-type.copy-label',
                    'attr' => [
                        'class' => '',
                    ],
                    'icon' => 'fa fa-copy',
            ]);
        }

        if (null !== $revision && $revision->getDraft()) {
            $contentType = $revision->getContentType();
            $environment = $contentType ? $contentType->getEnvironment() : null;

            if (null !== $environment && null !== $contentType && $contentType->hasVersionTags()) {
                $builder
                    ->add('publish_version_tags', ChoiceType::class, [
                        'translation_domain' => false,
                        'placeholder' => $revision->getOuuid() ? 'Silent' : null,
                        'choices' => \array_combine($contentType->getVersionTags(), $contentType->getVersionTags()),
                        'mapped' => false,
                        'required' => false
                    ])
                    ->add('publish_version', SubmitEmsType::class, [
                        'translation_domain' => EMSCoreExtension::TRANS_DOMAIN,
                        'attr' => ['class' => 'btn btn-primary btn-sm'],
                        'icon' => 'glyphicon glyphicon-open',
                        'label' => 'form.form.revision-type.publish-label',
                        'label_translation_parameters' => [
                            '%environment%' => $environment->getLabel(),
                        ],
                    ]);
            } elseif (null !== $environment) {
                $builder->add('publish', SubmitEmsType::class, [
                    'translation_domain' => EMSCoreExtension::TRANS_DOMAIN,
                    'label' => 'form.form.revision-type.publish-label',
                    'label_translation_parameters' => [
                        '%environment%' => $environment->getLabel(),
                    ],
                    'attr' => [
                        'class' => 'btn btn-primary btn-sm ',
                    ],
                    'icon' => 'glyphicon glyphicon-open',
                ]);
            }
        }
        $builder->add('allFieldsAreThere', HiddenType::class, [
                 'data' => true,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
                'compound' => true,
                'content_type' => null,
                'csrf_protection' => false,
                'data_class' => 'EMS\CoreBundle\Entity\Revision',
                'has_clipboard' => false,
                'has_copy' => false,
                'migration' => false,
                'with_warning' => true,
                'translation_domain' => 'EMSCoreBundle',
                'raw_data' => [],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'revision';
    }
}
