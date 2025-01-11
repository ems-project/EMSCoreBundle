<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\Form\LoadLinkModalEntity;
use EMS\CoreBundle\Form\Field\FileType;
use EMS\CoreBundle\Form\Field\ObjectPickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Routes;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraints\Email;

class LoadLinkModalType extends AbstractType
{
    public const LINK_TYPE_URL = 'url';
    public const LINK_TYPE_INTERNAL = 'internal';
    public const LINK_TYPE_FILE = 'file';
    public const LINK_TYPE_MAILTO = 'mailto';
    public const LINK_TYPE_ANCHOR = 'anchor';
    public const FIELD_LINK_TYPE = 'linkType';
    public const FIELD_HREF = 'href';
    public const FIELD_DATA_LINK = 'dataLink';
    public const FIELD_MAILTO = 'mailto';
    public const FIELD_SUBJECT = 'subject';
    public const FIELD_BODY = 'body';
    public const FIELD_FILE = 'file';
    public const FIELD_ANCHOR = 'anchor';
    public const FIELD_TARGET_BLANK = 'targetBlank';
    public const FIELD_SUBMIT = 'submit';
    public const WITH_TARGET_BLANK_FIELD = 'with_target_blank_field';
    public const ANCHOR_TARGETS = 'anchor_targets';
    private AnchorChoiceLoader $anchorLoader;

    public function __construct(private readonly RouterInterface $router)
    {
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->anchorLoader = new AnchorChoiceLoader($options[self::ANCHOR_TARGETS]);
        $builder
            ->add(self::FIELD_LINK_TYPE, ChoiceType::class, [
                'label' => 'link_modal.field.link_type',
                'required' => true,
                'expanded' => true,
                'choices' => [
                    'link_modal.link_type.url' => self::LINK_TYPE_URL,
                    'link_modal.link_type.internal' => self::LINK_TYPE_INTERNAL,
                    'link_modal.link_type.file' => self::LINK_TYPE_FILE,
                    'link_modal.link_type.mailto' => self::LINK_TYPE_MAILTO,
                    'link_modal.link_type.anchor' => self::LINK_TYPE_ANCHOR,
                ],
            ])
            ->add(self::FIELD_HREF, TextType::class, [
                'label' => 'link_modal.field.href',
                'required' => false,
                'row_attr' => [
                    'data-show-hide' => 'show',
                    'data-all-any' => 'any',
                    'data-rules' => Json::encode([[
                        'field' => \sprintf('[%s]', self::FIELD_LINK_TYPE),
                        'condition' => 'is',
                        'value' => self::LINK_TYPE_URL,
                    ]]),
                ],
            ])
            ->add(self::FIELD_DATA_LINK, ObjectPickerType::class, [
                'label' => 'link_modal.field.data_link',
                'required' => false,
                'multiple' => false,
                'row_attr' => [
                    'data-show-hide' => 'show',
                    'data-all-any' => 'any',
                    'data-rules' => Json::encode([[
                        'field' => \sprintf('[%s]', self::FIELD_LINK_TYPE),
                        'condition' => 'is',
                        'value' => self::LINK_TYPE_INTERNAL,
                    ]]),
                ],
            ])
            ->add(self::FIELD_MAILTO, EmailType::class, [
                'label' => 'link_modal.field.mailto',
                'required' => false,
                'row_attr' => [
                    'data-show-hide' => 'show',
                    'data-all-any' => 'any',
                    'data-rules' => Json::encode([[
                        'field' => \sprintf('[%s]', self::FIELD_LINK_TYPE),
                        'condition' => 'is',
                        'value' => self::LINK_TYPE_MAILTO,
                    ]]),
                ],
                'constraints' => [
                    new Email(),
                ],
            ])
            ->add(self::FIELD_SUBJECT, TextType::class, [
                'label' => 'link_modal.field.subject',
                'required' => false,
                'row_attr' => [
                    'data-show-hide' => 'show',
                    'data-all-any' => 'any',
                    'data-rules' => Json::encode([[
                        'field' => \sprintf('[%s]', self::FIELD_LINK_TYPE),
                        'condition' => 'is',
                        'value' => self::LINK_TYPE_MAILTO,
                    ]]),
                ],
            ])
            ->add(self::FIELD_BODY, TextareaType::class, [
                'label' => 'link_modal.field.body',
                'required' => false,
                'row_attr' => [
                    'data-show-hide' => 'show',
                    'data-all-any' => 'any',
                    'data-rules' => Json::encode([[
                        'field' => \sprintf('[%s]', self::FIELD_LINK_TYPE),
                        'condition' => 'is',
                        'value' => self::LINK_TYPE_MAILTO,
                    ]]),
                ],
            ])
            ->add(self::FIELD_FILE, FileType::class, [
                'label' => 'link_modal.field.file',
                'required' => false,
                'meta_fields' => false,
                'row_attr' => [
                    'data-show-hide' => 'show',
                    'data-all-any' => 'any',
                    'data-rules' => Json::encode([[
                        'field' => \sprintf('[%s]', self::FIELD_LINK_TYPE),
                        'condition' => 'is',
                        'value' => self::LINK_TYPE_FILE,
                    ]]),
                ],
            ])
            ->add(self::FIELD_ANCHOR, ChoiceType::class, [
                'label' => 'link_modal.field.anchor',
                'attr' => ['data-tags' => true, 'class' => 'select2'],
                'choice_loader' => $this->anchorLoader,
                'multiple' => false,
                'choice_translation_domain' => false,
                'required' => false,
                'row_attr' => [
                    'data-show-hide' => 'show',
                    'data-all-any' => 'any',
                    'data-rules' => Json::encode([[
                        'field' => \sprintf('[%s]', self::FIELD_LINK_TYPE),
                        'condition' => 'is',
                        'value' => self::LINK_TYPE_ANCHOR,
                    ]]),
                ],
            ]);
        $builder->get(self::FIELD_ANCHOR)->resetViewTransformers();

        if (true === ($options[self::WITH_TARGET_BLANK_FIELD] ?? false)) {
            $builder->add(self::FIELD_TARGET_BLANK, CheckboxType::class, [
                'label' => 'link_modal.field.target_blank',
                'required' => false,
                'row_attr' => [
                    'data-show-hide' => 'hide',
                    'data-all-any' => 'any',
                    'data-rules' => Json::encode([[
                        'field' => \sprintf('[%s]', self::FIELD_LINK_TYPE),
                        'condition' => 'is',
                        'value' => self::LINK_TYPE_MAILTO,
                    ]]),
                ],
            ]);
        }

        $builder->add(self::FIELD_SUBMIT, SubmitEmsType::class, [
            'label' => 'link_modal.field.submit',
            'attr' => [
                'class' => 'btn btn-primary',
                'data-ajax-save-url' => $this->router->generate(Routes::WYSIWYG_MODAL_LOAD_LINK, [
                    'anchorTargets' => Json::encode($options[self::ANCHOR_TARGETS]),
                ]),
            ],
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                self::WITH_TARGET_BLANK_FIELD => false,
                self::ANCHOR_TARGETS => [],
                'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
                'attr' => [
                    'class' => 'dynamic-form',
                    'data-ajax-save-url' => $this->router->generate(Routes::WYSIWYG_MODAL_LOAD_LINK),
                ],
            ]);
        parent::configureOptions($resolver);
    }

    #[\Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $data = $options['data'] ?? null;
        if ($data instanceof LoadLinkModalEntity and null !== $anchor = $data->getAnchor()) {
            $this->anchorLoader->addAnchor($anchor);
        }
        parent::buildView($view, $form, $options);
    }
}
