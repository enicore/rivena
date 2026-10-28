<?php
namespace Tests\Functions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Enicore\Rivena\Classes\Session;
use Tests\Support\TestConfig;

/**
 * Tests the global helper functions in src/Functions/web.php.
 */
#[CoversClass(\Enicore\Rivena\Classes\Web::class)]
class WebFunctionsTest extends TestCase
{
    private string $scriptFileName;
    private string $version;

    protected function setUp(): void
    {
        // the asset paths are relative to the directory of the running script
        $this->scriptFileName = $_SERVER['SCRIPT_FILENAME'];
        $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../Fixtures/index.php';
        $this->version = '?v=' . md5((string)filemtime(__DIR__ . '/../Fixtures/images/icon.ico'));
    }

    protected function tearDown(): void
    {
        $_SERVER['SCRIPT_FILENAME'] = $this->scriptFileName;
    }

    public function testFunctions(): void
    {
        $this->assertSame("/images/icon.ico$this->version", w_url('/images/icon.ico'));
        $this->assertSame("<script src='images/icon.ico$this->version' defer></script>",
            w_script('images/icon.ico', ['defer' => true]));
        $this->assertSame("<link rel='stylesheet' href='/images/icon.ico$this->version'>", w_style('/images/icon.ico'));
        $this->assertSame("<link rel='icon' href='/images/icon.ico$this->version' type='image/png'>",
            w_icon('/images/icon.ico', ['type' => 'image/png']));
        $this->assertSame("<img src='/images/icon.ico$this->version' alt='A &amp; B'>",
            w_image('/images/icon.ico', ['alt' => 'A & B']));
    }

    public function testHtmlClass(): void
    {
        $this->assertSame('dark', w_html_class());

        $_COOKIE = ['theme' => 'light', 'sidebar-collapsed' => '1'];
        $this->assertSame('light sidebar-collapsed', w_html_class());

        // only the known themes
        $_COOKIE = ['theme' => '"><script>'];
        $this->assertSame('dark', w_html_class());
        $_COOKIE = [];
    }

    public function testSiteTitle(): void
    {
        TestConfig::load(['site_name' => 'Site & Co']);
        $this->assertSame('Users - Site &amp; Co', w_site_title('Users'));
        $this->assertSame('Site &amp; Co', w_site_title());
    }

    public function testCsrf(): void
    {
        // no session, no token
        session_status() === PHP_SESSION_ACTIVE && session_destroy();
        $this->assertSame('', w_csrf());

        $token = Session::getCsrfToken();
        $this->assertSame($token, w_csrf());
        session_destroy();
        $_SESSION = [];
    }

    public function testAdminPath(): void
    {
        TestConfig::load();
        $this->assertSame('/usr', w_user_path());
        TestConfig::load(['user_path' => '/a&b']);
        $this->assertSame('/a&amp;b', w_user_path());
    }

    public function testSpaData(): void
    {
        $this->assertSame('<script type="application/json" id="spa-page">{"title":"\u003C\\/script\u003E"}</script>',
            w_spa_data(['title' => '</script>']));
        $this->assertSame('', w_spa_data(null));
        // invalid UTF-8 (e.g. ?search=%ff) is replaced, not an empty script
        $this->assertSame('<script type="application/json" id="spa-page">{"title":"a\\ufffd"}</script>',
            w_spa_data(['title' => "a\xff"]));
    }
}
