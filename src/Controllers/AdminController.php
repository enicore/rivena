<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\Controllers;

use Enicore\Rivena\Classes\Auth;
use Enicore\Rivena\Classes\Config;
use Enicore\Rivena\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Base controller of the admin pages: renders them in the admin layout, and has currentUser() and dataTable(). Extend
 * it for the project's admin pages.
 *
 * @package Enicore\Rivena
 */
class AdminController extends Controller
{
    protected string $layout = 'admin/layout';

    /**
     * Returns the logged-in user.
     *
     * @return User
     */
    protected function currentUser(): User
    {
        return User::findOrFail(Auth::id());
    }

    /**
     * Returns a page of the query (Eloquent's or the query builder's) for Table in table.js, with the client's page,
     * sort (a column of sortable, the first by default) and search (in the searchable columns); map turns each row into
     * what the client gets. The page size is table_rows_per_page (25 by default).
     *
     * @param Builder|QueryBuilder $query
     * @param array $params
     * @param array $sortable
     * @param array $searchable
     * @param callable $map
     * @param string $dir
     * @return array
     */
    protected function dataTable(Builder|QueryBuilder $query, array $params, array $sortable, array $searchable,
                                 callable $map, string $dir = 'asc'): array
    {
        $sort = in_array($params['sort'] ?? null, $sortable, true) ? $params['sort'] : $sortable[0];
        $dir = in_array($params['dir'] ?? null, ['asc', 'desc'], true) ? $params['dir'] : $dir;
        $search = is_string($params['search'] ?? null) ? trim($params['search']) : '';
        $page = filter_var($params['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $perPage = (int)Config::get('table_rows_per_page', 25);

        if ($search !== '') {
            // the search's % and _ are characters, not wildcards (an escape character that SQLite has too)
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
            $query->where(function (Builder|QueryBuilder $query) use ($searchable, $like) {
                foreach ($searchable as $column) {
                    $query->orWhereRaw($query->getGrammar()->wrap($column) . " like ? escape '!'", [$like]);
                }
            });
        }

        $total = (clone $query)->count();
        // at most the last page (a huge one would overflow the offset)
        $page = min($page, max(1, (int)ceil($total / $perPage)));
        // then the ID, so the pages don't overlap
        $rows = $query->orderBy($sort, $dir)->orderBy('id', $dir)->forPage($page, $perPage)->get();

        return ['rows' => $rows->map($map)->all(), 'total' => $total, 'page' => $page, 'perPage' => $perPage,
            'sort' => $sort, 'dir' => $dir, 'search' => $search];
    }
}
