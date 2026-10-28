<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\Controllers;

use Enicore\Rivena\Classes\Request;
use Enicore\Rivena\Classes\Response;

/**
 * The admin dashboard; its view is src/views/admin/dashboard.php.
 *
 * @package Enicore\Rivena
 */
class DashboardController extends AdminController
{
    /**
     * Route that sends the dashboard page.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function page(Request $request, Response $response): Response
    {
        return $this->sendPage($request, $response, 'admin/dashboard', [], 'Dashboard');
    }
}
