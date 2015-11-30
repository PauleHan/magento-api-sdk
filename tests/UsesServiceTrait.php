<?php
namespace Triggmine\Test;

use Triggmine\TriggmineClientInterface;
use Triggmine\Exception\TriggmineException;
use Triggmine\MockHandler;
use Triggmine\Result;
use Triggmine\Sdk;
use Triggmine\Api\Service;

/**
 * @internal
 */
trait UsesServiceTrait
{
    /**
     * Creates an instance of the Triggmine SDK for a test
     *
     * @param array $args
     *
     * @return Sdk
     */
    private function getTestSdk(array $args = [])
    {
        return new Sdk($args + [
                'version'     => 'latest',
                'retries'     => 0
            ]);
    }

    /**
     * Creates an instance of a service client for a test
     *
     * @param string $service
     * @param array  $args
     *
     * @return TriggmineClientInterface
     */
    private function getTestClient($service, array $args = [])
    {
        // Disable network access. If the INTEGRATION envvar is set, then this
        // disabling is not done.
        if (!isset($_SERVER['INTEGRATION'])
            && !isset($args['handler'])
            && !isset($args['http_handler'])
        ) {
            $args['handler'] = new MockHandler([]);
        }

        return $this->getTestSdk($args)->createClient($service);
    }

    /**
     * Queues up mock Result objects for a client
     *
     * @param TriggmineClientInterface $client
     * @param Result[]|array[]   $results
     * @param callable $onFulfilled Callback to invoke when the return value is fulfilled.
     * @param callable $onRejected  Callback to invoke when the return value is rejected.
     *
     * @return TriggmineClientInterface
     */
    private function addMockResults(
        TriggmineClientInterface $client,
        array $results,
        callable $onFulfilled = null,
        callable $onRejected = null
    ) {
        foreach ($results as &$res) {
            if (is_array($res)) {
                $res = new Result($res);
            }
        }

        $mock = new MockHandler($results, $onFulfilled, $onRejected);
        $client->getHandlerList()->setHandler($mock);

        return $client;
    }

    /**
     * Creates a mock CommandException with a given error code
     *
     * @param string $code
     * @param string $type
     * @param string|null $message
     *
     * @return TriggmineException
     */
    private function createMockTriggmineException(
        $code = null,
        $type = null,
        $message = null
    ) {
        $code = $code ?: 'ERROR';
        $type = $type ?: 'Triggmine\Exception\TriggmineException';

        $client = $this->getMockBuilder('Triggmine\TriggmineClientInterface')
            ->setMethods(['getApi'])
            ->getMockForAbstractClass();

        $client->expects($this->any())
            ->method('getApi')
            ->will($this->returnValue(
                new Service(
                    [
                        'metadata' => [
                            'endpointPrefix' => 'foo',
                            'apiVersion' => 'version'
                        ]
                    ],
                    function () { return []; }
                )));

        return new $type(
            $message ?: 'Test error',
            $this->getMock('Triggmine\CommandInterface'),
            [
                'message' => $message ?: 'Test error',
                'code'    => $code
            ]
        );
    }
}