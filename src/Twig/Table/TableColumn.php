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

    public function __construct(string $titleKey, string $attribute)
    {
        $this->titleKey = $titleKey;
        $this->attribute = $attribute;
    }

    public function getTitleKey(): string
    {
        return $this->titleKey;
    }

    public function getAttribute(): string
    {
        return $this->attribute;
    }
}
