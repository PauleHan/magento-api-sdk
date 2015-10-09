<?php
namespace Triggmine;

interface TriggmineClientInterface
{
    /**
     * Creates and executes a command for an operation by name.
     *
     * Suffixing an operation name with "Async" will return a
     * promise that can be used to execute commands asynchronously.
     *
     * @param string $name      Name of the command to execute.
     * @param array  $arguments Arguments to pass to the getCommand method.
     *
     * @return ResultInterface
     * @throws \Exception
     */
    public function __call($name, array $args);

    /**
     * Create a command for an operation name.
     *
     * Special keys may be set on the command to control how it behaves,
     * including:
     *
     * - @http: Associative array of transfer specific options to apply to the
     *   request that is serialized for this command. Available keys include
     *   "proxy", "verify", "timeout", "connect_timeout", "debug", "delay", and
     *   "headers".
     *
     * @param string $name Name of the operation to use in the command
     * @param array  $args Arguments to pass to the command
     *
     * @return CommandInterface
     * @throws \InvalidArgumentException if no command can be found by name
     */
    public function getCommand($name, array $args = []);

    /**
     * Execute a single command.
     *
     * @param CommandInterface $command Command to execute
     *
     * @return ResultInterface
     * @throws \Exception
     */
    public function execute(CommandInterface $command);

    /**
     * Execute a command asynchronously.
     *
     * @param CommandInterface $command Command to execute
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function executeAsync(CommandInterface $command);

}