<?php
namespace Tests\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Rivena\Classes\Request;
use Enicore\Rivena\Classes\Response;
use Enicore\Rivena\Classes\Session;
use Enicore\Rivena\Classes\View;
use Enicore\Rivena\Classes\Web;
use Enicore\Rivena\Controllers\Controller;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

#[CoversClass(\Enicore\Rivena\Controllers\Controller::class)]
class ControllerTest extends TestCase
{
    private Controller $controller;
    private string $token;

    protected function setUp(): void
    {
        $this->controller = $this->controller();
        $this->token = Session::getCsrfToken();
    }

    protected function tearDown(): void
    {
        session_destroy();
        $_SESSION = [];
    }

    /**
     * Returns a controller that exposes the protected methods.
     */
    private function controller(string $layout = 'layout'): Controller
    {
        $controller = new class(new View([__DIR__ . '/../Fixtures/app/src/views'])) extends Controller {
            public function call(string $method, mixed ...$args): ResponseInterface
            {
                return $this->$method(new Response(), ...$args);
            }

            public function page(Request $request, mixed ...$args): ResponseInterface
            {
                return $this->sendPage($request, new Response(), ...$args);
            }

            public function setLayout(string $layout): void
            {
                $this->layout = $layout;
            }
        };
        $controller->setLayout($layout);

        return $controller;
    }

    private function request(bool $json = false): Request
    {
        $request = Request::from((new ServerRequestFactory())->createServerRequest('GET', '/'));
        return $json ? $request->withHeader('Accept', 'application/json') : $request;
    }

    private function layout(string $content, string $title = ''): string
    {
        return "<layout csrf=\"$this->token\" title=\"$title\">$content</layout>\n";
    }

    private function json(ResponseInterface $response): array
    {
        $this->assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        return json_decode((string)$response->getBody(), true);
    }

    public function testSendPage(): void
    {
        $version = Web::assetsVersion();

        // RivenaSpa's request
        $response = $this->controller->page($this->request(true), 'home', ['title' => 'A'], 'Home');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Accept', $response->getHeaderLine('Vary'));
        $this->assertSame(
            ['success' => true, 'data' => ['title' => 'Home', 'data' => ['title' => 'A'], 'version' => $version,
                'html' => "<home>A</home>\n"]],
            $this->json($response)
        );

        // otherwise the whole page
        $response = $this->controller->page($this->request(), 'home', [], 'Home', 201);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame('Accept', $response->getHeaderLine('Vary'));
        $this->assertSame($this->layout("<home>Home</home>\n", 'Home'), (string)$response->getBody());

        // the view gets the title in RivenaSpa's requests too
        $response = $this->controller->page($this->request(true), 'home', [], 'Home');
        $this->assertSame("<home>Home</home>\n", $this->json($response)['data']['html']);

        // the layout gets the page for RivenaSpa
        $response = $this->controller('admin/layout')->page($this->request(), 'home', ['a' => 1], 'Home');
        $page = htmlspecialchars(json_encode(['title' => 'Home', 'data' => ['a' => 1], 'version' => $version]));
        $this->assertSame("<admin csrf=\"$this->token\" page=\"$page\"><home>Home</home>\n</admin>\n",
            (string)$response->getBody());
    }

    public function testNotFoundRoute(): void
    {
        $response = $this->controller->notFoundRoute($this->request(), new Response(), []);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame($this->layout("<error code=\"404\">Page not found</error>\n", 'Page not found'),
            (string)$response->getBody());

        // RivenaSpa shows the error view as the page's content (it shows only an error message for HTTP errors)
        $response = $this->controller->notFoundRoute($this->request(true), new Response(), []);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            ['title' => 'Page not found', 'data' => ['code' => 404, 'message' => 'Page not found'],
                'version' => Web::assetsVersion(), 'html' => "<error code=\"404\">Page not found</error>\n"],
            $this->json($response)['data']
        );
    }

    public function testSendView(): void
    {
        $response = $this->controller->call('sendView', 'home', ['title' => 'Login'], 401);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame("<home>Login</home>\n", (string)$response->getBody());
    }

    public function testSendErrorPage(): void
    {
        $response = $this->controller->call('sendErrorPage', 403, 'Forbidden');
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame($this->layout("<error code=\"403\">Forbidden</error>\n", 'Forbidden'),
            (string)$response->getBody());
    }
}
