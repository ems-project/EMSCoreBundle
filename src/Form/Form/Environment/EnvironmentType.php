<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form\Environment;

use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\Field\ColorPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\ObjectPickerType;
use EMS\CoreBundle\Form\Field\RolePickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Form\TranslationsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class EnvironmentType extends AbstractType
{
    public function __construct(
        private readonly ?string $circlesObject,
    ) {
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', IconTextType::class, [
                'col' => 4,
                'icon' => 'fa fa-tag',
                'label' => t('field.name', [], 'emsco-core'),
                'help' => t('message.environment_edit_notice_rename', [], 'emsco-core'),
            ])
            ->add('label', IconTextType::class, [
                'col' => 4,
                'required' => false,
                'icon' => 'fa fa-header',
                'label' => t('field.label', [], 'emsco-core'),
            ])
            ->add('color', ColorPickerType::class, [
                'col' => 4,
                'required' => false,
                'label' => t('field.color', [], 'emsco-core'),
            ]);

        if (false === $options['create']) {
            $builder
                ->add('labelTranslations', TranslationsType::class, [
                    'label' => t('field.label_translations', [], 'emsco-core'),
                    'required' => false,
                ])
                ->add('description', TextareaType::class, [
                    'col' => 4,
                    'required' => false,
                    'label' => t('field.description', [], 'emsco-core'),
                ])
                ->add('baseUrl', TextType::class, [
                    'col' => 6,
                    'required' => false,
                    'label' => t('field.base_url', [], 'emsco-core'),
                ])
                ->add('inDefaultSearch', CheckboxType::class, [
                    'required' => false,
                    'label' => t('option.default_search', [], 'emsco-core'),
                ])
                ->add('updateReferrers', CheckboxType::class, [
                    'required' => false,
                    'label' => t('option.update_referrers', [], 'emsco-core'),
                ])
                ->add('templatePublication', CodeEditorType::class, [
                    'required' => false,
                    'min-lines' => 10,
                    'label' => t('field.template_publication', [], 'emsco-core'),
                ])
                ->add('rolePublish', RolePickerType::class, [
                    'label' => t('field.role_publish', [], 'emsco-core'),
                    'translation_domain' => 'emsco-core',
                    'required' => false,
                ]);

            if ($this->circlesObject) {
                $builder->add('circles', ObjectPickerType::class, [
                    'required' => false,
                    'type' => $this->circlesObject,
                    'multiple' => true,
                    'label' => t('field.circles', [], 'emsco-core'),
                ]);
            }
        }

        $builder->add('save', SubmitEmsType::class, [
            'attr' => ['data-testid' => 'btn-action-save'],
            'label' => $options['create'] ? t('action.create', [], 'emsco-core') : t('action.update', [], 'emsco-core'),
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'create' => false,
            'data_class' => Environment::class,
        ]);
    }
}
