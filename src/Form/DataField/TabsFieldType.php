<?php

namespace EMS\CoreBundle\Form\DataField;

use Doctrine\Common\Collections\ArrayCollection;
use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Service\ElasticsearchService;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class TabsFieldType extends DataFieldType
{
    private const LOCALE_PREFERRED_FIRST_DISPLAY_OPTION = 'localePreferredFirst';

    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        FormRegistryInterface $formRegistry,
        ElasticsearchService $elasticsearchService,
        private readonly UserManager $userManager)
    {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
    }

    public function getLabel(): string
    {
        return 'Visual tab container (invisible in Elasticsearch)';
    }

    /**
     * {@inheritDoc}
     */
    public function importData(DataField $dataField, array|string|int|float|bool|null $sourceArray, bool $isMigration): array
    {
        throw new \Exception('This method should never be called');
    }

    /**
     * {@inheritDoc}
     */
    public function getBlockPrefix(): string
    {
        return 'tabsfieldtype';
    }

    public static function getIcon(): string
    {
        return 'fa fa-object-group';
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $builder->getOptions()['metadata'];

        /** @var ArrayCollection<int, FieldType> $children */
        $children = $fieldType->getChildren();

        if ($fieldType->getDisplayBoolOption(self::LOCALE_PREFERRED_FIRST_DISPLAY_OPTION, false)) {
            $userLanguage = $this->userManager->getUserLanguage();
            /** @var \ArrayIterator<int, FieldType> $iterator */
            $iterator = $children->getIterator();
            $iterator->uasort(fn (FieldType $a, FieldType $b) => match (true) {
                $a->getName() === $userLanguage => -1,
                $b->getName() === $userLanguage => 1,
                default => 0
            });
            $children = new ArrayCollection(\iterator_to_array($iterator));
        }

        foreach ($children as $fieldType) {
            $this->buildChildForm($fieldType, $options, $builder);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function buildObjectArray(DataField $data, array &$out): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public static function isContainer(): bool
    {
        /* this kind of compound field may contain children */
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        $optionsForm->get('displayOptions')
            ->add(self::LOCALE_PREFERRED_FIRST_DISPLAY_OPTION, CheckboxType::class, [
                'required' => false,
            ]);

        $optionsForm->remove('mappingOptions');
        $optionsForm->remove('migrationOptions');
        $optionsForm->get('restrictionOptions')->remove('mandatory');
        $optionsForm->get('restrictionOptions')->remove('mandatory_if');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefault(self::LOCALE_PREFERRED_FIRST_DISPLAY_OPTION, false);
    }

    /**
     * {@inheritDoc}
     */
    public static function isVirtual(array $option = []): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public static function getJsonNames(FieldType $current): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function generateMapping(FieldType $current): array
    {
        return [];
    }
}
