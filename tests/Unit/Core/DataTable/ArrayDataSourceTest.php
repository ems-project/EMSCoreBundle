<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Unit\Core\DataTable;

use EMS\CoreBundle\Core\DataTable\ArrayDataSource;
use PHPUnit\Framework\TestCase;

class ArrayDataSourceTest extends TestCase
{
    private readonly ArrayDataSource $sourceArrays;
    private readonly ArrayDataSource $sourceObjects;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->sourceArrays = new ArrayDataSource([
            ['id' => 1, 'color' => 'red'],
            ['id' => 2, 'color' => 'blue'],
            ['id' => 3, 'color' => 'green'],
        ]);
        $this->sourceObjects = new ArrayDataSource([
            (object) ['id' => 1, 'color' => 'red'],
            (object) ['id' => 2, 'color' => 'blue'],
            (object) ['id' => 3, 'color' => 'green'],
        ]);
    }

    public function testCount(): void
    {
        $this->assertEquals(3, $this->sourceArrays->count());
    }

    public function testSearch(): void
    {
        $this->assertEquals(
            [['id' => 1, 'color' => 'red']],
            $this->sourceArrays->search('red')->data
        );
        $this->assertEquals(
            [(object) ['id' => 3, 'color' => 'green']],
            $this->sourceObjects->search('green')->data
        );
    }

    public function testSort(): void
    {
        $this->assertEquals([
            ['id' => 2, 'color' => 'blue'],
            ['id' => 3, 'color' => 'green'],
            ['id' => 1, 'color' => 'red'],
        ], $this->sourceArrays->sort('[color]', 'asc')->data);
        $this->assertEquals([
            ['id' => 3, 'color' => 'green'],
            ['id' => 2, 'color' => 'blue'],
            ['id' => 1, 'color' => 'red'],
        ], $this->sourceArrays->sort('[id]', 'desc')->data);

        $this->assertEquals([
            (object) ['id' => 2, 'color' => 'blue'],
            (object) ['id' => 3, 'color' => 'green'],
            (object) ['id' => 1, 'color' => 'red'],
        ], $this->sourceObjects->sort('color', 'asc')->data);
        $this->assertEquals([
            (object) ['id' => 3, 'color' => 'green'],
            (object) ['id' => 2, 'color' => 'blue'],
            (object) ['id' => 1, 'color' => 'red'],
        ], $this->sourceObjects->sort('id', 'desc')->data);
    }

    public function testSizing(): void
    {
        $this->assertEquals([['id' => 3, 'color' => 'green']], $this->sourceArrays->getData(2, 1));
        $this->assertEquals([(object) ['id' => 2, 'color' => 'blue']], $this->sourceObjects->getData(1, 1));
    }
}
