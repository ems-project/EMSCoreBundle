<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\DataTable;

use EMS\CommonBundle\Elasticsearch\ElasticaLogger;
use EMS\CoreBundle\Form\Data\ElasticaTable;
use EMS\CoreBundle\Form\Data\TableInterface;
use EMS\CoreBundle\Form\Data\TableRowInterface;
use EMS\Helpers\Standard\Json;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\TemplateWrapper;

final readonly class TableRenderer
{
    public function __construct(
        private Environment $twig,
        private TranslatorInterface $translator,
        private ElasticaLogger $elasticaLogger,
    ) {
    }

    /**
     * @return string[]
     */
    public function buildHeaders(TableInterface $table): array
    {
        $headers = [];

        foreach ($table->getColumns() as $column) {
            $headers[] = $column->getTitleKey()->trans($this->translator);
        }

        return $headers;
    }

    /**
     * @return array<mixed>
     */
    public function buildAllRows(TableInterface $table, bool $export = false): array
    {
        if ($table instanceof ElasticaTable) {
            return $this->buildAllRowsElastica($table, $export);
        }

        $rows = [];
        $table->setSize(0);

        while ($table->next()) {
            $rows = [...$rows, ...$this->buildRows($table, $export)];
        }

        return $rows;
    }

    /**
     * @return array<mixed>
     */
    public function buildRows(TableInterface $table, bool $export = false): array
    {
        $rows = [];
        $template = $this->twig->createTemplate($table->getRowTemplate());

        foreach ($table as $line) {
            $rows[] = $this->lineToRow($template, $table, $line, $export);
        }

        return $rows;
    }

    /**
     * @return array<mixed>
     */
    private function buildAllRowsElastica(ElasticaTable $table, bool $export = false): array
    {
        $this->elasticaLogger->disable();

        $rows = [];
        $template = $this->twig->createTemplate($table->getRowTemplate());

        foreach ($table->scroll() as $line) {
            $rows[] = $this->lineToRow($template, $table, $line, $export);
        }

        $this->elasticaLogger->enable();

        return $rows;
    }

    /**
     * @return array<mixed>
     */
    private function lineToRow(TemplateWrapper $template, TableInterface $table, TableRowInterface $line, bool $export = false): array
    {
        return Json::decode($template->render([
            'table' => $table,
            'line' => $line,
            'export' => $export,
        ]));
    }
}
