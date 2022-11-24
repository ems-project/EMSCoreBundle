<?php

namespace EMS\CoreBundle\Entity\Form;

/**
 * RebuildIndex.
 */
class RebuildIndex
{
    /**
     * @var string
     */
    private $option;

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
        return $this->option;
    }
}
