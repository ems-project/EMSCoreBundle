<?php

namespace EMS\CoreBundle\Entity\Form;

/**
 * RebuildIndex.
 */
class RebuildIndex
{
    private ?string $option = null;

    /**
     * Set the rebuild option.
     *
     * @param string $option
     *
     * @return RebuildIndex
     */
    public function setOption($option)
    {
        $this->option = $option;

        return $this;
    }

    /**
     * Get the selected rebuild option.
     *
     * @return string
     */
    public function getOption()
    {
        if (null === $this->option) {
            throw new \RuntimeException('Unexpected null option');
        }

        return $this->option;
    }
}
