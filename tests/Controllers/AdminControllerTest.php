<?php
namespace Tests\Controllers;

use Tests\Support\TestConfig;
use Enicore\Rivena\Classes\View;
use Enicore\Rivena\Controllers\AdminController;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\Database;

class Item extends Model
{
    public $timestamps = false;
}

#[CoversClass(AdminController::class)]
class AdminControllerTest extends TestCase
{
    private AdminController $controller;

    protected function setUp(): void
    {
        Database::create();
        $this->controller = new class(new View([__DIR__ . '/../Fixtures/app/src/views'])) extends AdminController {
            public function table(mixed ...$args): array
            {
                return $this->dataTable(...$args);
            }
        };

        // 30 items: name01 (size 30) ... name30 (size 1), and a_b%
        for ($i = 1; $i <= 30; $i++) {
            Capsule::table('items')->insert(['name' => sprintf('name%02d', $i), 'size' => 31 - $i]);
        }
        Capsule::table('items')->insert(['name' => 'a_b%', 'size' => 5]);
    }

    private function names(array $table): array
    {
        return array_map(fn($row) => is_array($row) ? $row['name'] : $row->name, $table['rows']);
    }

    public function testDefaults(): void
    {
        $table = $this->controller->table(Item::query(), [], ['name', 'size'], ['name'],
            fn(Item $item) => ['name' => $item->name]);

        $this->assertSame(['total' => 31, 'page' => 1, 'perPage' => 25, 'sort' => 'name', 'dir' => 'asc',
            'search' => ''], array_diff_key($table, ['rows' => 0]));
        $this->assertSame(['a_b%', 'name01', 'name02'], array_slice($this->names($table), 0, 3));
        $this->assertCount(25, $table['rows']);
    }

    public function testRowsPerPage(): void
    {
        TestConfig::load(['table_rows_per_page' => 10]);
        $table = $this->controller->table(Item::query(), [], ['name'], ['name'], fn(Item $item) => $item->name);
        TestConfig::load();

        $this->assertSame(10, $table['perPage']);
        $this->assertCount(10, $table['rows']);
    }

    public function testParams(): void
    {
        $table = $this->controller->table(Item::query(), ['page' => '2', 'sort' => 'size', 'dir' => 'desc', 'search' => ' name '],
            ['name', 'size'], ['name'], fn(Item $item) => ['name' => $item->name]);

        // size desc: name01 (30) ... name30 (1); the second page has the last 5
        $this->assertSame([2, 'size', 'desc', 'name', 30], [$table['page'], $table['sort'], $table['dir'],
            $table['search'], $table['total']]);
        $this->assertSame(['name26', 'name27', 'name28', 'name29', 'name30'], $this->names($table));
    }

    /**
     * The search's % and _ are the characters, not LIKE's wildcards.
     */
    public function testSearchWildcards(): void
    {
        foreach (['_', '%', 'a_b%'] as $search) {
            $table = $this->controller->table(Item::query(), ['search' => $search], ['name'], ['name'],
                fn(Item $item) => ['name' => $item->name]);
            $this->assertSame(['a_b%'], $this->names($table), $search);
        }
    }

    public function testInvalidParamsAreIgnored(): void
    {
        $table = $this->controller->table(Item::query(), ['sort' => 'password', 'dir' => 'sideways'], ['name'], [],
            fn(Item $item) => ['name' => $item->name], 'desc');

        $this->assertSame(['name', 'desc'], [$table['sort'], $table['dir']]);
        $this->assertSame('name30', $this->names($table)[0]);

        // a search that isn't a string (search[]=a)
        $table = $this->controller->table(Item::query(), ['search' => ['a']], ['name'], ['name'],
            fn(Item $item) => ['name' => $item->name]);
        $this->assertSame(['', 31], [$table['search'], $table['total']]);

        // pages that aren't positive integers are the first, past the end the last (31 rows: 2 pages)
        foreach ([[0, 1], ['x', 1], [['a'], 1], [1.5, 1], [3, 2], [PHP_INT_MAX, 2], [1e30, 1]] as [$page, $expected]) {
            $table = $this->controller->table(Item::query(), ['page' => $page], ['name'], [],
                fn(Item $item) => $item->name);
            $this->assertSame($expected, $table['page']);
        }
    }
}
