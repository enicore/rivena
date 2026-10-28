<?php
namespace Tests\Classes;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Rivena\App;
use Enicore\Rivena\Classes\Session;
use Enicore\Rivena\Classes\View;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Slim\Factory\AppFactory;

#[CoversClass(\Enicore\Rivena\Classes\View::class)]
class ViewTest extends TestCase
{
    private View $view;

    protected function setUp(): void
    {
        $this->view = new View([__DIR__ . '/../Fixtures/app/src/views/']);
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    public function testViewExists(): void
    {
        $this->assertTrue($this->view->viewExists('home'));
        $this->assertTrue($this->view->viewExists('pages/about'));
        $this->assertFalse($this->view->viewExists('missing'));

        // the path works with or without the trailing slash
        $this->assertTrue((new View([__DIR__ . '/../Fixtures/app/src/views']))->viewExists('home'));
    }

    /**
     * Rivena's public layout has no noindex, so search engines index the public pages (the login pages' layout has
     * it, see AppTest::testAdminRoutes()).
     */
    public function testPublicLayoutIsIndexed(): void
    {
        $html = new View([__DIR__ . '/../../src/views'])->getLayoutHtml('error', ['code' => 404, 'message' => 'x']);

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringNotContainsString('noindex', $html);
    }

    public function testGetViewHtml(): void
    {
        $this->assertSame("<home>A &amp; B</home>\n", $this->view->partial('home', ['title' => 'A & B']));
        $this->assertSame("<about></about>\n", $this->view->partial('/pages/about'));
        // data named like the render's variables reaches the view (they have longer names)
        $this->assertSame("<about>abc</about>\n",
            $this->view->partial('pages/about', ['view' => 'a', 'data' => 'b', 'file' => 'c']));
    }

    public function testGetViewHtmlNotFound(): void
    {
        // and no access outside the view directory: only a-z, 0-9, _, - and / (a NUL between dots made ".." once)
        $names = ['missing', 'Home', '../views/home', '..\\Classes/ViewTest', ".\0./views/home", 'home.php'];
        foreach ($names as $name) {
            try {
                $this->view->partial($name);
                $this->fail($name);
            } catch (RuntimeException $e) {
                $this->assertStringStartsWith('View not found: ', $e->getMessage());
            }
        }
    }

    public function testGetLayoutHtml(): void
    {
        $token = Session::getCsrfToken();
        $html = $this->view->getLayoutHtml('home', ['title' => 'Title']);

        // the data is available in the layout and the view
        $this->assertSame("<layout csrf=\"$token\" title=\"Title\"><home>Title</home>\n</layout>\n", $html);
    }

    public function testGetLayoutHtmlWithoutSession(): void
    {
        // pages outside the session routes don't start a session
        $this->assertSame("<layout csrf=\"\" title=\"\"><home></home>\n</layout>\n", $this->view->getLayoutHtml('home'));
        $this->assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testGetLayoutHtmlOtherLayout(): void
    {
        $token = Session::getCsrfToken();

        $this->assertSame("<admin csrf=\"$token\"><home></home>\n</admin>\n",
            $this->view->getLayoutHtml('home', [], 'admin/layout'));
    }

    public function testViewErrorsDiscardOutput(): void
    {
        $level = ob_get_level();

        try {
            $this->view->partial('home', ['title' => new \stdClass()]);
            $this->fail('No exception for a view error.');
        } catch (\TypeError) {
        }

        // the output buffer of the failed view is closed
        $this->assertSame($level, ob_get_level());
    }

    public function testLoginFormPosts(): void
    {
        // without the script, the browser sends the form itself, and the password mustn't be in the URL; w_route() needs
        // routes (without them, its links are empty), also when no test ran new App() before
        App::$routes = AppFactory::create()->getRouteCollector();
        $view = new View([__DIR__ . '/../../src/views']);
        $html = $view->partial('login', ['action' => '/usr/login', 'passwordResets' => false]);

        $this->assertMatchesRegularExpression('/<form [^>]*method="post"[^>]*action="\/usr\/login"/', $html);
    }

    public function testSeveralDirectories(): void
    {
        // the project's views replace Rivena's; the partials come from either
        $dir = sys_get_temp_dir() . '/rivena_views_' . uniqid();
        mkdir("$dir/project", 0777, true);
        mkdir("$dir/rivena/partials", 0777, true);
        file_put_contents("$dir/project/page.php", "<project><?= \$this->partial('partials/item', ['x' => 1]) ?></project>");
        file_put_contents("$dir/rivena/page.php", '<rivena>');
        file_put_contents("$dir/rivena/other.php", '<other>');
        file_put_contents("$dir/rivena/partials/item.php", '<item><?= $x ?></item>');
        $view = new View(["$dir/project", "$dir/rivena"]);

        $this->assertSame('<project><item>1</item></project>', $view->partial('page'));
        $this->assertSame('<other>', $view->partial('other'));
        exec('rm -rf ' . escapeshellarg($dir));
    }
}
