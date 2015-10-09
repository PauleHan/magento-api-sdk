<?php
namespace Triggmine;

class TriggmineClient implements TriggmineClientInterface
{

    public function __construct(array $args)
    {
        list($service, $exceptionClass) = $this->parseClass();
        if (!isset($args['service'])) {
            $args['service'] = manifest($service)['endpoint'];
        }
        if (!isset($args['exception_class'])) {
            $args['exception_class'] = $exceptionClass;
        }
        $this->handlerList = new HandlerList();
        $resolver = new ClientResolver(static::getArguments());
        $config = $resolver->resolve($args, $this->handlerList);
//        var_dump($config);


        //$this->api = $config['api'];
        //$this->signatureProvider = $config['signature_provider'];
        //$this->endpoint = new Uri($config['endpoint']);
        //$this->credentialProvider = $config['credentials'];
        //$this->region = isset($config['region']) ? $config['region'] : null;
        //$this->config = $config['config'];
        //$this->defaultRequestOptions = $config['http'];
        //$this->addSignatureMiddleware();
        //if (isset($args['with_resolved'])) {
        //    $args['with_resolved']($config);
        //}
    }

    /**
     * Parse the class name and setup the custom exception class of the client
     * and return the "service" name of the client and "exception_class".
     *
     * @return array
     */
    private function parseClass()
    {
        $klass = get_class($this);
        if ($klass === __CLASS__) {
            return ['', 'Triggmine\Exception\TriggmineException'];
        }
        $service = substr($klass, strrpos($klass, '\\') + 1, -6);

        return [
            strtolower($service),
            "Triggmine\\{$service}\\Exception\\{$service}Exception"
        ];
    }

    public function __call($name, array $args)
    {
        $params = isset($args[0]) ? $args[0] : [];
        if (substr($name, -5) === 'Async') {
            return $this->executeAsync(
                $this->getCommand(substr($name, 0, -5), $params)
            );
        }

        return $this->execute($this->getCommand($name, $params));
    }

    public function executeAsync(CommandInterface $command)
    {
        $handler = $command->getHandlerList()->resolve();

        return $handler($command);
    }

    public function getCommand($name, array $args = [])
    {
        // Fail fast if the command cannot be found in the description.
        if (!isset($this->api['operations'][$name])) {
            $name = ucfirst($name);
            if (!isset($this->api['operations'][$name])) {
                throw new \InvalidArgumentException("Operation not found: $name");
            }
        }
        if (!isset($args['@http'])) {
            $args['@http'] = $this->defaultRequestOptions;
        } else {
            $args['@http'] += $this->defaultRequestOptions;
        }

        return new Command($name, $args, clone $this->getHandlerList());
    }

    public function execute(CommandInterface $command)
    {
        return $this->executeAsync($command)->wait();
    }

    /**
     * Get an array of client constructor arguments used by the client.
     *
     * @return array
     */
    public static function getArguments()
    {
        return ClientResolver::getDefaultArguments();
    }

}