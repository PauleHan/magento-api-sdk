<?php
namespace Triggmine\Api\Parser;

use Triggmine\CommandInterface;
use Triggmine\Exception\TriggmineException;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7;

/**
 * @internal Decorates a parser and validates the x-tm-crc32 header.
 */
class Crc32ValidatingParser extends AbstractParser
{
    /** @var callable */
    private $parser;

    /**
     * @param callable $parser Parser to wrap.
     */
    public function __construct(callable $parser)
    {
        $this->parser = $parser;
    }

    public function __invoke(
        CommandInterface $command,
        ResponseInterface $response
    ) {
        if ($expected = $response->getHeaderLine('x-tm-crc32')) {
            $hash = hexdec(Psr7\hash($response->getBody(), 'crc32b'));
            if ($expected != $hash) {
                throw new TriggmineException(
                    "crc32 mismatch. Expected {$expected}, found {$hash}.",
                    $command,
                    [
                        'code'             => 'ClientChecksumMismatch',
                        'connection_error' => true,
                        'response'         => $response
                    ]
                );
            }
        }

        $fn = $this->parser;
        return $fn($command, $response);
    }
}
