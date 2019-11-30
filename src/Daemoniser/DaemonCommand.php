<?php
namespace Daemoniser;

/**
 * Class DaemonCommand
 * @package Daemoniser
 */
class DaemonCommand
{
    const INVALID_COMMANDS = [
        'start',
        'stop',
        'restart',
        'pid',
        'rm-logs',
        'status',
        'execute',
        'getAdditionalCommands',
        'getCalledClass',
        'demonise',
        'checkLogFileSize',
        'rotateLogFile',
        'runWhilePidPresent',
        'getPid',
        'isRunning',
        'echoLine',
        'logInfo',
        'logError',
        'deleteMatchingFromLogDirectory',
        'deleteLogs',
        'banner',
        'run'
    ];

    /**
     * @var string
     */
    public $command = null;

    /**
     * @var string
     */
    public $description = null;

    /**
     * @var string
     */
    public $method = null;

    /**
     * Build a new command.
     *
     * @param string $command
     * @param string $description
     * @param string $method
     * @return DaemonCommand
     * @throws DaemonException
     */
    final public static function build($command, $description, $method)
    {
        if (in_array($command, self::INVALID_COMMANDS)) {
            throw new DaemonException(
                sprintf('Invalid command. Cannot use "%s" as a command. It is protected', $command)
            );
        }

        $inst = new DaemonCommand();
        $inst->command = $command;
        $inst->description = $description;
        $inst->method = $method;
        if ($inst->isValid() === false) {
            throw new DaemonException(
                'Invalid command. All parameters are required.'
            );
        }
        return $inst;
    }

    /**
     * Is this custom command valid?
     *
     * @return bool
     */
    final public function isValid()
    {
        return ($this->command !== null && $this->description !== null && $this->method !== null);
    }
}
