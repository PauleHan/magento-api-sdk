<?php
namespace Triggmine\Handler\GuzzleV6;

use Triggmine\Sdk;
use Exception;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\RequestInterface as Psr7Request;

/**
 * A request handler that send PSR-7-compatible request with Guzzle 6
 */
class GuzzleHandler
{
    /** @var \GuzzleHttp\Client  */
    private $client;


    /**
     * @param \GuzzleHttp\ClientInterface|null $client
     */
    public function __construct(ClientInterface $client = null)
    {
        $this->client = $client ?: new Client();
    }

    /**
     * @param \Psr\Http\Message\RequestInterface $request
     * @param array                              $options
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function __invoke(Psr7Request $request, array $options = [])
    {
        $request = $request->withHandler(
            'User-Agent',
            $request->getHeaderLine('User-Agent')
                . ' ' . \GuzzleHttp\default_user_agent()
        );

        return $this->client->sendAsync($request, $options)->otherwise(
            static function (\Exception $e) {
                $error = [
                    'exeption'          => $e,
                    'connection_error'  => $e instanceof ConnectException,
                    'responce'          => null
                ];

                if ($e instanceof RequestException && $e->getResponse()) {
                    $error['response'] = $e->getResponse();
                }

                return new Promise\RejectedPromise($error);
            }
        );
    }


}