<?php /** @noinspection ALL */

namespace Tests\Classes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Enicore\Rivena\Classes\Web;
use Tests\Support\TestConfig;

#[CoversClass(\Enicore\Rivena\Classes\Web::class)]
class WebTest extends TestCase
{
    private array $server;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_HOST'], $_SERVER['SERVER_NAME'], $_SERVER['SERVER_PORT'],
            $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTP_X_FORWARDED_HOST'], $_SERVER['HTTP_X_FORWARDED_PORT']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
    }

    public function testGetUrl(): void
    {
        TestConfig::load(['site_url' => 'https://example.com/']);
        $this->assertSame('https://example.com/', Web::getUrl());
        $this->assertSame('https://example.com/path', Web::getUrl('/path'));
        $this->assertSame('https://example.com/path', Web::getUrl('path'));

        // not the request's host, which the client sends
        $_SERVER['HTTP_HOST'] = 'evil.com';
        $_SERVER['HTTP_X_FORWARDED_HOST'] = 'evil.com';
        TestConfig::load(['site_url' => 'https://example.com:8443']);
        $this->assertSame('https://example.com:8443/path', Web::getUrl('path'));
    }

    public function testCreatePermalink(): void
    {
        $this->assertSame('hello-world', Web::createPermalink('Hello World!'));
        $this->assertSame('test-url', Web::createPermalink('Test URL?'));
        $this->assertFalse(Web::createPermalink('   '));
        $this->assertSame('0', Web::createPermalink('0'));
        // no dash left at the ends, nothing left is false
        $this->assertSame('emoji', Web::createPermalink('emoji 😀'));
        $this->assertSame('a-b', Web::createPermalink(' -- a _ / b ?! '));
        $this->assertSame('cafe-uber', Web::createPermalink('Café Über'));
        $this->assertFalse(Web::createPermalink('😀 !?'));
    }

    public function testGetBrowserLanguage(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9,fr;q=0.8';
        $this->assertSame('en-us', Web::getBrowserLanguage());

        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = '';
        $this->assertSame('en', Web::getBrowserLanguage());
    }

    public function testValidateEmail(): void
    {
        $this->assertTrue(Web::validateEmail('test@example.com', false));
        $this->assertFalse(Web::validateEmail('invalid-email', false));
        $this->assertTrue(Web::validateEmail('test@example.com')); // Assuming DNS exists for example.com
    }

    public function testGetBrowserLanguageQuality(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr;q=0.5,DE;q=0.9,en;q=0.1';
        $this->assertSame('de', Web::getBrowserLanguage());

        // with spaces after the commas
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr;q=0.5, de;q=0.8';
        $this->assertSame('de', Web::getBrowserLanguage());

        // spaces around ; and =, the first of equal ones, q=0 (not acceptable) never
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr ; q = 0.5, zh-Hant-TW;q=0.9, it;q=0.9, en;q=0';
        $this->assertSame('zh-hant-tw', Web::getBrowserLanguage());
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en;q=0';
        $this->assertSame('en', Web::getBrowserLanguage());
    }

    public function testGetBrowserLanguageInvalid(): void
    {
        // invalid entries are skipped
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = '<script>, de;q=0.5, *';
        $this->assertSame('de', Web::getBrowserLanguage());

        foreach ([';;;', '<script>', '*', str_repeat('a', 10000), 'en;q=2', 'en-'] as $header) {
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $header;
            $this->assertSame('en', Web::getBrowserLanguage(), $header);
        }

        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $this->assertSame('en', Web::getBrowserLanguage());
    }

    public function testVersionedUrl(): void
    {
        // the asset paths are relative to the directory of the running script
        $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../Fixtures/index.php';
        $version = md5((string)filemtime(__DIR__ . '/../Fixtures/images/icon.ico'));
        $this->assertSame("/images/icon.ico?v=$version", Web::versionedUrl('/images/icon.ico'));
        $this->assertSame("images/icon.ico?v=$version", Web::versionedUrl('images/icon.ico'));
    }

    public function testAssetsVersion(): void
    {
        $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../Fixtures/index.php';
        $js = __DIR__ . '/../Fixtures/js/app.js';
        $version = Web::assetsVersion();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $version);

        // it changes with the scripts (and the styles)
        touch($js, filemtime($js) + 10);
        clearstatcache();
        $this->assertNotSame($version, Web::assetsVersion());
    }

    public function testTags(): void
    {
        $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../Fixtures/index.php';
        $url = '/images/icon.ico?v=' . md5((string)filemtime(__DIR__ . '/../Fixtures/images/icon.ico'));

        $this->assertSame("<script src='$url' defer></script>",
            Web::scriptTag('/images/icon.ico', ['defer' => true, 'async' => false, 'nonce' => null]));
        $this->assertSame("<link rel='stylesheet' href='$url' media='print'>",
            Web::styleTag('/images/icon.ico', ['media' => 'print']));
        $this->assertSame("<link rel='icon' href='$url' sizes='32'>", Web::iconTag('/images/icon.ico', ['sizes' => 32]));
        $this->assertSame("<img src='$url' alt='&#039;&gt;&lt;script&gt;'>",
            Web::imageTag('/images/icon.ico', ['alt' => "'><script>"]));

        // the path is escaped too (a missing file: filemtime() warns)
        $this->assertSame("<img src='x&#039; onerror=&#039;a?v=" . md5('') . "'>", @Web::imageTag("x' onerror='a"));
    }
}
