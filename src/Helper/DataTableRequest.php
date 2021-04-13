<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Helper;

use Symfony\Component\HttpFoundation\Request;

final class DataTableRequest
{
    private int $draw;
    private int $from;
    private int $size;
    private ?string $orderField;
    private string $orderDirection;
    private string $searchValue;

    private function __construct(int $draw, int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue)
    {
        $this->draw = $draw;
        $this->from = $from;
        $this->size = $size;
        $this->orderField = $orderField;
        $this->orderDirection = $orderDirection;
        $this->searchValue = $searchValue;
    }

    public static function fromRequest(Request $request): self
    {
        $from = \intval($request->get('start', 0));
        $size = \intval($request->get('length', 10));
        $order = $request->get('order', []);
        if (!\is_array($order)) {
            throw new \RuntimeException('Unexpected non array request parameter');
        }
        $columns = $request->get('columns', []);
        if (!\is_array($columns)) {
            throw new \RuntimeException('Unexpected non array request parameter');
        }
        $orderDirection = \strval($order[0]['dir'] ?? 'asc');
        $orderColumn = \intval($order[0]['column'] ?? 0);
        $orderField = null;
        if (isset($columns[$orderColumn]['name']) && 'true' === $columns[$orderColumn]['orderable'] ?? null) {
            $orderField = \strval($columns[$orderColumn]['name']);
        }
        $search = $request->get('search', []);
        if (!\is_array($search)) {
            throw new \RuntimeException('Unexpected non array request parameter');
        }
        $searchValue = \strval($search['value'] ?? '');

        $draw = \intval($request->get('draw', 0));

        return new DataTableRequest($draw, $from, $size, $orderField, $orderDirection, $searchValue);
    }

    public function getDraw(): int
    {
        return $this->draw;
    }

    public function getFrom(): int
    {
        return $this->from;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getOrderField(): ?string
    {
        return $this->orderField;
    }

    public function getOrderDirection(): string
    {
        return $this->orderDirection;
    }

    public function getSearchValue(): string
    {
        return $this->searchValue;
    }
}
