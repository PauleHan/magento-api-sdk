<?php
namespace Triggmine\Test;

use Triggmine\Api\ErrorParser\JsonRpcErrorParser;
use Triggmine\Commerce\CommerceClient;
use Triggmine\TriggmineClient;
use Triggmine\Credentials\Credentials;
use Triggmine\MockHandler;
use Triggmine\Result;
use Triggmine\Signature\SignatureV3;
use Triggmine\WrappedHttpHandler;
use GuzzleHttp\Promise\RejectedPromise;

/**
 * @covers Triggmine\TriggmineClient
 */
class TriggmineClientTest extends \PHPUnit_Framework_TestCase
{
    use UsesServiceTrait;

    private function getApiProvider()
    {
        return function () {
            return [
                'metadata' => [
                    'protocol'       => 'query',
                    'endpointPrefix' => 'foo'
                ],
                'shapes' => []
            ];
        };
    }

    public function testHasGetters()
    {
        $config = [
            'handler'      => function () {},
            'credentials'  => new Credentials('foo', 'bar'),
            'region'       => 'foo',
            'endpoint'     => 'http://triggmine.com',
            'serializer'   => function () {},
            'api_provider' => $this->getApiProvider(),
            'service'      => 'foo',
            'error_parser' => function () {},
            'version'      => 'latest'
        ];

        $client = new TriggmineClient($config);
        $this->assertSame($config['handler'], $this->readAttribute($client->getHandlerList(), 'handler'));
        $this->assertSame($config['credentials'], $client->getCredentials()->wait());
        $this->assertEquals('foo', $client->getApi()->getEndpointPrefix());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Operation not found: Foo
     */
    public function testEnsuresOperationIsFoundWhenCreatingCommands()
    {
        $this->createClient()->getCommand('foo');
    }

    public function testReturnsCommandForOperation()
    {
        $client = $this->createClient([
            'operations' => [
                'foo' => [
                    'http' => ['method' => 'POST']
                ]
            ]
        ]);

        $this->assertInstanceOf(
            'Triggmine\CommandInterface',
            $client->getCommand('foo')
        );
    }

    /**
     * @expectedException \Triggmine\Commerce\Exception\CommerceException
     * @expectedExceptionMessage Error executing "foo" on "http://triggmine.com"; Triggmine HTTP error: Baz Bar!
     */
    public function testWrapsExceptions()
    {
        $parser = function () {};
        $errorParser = new JsonRpcErrorParser();
        $h = new WrappedHttpHandler(
            function () {
                return new RejectedPromise([
                    'exception'        => new \Exception('Baz Bar!'),
                    'connection_error' => true,
                    'response'         => null
                ]);
            },
            $parser,
            $errorParser,
            'Triggmine\Commerce\Exception\CommerceException'
        );

        $client = $this->createClient(
            ['operations' => ['foo' => ['http' => ['method' => 'POST']]]],
            ['handler' => $h]
        );

        $command = $client->getCommand('foo');
        $client->execute($command);
    }

    public function testChecksBothLowercaseAndUppercaseOperationNames()
    {
        $client = $this->createClient(['operations' => ['Foo' => [
            'http' => ['method' => 'POST']
        ]]]);

        $this->assertInstanceOf(
            'Triggmine\CommandInterface',
            $client->getCommand('foo')
        );
    }

    public function testReturnsAsyncResultsUsingMagicCall()
    {
        $client = $this->createClient(['operations' => ['Foo' => [
            'http' => ['method' => 'POST']
        ]]]);
        $client->getHandlerList()->setHandler(new MockHandler([new Result()]));
        $result = $client->fooAsync();
        $this->assertInstanceOf('GuzzleHttp\Promise\PromiseInterface', $result);
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testGetIteratorFailsForMissingConfig()
    {
        $client = $this->createClient();
        $client->getIterator('ListObjects');
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testGetPaginatorFailsForMissingConfig()
    {
        $client = $this->createClient();
        $client->getPaginator('ListObjects');
    }

    public function testCreatesClientsFromConstructor()
    {
        $client = new CommerceClient([
            'version' => 'latest'
        ]);
        $this->assertInstanceOf('Triggmine\Commerce\CommerceClient', $client);
    }

    public function testCanGetEndpoint()
    {
        $client = $this->createClient();
        $this->assertEquals(
            'http://triggmine.com',
            $client->getEndpoint()
        );
    }

    public function testCanGetSignatureProvider()
    {
        $client = $this->createClient([]);
        $ref = new \ReflectionMethod($client, 'getSignatureProvider');
        $ref->setAccessible(true);
        $provider = $ref->invoke($client);
        $this->assertTrue(is_callable($provider));
    }

    private function createClient(array $service = [], array $config = [])
    {
        $apiProvider = function ($type) use ($service, $config) {
            if ($type == 'paginator') {
                return isset($service['pagination'])
                    ? ['pagination' => $service['pagination']]
                    : ['pagination' => []];
            } elseif ($type == 'waiter') {
                return isset($service['waiters'])
                    ? ['waiters' => $service['waiters'], 'version' => 2]
                    : ['waiters' => [], 'version' => 2];
            } else {
                if (!isset($service['metadata'])) {
                    $service['metadata'] = [];
                }
                $service['metadata']['protocol'] = 'query';
                return $service;
            }
        };

        return new TriggmineClient($config + [
                'handler'      => new MockHandler(),
                'credentials'  => new Credentials('foo', 'bar'),
                'signature'    => new SignatureV3('foo', 'bar'),
                'endpoint'     => 'http://triggmine.com',
                'region'       => 'foo',
                'service'      => 'foo',
                'api_provider' => $apiProvider,
                'serializer'   => function () {},
                'error_parser' => function () {},
                'version'      => 'latest'
            ]);
    }
}