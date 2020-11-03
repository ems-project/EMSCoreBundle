<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\DataTransformer\DataFieldModelTransformer;
use EMS\CoreBundle\Form\DataTransformer\DataFieldViewTransformer;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
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
        $jsonMenuNestedEditors = [];

        foreach ($this->allChildren($form) as $child) {
            if ($child->getConfig()->hasOption('json_menu_nested_editor')) {
                $jsonMenuNestedEditors[] = $child->getConfig()->getOption('json_menu_nested_editor');
            }
        }

        $view->vars['all_json_menu_nested_editor'] = $jsonMenuNestedEditors;
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
        ])->add('save', SubmitEmsType::class, [
                'attr' => [
                        'class' => 'btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-save',
                'label' => 'data.edit_revision.save_draft',
        ]);

        $builder->get('data')
        ->addModelTransformer(new DataFieldModelTransformer($contentType->getFieldType(), $this->formRegistry))
        ->addViewTransformer(new DataFieldViewTransformer($contentType->getFieldType(), $this->formRegistry));

        if ($options['has_clipboard']) {
            $builder->add('paste', SubmitEmsType::class, [
                    'attr' => [
                            'class' => 'btn-primary btn-sm ',
                    ],
                    'icon' => 'fa fa-paste',
            ]);
        }

        if ($options['has_copy']) {
            $builder->add('copy', SubmitEmsType::class, [
                    'attr' => [
                            'class' => 'btn-primary btn-sm ',
                    ],
                    'icon' => 'fa fa-copy',
            ]);
        }

        if (null !== $revision && $revision->getDraft()) {
            $builder->add('publish', SubmitEmsType::class, [
                'attr' => [
                        'class' => 'btn-primary btn-sm ',
                ],
                'icon' => 'glyphicon glyphicon-open',
                'label' => 'Finalize draft',
            ]);
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
