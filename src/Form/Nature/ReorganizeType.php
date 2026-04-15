<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Nature;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\DataField\DataLinkFieldType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Service\ContentTypeService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<mixed>
 */
class ReorganizeType extends AbstractType
{
    public function __construct(private readonly ContentTypeService $contentTypeService)
    {
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('structure', HiddenType::class, [
            'attr' => [
                'class' => 'reorder-items',
            ],
        ])
        ->add('reorder', SubmitEmsType::class, [
            'attr' => [
                'class' => 'btn btn-primary reorder-button',
                'data-testid' => 'btn-action-reorder',
            ],
            'icon' => 'fa fa-reorder',
        ]);

        /** @var View */
        $view = $options['view'];
        if ($view instanceof View) {
            $fieldType = $this->contentTypeService->getChildByPath($view->getContentType()->getFieldType(), $view->getOptions()['field']);

            if ($fieldType instanceof FieldType) {
                $builder->add('addItem', DataLinkFieldType::class, [
                    'metadata' => $fieldType,
                    'label' => 'Add item',
                    'required' => false,
                    'type' => $fieldType->getDisplayOption('type'),
                ]);

                $builder->get('addItem')->addModelTransformer(new CallbackTransformer(
                    fn ($raw) => new DataField(),
                    fn (DataField $tagsAsString) => null
                ))->addViewTransformer(new CallbackTransformer(
                    fn (DataField $tagsAsString) => null,
                    fn ($raw) => new DataField()
                ));
            }
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'view' => null,
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
        ]);
    }
}
