<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\DataTable;

use EMS\CommonBundle\Common\Standard\Json;
use EMS\CommonBundle\Elasticsearch\ElasticaLogger;
use EMS\CoreBundle\Form\Data\ElasticaTable;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableInterface;
use EMS\CoreBundle\Form\Data\TableRowInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\TemplateWrapper;

final class TableRenderer
{
    private Environment $twig;
    private TranslatorInterface $translator;
    private ElasticaLogger $elasticaLogger;

    public function __construct(Environment $twig, TranslatorInterface $translator, ElasticaLogger $elasticaLogger)
    {
        $this->twig = $twig;
        $this->translator = $translator;
        $this->elasticaLogger = $elasticaLogger;
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
        if ($table instanceof ElasticaTable) {
            return $this->buildAllRowsElastica($table);
        }

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
            $rows[] = $this->lineToRow($template, $table, $line);
        }

        return $rows;
    }

    /**
     * @return array<mixed>
     */
    private function buildAllRowsElastica(ElasticaTable $table): array
    {
        $this->elasticaLogger->disable();

        $rows = [];
        $template = $this->twig->createTemplate($table->getRowTemplate());

        foreach ($table->scroll() as $line) {
            $rows[] = $this->lineToRow($template, $table, $line);
        }

        $this->elasticaLogger->enable();

        return $rows;
    }

    /**
     * @return array<mixed>
     */
    private function lineToRow(TemplateWrapper $template, TableInterface $table, TableRowInterface $line): array
    {
        return Json::decode($template->render([
            'table' => $table,
            'line' => $line,
            'export' => true,
        ]));
    }
}
