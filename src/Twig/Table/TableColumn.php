<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig\Table;

final class TableColumn
{
    /**
     * @var string
     */
    private $titleKey;
    /**
     * @var string
     */
    private $attribute;
    /**
     * @var array<mixed, string>
     */
    private $valueToIconMapping;

    /**
     * @param array<mixed, string> $valueToIconMapping
     */
    public function __construct(string $titleKey, string $attribute, array $valueToIconMapping = [])
    {
        $this->titleKey = $titleKey;
        $this->attribute = $attribute;
        $this->valueToIconMapping = $valueToIconMapping;
    }

    public function getTitleKey(): string
    {
        return $this->titleKey;
    }

    public function getAttribute(): string
    {
        return $this->attribute;
    }

    /**
     * @return array<mixed, string>
     */
    public function getValueToIconMapping(): array
    {
        return $this->valueToIconMapping;
    }
}
