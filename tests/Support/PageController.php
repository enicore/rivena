<?php
namespace Tests\Support;

use Enicore\Rivena\Classes\Request;
use Enicore\Rivena\Classes\Response;
use Enicore\Rivena\Controllers\Controller;
use Enicore\Rivena\Exceptions\InputException;

/**
 * A site's controller for SlimAppTest.
 */
class PageController extends Controller
{
    public function home(Request $request, Response $response): Response
    {
        return $this->sendPage($request, $response, 'home', ['title' => 'Welcome'], 'Home');
    }

    public function save(Request $request, Response $response): Response
    {
        try {
            $name = $request->getString('name', 10, allowEmpty: false);
        } catch (InputException $e) {
            return $response->sendError($e->getMessage(), 400);
        }

        return $response->sendSuccess(['name' => $name]);
    }

    public function fail(): Response
    {
        throw new \LogicException('Bug 55');
    }
}
