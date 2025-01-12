<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Form\Field\EditImageType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Routes;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

/**
 * @extends AbstractType<mixed>
 */
class EditImageModalType extends AbstractType
{
    public const FIELD_IMAGE = 'image';
    public const FIELD_SUBMIT = 'submit';

    public function __construct(private readonly RouterInterface $router)
    {
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(self::FIELD_IMAGE, EditImageType::class);
        $builder->add(self::FIELD_SUBMIT, SubmitEmsType::class, [
            'label' => 'edit_image_modal.field.submit',
            'attr' => [
                'class' => 'btn btn-primary',
                'data-ajax-save-url' => $this->router->generate(Routes::WYSIWYG_MODAL_EDIT_IMAGE),
            ],
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
                'attr' => [
                    'class' => 'dynamic-form',
                    'data-ajax-save-url' => $this->router->generate(Routes::WYSIWYG_MODAL_EDIT_IMAGE),
                ],
            ]);
        parent::configureOptions($resolver);
    }
}
