<?php

namespace EMS\CoreBundle\Twig;

use EMS\CoreBundle\Core\Table\TableInterface;
use Twig\Environment;
use Twig\Extension\RuntimeExtensionInterface;

class TableRuntime implements RuntimeExtensionInterface
{
    /** @var Environment */
    private $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function render(TableInterface $table): string
    {
        return $this->twig->load($table->getTemplate())->renderBlock('table', ['table' => $table]);
    }
}