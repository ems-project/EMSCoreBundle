<?php

namespace EMS\CoreBundle\Form\Form;

use Symfony\Component\Form\ChoiceList\Loader\AbstractChoiceLoader;

class AnchorChoiceLoader extends AbstractChoiceLoader
{
    /**
     * @param string[] $choices
     */
    public function __construct(private array $choices)
    {
    }

    public function addAnchor(string $anchor): void
    {
        if (\in_array($anchor, $this->choices)) {
            return;
        }
        $label = $anchor;
        if (\str_starts_with($label, '#')) {
            $label = \substr($label, 1);
        }
        $this->choices = \array_merge([$label => $anchor], $this->choices);
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function loadChoices(): iterable
    {
        return $this->choices;
    }
}
