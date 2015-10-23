<?php
namespace Triggmine\Api\ErrorParser;

use Psr\Http\Message\ResponseInterface;

/**
 * Parses JSON-REST errors.
 */
class RestJsonErrorParser
{
    use JsonParserTrait;

    public function __invoke(ResponseInterface $response)
    {
        $data = $this->genericHandler($response);

        // Merge in error data from the JSON body
        if ($json = $data['parsed']) {
            $data = array_replace($data, $json);
        }

        if (!empty($data['type'])) {
            $data['type'] = strtolower($data['type']);
        }

        if ($code = $response->getHeaderLine('x-tmn-errortype')) {
            $colon = strpos($code, ':');
            $data['code'] = $colon ? substr($code, 0, $colon) : $code;
        }

        return $data;
    }
}
