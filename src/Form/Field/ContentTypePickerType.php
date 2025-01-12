<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Form\DataTransformer\EntityNameModelTransformer;
use EMS\CoreBundle\Service\ContentTypeService;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContentTypePickerType extends ChoiceType
{
    public function __construct(private readonly ContentTypeService $contentTypeService)
    {
        parent::__construct();
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $options['choices'] = $this->contentTypeService->getAll();
        $builder->addModelTransformer(new EntityNameModelTransformer($this->contentTypeService, $options['multiple']));
        parent::buildForm($builder, $options);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'attr' => [
                'class' => 'select2',
            ],
            'choice_label' => fn (ContentType $contentType) => \sprintf('<span class="text-%s"><i class="%s"></i>&nbsp;%s', $contentType->getColor(), $contentType->getIcon() ?? 'fa fa-book', $contentType->getPluralName()),
            'choice_value' => function ($value) {
                if ($value instanceof ContentType) {
                    return $value->getName();
                }

                return $value;
            },
            'multiple' => false,
            'choice_translation_domain' => false,
        ]);
    }
}
