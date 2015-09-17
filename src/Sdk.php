<?php
namespace Triggmine;

/**
 * Class Sdk
 * Build TriggMIne client based on configuration settings
 * @package Triggmine
 */

class Sdk
{
    const VERSION = '3.0.0';

    /** @var  array Arguments for creating clients */
    private $args;

    /**
     * Builds Triggmine clients based on configuration settings.
     *
    */
    public function __construct(array $args = [])
    {
        $this->args = $args;

        if (!isset($args['handler']) && !isset($args['http_handler'])) {
            $this->args['http_handler'] = default_http_handler();
        }
    }

    public function __call($name, array $args)
    {
        if (strpos($name, 'create') === 0) {
            return $this->createClient(
                substr($name, 6),
                isset($args[0]) ? $args[0] : []
            );
        }

        throw new \BadMethodCallException("Unknown method: {$name}.");
    }

    /**
     * Get a client by name using an array of constructor options.
     * @param string $name Service name or namespace
     * @param array $args Arguments to configre the client
     *
     * @return TriggmineClientInterface
     * @throw \InvalidArgumentException if any required option are missing or
     *                                  the service is not supported.
     */
    public function createClient($name, array $args = [])
    {
        // Get information about the service from manifest file
        $service = manifest($name);
        $namespace = $service['namespace'];

        // Merge provider args with stored, service-specific args
        if (isset($this->args[$namespace])) {
            $args += $this->args[$namespace];
        }

        // Provide the endpoint prefix in the args
        if (!isset($args['service'])) {
            $args['service'] = $service['endpoint'];
        }

        // Initialize the client class
        $client = "Triggmine\\{$namespace}\\{$namespace}Client";
        return new $client($args + $this->args);
    }

    public function getEndpointPrefix($name)
    {
        return manifest($name)['endpoint'];
    }

}