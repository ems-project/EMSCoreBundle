<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity\Form;

class RebuildIndex
{
    private ?string $option = null;

    public function setOption(string $option): RebuildIndex
    {
        $this->option = $option;

        return $this;
    }

    public function getOption(): ?string
    {
        return $this->option;
    }
}
