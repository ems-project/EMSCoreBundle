<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use EMS\CoreBundle\Core\Form\FormManager;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\Form;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\Form\FieldHolderType;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class FormRuntime
{
    public function __construct(
        protected FormManager $formManager,
        protected DataService $dataService,
        protected RevisionService $revisionService,
        protected FormFactoryInterface $formFactory,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * @param mixed[] $rawData
     *
     * @return ?FormInterface<mixed>
     */
    public function getFormByName(string $name, array $rawData = []): ?FormInterface
    {
        $formEntity = $this->formManager->getByItemName($name);
        if (null === $formEntity) {
            return null;
        }

        if (!$formEntity instanceof Form) {
            throw new \RuntimeException('Unexpected non-Form entity');
        }
        $fakeContentType = new ContentType();
        $fakeContentType->setFieldType($formEntity->getFieldType());
        $fakeRevision = new Revision();
        $fakeRevision->setContentType($fakeContentType);
        $fakeRevision->setRawData($rawData);
        $form = $this->revisionService->createRevisionForm($fakeRevision, true);

        return $form->get('data');
    }

    /**
     * @param FormInterface<mixed> $form
     */
    public function getDataField(FormInterface $form): DataField
    {
        return $this->dataService->getDataFieldsStructure($form);
    }

    /**
     * @param mixed[] $data
     * @param mixed[] $options
     *
     * @return FormInterface<mixed>
     */
    public function handleForm(string $name, array $data = [], $options = []): FormInterface
    {
        $options = \array_merge($options, ['form_name' => $name]);
        $form = $this->formFactory->create(FieldHolderType::class, [$name => $data], $options);
        $form->handleRequest($this->requestStack->getCurrentRequest());

        return $form;
    }
}
