<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Core\Form\FormManager;
use EMS\CoreBundle\Form\DataTransformer\DataFieldViewTransformer;
use EMS\CoreBundle\Form\DataTransformer\FormModelTransformer;
use EMS\CoreBundle\Service\DataService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class FieldHolderType extends AbstractType
{
    public function __construct(
        private readonly FormManager $formManager,
        protected FormRegistryInterface $formRegistry,
        protected DataService $dataService)
    {
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!\is_string($options['form_name'])) {
            throw new \RuntimeException('The option form_name must be defined');
        }
        $form = $this->formManager->getByName($options['form_name']);
        $builder->add($options['form_name'], $form->getFieldType()->getType(), [
            'metadata' => $form->getFieldType(),
            'label' => false,
            'constraints' => [
                new Callback($this->validate(...)),
            ],
        ]);

        $builder->get($options['form_name'])
            ->addViewTransformer(new DataFieldViewTransformer($form->getFieldType(), $this->formRegistry))
            ->addModelTransformer(new FormModelTransformer($form->getFieldType(), $this->formRegistry));
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['form_name' => null]);
    }

    /**
     * @param mixed[] $data
     */
    public function validate(array $data, ExecutionContextInterface $context): void
    {
        $object = $context->getObject();
        if (!$object instanceof FormInterface) {
            throw new \RuntimeException('Unexpected non FormInterface object');
        }

        $this->dataService->isValid($object, null, $data);
    }
}
