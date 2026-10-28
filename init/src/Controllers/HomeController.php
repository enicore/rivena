<?php
namespace App\Controllers;

use Enicore\Rivena\Classes\Request;
use Enicore\Rivena\Classes\Response;
use Enicore\Rivena\Controllers\Controller;

/**
 * The home page; its view is src/views/home.php.
 */
class HomeController extends Controller
{
    /**
     * Route that sends the home page.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function page(Request $request, Response $response): Response
    {
        return $this->sendPage($request, $response, 'home', [], 'Home');
    }
}
