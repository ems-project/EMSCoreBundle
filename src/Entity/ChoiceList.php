<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\Helpers\Translations\Translation;
use EMS\Helpers\Translations\Translations;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ChoiceList extends JsonDeserializer implements \JsonSerializable, EntityInterface
{
    use CreatedModifiedTrait;

    private UuidInterface $id;
    protected string $name;
    protected string $label;
    /** @var array<string, array<string, string>>|null */
    protected ?array $labelTranslations;
    /** @var array<string, mixed> */
    protected array $options = [];

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->created = new \DateTime();
        $this->modified = new \DateTime();
    }

    #[\Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    #[\Override]
    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getLabel(?UserInterface $user = null): string
    {
        return $this->getLabelTranslation($user)->getLabel();
    }

    public function getLabelTranslation(?UserInterface $user = null): Translation
    {
        if (!$user instanceof User) {
            return new Translation($this->label);
        }

        return Translations::fromArray($this->getLabelTranslations())->getTranslation($user->getLocales(), $this->label);
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function getGroupsCount(): int
    {
        return \is_array($this->options['groups'] ?? null) ? \count($this->options['groups']) : 0;
    }

    public function getChoicesCount(): int
    {
        return \is_array($this->options['choices'] ?? null) ? \count($this->options['choices']) : 0;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    /**
     * @return mixed[][]
     */
    public function getLabelTranslations(): array
    {
        return $this->labelTranslations ?? [];
    }

    /**
     * @param mixed[][]|null $labelTranslations
     */
    public function setLabelTranslations(?array $labelTranslations): void
    {
        $this->labelTranslations = $labelTranslations ?? [];
    }

    #[\Override]
    public function jsonSerialize(): JsonClass
    {
        $json = new JsonClass(\get_object_vars($this), self::class);
        $json->removeProperty('id');
        $json->removeProperty('created');
        $json->removeProperty('modified');

        return $json;
    }

    public static function fromJson(string $json, ?EntityInterface $choiceList = null): ChoiceList
    {
        $meta = JsonClass::fromJsonString($json);
        $choiceList = $meta->jsonDeserialize($choiceList);
        if (!$choiceList instanceof ChoiceList) {
            throw new \Exception(\sprintf('Unexpected object class, got %s', $meta->getClass()));
        }

        return $choiceList;
    }

    /**
     * @param  string[]                                                                                           $values
     * @param  string[]                                                                                           $locales
     * @return array<string, array{label: string, value: string, group: array{name: string, label: string}|null}>
     */
    public function getLabelsAndGroups(array $values = [], array $locales = []): array
    {
        $labelsAndGroups = [];
        $groups = [];
        foreach ($this->options['groups'] ?? [] as $group) {
            $groups[$group['name']] = [
                'name' => $group['name'],
                'label' => Translations::fromSimpleArray($locales, $group['labels'] ?? [], $group['label']),
            ];
        }
        foreach ($this->options['choices'] ?? [] as $choice) {
            $labelsAndGroups[$choice['value']] = [
                'label' => Translations::fromSimpleArray($locales, $choice['labels'] ?? [], $choice['label']),
                'value' => $choice['value'],
                'group' => $choice['group'] ? $groups[$choice['group']] ?? null : null,
            ];
        }
        if ([] !== $values) {
            return \array_filter($labelsAndGroups, fn ($key) => \in_array($key, $values), ARRAY_FILTER_USE_KEY);
        }

        return $labelsAndGroups;
    }
}
