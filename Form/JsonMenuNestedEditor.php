<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form;

use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;

/**
 * Dto object for passing the nested to forms to the view layer
 */
final class JsonMenuNestedEditor
{
    /** @var FieldType */
    private $fieldType;
    /** @var FormFactoryInterface */
    private $formFactory;

    public function __construct(FieldType $fieldType, FormFactoryInterface $formFactory)
    {
        $this->fieldType = $fieldType;
        $this->formFactory = $formFactory;
    }

    public function name(): string
    {
        return $this->fieldType->getName();
    }

    public function getNodes(): array
    {
        $nodeTypes = [];

        foreach ($this->fieldType->getJsonMenuNestedNodeChildren() as $node) {
            $nodeTypes[$node->getName()] = [
                'name' => $node->getName(),
                'label' => $node->getDisplayOption('label', $node->getName()),
                'icon' => $node->getDisplayOption('icon', null),
                'formName' => $this->createNodeFormName($node),
            ];
        }

        return $nodeTypes;
    }

    /**
     * @return iterable|FormView[]
     */
    public function getNodeForms(): iterable
    {
        foreach ($this->fieldType->getJsonMenuNestedNodeChildren() as $node) {
            $formName = $this->createNodeFormName($node);
            $form = $this->formFactory->createNamed($formName, FormType::class, [], [
                'csrf_protection' => false,
            ]);

            $form->add('label', TextType::class, ['mapped' => false]);

            foreach ($node->getChildren() as $nodeChild) {
                $form->add($nodeChild->getName(), $nodeChild->getType(), array_merge([
                    'metadata' => $nodeChild,
                    'mapped' => false
                ], $nodeChild->getDisplayOptions()));
            }

            $formView = $form->createView();
            $formView->vars['structure'] = $this->createStructure($node, $formName, []);

            yield $formView;
        }
    }

    private function createNodeFormName(FieldType $node): string
    {
        return sprintf('form_%s_%s', $this->fieldType->getName(), $node->getName());
    }

    private function createStructure(FieldType $fieldType, string $formName, array $path)
    {
        $out = [];

        foreach ($fieldType->getChildren() as $child) {
            /** @var FieldType $child */
            if ($child->getDeleted()) {
                continue;
            }
            $type = $child->getType();

            if ($type::isContainer() && !$type::isNested()) {
                $out = array_merge_recursive($out, $this->createStructure($child, $formName, array_merge($path, [$child->getName()])));
            } else {
                $out[$child->getName()] = $this->createStructure($child, $formName, array_merge($path, [$child->getName()]));
            }
        }

        if (\count($out) === 0) {
            return sprintf('%s[%s]', $formName, implode('][', $path));
        }

        return $out;
    }
}