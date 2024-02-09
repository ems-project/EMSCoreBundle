<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component;

use EMS\CoreBundle\Core\UI\Modal\Modal;

class ComponentModal
{
    public Modal $modal;

    public function __construct(
        public readonly AbstractComponentTemplate $template,
        private readonly string $modalName
    ) {
        $this->modal = new Modal();
    }

    public function render(): Modal
    {
        foreach (['title', 'body', 'footer'] as $block) {
            $modalBlock = $this->modalName.'_'.$block;
            if ($this->template->hasBlock($modalBlock)) {
                $this->modal->{$block} = $this->template->block($modalBlock);
            }
        }

        return $this->modal;
    }
}
