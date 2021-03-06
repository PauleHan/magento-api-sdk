<?php
namespace Triggmine\Test;

use Triggmine;
use Triggmine\Api\ApiProvider;
use Triggmine\Api\Service;
use Triggmine\Command;
use Triggmine\CommandInterface;
use Triggmine\Credentials\Credentials;
use Triggmine\Credentials\CredentialProvider;
use Triggmine\HandlerList;
use Triggmine\Middleware;
use Triggmine\MockHandler;
use Triggmine\Result;
use Triggmine\ResultInterface;
use Triggmine\Signature\SignatureV3;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Promise;
use Psr\Http\Message\RequestInterface;

/**
 * @covers Triggmine\Middleware
 */
class MiddlewareTest extends \PHPUnit_Framework_TestCase
{
    public function setup()
    {
        \GuzzleHttp\Promise\queue()->run();
    }

    public function testCanTapIntoHandlerList()
    {
        $list = new HandlerList();
        $list->setHandler(new MockHandler([new Result()]));
        $list->appendSign(Middleware::tap(function () use (&$called) {
            $called = func_get_args();
        }));
        $handler = $list->resolve();
        $handler(new Command('foo'), new Request('GET', 'http://exmaple.com'));
        Promise\queue()->run();
        $this->assertCount(2, $called);
        $this->assertInstanceOf('Triggmine\CommandInterface', $called[0]);
        $this->assertInstanceOf('Psr\Http\Message\RequestInterface', $called[1]);
    }

    public function testWrapsWithRetryMiddleware()
    {
        $list = new HandlerList();
        $list->setHandler(new MockHandler([new Result()]));
        $list->appendSign(Middleware::retry(function () use (&$called) {
            $called = true;
        }));
        $handler = $list->resolve();
        $handler(new Command('foo'), new Request('GET', 'http://exmaple.com'));
        Promise\queue()->run();
        $this->assertTrue($called);
    }

    public function testAddsRetrySubscriber()
    {
        $list = new HandlerList();
        $mock = new MockHandler([
            new Result(['@metadata' => ['statusCode' => 500]]),
            new Result(['@metadata' => ['statusCode' => 200]]),
        ]);
        $this->assertCount(2, $mock);
        $list->setHandler($mock);
        $list->appendSign(Middleware::retry());
        $handler = $list->resolve();
        $handler(new Command('foo'), new Request('GET', 'http://127.0.0.1'))->wait();
        $this->assertCount(0, $mock);
    }

    public function testAddsSigner()
    {
        $list = new HandlerList();
        $mock = function ($command, $request) use (&$req) {
            $req = $request;
            return \GuzzleHttp\Promise\promise_for(
                new Result(['@metadata' => ['statusCode' => 200]])
            );
        };
        $list->setHandler($mock);
        $creds = CredentialProvider::fromCredentials(new Credentials('foo', 'bar'));
        $signature = new SignatureV3('a', 'b');
        $list->appendSign(Middleware::signer($creds, Triggmine\constantly($signature)));
        $handler = $list->resolve();
        $handler(new Command('foo'), new Request('GET', 'http://exmaple.com'));
        Promise\queue()->run();
        $this->assertTrue($req->hasHeader('Authorization'));
    }

