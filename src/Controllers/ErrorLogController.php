<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\Controllers;

use Enicore\Rivena\Classes\Request;
use Enicore\Rivena\Classes\Response;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * The admin Error Log page (ErrorLog) and its table.
 *
 * @package Enicore\Rivena
 */
class ErrorLogController extends AdminController
{
    /**
     * Route that sends the Error Log page, with the table's page, sort and search from the URL.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function page(Request $request, Response $response): Response
    {
        return $this->sendPage($request, $response, 'admin/error-log',
            ['table' => $this->table($request->getQueryParams())], 'Error Log');
    }

    /**
     * Route that returns a page of the error log table as JSON.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response): Response
    {
        return $response->sendSuccess($this->table($request->all()));
    }

    /**
     * Returns a page of the error log for the table, the last seen first.
     *
     * @param array $params
     * @return array
     */
    protected function table(array $params): array
    {
        $current = $this->currentUser();

        return $this->dataTable(DB::table('error_log'), $params, ['last_at', 'level', 'count'], ['message', 'path'],
            fn($error) => [
                'id' => $error->id,
                'time' => $current->formatTime($error->last_at),
                'level' => $error->level,
                'message' => $error->message,
                'path' => $error->path,
                'count' => $error->count,
            ], 'desc');
    }
}
