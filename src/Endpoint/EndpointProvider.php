<?php
namespace Triggmine\Endpoint;

use Triggmine\Exception\UnresolvedEndpointException;

/**
 * Endpoint providers.
 *
 * An endpoint provider is a function that accepts a hash of endpoint options,
 * including but not limited to "service" key value pairs. The
 * endpoint provider function returns a hash of endpoint data, which MUST
 * include an "endpoint" key value pair that represents the resolved endpoint
 * or NULL if an endpoint cannot be determined.
 *
 * You can wrap your calls to an endpoint provider with the
 * {@see EndpointProvider::resolve} function to ensure that an endpoint hash is
 * created. If an endpoint hash is not created, then the resolve() function
 * will throw an {@see Triggmine\Exception\UnresolvedEndpointException}.
 *
 *     use Triggmine\Endpoint\EndpointProvider;
 *     $provider = EndpointProvider::defaultProvider();
 *     // Returns an array or NULL.
 *     $endpoint = $provider(['service' => 'ec2']);
 *     // Returns an endpoint array or throws.
 *     $endpoint = EndpointProvider::resolve($provider, [
 *         'service' => 'ec2'
 *     ]);
 */
class EndpointProvider
{
    /**
     * Resolves and endpoint provider and ensures a non-null return value.
     *
     * @param callable $provider Provider function to invoke.
     * @param array    $args     Endpoint arguments to pass to the provider.
     *
     * @return array
     * @throws UnresolvedEndpointException
     */
    public static function resolve(callable $provider, array $args = [])
    {
        $result = $provider($args);
        if (is_array($result)) {
            return $result;
        }

        throw new UnresolvedEndpointException(
            'Unable to resolve an endpoint using the provider arguments: '
            . json_encode($args) . '. Note: you can provide an "endpoint" '
            . 'option to a client constructor to bypass invoking an endpoint '
            . 'provider.');
    }

    /**
     * Creates and returns the default SDK endpoint provider.
     *
     * @return callable
     */
    public static function defaultProvider()
    {
        $data = \Triggmine\load_compiled_json(__DIR__ . '/../data/endpoints.json');

        return new PatternEndpointProvider($data['endpoints']);
    }

    /**
     * Creates and returns an endpoint provider that uses patterns from an
     * array.
     *
     * @param array $patterns Endpoint patterns
     *
     * @return callable
     */
    public static function patterns(array $patterns)
    {
        return new PatternEndpointProvider($patterns);
    }
}
