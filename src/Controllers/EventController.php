<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\Controllers;

use Enicore\Rivena\Classes\Countries;
use Enicore\Rivena\Classes\Request;
use Enicore\Rivena\Classes\Response;
use Enicore\Rivena\Models\Event;

/**
 * The admin event log page and its table.
 *
 * @package Enicore\Rivena
 */
class EventController extends AdminController
{
    /**
     * Route that sends the event log page, with the table's page, sort and search from the URL.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function page(Request $request, Response $response): Response
    {
        // the table's page, sort and search from the URL (Table in table.js)
        return $this->sendPage($request, $response, 'admin/events',
            ['table' => $this->table($request->getQueryParams())], 'Event Log');
    }

    /**
     * Route that returns a page of the event log table as JSON.
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
     * Returns a page of the event log for the table.
     *
     * @param array $params
     * @return array
     */
    protected function table(array $params): array
    {
        $current = $this->currentUser();

        return $this->dataTable(Event::query(), $params, ['created_at', 'user_email', 'action', 'ip'],
            ['user_email', 'action', 'message', 'ip'], fn($entry) => [
                'id' => $entry->id,
                'time' => $current->formatTime($entry->created_at),
                'user' => (string)$entry->user_email,
                'action' => $entry->action,
                'message' => $entry->message,
                'ip' => (string)$entry->ip,
                'country' => Countries::getName((string)$entry->country),
                'user_agent' => (string)$entry->user_agent,
            ], 'desc');
    }
}
