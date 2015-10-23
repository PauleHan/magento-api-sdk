<?php
namespace Triggmine\Endpoint;

/**
 * Provides endpoints based on an endpoint pattern configuration array.
 */
class PatternEndpointProvider
{
    /** @var array */
    private $patterns;

    /**
     * @param array $patterns Hash of endpoint patterns mapping to endpoint
     *                        configurations.
     */
    public function __construct(array $patterns)
    {
        $this->patterns = $patterns;
    }

    public function __invoke(array $args = [])
    {
        $service = isset($args['service']) ? $args['service'] : '';
        $keys = ["*/{$service}", "*/*"];

        foreach ($keys as $key) {
            if (isset($this->patterns[$key])) {
                return $this->expand(
                    $this->patterns[$key],
                    isset($args['scheme']) ? $args['scheme'] : 'https',
                    $service
                );
            }
        }

        return null;
    }

    private function expand(array $config, $scheme, $service)
    {
        $config['endpoint'] = $scheme . '://'
            . strtr($config['endpoint'], [
                '{service}' => $service,
            ]);

        return $config;
    }
}
