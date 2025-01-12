<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CommonBundle\Helper\EmsFields;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<mixed>
 */
class FileType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('sha1', HiddenType::class, [
            'attr' => [
                'class' => 'sha1',
            ],
            'required' => $options['required'],
        ])
        ->add(EmsFields::CONTENT_IMAGE_RESIZED_HASH_FIELD, HiddenType::class, [
            'attr' => [
                'class' => 'resized-image-hash',
            ],
            'required' => false,
        ])
        ->add('mimetype', TextType::class, [
            'attr' => [
                'class' => 'type',
            ],
            'required' => $options['required'],
        ])
        ->add('filename', TextType::class, [
            'attr' => [
                'class' => 'name',
            ],
            'required' => $options['required'],
        ]);

        if ($options['meta_fields']) {
            $builder->add('_title', TextType::class, [
                'attr' => [
                    'class' => 'title',
                ],
                'required' => false,
            ])
            ->add('_date', TextType::class, [
                'attr' => [
                    'class' => 'date',
                ],
                'required' => false,
            ])
            ->add('_author', TextType::class, [
                'attr' => [
                    'class' => 'author',
                ],
                'required' => false,
            ])
            ->add('_language', TextType::class, [
                'attr' => [
                    'class' => 'language',
                ],
                'required' => false,
            ])
            ->add('_content', TextareaType::class, [
                'attr' => [
                    'class' => 'content',
                    'rows' => 6,
                ],
                'required' => false,
            ]);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'meta_fields' => true,
        ]);
    }

    /**
     * @param FormInterface<mixed> $form
     * @param array<string, mixed> $options
     */
    #[\Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars['meta_fields'] = $options['meta_fields'];
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'filetype';
    }
}
