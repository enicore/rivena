<?php
namespace Tests\Controllers;

use Enicore\Rivena\Classes\View;
use Enicore\Rivena\Controllers\Controller;
use Enicore\Rivena\Models\Event;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\Database;

#[CoversClass(Controller::class)]
class ControllerLogTest extends TestCase
{
    private Controller $controller;

    protected function setUp(): void
    {
        Database::create();
        $this->controller = new class(new View([__DIR__ . '/../Fixtures/app/src/views'])) extends Controller {
            public function logEvent(string $action, string $message = '', ?int $userId = null,
                                     ?string $userEmail = null): void
            {
                parent::logEvent($action, $message, $userId, $userEmail);
            }
        };
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }

    public function testLogTheLoggedInUser(): void
    {
        $_SESSION = ['auth_user_id' => 7, 'auth_email' => 'a@example.com'];
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 ' .
            '(KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36';

        $this->controller->logEvent('user.created', 'b@example.com');
        $entry = Event::first();

        $this->assertEqualsWithDelta(time(), $entry->created_at, 2);
        $this->assertSame('', $entry->country);
        $this->assertSame([7, 'a@example.com', '10.0.0.1', 'user.created', 'b@example.com', 'Chrome 141 / Windows'],
            [(int)$entry->user_id, $entry->user_email, $entry->ip, $entry->action, $entry->message,
                $entry->user_agent]);

        // an email's event (a failed login with an unknown one) isn't the logged-in user's
        $this->controller->logEvent('auth.login_failed', '', null, 'x@example.com');
        $entry = Event::orderByDesc('id')->first();
        $this->assertSame([null, 'x@example.com'], [$entry->user_id, $entry->user_email]);
    }

    public function testLogWithoutUser(): void
    {
        $this->controller->logEvent('auth.login_failed', 'x@example.com');
        $this->controller->logEvent('other', '', 3, 'c@example.com');
        $_SERVER['REMOTE_ADDR'] = '81.2.69.142';
        $this->controller->logEvent('country');
        $entries = Event::orderBy('id')->get();
        $this->assertSame('GB', $entries[2]->country);

        $this->assertNull($entries[0]->user_id);
        $this->assertNull($entries[0]->user_email);
        $this->assertSame([3, 'c@example.com'], [(int)$entries[1]->user_id, $entries[1]->user_email]);
    }

    public function testUnknownUserAgent(): void
    {
        // kept whole
        $_SERVER['HTTP_USER_AGENT'] = 'Unknown thing';
        $this->controller->logEvent('other');

        $this->assertSame('Unknown thing', Event::first()->user_agent);
    }
}
