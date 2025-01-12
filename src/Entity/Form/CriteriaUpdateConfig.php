<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity\Form;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\View;
use Psr\Log\LoggerInterface;

/**
 * RebuildIndex.
 */
class CriteriaUpdateConfig
{
    private ?string $columnCriteria = null;
    private ?string $rowCriteria = null;
    private ?DataField $category = null;

    /** @var array<mixed> */
    private array $criterion = [];

    public function __construct(View $view, private readonly LoggerInterface $logger)
    {
        $contentType = $view->getContentType();

        $rootFieldType = $contentType->getFieldType();

        if (!empty($view->getOptions()['categoryFieldPath']) && $categoryField = $rootFieldType->getChildByPath($view->getOptions()['categoryFieldPath'])) {
            $dataField = new DataField();
            $dataField->setFieldType($categoryField);
            $this->setCategory($dataField);
        }

        $criteriaField = $rootFieldType;

        if ('internal' == $view->getOptions()['criteriaMode']) {
            $criteriaField = $rootFieldType->get('ems_'.$view->getOptions()['criteriaField']);
        } elseif ('another' == $view->getOptions()['criteriaMode']) {
        } else {
            throw new \Exception('Should never happen');
        }

        $fieldPaths = \preg_split('/\\r\\n|\\r|\\n/', (string) $view->getOptions()['criteriaFieldPaths']);

        if (\is_array($fieldPaths)) {
            foreach ($fieldPaths as $path) {
                $child = $criteriaField->getChildByPath($path);
                if ($child) {
                    $dataField = new DataField();
                    $dataField->setFieldType($child);
                    $this->criterion[$child->getName()] = $dataField;
                } else {
                    $this->logger->warning('log.view.criteria.field_not_found', [
                        'field_path' => $path,
                    ]);
                }
            }
        }
    }

    public function setColumnCriteria(?string $columnCriteria): self
    {
        $this->columnCriteria = $columnCriteria;

        return $this;
    }

    public function getColumnCriteria(): ?string
    {
        return $this->columnCriteria;
    }

    public function setRowCriteria(?string $rowCriteria): self
    {
        $this->rowCriteria = $rowCriteria;

        return $this;
    }

    public function getRowCriteria(): ?string
    {
        return $this->rowCriteria;
    }

    public function setCategory(?DataField $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getCategory(): ?DataField
    {
        return $this->category;
    }

    public function addCriterion(DataField $criterion): self
    {
        $this->criterion[$criterion->giveFieldType()->getName()] = $criterion;

        return $this;
    }

    public function removeCriterion(DataField $criterion): void
    {
        if (isset($this->criterion[$criterion->giveFieldType()->getName()])) {
            unset($this->criterion[$criterion->giveFieldType()->getName()]);
        }
    }

    /**
     * @return array<string, DataField>
     */
    public function getCriterion(): array
    {
        return $this->criterion;
    }
}
