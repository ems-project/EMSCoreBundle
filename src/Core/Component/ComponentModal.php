<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component;

use EMS\CoreBundle\Core\UI\Modal\Modal;

class ComponentModal
{
    public Modal $modal;

    private string $blockTitle;
    private string $blockBody;
    private string $blockFooter;

    public function __construct(
        public readonly AbstractComponentTemplate $template,
        private readonly string $modalName,
    ) {
        $this->modal = new Modal();
        $this->blockTitle = $this->modalName.'_title';
        $this->blockBody = $this->modalName.'_body';
        $this->blockFooter = $this->modalName.'_footer';
    }

    public function setBlockTitle(string $blockTitle): self
    {
        $this->blockTitle = $blockTitle;

        return $this;
    }

    public function setBlockBody(string $blockBody): self
    {
        $this->blockBody = $blockBody;

        return $this;
    }

    public function setBlockFooter(string $blockFooter): self
    {
        $this->blockFooter = $blockFooter;

        return $this;
    }

    public function render(): Modal
    {
        $blocks = [
            'title' => $this->blockTitle,
            'body' => $this->blockBody,
            'footer' => $this->blockFooter,
        ];

        foreach ($blocks as $property => $blockName) {
            if ($this->template->hasBlock($blockName)) {
                $this->modal->{$property} = $this->template->block($blockName);
            }
        }

        return $this->modal;
    }
}
