<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

final class TemplateBlockTableColumn extends TemplateTableColumn
{
    public function __construct(string $label, string $blockName, string $template)
    {
        $options['label'] = $label;
        $options['template'] = \vsprintf("{{ block('%s', '%s') }}", [
            $blockName, $template,
        ]);
        parent::__construct($options);
    }
}