    public function testBuildsRequests()
    {
        $r = new Request('GET', 'http://www.foo.com');
        $serializer = function (CommandInterface $command) use ($r, &$called) {
            $called = true;
            return $r;
        };
        $list = new HandlerList();
        $list->setHandler(new MockHandler([new Result()]));
        $list->appendSign(Middleware::requestBuilder($serializer));
        $handler = $list->resolve();
        $handler(new Command('foo'));
        $this->assertTrue($called);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage [a] is missing and is a required parameter
     */
    public function testValidatesCommands()
    {
        $list = new HandlerList();
        $list->setHandler(new MockHandler([new Result()]));
        $api = new Service(
            [
                'metadata' => [
                    'endpointPrefix' => 'a',
                    'apiVersion'     => 'b'
                ],
                'operations' => [
                    'foo' => [
                        'input' => ['shape'=> 'foo']
                    ]
                ],
                'shapes' => [
                    'foo' => [
                        'type' => 'structure',
                        'required' => ['a'],
                        'members' => [
                            'a' => ['shape' => 'a']
                        ]
                    ],
                    'a' => ['type' => 'string']
                ]
            ],
            function () { return []; }
        );
        $list->appendValidate(Middleware::validation($api));
        $handler = $list->resolve();

        try {
            $handler(new Command('foo', ['a' => 'b']), new Request('GET', 'http://foo.com'));
        } catch (\InvalidArgumentException $e) {
            $this->fail();
        }

        $handler(new Command('foo'));
    }

    public function testExtractsSourceFileIntoBody()
    {
        $list = new HandlerList();
        $list->setHandler(function ($command, $request) use (&$called) {
            $called = true;
            $this->assertNull($command['SourceFile']);
        });
        $provider = ApiProvider::defaultProvider();
        $data = $provider('api', 'commerce', 'latest');
        $service = new Service($data, $provider);
        $list->appendInit(Middleware::sourceFile($service));
        $handler = $list->resolve();
        $handler(new Command('onAddProductInCart', [
            'Key'        => 'key',
        ]), new Request('Put', 'http://foo.com'));
        Promise\queue()->run();
        $this->assertTrue($called);
    }

    public function testAppliesHistory()
    {
        $h = new Triggmine\History();
        $mock = new MockHandler([new Result()]);
        $list = new HandlerList($mock);
        $list->appendSign(Middleware::history($h));
        $handler = $list->resolve();
        $req = new Request('GET', 'http://www.foo.com');
        $cmd = new Command('foo');
        $handler($cmd, $req);
        Promise\queue()->run();
        $this->assertCount(1, $h);
    }

    public function testCanSetContentTypeOfCommandsWithPayloads()
    {
        $h = new Triggmine\History();
        $list = new HandlerList();
        $list->setHandler(new MockHandler([new Result()]));
        $list->appendBuild(Middleware::contentType(['Foo']));
        $list->appendSign(Middleware::history($h));
        $handler = $list->resolve();
        $payload = Psr7\stream_for(fopen(__DIR__ . '/../docs/_static/logo.png', 'r'));
        $request = new Request('PUT', 'http://exmaple.com', [], $payload);
        $handler(new Command('Foo'), $request);

        $this->assertEquals(
            'image/png',
            $h->getLastRequest()->getHeaderLine('Content-Type')
        );
    }

    public function testCanMapCommands()
    {
        $list = new HandlerList();
        $mock = new MockHandler([new Result()]);
        $list->setHandler($mock);
        $list->appendInit(Middleware::mapCommand(function (CommandInterface $c) {
            $c['Hi'] = 'test';
            return $c;
        }));
        $handler = $list->resolve();
        $request = new Request('GET', 'http://exmaple.com');
        $handler(new Command('Foo'), $request);
        $this->assertEquals('test', $mock->getLastCommand()->offsetGet('Hi'));
    }

    public function testCanMapRequests()
    {
        $list = new HandlerList();
        $mock = new MockHandler([new Result()]);
        $list->setHandler($mock);
        $list->appendInit(Middleware::mapRequest(function (RequestInterface $r) {
            return $r->withHeader('X-Foo', 'Bar');
        }));
        $handler = $list->resolve();
        $request = new Request('GET', 'http://exmaple.com');
        $handler(new Command('Foo'), $request);
        $this->assertEquals(['Bar'], $mock->getLastRequest()->getHeader('X-Foo'));
    }

    public function testCanMapResults()
    {
        $list = new HandlerList();
        $mock = new MockHandler([new Result()]);
        $list->setHandler($mock);
        $list->appendSign(Middleware::mapResult(function (ResultInterface $r) {
            $r['Test'] = 'hi';
            return $r;
        }));
        $handler = $list->resolve();
        $request = new Request('GET', 'http://exmaple.com');
        $result = $handler(new Command('Foo'), $request)->wait();
        $this->assertEquals('hi', $result['Test']);
    }
}