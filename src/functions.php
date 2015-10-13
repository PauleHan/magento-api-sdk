<?php
namespace Triggmine;

use Psr\Http\Message\RequestInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\FulfilledPromise;


//-----------------------------------------------------------------------------
// Functional functions
//-----------------------------------------------------------------------------

function constantly($value)
{
    return function() use ($value) { return $value; };
}


//-----------------------------------------------------------------------------
// Misc. functions.
//-----------------------------------------------------------------------------

/**
 * Created a default HTTP handler based on the available clients
 *
 * @return callable
 */
function default_http_handler()
{
    $version = (string) ClientInterface::VERSION;
    if ($version[0] === '6') {
        return new \Triggmine\Handler\GuzzleV6\GuzzleHandler();
    } else {
        throw new \RuntimeException('Unknown Guzzle version: ' . $version);
    }
}

/**
 * Retrieves data for a service from the SDK's service manifest file.
 *
 * Manifest data is stored statically, so it does not need to be loaded more
 * than once per process. The JSON data is also cached in opcache.
 *
 * @param string $service Case-insensitive namespace or endpoint prefix of the
 *                        service for which you are retrieving manifest data.
 *
 * @return RequestInterface
 * @throws \InvalidArgumentException if the service is not supported
 */
function manifest($service = null)
{
    // Load the manifest and create aliases for lowercased namespaces.
    static $manifest = [];
    static $aliases = [];

    if (empty($manifest)) {
        $manifest = load_compiled_json(__DIR__ . '/data/manifest.json');
        foreach ($manifest as $endpoint => $info) {
            $alias = strtolower($info['namespace']);
            if ($alias != $endpoint) {
                $aliases[$alias] = $endpoint;
            }
        }

    }

    // If mo service specified, then return the whole manifest.
    if ($service === null) {
        return $manifest;
    }

    $service = strtolower($service);
    if (isset($manifest[$service])) {
        return $manifest[$service] + ['endpoint' => $service];
    } elseif ($aliases[$service]) {
        return manifest($aliases[$service]);
    } else {
        return new \InvalidArgumentException(
            "The service \"{$service}\" is not provided by the TriggMine SDK
            for Php"
        );
    }

}

//-----------------------------------------------------------------------------
// JSON compiler and loading functions
//-----------------------------------------------------------------------------

/**
 * @param string $path Path to json file on disk.
 *
 * @return mixed Returns the JSON decoded data. Note that JSON objects are
 * decoded as associative arrays.
 */
function load_compiled_json($path)
{
    if ($compiled = @include("$path.php")) {
        return $compiled;
    }

    if (!file_exists($path)) {
        throw new \InvalidArgumentException(
            sprintf("File not found: %s", $path)
        );
    }

    return json_decode(file_get_contents($path), true);
}

/**
 * Debug function used to describe the provided value type and class.
 *
 * @param mixed $input
 *
 * @return string Returns a string containing the type of the variable and
 *                if a class is provided, the class name.
 */
function describe_type($input)
{
    switch (gettype($input)) {
        case 'object':
            return 'object(' . get_class($input) . ')';
        case 'array':
            return 'array(' . count($input) . ')';
        default:
            ob_start();
            var_dump($input);

            // normalize float vs double
            return str_replace('double(', 'float(', rtrim(ob_get_clean()));
    }
}