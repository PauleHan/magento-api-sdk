<?php
namespace Triggmine\Signature;

use Triggmine\Exception\UnresolvedSignatureException;

/**
 * Signature providers.
 *
 * A signature provider is a function that accepts a version, service
 * and returns a {@see SignatureInterface} object on success or NULL if
 * no signature can be created from the provided arguments.
 *
 * You can wrap your calls to a signature provider with the
 * {@see SignatureProvider::resolve} function to ensure that a signature object
 * is created. If a signature object is not created, then the resolve()
 * function will throw a {@see Triggmine\Exception\UnresolvedSignatureException}.
 *
 *     use Triggmine\Signature\SignatureProvider;
 *     $provider = SignatureProvider::defaultProvider();
 *     // Returns a SignatureInterface or NULL.
 *     $signer = $provider('v3');
 *     // Returns a SignatureInterface or throws.
 *     $signer = SignatureProvider::resolve($provider, 'no', 's3', 'foo');
 */
class SignatureProvider
{
    /**
     * Resolves and signature provider and ensures a non-null return value.
     *
     * @param callable $provider Provider function to invoke.
     * @param string   $version  Signature version.
     * @param string   $service  Service name.
     *
     * @return SignatureInterface
     * @throws UnresolvedSignatureException
     */
    public static function resolve(callable $provider, $version, $service)
    {
        $result = $provider($version, $service);
        if ($result instanceof SignatureInterface) {
            return $result;
        }

        throw new UnresolvedSignatureException(
            "Unable to resolve a signature for $version/$service\n"
            . "Valid signature versions include v3 and anonymous."
        );
    }

    /**
     * Default SDK signature provider.
     *
     * @return callable
     */
    public static function defaultProvider()
    {
        return self::memoize(self::version());
    }

    /**
     * Creates a signature provider that caches previously created signature
     * objects. The computed cache key is the concatenation of the version,
     * service.
     *
     * @param callable $provider Signature provider to wrap.
     *
     * @return callable
     */
    public static function memoize(callable $provider)
    {
        $cache = [];
        return function ($version, $service) use (&$cache, $provider) {
            $key = "($version)($service)";
            if (!isset($cache[$key])) {
                $cache[$key] = $provider($version, $service);
            }
            return $cache[$key];
        };
    }

    /**
     * Creates signature objects from known signature versions.
     *
     * This provider currently recognizes the following signature versions:
     *
     * - v3: Signature version 3.
     * - anonymous: Does not sign requests.
     *
     * @return callable
     */
    public static function version()
    {
        return function ($version, $service) {
            switch ($version) {
                case 'v3':
                    return new SignatureV3($service);
                case 'anonymous':
                    return new AnonymousSignature();
                default:
                    return null;
            }
        };
    }
}
