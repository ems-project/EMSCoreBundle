<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\DependencyInjection\EMSCoreExtension;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\DataTransformer\DataFieldModelTransformer;
use EMS\CoreBundle\Form\DataTransformer\DataFieldViewTransformer;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RevisionType extends AbstractType
{
    private FormRegistryInterface $formRegistry;

    public function __construct(FormRegistryInterface $formRegistry)
    {
        $this->formRegistry = $formRegistry;
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Revision|null $revision */
        $revision = $builder->getData();
        $contentType = $revision ? $revision->giveContentType() : $options['content_type'];

        if (!$contentType instanceof ContentType) {
            throw new \RuntimeException('Missing content type');
        }

        $builder->add('data', $contentType->getFieldType()->getType(), [
                'metadata' => $contentType->getFieldType(),
                'error_bubbling' => false,
                'migration' => $options['migration'],
                'with_warning' => $options['with_warning'],
                'raw_data' => $options['raw_data'],
                'disabled_fields' => $contentType->getDisabledDataFields(),
                'referrer-ems-id' => $revision && $revision->hasOuuid() ? $revision->getEmsId() : null,
        ]);

        if ($revision) {
            if ($revision->getDraft()) {
                $builder->add('save', SubmitEmsType::class, [
                    'label' => 'form.form.revision-type.save-draft-label',
                    'attr' => ['class' => 'btn btn-default btn-sm'],
                    'icon' => 'fa fa-save',
                ]);
            } else {
                $publishedEnvironmentLabels = $revision->getEnvironments()->map(fn (Environment $e) => $e->getLabel());
                if (\count($publishedEnvironmentLabels) > 0) {
                    $builder->add('save', SubmitEmsType::class, [
                        'label' => 'form.form.revision-type.publish-label',
                        'label_translation_parameters' => [
                            '%environment%' => \implode(', ', $publishedEnvironmentLabels->toArray()),
                        ],
                        'attr' => ['class' => 'btn btn-primary btn-sm'],
                        'icon' => 'glyphicon glyphicon-open',
                    ]);
                } else {
                    $builder->add('save', SubmitEmsType::class, [
                        'label' => 'form.form.revision-type.save-label',
                        'attr' => ['class' => 'btn btn-primary btn-sm'],
                        'icon' => 'fa fa-save',
                    ]);
                }
            }
        }

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
                        'required' => false,
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

    public function configureOptions(OptionsResolver $resolver): void
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

    public function getBlockPrefix(): string
    {
        return 'revision';
    }
}
