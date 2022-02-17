<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\DataTable;

use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class TableRenderer
{
    private Environment $twig;
    private TranslatorInterface $translator;

    public function __construct(Environment $twig, TranslatorInterface $translator)
    {
        $this->twig = $twig;
        $this->translator = $translator;
    }

    /**
     * @return string[]
     */
    public function buildHeaders(TableInterface $table): array
    {
        $headers = [];

        foreach ($table->getColumns() as $column) {
            if ($table instanceof EntityTable) {
                $headers[] = $this->translator->trans($column->getTitleKey(), [], 'EMSCoreBundle');
            } else {
                $headers[] = $column->getTitleKey();
            }
        }

        return $headers;
    }

    /**
     * @return array<mixed>
     */
    public function buildAllRows(TableInterface $table): array
    {
        $rows = [];
        $table->setSize(0);

        while ($table->next()) {
            $rows = [...$rows, ...$this->buildRows($table)];
        }

        return $rows;
    }

    /**
     * @return array<mixed>
     */
    public function buildRows(TableInterface $table): array
    {
        $rows = [];
        $template = $this->twig->createTemplate($table->getRowTemplate());

        foreach ($table as $line) {
            $row = \json_decode($template->render([
                'table' => $table,
                'line' => $line,
                'export' => true,
            ]));
            if (!\is_array($row)) {
                throw new \RuntimeException('Unexpected non array object');
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
