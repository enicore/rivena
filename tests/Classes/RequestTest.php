<?php
namespace Tests\Classes;

use Enicore\Rivena\Classes\Request;
use Enicore\Rivena\Exceptions\InputException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

#[CoversClass(\Enicore\Rivena\Classes\Request::class)]
#[CoversClass(\Enicore\Rivena\Exceptions\InputException::class)]
class RequestTest extends TestCase
{
    private Request $input;

    protected function setUp(): void
    {
        $this->input = $this->request([
            'name' => '  Rivena  ',
            'empty' => '',
            'spaces' => '   ',
            'count' => '42',
            'negative' => -3,
            'decimal' => '1.5',
            'number' => 7,
            'on' => 'on',
            'off' => '0',
            'yes' => true,
            'email' => ' user@example.com ',
            'list' => ['a', 'b'],
            'null' => null,
        ]);
    }

    /**
     * Asserts that the call throws an InputException with the message.
     */
    private function assertInputError(string $message, callable $call): void
    {
        try {
            $call();
            $this->fail("No exception: $message");
        } catch (InputException $e) {
            $this->assertSame($message, $e->getMessage());
        }
    }

    // a POST request with the parsed body
    private function request(array $body): Request
    {
        return Request::from((new ServerRequestFactory())->createServerRequest('POST', '/')->withParsedBody($body));
    }

    public function testUrlFor(): void
    {
        $app = AppFactory::create();
        $app->setBasePath('/app');
        $app->get('/users/{id}', fn() => null)->setName('user');
        $app->get('/', function (Request $request, ResponseInterface $response) {
            $response->getBody()->write($request->urlFor('user', ['id' => 5], ['tab' => 'a b']));
            return $response;
        });
        $app->addRoutingMiddleware();

        $response = $app->handle(Request::from((new ServerRequestFactory())->createServerRequest('GET', '/app/')));
        $this->assertSame('/app/users/5?tab=a+b', (string)$response->getBody());
    }

    public function testFrom(): void
    {
        $slim = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.com/save?page=2')
            ->withHeader('X-CSRF-Token', 'abc')
            ->withParsedBody(['name' => 'Rivena']);
        $request = Request::from($slim);

        $this->assertSame(['name' => 'Rivena'], $request->all());
        $this->assertSame(['POST', '/save', 'abc'], [$request->getMethod(), $request->getUri()->getPath(),
            $request->getHeaderLine('X-CSRF-Token')]);
        // the with methods keep the class
        $this->assertSame([], $request->withParsedBody(null)->all());
    }

    public function testGetHasAll(): void
    {
        $this->assertSame('  Rivena  ', $this->input->get('name'));
        $this->assertSame('default', $this->input->get('missing', 'default'));
        $this->assertTrue($this->input->has('empty'));
        $this->assertFalse($this->input->has('null'));
        $this->assertFalse($this->input->has('missing'));
        $this->assertCount(13, $this->input->all());
    }

    public function testGetString(): void
    {
        $this->assertSame('Rivena', $this->input->getString('name'));
        $this->assertSame('42', $this->input->getString('count'));
        $this->assertSame('7', $this->input->getString('number'));
        $this->assertSame('', $this->input->getString('empty'));
        $this->assertSame('', $this->input->getString('spaces'));
        $this->assertSame('default', $this->input->getString('missing', default: 'default'));
        $this->assertSame('default', $this->input->getString('empty', default: 'default', allowEmpty: false));
        // the length in characters, after trimming
        $this->assertSame('Rivena', $this->input->getString('name', 6));
        $this->assertSame('üöä', $this->request(['a' => ' üöä '])->getString('a', 3));

        $this->assertInputError('Missing value: missing', fn() => $this->input->getString('missing'));
        $this->assertInputError('Missing value: null', fn() => $this->input->getString('null'));
        $this->assertInputError('Missing value: spaces', fn() => $this->input->getString('spaces', allowEmpty: false));
        $this->assertInputError('Invalid value: list', fn() => $this->input->getString('list'));
        $this->assertInputError('Invalid value: yes', fn() => $this->input->getString('yes'));
        $this->assertInputError('Too long: name', fn() => $this->input->getString('name', 5));
        $this->assertInputError('Too long: a', fn() => $this->request(['a' => 'üöä'])->getString('a', 2));
    }

    public function testGetInt(): void
    {
        $this->assertSame(42, $this->input->getInt('count'));
        $this->assertSame(-3, $this->input->getInt('negative'));
        $this->assertSame(5, $this->input->getInt('missing', 5));
        // an empty form field is a missing value
        $this->assertSame(5, $this->input->getInt('empty', 5));

        $this->assertInputError('Invalid number: decimal', fn() => $this->input->getInt('decimal'));
        $this->assertInputError('Invalid number: name', fn() => $this->input->getInt('name'));
        $this->assertInputError('Missing value: spaces', fn() => $this->input->getInt('spaces'));
        $this->assertInputError('Invalid value: list', fn() => $this->input->getInt('list'));
    }

    public function testGetFloat(): void
    {
        $this->assertSame(1.5, $this->input->getFloat('decimal'));
        $this->assertSame(42.0, $this->input->getFloat('count'));
        $this->assertSame(0.5, $this->input->getFloat('missing', 0.5));

        $this->assertInputError('Invalid number: name', fn() => $this->input->getFloat('name'));
        $this->assertInputError('Missing value: missing', fn() => $this->input->getFloat('missing'));
    }

    public function testGetBool(): void
    {
        $this->assertTrue($this->input->getBool('on'));
        $this->assertFalse($this->input->getBool('off'));
        $this->assertTrue($this->input->getBool('yes'));
        $this->assertTrue($this->request(['a' => ' TRUE '])->getBool('a'));
        // unchecked checkboxes send nothing
        $this->assertFalse($this->input->getBool('missing', false));

        $this->assertInputError('Invalid value: name', fn() => $this->input->getBool('name'));
        $this->assertInputError('Missing value: missing', fn() => $this->input->getBool('missing'));
    }

    public function testGetEmail(): void
    {
        $this->assertSame('user@example.com', $this->input->getEmail('email'));
        $this->assertSame('', $this->input->getEmail('empty', ''));

        $this->assertInputError('Invalid email address: name', fn() => $this->input->getEmail('name'));
        $this->assertInputError('Missing value: empty', fn() => $this->input->getEmail('empty'));
    }

    public function testGetArray(): void
    {
        $this->assertSame(['a', 'b'], $this->input->getArray('list'));
        $this->assertSame([], $this->input->getArray('missing', []));

        $this->assertInputError('Invalid value: name', fn() => $this->input->getArray('name'));
        $this->assertInputError('Missing value: missing', fn() => $this->input->getArray('missing'));
    }

    public function testExceptionKey(): void
    {
        try {
            $this->input->getInt('name');
        } catch (InputException $e) {
            $this->assertSame('name', $e->getKey());
        }
    }
}
