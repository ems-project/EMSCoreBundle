<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use EMS\CoreBundle\Core\ChoiceList\ChoiceListManager;
use EMS\CoreBundle\Core\Form\FormManager;
use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\Form;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Form\Form\FieldHolderType;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

readonly class FormExtension
{
    public function __construct(
        protected FormManager $formManager,
        protected DataService $dataService,
        protected RevisionService $revisionService,
        protected FormFactoryInterface $formFactory,
        private RequestStack $requestStack,
        private ChoiceListManager $choiceListManager,
        private UserManager $userManager,
    ) {
    }

    /**
     * @param mixed[] $rawData
     *
     * @return ?FormInterface<mixed>
     */
    #[AsTwigFunction(name: 'emsco_get_form')]
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
    #[AsTwigFunction(name: 'emsco_get_data_field')]
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
    #[AsTwigFunction(name: 'emsco_form')]
    public function handleForm(string $name, array $data = [], $options = []): FormInterface
    {
        $options = \array_merge($options, ['form_name' => $name]);
        $form = $this->formFactory->create(FieldHolderType::class, [$name => $data], $options);
        $form->handleRequest($this->requestStack->getCurrentRequest());

        return $form;
    }

    /**
     * @param  string[]                                                                                           $values
     * @return array<string, array{label: string, value: string, group: array{name: string, label: string}|null}>
     */
    #[AsTwigFilter(name: 'emsco_choice_list')]
    public function getChoiceList(string $name, array $values = [], ?string $locale = null): array
    {
        if (!$this->choiceListManager->existsByName($name)) {
            return \array_combine($values, \array_map(fn (string $value) => [
                'label' => $value,
                'value' => $value,
                'group' => null,
            ], $values));
        }
        $choicesList = $this->choiceListManager->getByName($name);
        if (null === $locale) {
            $user = $this->userManager->getUser();
            if (!$user instanceof User) {
                throw new \RuntimeException('Unexpected User');
            }
            $locales = $user->getLocales();
        } else {
            $locales = [$locale];
        }

        return $choicesList->getLabelsAndGroups($values, $locales);
    }
}
