<?php
namespace Tests\Classes;

use Enicore\Rivena\Classes\Assets;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Builds a project's styles (with the sass command, which must be installed) and scripts.
 */
#[CoversClass(Assets::class)]
class AssetsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/rivena_assets_' . uniqid();
        mkdir("$this->root/src/styles", 0777, true);
        mkdir("$this->root/src/scripts/controllers", 0777, true);
        file_put_contents("$this->root/src/styles/_colors.scss", '$main: #123456;');
        file_put_contents("$this->root/src/styles/app.scss", "@use 'colors';\nbody { a { color: colors.\$main; } }");
        file_put_contents("$this->root/src/scripts/app.js", "class Base { base = 1 }");
        file_put_contents("$this->root/src/scripts/controllers/users.js", "class Users extends Base {}");
        file_put_contents("$this->root/src/scripts/a.js", "const a = 1;");
        $this->setTime(time() - 100);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    // sets the sources' time, so the build is newer
    private function setTime(int $time): void
    {
        exec('find ' . escapeshellarg("$this->root/src") . " -exec touch -d @$time {} +");
    }

    public function testBuild(): void
    {
        $assets = new Assets($this->root);

        $this->assertSame(['public/css/app.css', 'public/js/app.js'], $assets->build());
        $this->assertSame('body a{color:#123456}', trim(file_get_contents("$this->root/public/css/app.css")));
        $this->assertFileDoesNotExist("$this->root/public/css/_colors.css");

        // rivena.js, RivenaSpa, Rivena's admin base classes and controllers, the project's app.js, the others in
        // alphabetical order, Alpine
        $js = file_get_contents("$this->root/public/js/app.js");
        $positions = array_map(fn($text) => strpos($js, $text),
            ['class Rivena', 'rivena_spa', 'class Controller', 'class ConfirmDialog', 'class Table',
                'class EventController', 'class Base{base=1}', 'const a=1', 'class Users extends Base{}', 'Alpine']);
        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);

        // nothing changed
        $this->assertSame([], $assets->build());
        $this->assertSame(['public/css/app.css', 'public/js/app.js'], $assets->build(true));
    }

    public function testChanges(): void
    {
        $assets = new Assets($this->root);
        $assets->build();
        // later than Rivena's files, which can have changed just now
        touch("$this->root/public/css/app.css", time() + 50);
        touch("$this->root/public/js/app.js", time() + 50);

        // a changed partial builds the styles
        touch("$this->root/src/styles/_colors.scss", time() + 60);
        $this->assertSame(['public/css/app.css'], $assets->build());
        touch("$this->root/public/css/app.css", time() + 100);

        // a deleted file changes its directory
        unlink("$this->root/src/scripts/a.js");
        touch("$this->root/src/scripts", time() + 70);
        $this->assertSame(['public/js/app.js'], $assets->build());
        $this->assertStringNotContainsString('const a=1', file_get_contents("$this->root/public/js/app.js"));
    }

    public function testSassError(): void
    {
        file_put_contents("$this->root/src/styles/app.scss", 'body { color: $missing; }');

        try {
            (new Assets($this->root))->build();
            $this->fail('No exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Undefined variable', $e->getMessage());
        }

        // Sass shows the error on the page
        $this->assertStringContainsString('Undefined variable', file_get_contents("$this->root/public/css/app.css"));
    }

    public function testNoSources(): void
    {
        // Rivena's scripts only
        exec('rm -rf ' . escapeshellarg("$this->root/src"));
        $this->assertSame(['public/js/app.js'], (new Assets($this->root))->build());
    }
}
